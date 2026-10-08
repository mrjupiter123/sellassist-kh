<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customer\Models\Customer;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Product\Models\Product;
use App\Domain\Social\Actions\MarkSocialConversationRead;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Enums\OrderExtractionReviewVerdict;
use App\Domain\Social\Enums\OrderExtractionStatus;
use App\Domain\Social\Models\SocialConversation;
use App\Domain\Social\Models\SocialReplyTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialConversationController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = SocialConversation::query()
            ->with([
                'channel:id,uuid,platform,name',
                'contact.customer:id,uuid,name',
                'contact.suggestedCustomer:id,uuid,name',
                'assignee:id,uuid,name',
                'readReceipts' => fn ($query) => $query->where('user_id', $request->user()->id),
            ])
            ->withCount('messages')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.addcslashes(trim($request->string('search')->toString()), '%_\\').'%';
                $query->whereHas('contact', fn ($query) => $query
                    ->where('display_name', 'like', $term)
                    ->orWhere('external_id', 'like', $term));
            })
            ->when($request->boolean('unread'), fn ($query) => $query
                ->whereNotNull('last_inbound_at')
                ->where(function ($query) use ($request): void {
                    $query->whereDoesntHave('readReceipts', fn ($query) => $query->where('user_id', $request->user()->id))
                        ->orWhereHas('readReceipts', fn ($query) => $query
                            ->where('user_id', $request->user()->id)
                            ->whereColumn('social_conversation_reads.read_at', '<', 'social_conversations.last_inbound_at'));
                }))
            ->when($request->filled('assigned'), function ($query) use ($request): void {
                $assigned = $request->string('assigned')->toString();
                match ($assigned) {
                    'mine' => $query->where('assigned_to', $request->user()->id),
                    'unassigned' => $query->whereNull('assigned_to'),
                    default => ctype_digit($assigned) ? $query->where('assigned_to', (int) $assigned) : null,
                };
            })
            ->latest('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('social.conversations.index', [
            'conversations' => $conversations,
            'statuses' => ConversationStatus::cases(),
            'assignees' => $this->assignees(),
        ]);
    }

    public function show(
        Request $request,
        SocialConversation $conversation,
        MarkSocialConversationRead $markRead,
    ): View {
        $markRead->execute($conversation, $request->user());
        $conversation->load([
            'channel', 'contact.customer', 'contact.suggestedCustomer',
            'messages', 'convertedOrder', 'assignee:id,uuid,name', 'assigner:id,uuid,name',
            'latestOrderExtraction.items.product:id,name',
            'latestOrderExtraction.items.variant:id,product_id,color,size',
            'latestOrderExtraction.review.reviewer:id,uuid,name',
        ]);
        $products = Product::query()
            ->where('active', true)
            ->with(['variants' => fn ($query) => $query->where('active', true)->orderBy('color')->orderBy('size')])
            ->orderBy('name')
            ->get();

        $extraction = $conversation->latestOrderExtraction;
        $readyExtraction = $extraction?->status === OrderExtractionStatus::Ready ? $extraction : null;
        $suggestedItems = $readyExtraction?->items
            ->filter(fn ($item): bool => $item->product_id !== null)
            ->map(fn ($item): array => [
                'product_id' => (string) $item->product_id,
                'product_variant_id' => $item->product_variant_id === null ? '' : (string) $item->product_variant_id,
                'quantity' => $item->quantity,
                'discount' => '0',
            ])->values()->all() ?? [];

        return view('social.conversations.show', [
            'conversation' => $conversation,
            'latestExtraction' => $extraction,
            'aiExtractionConfigured' => config('social.ai.enabled') && filled(config('social.ai.api_key')),
            'aiLowConfidenceThreshold' => (float) config('social.ai.low_confidence_threshold', 0.65),
            'extractionReviewVerdicts' => OrderExtractionReviewVerdict::cases(),
            'suggestedOrderItems' => $suggestedItems,
            'suggestedCustomer' => [
                'name' => $readyExtraction?->customer_name,
                'phone' => $readyExtraction?->phone,
                'address' => $readyExtraction?->address,
                'province' => $readyExtraction?->province,
                'district' => $readyExtraction?->district,
                'commune' => $readyExtraction?->commune,
                'notes' => $readyExtraction?->notes,
            ],
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'currencies' => Currency::cases(),
            'assignees' => $this->assignees(),
            'replyTemplates' => SocialReplyTemplate::query()
                ->where('active', true)
                ->orderBy('title')
                ->get(['id', 'title', 'body']),
            'catalog' => $products->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->base_price,
                'variants' => $product->variants->map(fn ($variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->display_name,
                    'price' => $variant->price ?? $product->base_price,
                ])->values(),
            ])->values(),
        ]);
    }

    /** @return Collection<int, User> */
    private function assignees(): Collection
    {
        return User::query()
            ->where('active', true)
            ->permission('social.view')
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);
    }
}
