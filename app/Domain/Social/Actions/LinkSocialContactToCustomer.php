<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Customer\Models\Customer;
use App\Domain\Social\Models\SocialContact;

final class LinkSocialContactToCustomer
{
    public function execute(SocialContact $contact, Customer $customer): SocialContact
    {
        $contact->update(['customer_id' => $customer->id, 'suggested_customer_id' => null]);

        return $contact->refresh();
    }
}
