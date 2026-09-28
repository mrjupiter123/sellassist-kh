@extends('layouts.app')

@section('title', 'Customers · SellAssist KH')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h1 class="h3 mb-1">Customers</h1><p class="text-secondary mb-0">Find customer details and order history.</p></div>
    @can('customers.create')<a class="btn btn-primary" href="{{ route('customers.create') }}">Add customer</a>@endcan
</div>
<div class="card">
    <div class="card-body border-bottom">
        <form class="row g-2" method="GET">
            <div class="col-sm-9"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search name, phone, or Facebook name"></div>
            <div class="col-sm-3 d-grid"><button class="btn btn-outline-secondary">Search</button></div>
        </form>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Name</th><th>Contact</th><th>Source</th><th>Orders</th><th></th></tr></thead>
        <tbody>
        @forelse($customers as $customer)
            <tr>
                <td><a class="fw-semibold text-decoration-none" href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a><div class="small text-secondary">{{ $customer->facebook_name ?: '—' }}</div></td>
                <td>{{ $customer->phone ?: '—' }}<div class="small text-secondary">{{ $customer->email }}</div></td>
                <td>{{ $customer->source->label() }}</td><td>{{ $customer->orders_count }}</td>
                <td class="text-end">@can('customers.update')<a class="btn btn-sm btn-outline-secondary" href="{{ route('customers.edit', $customer) }}">Edit</a>@endcan</td>
            </tr>
        @empty<tr><td colspan="5" class="text-center text-secondary py-4">No customers found.</td></tr>@endforelse
        </tbody>
    </table></div>
    @if($customers->hasPages())<div class="card-footer bg-white">{{ $customers->links() }}</div>@endif
</div>
@endsection

