<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SellAssist KH')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
@auth
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container-xl">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="{{ route('dashboard') }}">
                <span class="navbar-brand-mark">S</span> SellAssist KH
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    @can('orders.view')<li class="nav-item"><a class="nav-link" href="{{ route('orders.index') }}">Orders</a></li>@endcan
                    @can('customers.view')<li class="nav-item"><a class="nav-link" href="{{ route('customers.index') }}">Customers</a></li>@endcan
                    @can('products.view')<li class="nav-item"><a class="nav-link" href="{{ route('products.index') }}">Products</a></li>@endcan
                    @can('inventory.view')<li class="nav-item"><a class="nav-link" href="{{ route('inventory.index') }}">Inventory</a></li>@endcan
                    @can('users.view')<li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">Users</a></li>@endcan
                </ul>
                <span class="navbar-text me-3">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">Log out</button>
                </form>
            </div>
        </div>
    </nav>
@endauth

<main class="container-xl py-4">
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Please check the form.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>
</body>
</html>

