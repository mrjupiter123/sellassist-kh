<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Social\Enums\AiEvaluationResultStatus;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationRun;
use App\Domain\Social\Models\SocialAiExtractionProfile;
use App\Jobs\RunSocialAiProfileEvaluation;
use Database\Seeders\AiEvaluationCaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SocialAiEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'social.ai.enabled' => true,
            'social.ai.api_key' => 'evaluation-test-key',
            'social.ai.evaluation_pass_threshold' => 0.85,
            'social.ai.evaluation_approval_threshold' => 0.85,
        ]);
        $this->seed(AiEvaluationCaseSeeder::class);
    }

    public function test_curated_dataset_is_synthetic_encrypted_and_contains_khmer_and_english_cases(): void
    {
        $this->assertDatabaseCount('social_ai_evaluation_cases', 2);
        $this->assertSame(['en', 'km'], SocialAiEvaluationCase::query()->orderBy('locale')->pluck('locale')->all());
        $khmer = SocialAiEvaluationCase::query()->where('locale', 'km')->sole();
        $this->assertStringContainsString('ដារ៉ា', $khmer->messages[0]);
        $this->assertStringNotContainsString('ដារ៉ា', (string) $khmer->getRawOriginal('messages'));
        $this->assertDatabaseCount('social_conversations', 0);
    }

    public function test_manual_run_is_idempotently_queued_and_scores_structured_synthetic_results(): void
    {
        Queue::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $profile = $this->profile($admin->id);

        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.store', $profile))->assertRedirect();
        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.store', $profile))->assertRedirect();
        $this->assertDatabaseCount('social_ai_evaluation_runs', 1);
        Queue::assertPushed(RunSocialAiProfileEvaluation::class, 1);

        $run = SocialAiEvaluationRun::query()->sole();
        Http::fake(fn (Request $request) => Http::response($this->providerResponse($request), 200));
        app()->call([new RunSocialAiProfileEvaluation($run->id), 'handle']);

        $run->refresh();
        $this->assertSame(AiEvaluationRunStatus::Completed, $run->status);
        $this->assertSame(2, $run->total_cases);
        $this->assertSame(2, $run->passed_cases);
        $this->assertSame(0, $run->failed_cases);
        $this->assertSame('1.0000', $run->score);
        $this->assertSame(240, $run->total_tokens);
        $this->assertDatabaseCount('social_ai_evaluation_results', 2);
        $this->assertDatabaseCount('social_conversations', 0);
        Http::assertSent(fn (Request $request): bool => $request->data()['store'] === false);
    }

    public function test_qualifying_run_requires_explicit_approval_before_separate_activation(): void
    {
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $profile = $this->profile($admin->id);
        $run = $profile->evaluationRuns()->create([
            'status' => AiEvaluationRunStatus::Completed,
            'case_ids' => SocialAiEvaluationCase::query()->orderBy('id')->pluck('id')->all(),
            'total_cases' => 2,
            'passed_cases' => 2,
            'failed_cases' => 0,
            'score' => 1,
            'requested_by' => $admin->id,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
        foreach (SocialAiEvaluationCase::query()->get() as $case) {
            $run->results()->create([
                'social_ai_evaluation_case_id' => $case->id,
                'status' => AiEvaluationResultStatus::Passed,
                'score' => 1,
                'passed' => true,
                'actual_result' => [],
                'differences' => [],
                'processed_at' => now(),
            ]);
        }

        $this->actingAs($admin)->post(route('social.ai-profiles.activate', $profile))
            ->assertSessionHas('error', 'Approve a qualifying synthetic evaluation run before activating this profile.');
        $this->assertFalse($profile->refresh()->active);

        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.approve', [$profile, $run]))
            ->assertRedirect()
            ->assertSessionHas('success');
        $profile->refresh();
        $this->assertTrue($profile->activation_eligible);
        $this->assertFalse($profile->active);
        $this->assertSame($run->id, $profile->approval_evaluation_run_id);

        $this->actingAs($admin)->post(route('social.ai-profiles.activate', $profile))->assertRedirect();
        $this->assertTrue($profile->refresh()->active);
    }

    public function test_low_scoring_run_and_unauthorized_user_cannot_approve_profile(): void
    {
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $staff = $this->userWithPermissions(['social.extract']);
        $profile = $this->profile($admin->id);
        $run = $profile->evaluationRuns()->create([
            'status' => AiEvaluationRunStatus::Completed,
            'case_ids' => SocialAiEvaluationCase::query()->orderBy('id')->pluck('id')->all(),
            'total_cases' => 2,
            'passed_cases' => 0,
            'failed_cases' => 2,
            'score' => 0.5,
            'requested_by' => $admin->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($staff)->post(route('social.ai-evaluations.runs.approve', [$profile, $run]))->assertForbidden();
        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.approve', [$profile, $run]))
            ->assertSessionHas('error', 'This evaluation run does not satisfy the profile approval gate.');
        $this->assertFalse($profile->refresh()->activation_eligible);
    }

    private function profile(int $userId): SocialAiExtractionProfile
    {
        return SocialAiExtractionProfile::query()->create([
            'name' => 'Evaluation candidate',
            'version' => 'v1',
            'model' => 'evaluation-model',
            'instructions' => 'Extract explicit Khmer and English address fields.',
            'active' => false,
            'activation_eligible' => false,
            'created_by' => $userId,
        ]);
    }

    /** @return array<string, mixed> */
    private function providerResponse(Request $request): array
    {
        $context = (string) $request->data()['input'][0]['content'][0]['text'];
        $khmer = str_contains($context, 'ដារ៉ា');
        $payload = $khmer ? [
            'customer_name' => 'ដារ៉ា', 'phone' => '012345678', 'address' => 'ភ្នំពេញ',
            'province' => 'ភ្នំពេញ', 'district' => null, 'commune' => null, 'notes' => null,
            'overall_confidence' => 0.95,
            'items' => [[
                'product_ref' => '10000000-0000-4000-8000-000000000001',
                'variant_ref' => '10000000-0000-4000-8000-000000000002',
                'product_query' => 'អាវខ្មៅ', 'variant_query' => 'Black / M', 'quantity' => 2, 'confidence' => 0.95,
            ]],
        ] : [
            'customer_name' => 'Lina', 'phone' => '098765432', 'address' => 'Siem Reap',
            'province' => 'Siem Reap', 'district' => null, 'commune' => null, 'notes' => null,
            'overall_confidence' => 0.95,
            'items' => [[
                'product_ref' => '20000000-0000-4000-8000-000000000001',
                'variant_ref' => '20000000-0000-4000-8000-000000000002',
                'product_query' => 'red tote bag', 'variant_query' => 'Red', 'quantity' => 1, 'confidence' => 0.95,
            ]],
        ];

        return [
            'id' => $khmer ? 'resp_eval_km' : 'resp_eval_en',
            'usage' => ['input_tokens' => 90, 'output_tokens' => 30, 'total_tokens' => 120],
            'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]]]],
        ];
    }
}
