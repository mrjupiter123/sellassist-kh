@extends('layouts.app')
@section('title', ($managedUser ? 'Edit user' : 'Add user').' · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('users.index') }}">← Users</a><h1 class="h3 mt-2">{{ $managedUser ? 'Edit user' : 'Add user' }}</h1></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ $managedUser ? route('users.update', $managedUser) : route('users.store') }}">
    @csrf @if($managedUser) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-md-6"><label class="form-label">Name *</label><input class="form-control" name="name" value="{{ old('name', $managedUser?->name) }}" required></div>
        <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email" value="{{ old('email', $managedUser?->email) }}" required></div>
        <div class="col-md-6"><label class="form-label">Password {{ $managedUser ? '(leave blank to keep current)' : '*' }}</label><input class="form-control" type="password" name="password" {{ $managedUser ? '' : 'required' }}></div>
        <div class="col-md-6"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="password_confirmation" {{ $managedUser ? '' : 'required' }}></div>
        <div class="col-md-6"><label class="form-label">Role *</label><select class="form-select" name="role" required>@foreach(['admin' => 'Admin', 'staff' => 'Staff'] as $value => $label)<option value="{{ $value }}" @selected(old('role', $managedUser?->roles->first()?->name ?? 'staff') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-6 d-flex align-items-end"><input type="hidden" name="active" value="0"><div class="form-check mb-2"><input class="form-check-input" id="active" type="checkbox" name="active" value="1" @checked((bool) old('active', $managedUser?->active ?? true))><label class="form-check-label" for="active">Active account</label></div></div>
    </div>
    <button class="btn btn-primary mt-4">{{ $managedUser ? 'Save changes' : 'Create user' }}</button>
</form></div></div>
@endsection
