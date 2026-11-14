@extends('layouts.app')
@section('title', $role->exists ? 'Edit role' : 'New role')
@section('subtitle', 'Role permissions')
@section('content')
<div class="page-head"><div><h1>{{ $role->exists ? 'Edit role' : 'New custom role' }}</h1><p>Choose exactly which screens and actions this role can use. A profile never grants extra permissions.</p></div><div class="page-actions"><a class="btn btn-secondary" href="{{ route('roles.index') }}">Back to roles</a></div></div>
@if($role->is_system)<div class="alert alert-info">This is a fixed system role. Create a custom role to change permissions.</div>@endif
<form method="POST" action="{{ $role->exists ? route('roles.update', $role) : route('roles.store') }}">@csrf @if($role->exists)@method('PATCH')@endif
<div class="card"><div class="card-head"><h3>Role details</h3></div><div class="card-body"><div class="form-grid">
<div class="field"><label>Role name *</label><input name="name" value="{{ old('name', $role->name) }}" required maxlength="80" placeholder="Senior Cashier"></div>
<div class="field"><label>Staff profile *</label><select name="base_role" required>@foreach(['owner'=>'Owner','manager'=>'Manager','cashier'=>'Cashier','storekeeper'=>'Storekeeper','accountant'=>'Accountant'] as $value=>$label)<option value="{{ $value }}" @selected(old('base_role', $role->base_role) === $value)>{{ $label }}</option>@endforeach</select><div class="hint">Sets counter behaviour, such as cashier-only invoice scope. Screen access is controlled by the permissions below.</div></div>
</div><div class="mt-2"><label class="check"><input type="checkbox" id="role-all"> Select all permissions</label></div>
<div class="grid-2 mt-2">@foreach($permissions as $key=>$label)<label class="variant-option" style="cursor:pointer"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, old('permissions', $role->permissions ?? [])))><span><span class="v-name">{{ $label }}</span><span class="v-meta">{{ $key }}</span></span></label>@endforeach</div>
</div><div class="modal-foot"><a class="btn btn-secondary" href="{{ route('roles.index') }}">Cancel</a><button class="btn" type="submit" @disabled($role->is_system)>{{ $role->exists ? 'Save role' : 'Create role' }}</button></div></div></form>
@push('scripts')<script>document.getElementById('role-all')?.addEventListener('change', e => document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = e.target.checked));</script>@endpush
@endsection
