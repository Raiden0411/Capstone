{{-- resources/views/tenant/pages/employee/⚡create-employee.blade.php --}}
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
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

new 
#[Layout('tenant.layouts.app')]
#[Title('Add Employee')]
class extends Component {
    use WithFileUploads;

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

    public string $user_mode = 'none';
    public string $search = '';
    public $existingUserResults = [];
    public ?int $existing_user_id = null;

    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public array $selectedRoles = [];

    public string $roleSearch = '';
    public string $roleTypeFilter = 'all';

    public bool $showNewRoleForm = false;
    public string $newRoleName = '';
    public array $newRolePermissions = [];
    public string $newRolePermissionSearch = '';

    public bool $saveAndAddAnother = false;

    public function mount()
    {
        $user = Auth::user();
        if (!$user || !$user->tenant_id) {
            abort(403);
        }

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage employees');

        if (!$canManage) {
            abort(403, 'You are not authorized to add employees.');
        }

        $this->generateCode();
    }

    public function generateCode()
    {
        do {
            $candidate = 'EMP-' . strtoupper(Str::random(6));
        } while (
            Employee::where('tenant_id', Auth::user()->tenant_id)
                ->where('code', $candidate)
                ->exists()
        );

        $this->code = $candidate;
    }

    public function updated($field)
    {
        $trims = ['name', 'employeeRole', 'phone', 'email', 'code', 'search', 'roleSearch', 'newRoleName'];
        if (in_array($field, $trims)) {
            $this->$field = trim($this->$field);
        }
        if ($field === 'search') {
            $this->searchExistingUsers();
        }
        if ($field === 'employeeRole') {
            $this->maybeAutoSelectRole();
        }
    }

    public function updatedUserMode()
    {
        if ($this->user_mode !== 'new') {
            $this->password = '';
            $this->password_confirmation = '';
        }
        if ($this->user_mode === 'none') {
            $this->selectedRoles = [];
        }
        if ($this->user_mode !== 'existing') {
            $this->existing_user_id = null;
            $this->search = '';
            $this->existingUserResults = [];
        }
    }

    public function searchExistingUsers()
    {
        if (strlen($this->search) < 2) {
            $this->existingUserResults = [];
            return;
        }

        $this->existingUserResults = User::where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->where('id', '!=', Auth::id())
            ->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                  ->orWhere('email', 'like', '%'.$this->search.'%')
                  ->orWhere('phone', 'like', '%'.$this->search.'%');
            })
            ->select('id', 'name', 'email', 'phone')
            ->limit(6)
            ->get();
    }

    public function selectExistingUser($userId)
    {
        $user = User::where('tenant_id', Auth::user()->tenant_id)->find($userId);
        if (!$user) return;

        $this->existing_user_id = $user->id;
        $this->name = $user->name;
        $this->phone = $user->phone ?? '';
        $this->email = $user->email ?? '';
        $this->search = $user->name;
        $this->existingUserResults = [];

        $tenantId  = Auth::user()->tenant_id;
        $usesTeams = (bool) config('permission.teams');
        $teamKey   = $usesTeams
            ? config('permission.column_names.team_foreign_key', 'team_id')
            : null;

        $values = [];
        foreach ($user->roles as $role) {
            if (in_array($role->name, ['super-admin', 'admin'], true)) {
                continue;
            }

            if ($usesTeams && $teamKey) {
                $roleTeamId = $role->{$teamKey} ?? null;
                if ($roleTeamId === null) {
                    $values[] = $role->name;
                } elseif ((int) $roleTeamId === (int) $tenantId) {
                    $values[] = 'role_' . $role->id;
                }
            } else {
                $values[] = $role->name;
            }
        }

        $this->selectedRoles = array_values(array_unique($values));
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
                'value'       => $role->name,
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

    public function maybeAutoSelectRole()
    {
        if ($this->user_mode !== 'new' || count($this->selectedRoles) > 0) return;

        $jobTitle = strtolower(trim($this->employeeRole));
        if (strlen($jobTitle) < 3) return;

        $match = $this->availableRoles->first(
            fn ($role) => strtolower($role['label']) === $jobTitle
        );

        if ($match) {
            $this->selectedRoles = [$match['value']];
        }
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
        $rules = [
            'name'         => 'required|string|max:255',
            'employeeRole' => 'required|string|max:50',
            'phone'        => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'is_active'    => 'boolean',
            'code'         => ['nullable', 'string', 'max:20',
                Rule::unique('employees', 'code')->where('tenant_id', Auth::user()->tenant_id),
            ],
            'avatar'       => 'nullable|image|max:2048',
        ];

        if ($this->user_mode === 'existing') {
            $rules['existing_user_id'] = [
                'required',
                Rule::exists('users', 'id')->where('tenant_id', Auth::user()->tenant_id),
            ];
            $rules['selectedRoles'] = 'array';
        }

        if ($this->user_mode === 'new') {
            $rules['email']         = ['required', 'email', 'max:255', Rule::unique('users', 'email')];
            $rules['password']      = 'required|min:8|confirmed';
            $rules['selectedRoles'] = 'required|array|min:1';
        }

        return $rules;
    }

    public function saveAndAddAnotherEmployee()
    {
        $this->saveAndAddAnother = true;
        $this->save();
    }

    public function save()
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $userId = null;

                if ($this->user_mode === 'existing') {
                    $userId = $this->existing_user_id;
                    $this->syncUserRoles($userId);
                } elseif ($this->user_mode === 'new') {
                    $user = User::create([
                        'tenant_id' => Auth::user()->tenant_id,
                        'name'      => $this->name,
                        'email'     => $this->email,
                        'phone'     => $this->phone,
                        'password'  => Hash::make($this->password),
                        'is_active' => true,
                    ]);

                    $this->syncUserRoles($user->id);
                    $userId = $user->id;
                }

                $avatarPath = null;
                if ($this->avatar) {
                    $avatarPath = $this->avatar->store('employee-avatars', 'public');
                }

                Employee::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'user_id'   => $userId,
                    'code'      => $this->code ?: null,
                    'name'      => $this->name,
                    'role'      => $this->employeeRole,
                    'phone'     => $this->phone,
                    'avatar'    => $avatarPath,
                    'is_active' => $this->is_active,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Employee creation failed: ' . $e->getMessage(), [
                'tenant_id' => Auth::user()->tenant_id,
                'email'     => $this->email,
            ]);
            session()->flash('error', 'Failed to create employee. Please try again.');
            $this->saveAndAddAnother = false;
            return;
        }

        session()->flash('message', 'Employee created successfully.');

        if ($this->saveAndAddAnother) {
            $this->saveAndAddAnother = false;
            $this->reset([
                'name', 'employeeRole', 'phone', 'avatar',
                'user_mode', 'existing_user_id', 'email',
                'password', 'password_confirmation',
                'selectedRoles', 'roleSearch', 'roleTypeFilter',
            ]);
            $this->generateCode();
            $this->is_active = true;
            return;
        }

        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }

    protected function syncUserRoles(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $roleNames = [];

        foreach ($this->selectedRoles as $roleValue) {
            $selected = $this->availableRoles->firstWhere('value', $roleValue);
            if (!$selected) continue;

            if ($selected['type'] === 'global') {
                $roleNames[] = $selected['value'];
            } else {
                $roleId    = (int) substr($selected['value'], strlen('role_'));
                $roleModel = Role::findById($roleId, 'web');
                if ($roleModel) {
                    $roleNames[] = $roleModel->name;
                }
            }
        }

        $roleNames = array_values(array_unique($roleNames));

        if ($this->user_mode === 'new') {
            if (!empty($roleNames)) {
                $user->syncRoles($roleNames);
            }
        } else {
            if (!empty($roleNames)) {
                $user->assignRole($roleNames);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6"
     x-data="{ avatarPreview: null }">

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
                Add <em class="italic text-primary-600 dark:text-primary-400">Employee</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Register a new team member.</p>
        </div>
        <a href="{{ route('tenant.employees.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Employees
        </a>
    </div>

    <form wire:submit="save" class="card p-5 sm:p-6 space-y-5">

        {{-- Employee Code --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee Code</label>
                <input type="text" wire:model="code" class="input font-mono">
                @error('code') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <button type="button" wire:click="generateCode"
                    class="btn-secondary self-start sm:self-end active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Regenerate
            </button>
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
                    <div class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/></svg>
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
                <input type="text" wire:model.live.debounce.300ms="employeeRole" placeholder="e.g. Receptionist, Guide" class="input">
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
                <label class="relative inline-flex items-center cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50 rounded-full">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</span>
                </label>
            </div>
        </div>

        {{-- User account mode --}}
        <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">User Account</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                @foreach([
                    ['none', 'No Account'],
                    ['existing', 'Link Existing User'],
                    ['new', 'Create New User'],
                ] as [$val, $label])
                    <label class="cursor-pointer" wire:key="user-mode-{{ $val }}">
                        <input type="radio" wire:model.live="user_mode" value="{{ $val }}" class="sr-only peer">
                        <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 text-center transition-all duration-200 active:scale-[0.98]
                                    {{ $user_mode === $val ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10' : 'hover:border-gray-300 dark:hover:border-gray-600' }}">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $label }}</p>
                        </div>
                    </label>
                @endforeach
            </div>

            @if($user_mode === 'existing')
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search Existing User</label>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Type name, email or phone…" class="input">
                    @if(count($existingUserResults) > 0)
                        <div class="mt-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl max-h-40 overflow-y-auto shadow-lg">
                            @foreach($existingUserResults as $user)
                                <button type="button" wire:key="existing-user-{{ $user->id }}"
                                        wire:click="selectExistingUser({{ $user->id }})"
                                        class="w-full text-left px-4 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-[0.99]">
                                    <p class="text-sm text-gray-900 dark:text-white">{{ $user->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email ?? $user->phone }}</p>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @error('existing_user_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            @if($user_mode === 'new')
                <div class="mt-4 pl-4 border-l-2 border-primary-600 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email *</label>
                        <input type="email" wire:model="email" class="input">
                        @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password *</label>
                            <input type="password" wire:model="password" class="input">
                            @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password *</label>
                            <input type="password" wire:model="password_confirmation" class="input">
                        </div>
                    </div>
                </div>
            @endif

            {{-- Role selection (shown for BOTH new and existing) --}}
            @if($user_mode === 'new' || $user_mode === 'existing')
                <div class="mt-4 pl-4 border-l-2 border-primary-600">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $user_mode === 'existing' ? 'Roles (additive)' : 'Assign System Roles *' }}
                        </label>
                        <button type="button" wire:click="toggleNewRoleForm"
                                class="text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            + New Role
                        </button>
                    </div>

                    @if($user_mode === 'existing')
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                            Existing roles are preserved. Checked roles will be added.
                        </p>
                    @endif

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
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Employee</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Saving…
                </span>
            </button>
            <button type="button"
                    wire:click="saveAndAddAnotherEmployee"
                    wire:loading.attr="disabled"
                    wire:target="saveAndAddAnotherEmployee"
                    class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                Save & Add Another
            </button>
            <a href="{{ route('tenant.employees.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>