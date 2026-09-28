@extends('layouts.app')
@section('title', 'Add product · SellAssist KH')
@section('content')<div class="mb-4"><a href="{{ route('products.index') }}">← Products</a><h1 class="h3 mt-2">Add product</h1></div><div class="card"><div class="card-body"><form method="POST" action="{{ route('products.store') }}">@csrf @include('products._form')<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('products.index') }}">Cancel</a><button class="btn btn-primary">Save product</button></div></form></div></div>@endsection

