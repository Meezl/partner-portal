<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\StaffAccountCreatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'role' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'active' => ['nullable', 'in:all,active,inactive'],
        ]);

        $users = User::query()
            ->with('partner:id,organization_name,slug')
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['search'] ?? null, function ($q, $term) {
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->when(($filters['active'] ?? 'all') === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['active'] ?? 'all') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('role')
            ->orderBy('name')
            ->get([
                'id', 'name', 'email', 'role', 'phone', 'department',
                'is_active', 'last_login_at', 'created_at', 'partner_id',
            ]);

        $counts = User::query()
            ->selectRaw('role, count(*) as c')
            ->groupBy('role')
            ->pluck('c', 'role');

        $roles = collect(UserRole::cases())->map(fn ($r) => [
            'value' => $r->value,
            'label' => $r->label(),
            'count' => (int) ($counts[$r->value] ?? 0),
        ])->values();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'roles' => $roles,
            'filters' => [
                'role' => $filters['role'] ?? null,
                'search' => $filters['search'] ?? '',
                'active' => $filters['active'] ?? 'all',
            ],
            'totals' => [
                'all' => (int) $counts->sum(),
                'active' => User::where('is_active', true)->count(),
            ],
            'can' => [
                'manage' => $this->canManage($request->user()),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request);

        $password = $data['password'] ?: Str::random(16);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($password),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'department' => $data['department'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'email_verified_at' => now(),
        ]);

        // The new account holder is emailed and sets their own password through
        // the reset flow. The generated password is deliberately not echoed back
        // in the flash message — that would put a live credential into a toast,
        // the browser history and any log that captures session data.
        $user->notify(new StaffAccountCreatedNotification($request->user()));

        return back()->with('success', "User {$user->name} created and emailed a link to set their password.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $this->validated($request, $user);

        $user->update([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'role' => $data['role'],
            'phone' => $data['phone'] ?? null,
            'department' => $data['department'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return back()->with('success', "User {$user->name} updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManage($request);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->partner_id || $user->partner) {
            return back()->with('error', 'This user owns a partner profile — remove or reassign the partner record first.');
        }

        $name = $user->name;
        $user->delete();

        return back()->with('success', "User {$name} deleted.");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::in(array_column(UserRole::cases(), 'value'))],
            'phone' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'password' => [$user ? 'nullable' : 'nullable', 'string', 'min:8', 'max:255'],
        ]);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($this->canManage($request->user()), 403, 'You do not have permission to manage users.');
    }

    private function canManage(?User $user): bool
    {
        return $user && in_array($user->role, [UserRole::SuperAdmin, UserRole::Admin], true);
    }
}
