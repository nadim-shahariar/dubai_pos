@extends('layouts.app')
@section('title', 'Stock receipts')
@section('subtitle', 'Goods received by date')
@section('content')
<div class="page-head"><div><h1>Stock receipt history</h1><p>Filter by the date goods were received.</p></div><div class="page-actions"><a class="btn" href="{{ route('inventory.receipts.create') }}">+ Receive multiple items</a><a class="btn btn-secondary" href="{{ route('inventory.index') }}">Inventory</a></div></div>
<form method="GET" class="card card-pad mb-2"><div class="inline-form"><div class="field"><label>From</label><input type="date" name="from" value="{{ $from }}"></div><div class="field"><label>To</label><input type="date" name="to" value="{{ $to }}"></div><button class="btn btn-secondary">Filter</button></div></form>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Receipt</th><th>Received date</th><th>Reference</th><th>Items</th><th>Recorded by</th><th></th></tr></thead><tbody>
@forelse($receipts as $receipt)<tr><td class="mono">{{ $receipt->receipt_number }}</td><td>{{ $receipt->received_on->format('d M Y') }}</td><td>{{ $receipt->reference ?: '—' }}</td><td>{{ $receipt->items_count }}</td><td>{{ $receipt->user->name }}</td><td><a class="btn btn-secondary btn-sm" href="{{ route('inventory.receipts.show', $receipt) }}">Open / print</a></td></tr>@empty<tr><td colspan="6">No receipts in this date range.</td></tr>@endforelse
</tbody></table></div></div>
@if($receipts->hasPages())<div class="pagination"><span class="info">Page {{ $receipts->currentPage() }} of {{ $receipts->lastPage() }}</span>@if($receipts->previousPageUrl())<a href="{{ $receipts->previousPageUrl() }}">← Previous</a>@endif @if($receipts->nextPageUrl())<a href="{{ $receipts->nextPageUrl() }}">Next →</a>@endif</div>@endif
@endsection
