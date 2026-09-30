<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customer\Models\Customer;
use App\Domain\Social\Actions\CreateCustomerFromSocialContact;
use App\Domain\Social\Actions\LinkSocialContactToCustomer;
use App\Domain\Social\Models\SocialConversation;
use App\Http\Requests\Social\CreateSocialCustomerRequest;
use App\Http\Requests\Social\LinkSocialContactRequest;
use Illuminate\Http\RedirectResponse;

class SocialContactController extends Controller
{
    public function link(
        LinkSocialContactRequest $request,
        SocialConversation $conversation,
        LinkSocialContactToCustomer $action,
    ): RedirectResponse {
        $action->execute($conversation->contact, Customer::query()->findOrFail($request->integer('customer_id')));

        return back()->with('success', 'Conversation linked to the customer.');
    }

    public function createCustomer(
        CreateSocialCustomerRequest $request,
        SocialConversation $conversation,
        CreateCustomerFromSocialContact $action,
    ): RedirectResponse {
        $action->execute($conversation->contact, $request->validated());

        return back()->with('success', 'Customer created and linked to the conversation.');
    }
}
