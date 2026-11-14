<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roleModel')->orderBy('name')->paginate(20);

        return view('users.index', compact('users'));
    }

    public function create()
    {
        return view('users.form', ['user' => new User, 'roles' => Role::orderBy('name')->get()]);
    }

    public function store(Request $request, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'pin' => ['nullable', 'digits:4'],
            'role' => ['required', 'exists:roles,slug'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $this->authorizeRole($request, $data['role']);
        $user = User::create($data);
        $audit->log('user.created', $user, ['role' => $user->role], $request->user());

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        return view('users.form', ['user' => $user, 'roles' => Role::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user, AuditService $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'pin' => ['nullable', 'digits:4'],
            'role' => ['required', 'exists:roles,slug'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (empty($data['pin'])) {
            unset($data['pin']);
        }
        $data['is_active'] = $request->boolean('is_active');
        $this->authorizeRole($request, $data['role']);
        $this->authorizeRole($request, $user->role);
        if ($user->id === $request->user()->id && (! $data['is_active'] || ! Role::where('slug', $data['role'])->firstOrFail()->hasPermission('manage_users'))) {
            throw ValidationException::withMessages(['role' => 'Keep your own account active with user-management access.']);
        }
        DB::transaction(function () use ($user, $data) {
            $owners = User::where('role', 'owner')->where('is_active', true)->lockForUpdate()->get();
            if ($user->role === 'owner' && $user->is_active && ($data['role'] !== 'owner' || ! $data['is_active']) && $owners->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'At least one active owner must remain.']);
            }
            $user->update($data);
        });
        $audit->log('user.updated', $user, ['role' => $user->role, 'active' => $user->is_active], $request->user());

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    private function authorizeRole(Request $request, string $slug): void
    {
        $role = Role::where('slug', $slug)->firstOrFail();
        foreach ($role->permissions ?? [] as $permission) {
            abort_unless($request->user()->hasPermission($permission), 403, 'You cannot assign or manage a role above your permissions.');
        }
    }
}
