@extends('layouts.app')
@section('title', 'Roles')
@section('subtitle', 'Custom permissions')
@section('content')
<div class="page-head"><div><h1>Roles &amp; permissions</h1><p>System roles are fixed. Create custom roles with a staff profile and selected permissions.</p></div><div class="page-actions"><a class="btn btn-secondary" href="{{ route('users.index') }}">Users</a><a class="btn" href="{{ route('roles.create') }}">+ New role</a></div></div>
<div class="card"><div class="table-wrap"><table class="table"><thead><tr><th>Role</th><th>Staff profile</th><th>Permissions</th><th>Users</th><th></th></tr></thead><tbody>
@foreach($roles as $role)<tr><td class="main-cell">{{ $role->name }}@if($role->is_system)<div class="sub">System role</div>@endif</td><td>{{ ucfirst($role->base_role) }}</td><td><div class="flex gap-1" style="flex-wrap:wrap">@foreach(array_slice($role->permissions ?? [], 0, 6) as $permission)<span class="badge badge-gray">{{ str_replace('_',' ', $permission) }}</span>@endforeach @if(count($role->permissions ?? []) > 6)<span class="small muted">+{{ count($role->permissions) - 6 }}</span>@endif</div></td><td>{{ $role->users_count }}</td><td class="text-right">@if(!$role->is_system)<a class="btn btn-secondary btn-sm" href="{{ route('roles.edit', $role) }}">Edit</a>@else<span class="small muted">Fixed</span>@endif</td></tr>@endforeach
</tbody></table></div></div>
@endsection
