<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Spatie\Multitenancy\Models\Tenant;

class UserService
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $tenantId = Tenant::current()?->id;

        return User::query()
            ->where('tenant_id', $tenantId)
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, function ($q, $role) {
                $q->whereHas('roles', fn ($q) => $q->where('name', $role));
            })
            ->with('roles')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
    }

    public function create(array $data): User
    {
        $tenantId = Tenant::current()?->id;

        $user = User::create([
            'tenant_id' => $tenantId,
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
        ]);

        if (!empty($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $attributes = [
            'name'  => $data['name'],
            'email' => $data['email'],
        ];

        if (!empty($data['password'])) {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user->update($attributes);

        if (array_key_exists('role', $data)) {
            $user->syncRoles($data['role'] ? [$data['role']] : []);
        }

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    public function rolesForSelect(): \Illuminate\Database\Eloquent\Collection
    {
        return Role::orderBy('name')->get(['id', 'name']);
    }
}
