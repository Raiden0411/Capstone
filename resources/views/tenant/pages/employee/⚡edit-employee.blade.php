{{-- resources/views/tenant/pages/employee/⚡edit-employee.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\Employee;
use App\Models\User;
use App\Models\TenantSetting;
use App\Scopes\TenantScope;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Employee')]
class extends Component {
    use WithFileUploads;

    public Employee $employee;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|max:50')]
    public $employeeRole = '';

    public $phone = '';

    #[Validate('boolean')]
    public $is_active = true;

    #[Validate('nullable|string|max:20')]
    public $code = '';

    #[Validate('nullable|image|max:2048')]
    public $avatar;

    public array $selectedRoles = [];

    public string $roleSearch = '';
    public string $roleTypeFilter = 'all';

    public bool $showNewRoleForm = false;
    public string $newRoleName = '';
    public array $newRolePermissions = [];
    public string $newRolePermissionSearch = '';

    public function mount($employee)
    {
        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            abort(403);
        }

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage employees');

        if (!$canManage) {
            abort(403, 'You are not authorized to edit employees.');
        }

        // Relationship loader — reused for both the "already a model" and "string ID" cases
        $userRelation = fn ($q) => $q
            ->select('id', 'tenant_id', 'name', 'email', 'phone', 'avatar')
            ->with('roles:id,name');

        if (!$employee instanceof Employee) {
            $employee = Employee::withoutGlobalScope(TenantScope::class)
                ->with(['user' => $userRelation])
                ->findOrFail($employee);
        } else {
            // When Livewire v4 route-model-binding passes the model directly,
            // relationships aren't eager-loaded yet — load them here.
            $employee->load(['user' => $userRelation]);
        }

        if ($employee->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized.');
        }

        $this->employee = $employee;
        $this->name = $employee->name;
        $this->employeeRole = $employee->role ?? '';
        $this->phone = $employee->phone ?? '';
        $this->is_active = (bool) $employee->is_active;
        $this->code = $employee->code ?? '';
        $this->avatar = null;

        if ($employee->user_id && $employee->user) {
            $this->selectedRoles = $employee->user->roles
                ->reject(fn ($r) => in_array($r->name, ['admin', 'super-admin'], true))
                ->map(fn ($r) => 'role_' . $r->id)
                ->values()
                ->all();
        }
    }

    public function updated($field)
    {
        $trims = ['name', 'employeeRole', 'phone', 'code', 'roleSearch', 'newRoleName'];
        if (in_array($field, $trims)) {
            $this->$field = trim($this->$field);
        }
    }

    /**
     * @return Collection<int, array{
     *     type: string,
     *     value: string,
     *     label: string,
     *     permissions: array<int, string>
     * }>
     */
    #[Computed]
    public function availableRoles()
    {
        $tenantId  = Auth::user()->tenant_id;
        $usesTeams = (bool) config('permission.teams');
        $teamKey   = $usesTeams
            ? config('permission.column_names.team_foreign_key', 'team_id')
            : null;

        $roles = [];

        $globalQuery = Role::query()
            ->whereNotIn('name', ['super-admin', 'admin'])
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name');

        if ($usesTeams && $teamKey) {
            $globalQuery->whereNull($teamKey);
        }

        foreach ($globalQuery->get() as $role) {
            $roles[] = [
                'type'        => 'global',
                'value'       => 'role_' . $role->id,
                'label'       => ucfirst($role->name),
                'permissions' => $role->permissions->pluck('name')->all(),
            ];
        }

        $customQuery = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('name');

        if ($usesTeams && $teamKey) {
            $customQuery->where($teamKey, $tenantId);
        } else {
            $setting = TenantSetting::query()
                ->where('tenant_id', $tenantId)
                ->where('key', 'custom_roles')
                ->first();

            $meta = ($setting && is_array($setting->value)) ? $setting->value : [];
            $ids  = array_values(array_unique(array_filter(array_column($meta, 'role_id'))));

            if (empty($ids)) {
                $customQuery->whereRaw('1 = 0');
            } else {
                $customQuery->whereIn('id', $ids);
            }
        }

        foreach ($customQuery->get() as $role) {
            $roles[] = [
                'type'        => 'custom',
                'value'       => 'role_' . $role->id,
                'label'       => $role->name,
                'permissions' => $role->permissions->pluck('name')->all(),
            ];
        }

        $search     = $this->roleSearch;
        $typeFilter = $this->roleTypeFilter;

        if ($search !== '') {
            $roles = array_values(array_filter(
                $roles,
                fn (array $r) => stripos($r['label'], $search) !== false
            ));
        }

        if ($typeFilter !== 'all') {
            $roles = array_values(array_filter(
                $roles,
                fn (array $r) => $r['type'] === $typeFilter
            ));
        }

        return collect($roles);
    }

    /**
     * @return Collection<int, Permission>
     */
    #[Computed]
    public function allPermissions(): Collection
    {
        $excludedExact = [
            'manage roles',
            'manage permissions',
            'manage tenants',
            'manage platform',
            'manage users',
            'delete tenants',
            'delete users',
        ];

        return Permission::query()
            ->whereNotIn('name', $excludedExact)
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    #[Computed]
    public function groupedPermissions(): Collection
    {
        return $this->allPermissions->groupBy(function ($permission) {
            $parts = explode(' ', str_replace(['-', '_'], ' ', $permission->name));
            if (count($parts) >= 2) {
                return ucwords(end($parts));
            }
            return 'General';
        })->sortKeys();
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
    #[Computed]
    public function filteredGroupedPermissions(): Collection
    {
        if (empty($this->newRolePermissionSearch)) {
            return $this->groupedPermissions;
        }

        $search = strtolower($this->newRolePermissionSearch);

        return $this->groupedPermissions->map(function ($permissions) use ($search) {
            return $permissions->filter(function ($permission) use ($search) {
                return str_contains(strtolower($permission->name), $search);
            });
        })->filter(fn ($permissions) => $permissions->isNotEmpty());
    }

    public function selectAllQuickRolePermissions()
    {
        $this->newRolePermissions = $this->allPermissions->pluck('name')->all();
    }

    public function deselectAllQuickRolePermissions()
    {
        $this->newRolePermissions = [];
    }

    public function toggleNewRoleForm()
    {
        $this->showNewRoleForm = !$this->showNewRoleForm;
        $this->newRoleName = '';
        $this->newRolePermissions = [];
        $this->newRolePermissionSearch = '';
        $this->resetErrorBag(['newRoleName', 'newRolePermissions']);
    }

    public function createQuickRole()
    {
        $this->validate([
            'newRoleName' => ['required', 'string', 'min:3', 'max:50', function ($attr, $value, $fail) {
                if (preg_match('/(^|\s|\-|_)(super[\s\-_]?admin|admin)($|\s|\-|_)/i', $value)) {
                    $fail('Role names containing "admin" are reserved.');
                }
            }],
            'newRolePermissions' => 'required|array|min:1',
        ]);

        try {
            DB::transaction(function () {
                $tenantId  = Auth::user()->tenant_id;
                $guard     = 'web';
                $usesTeams = (bool) config('permission.teams');

                $data = ['name' => $this->newRoleName, 'guard_name' => $guard];
                if ($usesTeams) {
                    $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                    $data[$teamKey] = $tenantId;
                }

                $role = Role::create($data);
                $role->syncPermissions($this->newRolePermissions);

                $setting = TenantSetting::where('tenant_id', $tenantId)
                    ->where('key', 'custom_roles')
                    ->first();
                $customRoles = ($setting && is_array($setting->value)) ? $setting->value : [];
                $customRoles[] = [
                    'role_id'     => $role->id,
                    'name'        => $role->name,
                    'permissions' => $this->newRolePermissions,
                ];

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => 'custom_roles'],
                    ['value' => $customRoles]
                );

                $this->selectedRoles[] = 'role_' . $role->id;

                app(PermissionRegistrar::class)->forgetCachedPermissions();
            });

            $this->showNewRoleForm = false;
            $this->newRoleName = '';
            $this->newRolePermissions = [];
            $this->newRolePermissionSearch = '';
            session()->flash('message', 'Custom role created and selected.');
        } catch (\Spatie\Permission\Exceptions\RoleAlreadyExists $e) {
            $this->addError('newRoleName', 'A role with this name already exists.');
        } catch (\Exception $e) {
            Log::error('Quick role creation failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to create role. Please try again.');
        }
    }

    public function rules()
    {
        return [
            'name'          => 'required|string|max:255',
            'employeeRole'  => 'required|string|max:50',
            'phone'         => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'is_active'     => 'boolean',
            'code'          => ['nullable', 'string', 'max:20',
                Rule::unique('employees', 'code')
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->ignore($this->employee->id),
            ],
            'avatar'        => 'nullable|image|max:2048',
            'selectedRoles' => $this->employee->user_id ? 'required|array|min:1' : 'array',
        ];
    }

    public function update()
    {
        $this->name = trim($this->name);
        $this->validate();

        // Prevent user from deactivating their own linked account
        if (
            $this->employee->user_id === Auth::id()
            && !$this->is_active
            && $this->employee->is_active
        ) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }

        $newAvatarPath = null;
        $oldAvatarPath = $this->employee->avatar;

        try {
            if ($this->avatar) {
                $newAvatarPath = $this->avatar->store('employee-avatars', 'public');
            }

            DB::transaction(function () use ($newAvatarPath) {
                $this->employee->update([
                    'code'      => $this->code ?: $this->employee->code,
                    'name'      => $this->name,
                    'role'      => $this->employeeRole,
                    'phone'     => $this->phone,
                    'avatar'    => $newAvatarPath ?? $this->employee->avatar,
                    'is_active' => $this->is_active,
                ]);

                if ($this->employee->user_id) {
                    /** @var User|null $user */
                    $user = User::find($this->employee->user_id);
                    if ($user) {
                        $user->update([
                            'name'  => $this->name,
                            'phone' => $this->phone,
                        ]);

                        $roleIds = [];
                        foreach ($this->selectedRoles as $roleValue) {
                            if (!is_string($roleValue) || !str_starts_with($roleValue, 'role_')) {
                                continue;
                            }
                            $roleIds[] = (int) substr($roleValue, strlen('role_'));
                        }
                        $roleIds = array_values(array_unique(array_filter($roleIds)));

                        $roleNames = !empty($roleIds)
                            ? Role::whereIn('id', $roleIds)->pluck('name')->all()
                            : [];

                        $preserved = $user->roles
                            ->filter(fn ($r) => in_array($r->name, ['admin', 'super-admin'], true))
                            ->pluck('name')
                            ->all();

                        $user->syncRoles(array_values(array_unique(array_merge($preserved, $roleNames))));
                        app(PermissionRegistrar::class)->forgetCachedPermissions();
                    }
                }
            });
        } catch (\Exception $e) {
            // Clean up newly stored avatar if the transaction failed
            if ($newAvatarPath && Storage::disk('public')->exists($newAvatarPath)) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            Log::error('Employee update failed: ' . $e->getMessage(), [
                'tenant_id'   => Auth::user()->tenant_id,
                'employee_id' => $this->employee->id,
            ]);
            session()->flash('error', 'Failed to update employee. Please try again.');
            return;
        }

        // Delete old avatar only after successful save
        if ($newAvatarPath && $oldAvatarPath && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $this->reset('avatar');

        session()->flash('message', 'Employee updated successfully.');
        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Employees</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Edit <em class="italic text-primary-600 dark:text-primary-400">{{ $employee->name }}</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Update details, roles, and access.</p>
        </div>
        <a href="{{ route('tenant.employees.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Employees
        </a>
    </div>

    <form wire:submit="update" class="card p-5 sm:p-6 space-y-5"
          x-data="{ avatarPreview: null }">

        {{-- Employee Code --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee Code</label>
            <input type="text" wire:model="code" class="input font-mono">
            @error('code') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Avatar — Drag & Drop with Preview --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Picture</label>
            <div
                x-data="{ dragging: false }"
                x-on:dragover.prevent="dragging = true"
                x-on:dragleave.prevent="dragging = false"
                x-on:drop.prevent="dragging = false; $refs.avatarInput.files = $event.dataTransfer.files; $refs.avatarInput.dispatchEvent(new Event('change'))"
                :class="dragging ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-300 dark:border-gray-600'"
                class="relative flex items-center gap-4 rounded-xl border-2 border-dashed p-4 transition-colors"
            >
                {{-- Preview --}}
                <template x-if="avatarPreview">
                    <img :src="avatarPreview" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                </template>

                <template x-if="!avatarPreview">
                    <div class="shrink-0">
                        @if($employee->avatar)
                            <img src="{{ asset('storage/' . $employee->avatar) }}"
                                 class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                                 alt="{{ $employee->name }}">
                        @else
                            <div class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                        @endif
                    </div>
                </template>

                <div class="flex-1 min-w-0">
                    <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600 dark:text-primary-400">
                        {{ $avatar ? 'Change photo' : 'Upload a photo' }}
                    </span>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Drag & drop, or click to browse. PNG/JPG up to 2MB.</p>
                    <div wire:loading wire:target="avatar" class="text-xs text-primary-600 dark:text-primary-400 mt-1 flex items-center gap-1">
                        <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Uploading…
                    </div>
                </div>

                @if ($avatar)
                    <button type="button" wire:click="$set('avatar', null)" @click="avatarPreview = null"
                            class="relative z-10 shrink-0 text-xs font-semibold text-rose-500 hover:text-rose-700 active:scale-95 transition-transform">
                        Remove
                    </button>
                @endif

                <input x-ref="avatarInput" type="file" wire:model="avatar" accept="image/*"
                       @change="avatarPreview = $refs.avatarInput.files[0] ? URL.createObjectURL($refs.avatarInput.files[0]) : null"
                       class="absolute inset-0 opacity-0 cursor-pointer">
            </div>
            @error('avatar') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Name & Job Title --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                <input type="text" wire:model="name" class="input">
                @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Job Title / Role *</label>
                <input type="text" wire:model="employeeRole" placeholder="e.g. Receptionist, Guide" class="input">
                @error('employeeRole') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Phone & Active --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                <input type="text" wire:model="phone" placeholder="09123456789" class="input">
                @error('phone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center sm:pt-6">
                @php $isSelf = $employee->user_id === Auth::id(); @endphp
                <label class="relative inline-flex items-center cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50 rounded-full {{ $isSelf ? 'opacity-60 cursor-not-allowed' : '' }}">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer" @if($isSelf) disabled @endif>
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full peer-disabled:opacity-50"></div>
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
                @if($isSelf)
                    <span class="ml-2 text-xs text-gray-400 dark:text-gray-500">(You)</span>
                @endif
            </div>
        </div>

        {{-- Linked User / Roles & Permissions --}}
        <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
            <h2 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-4">User Account & Roles</h2>

            @if($employee->user_id && $employee->user)
                <div class="mb-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-primary-600/10 border border-primary-600/20 flex items-center justify-center text-primary-600 dark:text-primary-400 font-bold text-sm shrink-0">
                            {{ strtoupper(substr($employee->user->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Linked User</p>
                            <p class="text-gray-900 dark:text-white font-medium truncate">{{ $employee->user->name }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400 truncate">{{ $employee->user->email }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">The linked user cannot be changed from this page.</p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign System Roles *</label>
                    <button type="button" wire:click="toggleNewRoleForm"
                            class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        + New Role
                    </button>
                </div>

                @if($showNewRoleForm)
                    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700 space-y-4">

                        {{-- Role Name --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role Name</label>
                            <input type="text" wire:model="newRoleName" class="input" placeholder="e.g. Front Desk">
                            @error('newRoleName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>

                        {{-- Permissions Toolbar --}}
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Permissions *
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                    {{ count($newRolePermissions) }} of {{ $this->allPermissions->count() }} selected
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button" wire:click="selectAllQuickRolePermissions"
                                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    Select All
                                </button>
                                <button type="button" wire:click="deselectAllQuickRolePermissions"
                                        class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    Clear All
                                </button>

                                <div class="relative w-full sm:w-40">
                                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text" wire:model.live.debounce.200ms="newRolePermissionSearch"
                                           placeholder="Filter…"
                                           class="pl-9 pr-3 py-2 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition w-full">
                                </div>
                            </div>
                        </div>

                        {{-- Grouped Permission Grid --}}
                        @if($this->filteredGroupedPermissions->isEmpty())
                            <div class="text-center py-6 text-xs text-gray-500 dark:text-gray-400">
                                No permissions match your search.
                            </div>
                        @else
                            <div class="space-y-3 max-h-72 overflow-y-auto pr-2">
                                @foreach($this->filteredGroupedPermissions as $module => $permissions)
                                    <div wire:key="quick-group-{{ md5($module) }}"
                                         class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800">
                                        <h4 class="font-semibold text-xs text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                            <span class="w-1 h-4 bg-primary-600 rounded-full"></span>
                                            {{ $module }}
                                            <span class="text-[10px] font-normal text-gray-500 dark:text-gray-400">({{ $permissions->count() }})</span>
                                        </h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                                            @foreach($permissions as $perm)
                                                <label wire:key="quick-perm-{{ $perm->id }}"
                                                       class="flex items-center gap-2 p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                                    <input type="checkbox"
                                                           wire:model.live="newRolePermissions"
                                                           value="{{ $perm->name }}"
                                                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                                                    <span class="text-xs text-gray-700 dark:text-gray-300">
                                                        {{ ucwords(str_replace(['-', '_'], ' ', $perm->name)) }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @error('newRolePermissions') <span class="text-red-500 dark:text-red-400 text-xs block">{{ $message }}</span> @enderror

                        <div class="flex flex-col sm:flex-row gap-2 pt-2">
                            <button type="button" wire:click="createQuickRole"
                                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                Create Role
                            </button>
                            <button type="button" wire:click="toggleNewRoleForm"
                                    class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                Cancel
                            </button>
                        </div>
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                    <input type="text" wire:model.live.debounce.300ms="roleSearch" placeholder="Search roles…" class="input">
                    <select wire:model.live="roleTypeFilter" class="select">
                        <option value="all">All Roles</option>
                        <option value="global">Global Only</option>
                        <option value="custom">Custom Only</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-72 overflow-y-auto">
                    @forelse($this->availableRoles as $role)
                        <label class="cursor-pointer" wire:key="role-{{ $role['value'] }}">
                            <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role['value'] }}" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 transition-all duration-200 active:scale-[0.98]
                                        {{ in_array($role['value'], $selectedRoles) ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10' : 'hover:border-gray-300 dark:hover:border-gray-600' }}">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $role['label'] }}</p>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @forelse(array_slice($role['permissions'], 0, 4) as $perm)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-[10px] font-medium">
                                            {{ $perm }}
                                        </span>
                                    @empty
                                        <span class="text-[10px] text-gray-400 italic">No permissions</span>
                                    @endforelse
                                    @if(count($role['permissions']) > 4)
                                        <span class="text-[10px] text-gray-400">+{{ count($role['permissions']) - 4 }} more</span>
                                    @endif
                                </div>
                            </div>
                        </label>
                    @empty
                        <p class="col-span-2 text-sm text-gray-400 dark:text-gray-500 text-center py-6">No roles match your filters.</p>
                    @endforelse
                </div>
                @error('selectedRoles') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            @else
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    No user account linked. Link a user from the Create Employee page.
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="update"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="update">Update Employee</span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('tenant.employees.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>