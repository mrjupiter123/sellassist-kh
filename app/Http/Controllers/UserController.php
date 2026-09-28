<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\User\Actions\CreateUser;
use App\Domain\User\Actions\UpdateUser;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::query()->with('roles')->orderBy('name')->paginate(20)]);
    }

    public function create(): View
    {
        return view('users.form', ['managedUser' => null]);
    }

    public function store(StoreUserRequest $request, CreateUser $action): RedirectResponse
    {
        $action->execute($request->validated());

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.form', ['managedUser' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $action): RedirectResponse
    {
        $action->execute($user, $request->validated(), $request->user());

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }
}
