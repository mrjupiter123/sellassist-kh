@extends('layouts.app')
@section('title', 'Edit customer · SellAssist KH')
@section('content')
<div class="mb-4"><a href="{{ route('customers.show', $customer) }}">← {{ $customer->name }}</a><h1 class="h3 mt-2">Edit customer</h1></div>
<div class="card"><div class="card-body"><form method="POST" action="{{ route('customers.update', $customer) }}">@csrf @method('PUT') @include('customers._form')<div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-light" href="{{ route('customers.show', $customer) }}">Cancel</a><button class="btn btn-primary">Save changes</button></div></form></div></div>
@endsection

