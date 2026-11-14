<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function create()
    {
        return view('roles.form', ['role' => new Role, 'permissions' => Role::permissionList()]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['name']);
        $data['is_system'] = false;
        $role = Role::create($data);
        $audit->log('role.created', $role, ['name' => $role->name, 'permissions' => $role->permissions], $request->user());

        return redirect()->route('roles.index')->with('success', 'Custom role created.');
    }

    public function edit(Role $role)
    {
        return view('roles.form', ['role' => $role, 'permissions' => Role::permissionList()]);
    }

    public function update(Request $request, Role $role, AuditService $audit)
    {
        abort_if($role->is_system, 422, 'System roles are fixed. Create a custom role for different permissions.');
        $data = $this->validated($request);
        if ($request->user()->role === $role->slug && ! in_array('manage_users', $data['permissions'], true)) {
            throw ValidationException::withMessages(['permissions' => 'Keep your own user-management permission to avoid losing access.']);
        }
        foreach ($role->permissions ?? [] as $permission) {
            abort_unless($request->user()->hasPermission($permission), 403);
        }
        $role->update($data);
        $audit->log('role.updated', $role, ['permissions' => $role->permissions], $request->user());

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'base_role' => ['required', 'in:owner,manager,cashier,storekeeper,accountant'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(Role::permissionList()))],
        ]);
        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));
        foreach ($data['permissions'] as $permission) {
            abort_unless($request->user()->hasPermission($permission), 403, 'You cannot grant a permission you do not have.');
        }

        return $data;
    }

    private function slug(string $name): string
    {
        $base = Str::limit(Str::slug($name) ?: 'custom-role', 68, '');
        $slug = $base;
        $i = 2;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
