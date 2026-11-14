@extends('layouts.app')
@section('title', 'Stock receipt '.$receipt->receipt_number)
@section('body-class', 'stock-receipt-page')
@section('content')
<div class="page-head"><div><h1>{{ $receipt->receipt_number }}</h1><p>Received: {{ $receipt->received_on->format('d M Y') }} · Reference: {{ $receipt->reference ?: '—' }}</p></div><div class="page-actions no-print"><a class="btn btn-secondary" href="{{ route('inventory.receipts.index') }}">Receipt history</a><button class="btn" onclick="window.print()">Print receipt</button></div></div>
<div class="print-only"><strong>{{ \App\Support\Pos::company() }}</strong><p>{{ \App\Support\Pos::address() }}</p></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Product</th><th>Variant</th><th>Part no.</th><th>Qty received</th><th>Stock after recording</th>@if(auth()->user()->canViewCost())<th>Unit cost AED</th><th>Value AED</th>@endif</tr></thead><tbody>
@foreach($receipt->items as $item)<tr><td>{{ $item->product_name }}</td><td>{{ $item->variant_label ?: 'Single product' }}</td><td>{{ $item->part_number ?: '—' }}</td><td>{{ (float) $item->qty }}</td><td>{{ (float) $item->balance_after }}</td>@if(auth()->user()->canViewCost())<td>{{ number_format((float) $item->unit_cost, 2) }}</td><td>{{ number_format((float) $item->qty * (float) $item->unit_cost, 2) }}</td>@endif</tr>@endforeach
</tbody></table></div><div class="card-body"><p>Recorded by {{ $receipt->user->name }} on {{ $receipt->created_at->format('d M Y H:i') }}.</p>@if($receipt->notes)<p>{{ $receipt->notes }}</p>@endif</div></div>
@endsection
