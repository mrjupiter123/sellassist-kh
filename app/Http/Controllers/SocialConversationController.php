<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customer\Models\Customer;
use App\Domain\Payment\Enums\Currency;
use App\Domain\Product\Models\Product;
use App\Domain\Social\Enums\ConversationStatus;
use App\Domain\Social\Models\SocialConversation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SocialConversationController extends Controller
{
    public function index(Request $request): View
    {
        $conversations = SocialConversation::query()
            ->with(['channel:id,uuid,platform,name', 'contact.customer:id,uuid,name', 'contact.suggestedCustomer:id,uuid,name'])
            ->withCount('messages')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.addcslashes(trim($request->string('search')->toString()), '%_\\').'%';
                $query->whereHas('contact', fn ($query) => $query
                    ->where('display_name', 'like', $term)
                    ->orWhere('external_id', 'like', $term));
            })
            ->latest('last_message_at')
            ->paginate(20)
            ->withQueryString();

        return view('social.conversations.index', [
            'conversations' => $conversations,
            'statuses' => ConversationStatus::cases(),
        ]);
    }

    public function show(SocialConversation $conversation): View
    {
        $conversation->load([
            'channel', 'contact.customer', 'contact.suggestedCustomer',
            'messages', 'convertedOrder',
        ]);
        $products = Product::query()
            ->where('active', true)
            ->with(['variants' => fn ($query) => $query->where('active', true)->orderBy('color')->orderBy('size')])
            ->orderBy('name')
            ->get();

        return view('social.conversations.show', [
            'conversation' => $conversation,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'currencies' => Currency::cases(),
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
}
