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
use App\Traits\ChecksTenantPermissions;
use App\Traits\HandlesImageUploads;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new
#[Layout('tenant.layouts.app')]
#[Title('Edit Employee')]
class extends Component {
    use WithFileUploads;
    use HandlesImageUploads;
    use ChecksTenantPermissions;

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

    #[Validate('nullable|image|mimes:jpg,jpeg,png,webp|max:5120')]
    public $avatar;

    public array $selectedRoles = [];

    public string $roleSearch = '';
    public string $roleTypeFilter = 'all';

    public bool $showNewRoleForm = false;
    public string $newRoleName = '';
    public array $newRolePermissions = [];
    public string $newRolePermissionSearch = '';

    public function mount($employee): void
    {
        $this->authorizeManageEmployees();

        $user = Auth::user();

        $userRelation = fn ($q) => $q->select('id', 'tenant_id', 'name', 'email', 'phone', 'avatar');

        if (! $employee instanceof Employee) {
            $employee = Employee::withoutGlobalScope(TenantScope::class)
                ->with(['user' => $userRelation])
                ->findOrFail($employee);
        } else {
            $employee->load(['user' => $userRelation]);
        }

        if ($employee->tenant_id !== $user->tenant_id) {
            abort(403, 'Unauthorized.');
        }

        $this->employee     = $employee;
        $this->name         = $employee->name;
        $this->employeeRole = $employee->role ?? '';
        $this->phone        = $employee->phone ?? '';
        $this->is_active    = (bool) $employee->is_active;
        $this->code         = $employee->code ?? '';
        $this->avatar       = null;

        if ($employee->user_id) {
            $this->selectedRoles = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $employee->user_id)
                ->where('model_has_roles.model_type', User::class)
                ->where('model_has_roles.team_id', $employee->tenant_id)
                ->whereNotIn('roles.name', ['admin', 'super-admin'])
                ->pluck('roles.id')
                ->map(fn ($id) => 'role_' . (int) $id)
                ->values()
                ->all();
        }
    }

    public function hydrate(): void
    {
        $this->authorizeManageEmployees();

        $user = Auth::user();
        abort_unless($user && $user->tenant_id, 403);

        abort_unless($this->employee->tenant_id === $user->tenant_id, 403);
    }

    protected function authorizeManageEmployees(): void
    {
        $user = Auth::user();

        abort_unless($user && $user->tenant_id, 403);

        abort_unless(
            $this->tenantCan('manage employees'),
            403,
            'You are not authorized to edit employees.'
        );
    }

    public function updated($field): void
    {
        $trims = ['name', 'employeeRole', 'phone', 'code', 'roleSearch', 'newRoleName'];
        if (in_array($field, $trims, true)) {
            $this->$field = trim((string) $this->$field);
        }
    }

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

    public function selectAllQuickRolePermissions(): void
    {
        $this->newRolePermissions = $this->allPermissions->pluck('name')->all();
    }

    public function deselectAllQuickRolePermissions(): void
    {
        $this->newRolePermissions = [];
    }

    public function toggleNewRoleForm(): void
    {
        $this->showNewRoleForm         = ! $this->showNewRoleForm;
        $this->newRoleName             = '';
        $this->newRolePermissions      = [];
        $this->newRolePermissionSearch = '';
        $this->resetErrorBag(['newRoleName', 'newRolePermissions']);
    }

    public function createQuickRole(): void
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

            unset($this->availableRoles);

            $this->showNewRoleForm         = false;
            $this->newRoleName             = '';
            $this->newRolePermissions      = [];
            $this->newRolePermissionSearch = '';
            session()->flash('message', 'Custom role created and selected.');
        } catch (\Spatie\Permission\Exceptions\RoleAlreadyExists $e) {
            $this->addError('newRoleName', 'A role with this name already exists.');
        } catch (\Exception $e) {
            Log::error('Quick role creation failed: ' . $e->getMessage());
            session()->flash('error', 'Failed to create role. Please try again.');
        }
    }

    public function rules(): array
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
            'avatar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'selectedRoles' => $this->employee->user_id ? 'required|array|min:1' : 'array',
        ];
    }

    public function update()
    {
        $this->authorizeManageEmployees();

        $this->name = trim($this->name);
        $this->validate();

        if (
            $this->employee->user_id === Auth::id()
            && ! $this->is_active
            && $this->employee->is_active
        ) {
            session()->flash('error', 'You cannot deactivate your own account.');
            return;
        }

        $allowedRoleIds = $this->availableRoles
            ->pluck('value')
            ->map(fn ($v) => (int) substr($v, strlen('role_')))
            ->filter()
            ->values()
            ->all();

        $roleIds    = [];
        $foreignIds = [];
        foreach ($this->selectedRoles as $roleValue) {
            if (! is_string($roleValue) || ! str_starts_with($roleValue, 'role_')) {
                continue;
            }
            $id = (int) substr($roleValue, strlen('role_'));

            if (! in_array($id, $allowedRoleIds, true)) {
                $foreignIds[] = $id;
                continue;
            }

            $roleIds[] = $id;
        }

        if (! empty($foreignIds)) {
            Log::warning('Employee update rejected foreign role ids', [
                'tenant_id'          => Auth::user()->tenant_id,
                'employee_id'        => $this->employee->id,
                'attempted_role_ids' => $foreignIds,
            ]);

            session()->flash('error', 'One or more selected roles are not available for your business. Please refresh the page and try again.');
            return;
        }

        $roleIds = array_values(array_unique($roleIds));

        $newAvatarPath = null;
        $oldAvatarPath = $this->employee->avatar;

        try {
            if ($this->avatar) {
                $newAvatarPath = $this->storeImage($this->avatar, 'employee-avatars', 'public', 'employees');

                if (! $newAvatarPath) {
                    throw new \RuntimeException('Failed to store the employee avatar.');
                }
            }

            DB::transaction(function () use ($newAvatarPath, $roleIds) {
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

                        $isAdminUser = $user->hasAnyRole(['admin', 'super-admin']);
                        if (! $isAdminUser) {
                            $user->update(['is_active' => $this->is_active]);
                        }

                        $roleNames = ! empty($roleIds)
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

        if ($newAvatarPath && $oldAvatarPath && Storage::disk('public')->exists($oldAvatarPath)) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        $this->reset('avatar');
        $this->dispatch('employee-avatar-cleared');

        session()->flash('message', 'Employee updated successfully.');
        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-employee-form-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-employee-form-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

<div class="relative">
    <div class="tenant-employee-form-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        @if (session()->has('message'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('message') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if (session()->has('error'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Employees</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Edit {{ $employee->name }}
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Update details, roles, and access.
                </p>
            </div>
            <a href="{{ route('tenant.employees.index') }}" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <span>Back to Employees</span>
            </a>
        </div>

        <form wire:submit="update" class="space-y-6">

            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Employee Details
                    </h2>
                </div>

                <div
                    x-data="{
                        ...imageCropper({
                            wireProperty: 'avatar',
                            aspect: 1,
                            title: 'Crop profile picture',
                            description: 'Square crop works best',
                            previewEvent: 'employee-avatar-preview',
                        }),
                        ...avatarPreview(),
                        dragging: false,
                        serverUrl: '{{ $employee->avatar ? '/storage/' . ltrim($employee->avatar, '/') : '' }}',
                        get showPreview() {
                            return !!this.previewUrl || !!this.serverUrl;
                        },
                    }"
                    x-init="init()"
                    x-on:employee-avatar-preview.window="setUrl($event.detail.url)"
                    x-on:employee-avatar-cleared.window="clear()"
                    class="space-y-3"
                >
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Profile Picture <span class="text-gray-400 font-normal">(optional)</span>
                    </label>

                    <div class="flex flex-col sm:flex-row sm:items-start gap-5">
                        <div
                            x-on:dragover.prevent="dragging = true"
                            x-on:dragleave.prevent="dragging = false"
                            x-on:drop.prevent="
                                dragging = false;
                                const dt = new DataTransfer();
                                for (const f of $event.dataTransfer.files) dt.items.add(f);
                                $refs.input.files = dt.files;
                                $refs.input.dispatchEvent(new Event('change'));
                            "
                            :class="dragging
                                ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10'
                                : 'border-gray-300 dark:border-gray-600'"
                            class="relative w-40 h-40 shrink-0 border-2 border-dashed rounded-xl overflow-hidden transition-colors"
                        >
                            <input
                                x-ref="input"
                                id="employee-avatar-input"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                x-on:change="pick($event)"
                            >

                            <img
                                :src="previewUrl || serverUrl"
                                :class="showPreview ? 'block' : 'hidden'"
                                alt="{{ $employee->name }}"
                                class="absolute inset-0 w-full h-full object-cover"
                                loading="lazy"
                                decoding="async"
                            >

                            <label
                                for="employee-avatar-input"
                                :class="showPreview ? 'hidden' : 'flex'"
                                class="absolute inset-0 flex-col items-center justify-center p-3 text-center cursor-pointer
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                            >
                                <svg class="h-8 w-8 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/>
                                </svg>
                                <p class="mt-2 text-xs font-medium text-gray-700 dark:text-gray-300 leading-tight">
                                    Click or drop a photo
                                </p>
                            </label>

                            <div
                                wire:loading.flex
                                wire:target="avatar"
                                class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                                aria-hidden="true"
                            >
                                <svg class="animate-spin h-5 w-5 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        </div>

                        <div class="flex-1 min-w-0 space-y-3">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                PNG, JPG, or WebP · max 5 MB · auto-cropped to a square and compressed to ≤512 KB.
                            </p>

                            <div :class="showPreview ? 'flex' : 'hidden'" class="flex-wrap items-center gap-2">
                                <label for="employee-avatar-input"
                                       class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                              border border-gray-300 dark:border-gray-600
                                              bg-white dark:bg-gray-800
                                              text-gray-700 dark:text-gray-200
                                              text-xs font-semibold cursor-pointer
                                              transition-all duration-200 active:scale-95
                                              hover:bg-gray-50 dark:hover:bg-gray-700
                                              [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                              focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span>Replace</span>
                                </label>

                                <button type="button"
                                        :class="previewUrl ? 'inline-flex' : 'hidden'"
                                        wire:click="$set('avatar', null)"
                                        x-on:click="clear()"
                                        class="items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                               border border-rose-300 dark:border-rose-500/40
                                               bg-white dark:bg-gray-800
                                               text-rose-700 dark:text-rose-300
                                               text-xs font-semibold
                                               transition-all duration-200 active:scale-95
                                               hover:bg-rose-50 dark:hover:bg-rose-500/10
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <span>Remove new photo</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    @error('avatar') <span class="text-rose-500 dark:text-rose-400 text-xs block">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label for="field-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Employee Code
                    </label>
                    <input type="text" id="field-code" wire:model="code" class="input w-full font-mono">
                    @error('code') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Full Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="field-name" wire:model="name" class="input" placeholder="e.g. Jane Dela Cruz">
                        @error('name') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label for="field-role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Job Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="field-role" wire:model="employeeRole" class="input" placeholder="e.g. Receptionist, Guide">
                        @error('employeeRole') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                @php $isSelf = $employee->user_id === Auth::id(); @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="field-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Phone <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="field-phone" wire:model="phone" inputmode="tel" class="input" placeholder="09123456789">
                        @error('phone') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="sm:pt-7">
                        <label class="inline-flex items-center gap-3 cursor-pointer select-none min-h-[44px] {{ $isSelf ? 'opacity-60 cursor-not-allowed' : '' }}
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                            <span class="relative inline-flex items-center shrink-0">
                                <input type="checkbox" wire:model="is_active" class="sr-only peer" @if($isSelf) disabled @endif>
                                <span class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full
                                             peer peer-checked:bg-primary-600
                                             after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                             after:bg-white after:rounded-full after:h-5 after:w-5
                                             after:transition-all peer-checked:after:translate-x-full
                                             peer-disabled:opacity-50"></span>
                            </span>
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                Active
                                @if($isSelf)
                                    <span class="text-gray-400 dark:text-gray-500">(You)</span>
                                @endif
                            </span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        User Account &amp; Roles
                    </h2>
                </div>

                @if($employee->user_id && $employee->user)
                    <div class="p-4 rounded-xl bg-gray-50/80 dark:bg-gray-900/50 border border-gray-200/70 dark:border-gray-700/60">
                        <div class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200/50 dark:border-primary-500/20 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-sm shrink-0">
                                {{ strtoupper(substr($employee->user->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Linked User</p>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white mt-0.5 truncate">{{ $employee->user->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $employee->user->email }}</p>
                                <p class="text-[11px] text-gray-400 dark:text-gray-500 mt-1">The linked user cannot be changed from this page.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-3">
                            <span class="w-5 h-px bg-primary-600"></span>
                            <h3 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                System Roles <span class="text-rose-500">*</span>
                            </h3>
                        </div>

                        <button type="button"
                                wire:click="toggleNewRoleForm"
                                class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                       py-2.5 -my-2.5 px-1 -mx-1 rounded
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95 transition-transform">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            <span>{{ $showNewRoleForm ? 'Cancel new role' : 'New Role' }}</span>
                        </button>
                    </div>

                    @if($showNewRoleForm)
                        <div class="p-4 bg-gray-50/80 dark:bg-gray-700/30 rounded-xl border border-gray-200/70 dark:border-gray-700/60 space-y-4">
                            <div>
                                <label for="field-new-role-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Role Name <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" id="field-new-role-name" wire:model="newRoleName" class="input" placeholder="e.g. Front Desk">
                                @error('newRoleName') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Permissions <span class="text-rose-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                        {{ count($newRolePermissions) }} of {{ $this->allPermissions->count() }} selected
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button"
                                            wire:click="selectAllQuickRolePermissions"
                                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-lg
                                                   border border-gray-300 dark:border-gray-600
                                                   bg-white dark:bg-gray-800
                                                   text-gray-700 dark:text-gray-200
                                                   text-xs font-semibold
                                                   transition-all duration-200 active:scale-95
                                                   hover:bg-gray-50 dark:hover:bg-gray-700
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <span>Select All</span>
                                    </button>
                                    <button type="button"
                                            wire:click="deselectAllQuickRolePermissions"
                                            class="inline-flex items-center justify-center h-11 sm:h-9 px-3.5 rounded-lg
                                                   border border-gray-300 dark:border-gray-600
                                                   bg-white dark:bg-gray-800
                                                   text-gray-700 dark:text-gray-200
                                                   text-xs font-semibold
                                                   transition-all duration-200 active:scale-95
                                                   hover:bg-gray-50 dark:hover:bg-gray-700
                                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        <span>Clear All</span>
                                    </button>

                                    <div class="relative w-40">
                                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                        </svg>
                                        <input type="text"
                                               wire:model.live.debounce.200ms="newRolePermissionSearch"
                                               placeholder="Filter…"
                                               aria-label="Filter permissions"
                                               class="w-full h-11 sm:h-9 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg pl-9 pr-3 text-base sm:text-xs text-gray-900 dark:text-white placeholder-gray-400
                                                      focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition
                                                      [touch-action:manipulation]">
                                    </div>
                                </div>
                            </div>

                            @if($this->filteredGroupedPermissions->isEmpty())
                                <div class="text-center py-6 text-xs text-gray-500 dark:text-gray-400">
                                    No permissions match your search.
                                </div>
                            @else
                                <div class="space-y-3 max-h-72 overflow-y-auto pr-2">
                                    @foreach($this->filteredGroupedPermissions as $module => $permissions)
                                        <div wire:key="quick-group-{{ md5($module) }}"
                                             class="border border-gray-200/70 dark:border-gray-700/60 rounded-lg p-3 bg-white dark:bg-gray-800">
                                            <h4 class="font-semibold text-xs text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                                <span class="w-1 h-4 bg-primary-600 rounded-full"></span>
                                                {{ $module }}
                                                <span class="text-[10px] font-normal text-gray-500 dark:text-gray-400 tabular-nums">({{ $permissions->count() }})</span>
                                            </h4>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                                                @foreach($permissions as $perm)
                                                    <label wire:key="quick-perm-{{ $perm->id }}"
                                                           class="flex items-center gap-2 p-1.5 min-h-[44px] rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors
                                                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                                                        <input type="checkbox"
                                                               wire:model.live="newRolePermissions"
                                                               value="{{ $perm->name }}"
                                                               class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
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

                            @error('newRolePermissions') <span class="text-rose-500 dark:text-rose-400 text-xs block">{{ $message }}</span> @enderror

                            <div class="flex flex-col sm:flex-row sm:justify-end gap-2 pt-2">
                                <button type="button"
                                        wire:click="toggleNewRoleForm"
                                        class="inline-flex items-center justify-center h-11 px-5 rounded-xl
                                               border border-gray-300 dark:border-gray-600
                                               bg-white dark:bg-gray-800
                                               text-gray-700 dark:text-gray-200
                                               text-sm font-semibold
                                               transition-all duration-200 active:scale-95
                                               hover:bg-gray-50 dark:hover:bg-gray-700
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                    <span>Cancel</span>
                                </button>
                                <button type="button"
                                        wire:click="createQuickRole"
                                        wire:loading.attr="disabled"
                                        wire:target="createQuickRole"
                                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl
                                               bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                               transition-all duration-200 active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                               disabled:opacity-60 disabled:cursor-not-allowed">
                                    <span wire:loading.remove wire:target="createQuickRole">Create Role</span>
                                    <span wire:loading wire:target="createQuickRole" class="inline-flex items-center gap-2">
                                        <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                        </svg>
                                        Creating…
                                    </span>
                                </button>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text"
                                   wire:model.live.debounce.300ms="roleSearch"
                                   placeholder="Search roles…"
                                   aria-label="Search roles"
                                   class="input pl-10 w-full">
                        </div>
                        <select wire:model.live="roleTypeFilter" aria-label="Filter roles by type" class="select w-full">
                            <option value="all">All Roles</option>
                            <option value="global">Global Only</option>
                            <option value="custom">Custom Only</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-80 overflow-y-auto">
                        @forelse($this->availableRoles as $role)
                            <label class="cursor-pointer" wire:key="role-{{ $role['value'] }}">
                                <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role['value'] }}" class="sr-only peer">
                                <div class="rounded-xl border-2 p-3 min-h-[44px] transition-all duration-200 active:scale-[0.98]
                                            [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                            {{ in_array($role['value'], $selectedRoles)
                                               ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10 shadow-sm'
                                               : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600' }}">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-semibold text-sm text-gray-900 dark:text-white">{{ $role['label'] }}</p>
                                        <span class="text-[9px] font-bold uppercase tracking-wider
                                                     {{ $role['type'] === 'global'
                                                        ? 'text-gray-500 dark:text-gray-400'
                                                        : 'text-primary-600 dark:text-primary-400' }}">
                                            {{ $role['type'] }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        @forelse(array_slice($role['permissions'], 0, 3) as $perm)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-[10px] font-medium">
                                                {{ $perm }}
                                            </span>
                                        @empty
                                            <span class="text-[10px] text-gray-400 italic">No permissions</span>
                                        @endforelse
                                        @if(count($role['permissions']) > 3)
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 tabular-nums">+{{ count($role['permissions']) - 3 }} more</span>
                                        @endif
                                    </div>
                                </div>
                            </label>
                        @empty
                            <p class="col-span-2 text-sm text-gray-400 dark:text-gray-500 text-center py-6">No roles match your filters.</p>
                        @endforelse
                    </div>

                    @error('selectedRoles') <span class="text-rose-500 dark:text-rose-400 text-xs block">{{ $message }}</span> @enderror
                @else
                    <div class="p-4 rounded-xl bg-gray-50/80 dark:bg-gray-900/50 border border-gray-200/70 dark:border-gray-700/60">
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500 shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">No user account linked</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    This employee doesn't have a login. To grant system access, create a linked user from the
                                    <a href="{{ route('tenant.employees.create') }}" wire:navigate class="font-semibold text-primary-600 dark:text-primary-400 hover:underline">Create Employee</a>
                                    page.
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-5 border-t border-gray-200/70 dark:border-gray-800/70">
                <a href="{{ route('tenant.employees.index') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                          transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <span>Cancel</span>
                </a>

                <button type="submit"
                        wire:loading.attr="disabled"
                        wire:target="update"
                        class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                               disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove wire:target="update">Update Employee</span>
                    <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
            </div>
        </form>

        <x-image-crop-modal />
    </div>
</div>