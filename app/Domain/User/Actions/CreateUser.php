<?php

declare(strict_types=1);

namespace App\Domain\User\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateUser
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = (string) $data['role'];
            unset($data['role']);

            $user = User::query()->create($data);
            $user->syncRoles([$role]);

            return $user->refresh();
        });
    }
}
