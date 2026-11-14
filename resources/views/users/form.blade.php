@extends('layouts.app')
@section('title', $user->exists ? 'Edit user' : 'New user')
@section('subtitle', 'Staff account and role')
@section('content')
<div class="page-head"><div><h1>{{ $user->exists ? 'Edit user' : 'New user' }}</h1><p>Assign a role to control which screens and permissions this user gets.</p></div><div class="page-actions"><a class="btn btn-secondary" href="{{ route('users.index') }}">Back to users</a></div></div>
<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}">@csrf @if($user->exists)@method('PATCH')@endif
<div class="card"><div class="card-head"><h3>Account details</h3></div><div class="card-body"><div class="form-grid">
<div class="field"><label>Name *</label><input name="name" value="{{ old('name', $user->name) }}" required maxlength="150"></div>
<div class="field"><label>Email *</label><input name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="150"></div>
<div class="field"><label>Password {{ $user->exists ? '' : '*' }}</label><input name="password" type="password" {{ $user->exists ? '' : 'required' }} minlength="8"><div class="hint">{{ $user->exists ? 'Leave blank to keep the current password.' : 'Minimum 8 characters.' }}</div></div>
<div class="field"><label>Approval PIN (4 digits)</label><input name="pin" inputmode="numeric" maxlength="4" placeholder="{{ $user->exists ? 'Leave blank to keep' : 'Optional' }}"><div class="hint">Used for discounts, voids and approvals.</div></div>
<div class="field"><label>Role *</label><select name="role" required>@foreach($roles as $role)<option value="{{ $role->slug }}" @selected(old('role', $user->role) === $role->slug)>{{ $role->name }}@if(!$role->is_system) (custom)@endif</option>@endforeach</select></div>
<div class="field"><label>Status</label><label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->exists ? $user->is_active : true))> Active</label></div>
</div></div></div>
<div class="flex justify-between items-center mt-2"><a class="btn btn-ghost" href="{{ route('users.index') }}">Cancel</a><button class="btn btn-lg" type="submit">{{ $user->exists ? 'Save user' : 'Create user' }}</button></div>
</form>
@endsection
