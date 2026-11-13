@extends('layouts.app')
@section('title', 'Add products')
@section('subtitle', 'Enter products and variants in a table')
@section('content')
<div class="page-head"><div><h1>Add products</h1><p>Enter multiple products in one sheet. Each product can have its own vehicle variant rows.</p></div><div class="page-actions"><a class="btn btn-secondary" href="{{ route('products.index') }}">Back to catalogue</a></div></div>
<div class="card">@include('products.partials.entry-sheet')</div>
@endsection
