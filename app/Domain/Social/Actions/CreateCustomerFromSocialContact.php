<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Customer\Actions\CreateCustomer;
use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Social\Models\SocialContact;
use Illuminate\Support\Facades\DB;

final class CreateCustomerFromSocialContact
{
    public function __construct(
        private readonly CreateCustomer $createCustomer,
        private readonly LinkSocialContactToCustomer $linkContact,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(SocialContact $contact, array $data): Customer
    {
        return DB::transaction(function () use ($contact, $data): Customer {
            $customer = $this->createCustomer->execute([
                ...$data,
                'facebook_name' => $contact->display_name,
                'source' => CustomerSource::Messenger,
            ]);
            $this->linkContact->execute($contact, $customer);

            return $customer;
        });
    }
}
