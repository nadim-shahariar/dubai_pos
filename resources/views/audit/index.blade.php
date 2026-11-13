@extends('layouts.app')

@section('title', 'Audit trail')
@section('subtitle', 'Who did what, and when')

@section('content')
<div class="page-head">
    <div><h1>Audit trail</h1><p>Append-only record of sales, cost and price changes, stock actions, returns, settings and logins.</p></div>
</div>

<div class="card">
    <div class="card-head">
        <form method="GET" class="inline-form" style="flex:1">
            <div class="field" style="max-width:220px"><label>Event contains</label><input name="event" value="{{ request('event') }}" placeholder="sale.completed"></div>
            <div class="field" style="max-width:180px"><label>User</label><select name="user"><option value="">All users</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
            <div class="field" style="max-width:150px"><label>From</label><input type="date" name="from" value="{{ request('from') }}"></div>
            <div class="field" style="max-width:150px"><label>To</label><input type="date" name="to" value="{{ request('to') }}"></div>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
        <span class="small muted">{{ $logs->total() }} events</span>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>When</th><th>Event</th><th>User</th><th>Record</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                    <td><span class="badge badge-navy">{{ $log->event }}</span></td>
                    <td>{{ $log->user?->name ?: 'System' }}</td>
                    <td class="small mono">{{ class_basename($log->auditable_type ?? '—') }}{{ $log->auditable_id ? ' #'.$log->auditable_id : '' }}</td>
                    <td class="small" style="max-width:360px">{{ $log->meta ? \Illuminate\Support\Str::limit(json_encode($log->meta), 160) : '—' }}</td>
                    <td class="small muted">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty">No audit events in this view.</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@if($logs->hasPages())
    <div class="pagination">
        <span class="info">Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}</span>
        @if($logs->previousPageUrl())<a href="{{ $logs->previousPageUrl() }}">← Previous</a>@endif
        @if($logs->nextPageUrl())<a href="{{ $logs->nextPageUrl() }}">Next →</a>@endif
    </div>
@endif
@endsection
