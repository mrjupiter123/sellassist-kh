@extends('layouts.app')
@section('title', 'Users · SellAssist KH')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h1 class="h3 mb-1">Users</h1><p class="text-secondary mb-0">Manage administrator and staff access.</p></div>
    @can('users.manage')<a class="btn btn-primary" href="{{ route('users.create') }}">Add user</a>@endcan
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>@forelse($users as $managedUser)<tr>
        <td class="fw-semibold">{{ $managedUser->name }}</td><td>{{ $managedUser->email }}</td>
        <td>{{ ucfirst($managedUser->roles->first()?->name ?? 'Unassigned') }}</td>
        <td><span class="badge {{ $managedUser->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $managedUser->active ? 'Active' : 'Inactive' }}</span></td>
        <td class="text-end">@can('users.manage')<a class="btn btn-sm btn-outline-secondary" href="{{ route('users.edit', $managedUser) }}">Edit</a>@endcan</td>
    </tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">No users found.</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $users->links() }}</div>
@endsection
