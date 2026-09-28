@extends('layouts.app')
@section('title', 'Add customer · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('customers.index') }}">← Customers</a><h1 class="h3 mt-2">Add customer</h1></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('customers.store') }}">@csrf @include('customers._form')<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('customers.index') }}">Cancel</a><button class="btn btn-primary">Save customer</button></div></form></div></div>
@endsection

