@extends('layouts.app')
@section('title', 'Products · SellAssist KH')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4"><div><h1 class="h3 mb-1">Products</h1><p class="text-secondary mb-0">Catalog, variants, pricing, and stock.</p></div>@can('products.create')<a class="btn btn-primary" href="{{ route('products.create') }}">Add product</a>@endcan</div>
<div class="card"><div class="card-body border-bottom"><form class="row g-2" method="GET"><div class="col-sm-9"><input class="form-control" name="search" value="{{ $search }}" placeholder="Search product name or SKU"></div><div class="col-sm-3 d-grid"><button class="btn btn-outline-secondary">Search</button></div></form></div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Variants</th><th>Base stock</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($products as $product)<tr><td><a class="fw-semibold text-decoration-none" href="{{ route('products.show', $product) }}">{{ $product->name }}</a><div class="small text-secondary">{{ $product->sku ?: 'No SKU' }}</div></td><td>{{ $product->category ?: '—' }}</td><td>${{ number_format((float) $product->base_price, 2) }}</td><td>{{ $product->variants_count }}</td><td>{{ $product->stock_quantity }}</td><td><span class="badge {{ $product->active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $product->active ? 'Active' : 'Inactive' }}</span></td><td class="text-end">@can('products.update')<a class="btn btn-sm btn-outline-secondary" href="{{ route('products.edit', $product) }}">Edit</a>@endcan</td></tr>
@empty<tr><td colspan="7" class="text-center text-secondary py-4">No products found.</td></tr>@endforelse
</tbody></table></div>@if($products->hasPages())<div class="card-footer bg-white">{{ $products->links() }}</div>@endif</div>
@endsection

