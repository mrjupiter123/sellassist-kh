@extends('layouts.app')
@section('title', 'Edit product · SellAssist KH')
@section('content')<div class="mb-4"><a href="{{ route('products.show', $product) }}">← {{ $product->name }}</a><h1 class="h3 mt-2">Edit product</h1></div><div class="card"><div class="card-body"><form method="POST" action="{{ route('products.update', $product) }}">@csrf @method('PUT') @include('products._form')<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('products.show', $product) }}">Cancel</a><button class="btn btn-primary">Save changes</button></div></form></div></div>@endsection

