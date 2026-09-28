<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateUser
{
    /** @param array<string, mixed> $data */
    public function execute(User $user, array $data, User $actor): User
    {
        return DB::transaction(function () use ($user, $data, $actor): User {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $role = (string) $data['role'];
            $active = (bool) $data['active'];

            if ($lockedUser->is($actor) && ! $active) {
                throw ValidationException::withMessages(['active' => 'You cannot deactivate your own account.']);
            }

            if ($lockedUser->hasRole('admin') && (! $active || $role !== 'admin')) {
                $otherActiveAdmins = User::query()
                    ->whereKeyNot($lockedUser->getKey())
                    ->where('active', true)
                    ->role('admin')
                    ->lockForUpdate()
                    ->exists();

                if (! $otherActiveAdmins) {
                    throw ValidationException::withMessages(['role' => 'At least one active administrator is required.']);
                }
            }

            $values = Arr::except($data, ['role', 'password']);
            if (filled($data['password'] ?? null)) {
                $values['password'] = $data['password'];
            }

            $lockedUser->update($values);
            $lockedUser->syncRoles([$role]);

            return $lockedUser->refresh();
        });
    }
}
