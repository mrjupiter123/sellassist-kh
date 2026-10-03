<?php

declare(strict_types=1);

namespace App\Domain\Social\Services;

use App\Domain\Customer\Models\Customer;
use App\Domain\Social\Enums\SocialPlatform;
use App\Domain\Social\Models\SocialContact;

final class CustomerMatchService
{
    public function suggest(SocialContact $contact, ?string $message): ?Customer
    {
        if ($contact->customer_id !== null) {
            return null;
        }

        $contact->loadMissing('channel');
        if ($contact->channel->platform === SocialPlatform::FacebookMessenger && filled($contact->display_name)) {
            $nameMatches = Customer::query()
                ->where('facebook_name', $contact->display_name)
                ->limit(2)
                ->get();
            if ($nameMatches->count() === 1) {
                return $nameMatches->first();
            }
        }

        $phone = $this->extractPhone($message);
        if ($phone === null) {
            return null;
        }

        $matches = Customer::query()
            ->where('phone', $phone)
            ->orWhere('phone_secondary', $phone)
            ->limit(2)
            ->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function extractPhone(?string $message): ?string
    {
        if (! filled($message)) {
            return null;
        }

        preg_match_all('/(?:\+?855|0)[\d\s-]{7,13}/', (string) $message, $matches);
        $phones = collect($matches[0] ?? [])
            ->map(fn (string $value): string => str_starts_with($value, '+')
                ? '+'.preg_replace('/\D/', '', $value)
                : preg_replace('/\D/', '', $value))
            ->filter(fn (string $value): bool => strlen($value) >= 9)
            ->unique()
            ->values();

        return $phones->count() === 1 ? $phones->first() : null;
    }
}
