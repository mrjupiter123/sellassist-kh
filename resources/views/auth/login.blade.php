@extends('layouts.app')

@section('title', 'Sign in · SellAssist KH')

@section('content')
<div class="row justify-content-center py-5">
    <div class="col-sm-10 col-md-6 col-lg-4">
        <div class="text-center mb-4">
            <span class="navbar-brand-mark mb-2">S</span>
            <h1 class="h3">Welcome to SellAssist KH</h1>
            <p class="text-secondary">Sign in to manage orders and stock.</p>
        </div>
        <div class="card"><div class="card-body p-4">
            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input class="form-control" id="password" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-check mb-3">
                    <input type="hidden" name="remember" value="0">
                    <input class="form-check-input" id="remember" type="checkbox" name="remember" value="1">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button class="btn btn-primary w-100" type="submit">Sign in</button>
            </form>
        </div></div>
    </div>
</div>
@endsection

