<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Social\Enums\AiEvaluationResultStatus;
use App\Domain\Social\Enums\AiEvaluationRunStatus;
use App\Domain\Social\Models\SocialAiEvaluationCase;
use App\Domain\Social\Models\SocialAiEvaluationDataset;
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
        $this->assertDatabaseCount('social_ai_evaluation_datasets', 1);
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
        $dataset = SocialAiEvaluationDataset::query()->sole();

        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.store', $profile), ['dataset_id' => $dataset->uuid])->assertRedirect();
        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.store', $profile), ['dataset_id' => $dataset->uuid])->assertRedirect();
        $this->assertDatabaseCount('social_ai_evaluation_runs', 1);
        Queue::assertPushed(RunSocialAiProfileEvaluation::class, 1);

        $run = SocialAiEvaluationRun::query()->sole();
        $this->assertSame($dataset->id, $run->social_ai_evaluation_dataset_id);
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
        $dataset = SocialAiEvaluationDataset::query()->sole();
        $run = $profile->evaluationRuns()->create([
            'social_ai_evaluation_dataset_id' => $dataset->id,
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

        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.approve', [$profile, $run]), [
            'release_notes' => 'Validated against the initial frozen synthetic dataset.',
        ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $profile->refresh();
        $this->assertTrue($profile->activation_eligible);
        $this->assertFalse($profile->active);
        $this->assertSame($run->id, $profile->approval_evaluation_run_id);
        $this->assertSame('Validated against the initial frozen synthetic dataset.', $profile->approval_release_notes);

        $this->actingAs($admin)->post(route('social.ai-profiles.activate', $profile))->assertRedirect();
        $this->assertTrue($profile->refresh()->active);
    }

    public function test_low_scoring_run_and_unauthorized_user_cannot_approve_profile(): void
    {
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $staff = $this->userWithPermissions(['social.extract']);
        $profile = $this->profile($admin->id);
        $dataset = SocialAiEvaluationDataset::query()->sole();
        $run = $profile->evaluationRuns()->create([
            'social_ai_evaluation_dataset_id' => $dataset->id,
            'status' => AiEvaluationRunStatus::Completed,
            'case_ids' => SocialAiEvaluationCase::query()->orderBy('id')->pluck('id')->all(),
            'total_cases' => 2,
            'passed_cases' => 0,
            'failed_cases' => 2,
            'score' => 0.5,
            'requested_by' => $admin->id,
            'completed_at' => now(),
        ]);

        $payload = ['release_notes' => 'Low score must not pass.'];
        $this->actingAs($staff)->post(route('social.ai-evaluations.runs.approve', [$profile, $run]), $payload)->assertForbidden();
        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.approve', [$profile, $run]), $payload)
            ->assertSessionHas('error', 'This evaluation run does not satisfy the profile approval gate.');
        $this->assertFalse($profile->refresh()->activation_eligible);
    }

    public function test_admin_can_create_encrypted_synthetic_case_and_freeze_a_new_dataset_version(): void
    {
        Queue::fake();
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $profile = $this->profile($admin->id);

        $this->actingAs($admin)->post(route('social.ai-evaluations.cases.store'), [
            'name' => 'Synthetic mixed-language edge case',
            'locale' => 'km',
            'messages_text' => "Test Customer wants one test item.\nPhone 012000000.",
            'catalog_json' => json_encode([['product_ref' => 'synthetic-product', 'name' => 'Test item', 'variants' => []]], JSON_THROW_ON_ERROR),
            'expected_result_json' => json_encode(['customer_name' => 'Test Customer', 'phone' => '012000000', 'items' => []], JSON_THROW_ON_ERROR),
        ])->assertRedirect()->assertSessionHas('success');

        $newCase = SocialAiEvaluationCase::query()->where('name', 'Synthetic mixed-language edge case')->sole();
        $this->assertSame('Test Customer wants one test item.', $newCase->messages[0]);
        $this->assertStringNotContainsString('Test Customer', (string) $newCase->getRawOriginal('messages'));

        $caseIds = SocialAiEvaluationCase::query()->orderBy('id')->pluck('id')->all();
        $this->actingAs($admin)->post(route('social.ai-evaluations.datasets.store'), [
            'name' => 'Core order extraction',
            'version' => 'v2',
            'release_notes' => 'Adds one mixed-language synthetic edge case.',
            'case_ids' => $caseIds,
        ])->assertRedirect()->assertSessionHas('success');

        $dataset = SocialAiEvaluationDataset::query()->where('version', 'v2')->sole();
        $this->assertSame(3, $dataset->cases()->count());
        $this->actingAs($admin)->post(route('social.ai-evaluations.runs.store', $profile), [
            'dataset_id' => $dataset->uuid,
        ])->assertRedirect();

        $run = SocialAiEvaluationRun::query()->latest('id')->firstOrFail();
        $this->assertSame($dataset->id, $run->social_ai_evaluation_dataset_id);
        $this->assertCount(3, $run->case_ids);
    }

    public function test_same_dataset_comparison_detects_regression_and_blocks_profile_approval(): void
    {
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $dataset = SocialAiEvaluationDataset::query()->with('cases')->sole();
        $baselineProfile = $this->profile($admin->id, 'Active baseline', 'v1');
        $baselineProfile->update(['active' => true, 'activation_eligible' => true]);
        $candidateProfile = $this->profile($admin->id, 'Candidate', 'v2');

        $baseline = $this->completedRun($baselineProfile, $dataset, $admin->id, [1.0, 1.0]);
        $baselineProfile->update([
            'approval_evaluation_run_id' => $baseline->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'approval_release_notes' => 'Current approved baseline.',
        ]);
        $candidate = $this->completedRun($candidateProfile, $dataset, $admin->id, [1.0, 0.8], 0.9);

        $this->actingAs($admin)->get(route('social.ai-evaluations.index', [
            'baseline' => $baseline->uuid,
            'candidate' => $candidate->uuid,
        ]))->assertOk()->assertSee('Regression detected');

        $this->actingAs($admin)->post(
            route('social.ai-evaluations.runs.approve', [$candidateProfile, $candidate]),
            ['release_notes' => 'Candidate release attempt.'],
        )->assertSessionHas('error', 'Approval blocked because this profile regresses against the active profile on the same dataset.');
        $this->assertFalse($candidateProfile->refresh()->activation_eligible);
    }

    public function test_replacement_approval_requires_an_approved_active_profile_baseline(): void
    {
        $admin = $this->userWithPermissions(['social.ai.manage']);
        $dataset = SocialAiEvaluationDataset::query()->with('cases')->sole();
        $activeProfile = $this->profile($admin->id, 'Legacy active profile', 'v1');
        $activeProfile->update(['active' => true, 'activation_eligible' => true]);
        $candidateProfile = $this->profile($admin->id, 'Replacement', 'v2');
        $candidate = $this->completedRun($candidateProfile, $dataset, $admin->id, [1.0, 1.0]);

        $this->actingAs($admin)->post(
            route('social.ai-evaluations.runs.approve', [$candidateProfile, $candidate]),
            ['release_notes' => 'Replacement candidate.'],
        )->assertSessionHas(
            'error',
            'Evaluate and approve the active profile on this frozen dataset before approving a replacement.',
        );
        $this->assertFalse($candidateProfile->refresh()->activation_eligible);
    }

    private function profile(int $userId, string $name = 'Evaluation candidate', string $version = 'v1'): SocialAiExtractionProfile
    {
        return SocialAiExtractionProfile::query()->create([
            'name' => $name,
            'version' => $version,
            'model' => 'evaluation-model',
            'instructions' => 'Extract explicit Khmer and English address fields.',
            'active' => false,
            'activation_eligible' => false,
            'created_by' => $userId,
        ]);
    }

    /** @param list<float> $scores */
    private function completedRun(
        SocialAiExtractionProfile $profile,
        SocialAiEvaluationDataset $dataset,
        int $userId,
        array $scores,
        ?float $overallScore = null,
    ): SocialAiEvaluationRun {
        $run = $profile->evaluationRuns()->create([
            'social_ai_evaluation_dataset_id' => $dataset->id,
            'status' => AiEvaluationRunStatus::Completed,
            'case_ids' => $dataset->cases->pluck('id')->all(),
            'total_cases' => count($scores),
            'passed_cases' => collect($scores)->filter(fn (float $score): bool => $score >= 0.85)->count(),
            'failed_cases' => collect($scores)->filter(fn (float $score): bool => $score < 0.85)->count(),
            'score' => $overallScore ?? array_sum($scores) / count($scores),
            'requested_by' => $userId,
            'completed_at' => now(),
        ]);
        foreach ($dataset->cases->values() as $index => $case) {
            $score = $scores[$index];
            $run->results()->create([
                'social_ai_evaluation_case_id' => $case->id,
                'status' => $score >= 0.85 ? AiEvaluationResultStatus::Passed : AiEvaluationResultStatus::Failed,
                'score' => $score,
                'passed' => $score >= 0.85,
                'actual_result' => [],
                'differences' => $score >= 0.85 ? [] : ['quantity'],
                'processed_at' => now(),
            ]);
        }

        return $run;
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
