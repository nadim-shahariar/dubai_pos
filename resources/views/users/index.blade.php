@extends('layouts.app')
@section('title', 'Users')
@section('subtitle', 'Staff accounts and role assignment')
@section('content')
<div class="page-head">
    <div><h1>Users</h1><p>Create staff accounts and assign a system or custom role.</p></div>
    <div class="page-actions"><a class="btn btn-secondary" href="{{ route('roles.index') }}">Manage roles</a><a class="btn" href="{{ route('users.create') }}">+ Add user</a></div>
</div>
<div class="card"><div class="table-wrap"><table class="table">
<thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead>
<tbody>@forelse($users as $user)
<tr>
<td class="main-cell">{{ $user->name }}</td>
<td>{{ $user->email }}</td>
<td><span class="badge badge-teal">{{ $user->roleLabel() }}</span>@if($user->roleModel && !$user->roleModel->is_system)<div class="sub">Custom · base {{ ucfirst($user->roleModel->base_role) }}</div>@endif</td>
<td><span class="badge {{ $user->is_active ? 'badge-green' : 'badge-red' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
<td>{{ $user->last_login_at?->format('d M Y H:i') ?: 'Never' }}</td>
<td class="text-right"><a class="btn btn-secondary btn-sm" href="{{ route('users.edit', $user) }}">Edit</a></td>
</tr>
@empty<tr><td colspan="6"><div class="empty">No users yet.</div></td></tr>@endforelse</tbody>
</table></div></div>
@if($users->hasPages())<div class="pagination"><span class="info">Page {{ $users->currentPage() }} of {{ $users->lastPage() }}</span>@if($users->previousPageUrl())<a href="{{ $users->previousPageUrl() }}">← Previous</a>@endif @if($users->nextPageUrl())<a href="{{ $users->nextPageUrl() }}">Next →</a>@endif</div>@endif
@endsection
