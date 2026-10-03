<?php

declare(strict_types=1);

namespace App\Domain\Social\Actions;

use App\Domain\Customer\Actions\CreateCustomer;
use App\Domain\Customer\Enums\CustomerSource;
use App\Domain\Customer\Models\Customer;
use App\Domain\Social\Enums\SocialPlatform;
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
            $contact->loadMissing('channel');
            $source = match ($contact->channel->platform) {
                SocialPlatform::FacebookMessenger => CustomerSource::Messenger,
                SocialPlatform::Telegram => CustomerSource::Telegram,
            };
            $customerData = [
                ...$data,
                'source' => $source,
            ];
            if ($contact->channel->platform === SocialPlatform::FacebookMessenger) {
                $customerData['facebook_name'] = $contact->display_name;
            }

            $customer = $this->createCustomer->execute($customerData);
            $this->linkContact->execute($contact, $customer);

            return $customer;
        });
    }
}
