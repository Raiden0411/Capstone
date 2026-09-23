
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
use App\Traits\HandlesImageUploads;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Add Employee')]
class extends Component {
    use WithFileUploads;
    use HandlesImageUploads;

    // ═══ Identity ═══
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

    // ═══ User account ═══
    public string $user_mode = 'none';
    public string $search = '';
    public $existingUserResults = [];
    public ?int $existing_user_id = null;

    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    // ═══ Roles ═══
    public array $selectedRoles = [];
    public string $roleSearch = '';
    public string $roleTypeFilter = 'all';

    // ═══ Quick role ═══
    public bool $showNewRoleForm = false;
    public string $newRoleName = '';
    public array $newRolePermissions = [];
    public string $newRolePermissionSearch = '';

    public bool $saveAndAddAnother = false;

    // ─────────────────────────────────────────────────────────
    //  Lifecycle
    // ─────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->authorizeManageEmployees();
        $this->generateCode();
    }

    /**
     * Four-layer pattern, Layer 3 — re-verify on every Livewire update
     * request. Route middleware only runs on the initial GET.
     */
    public function hydrate(): void
    {
        $this->authorizeManageEmployees();
    }

    protected function authorizeManageEmployees(): void
    {
        $user = Auth::user();

        abort_unless($user && $user->tenant_id, 403);

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage employees');

        abort_unless($canManage, 403, 'You are not authorized to add employees.');
    }

    public function generateCode(): void
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

    public function updated($field): void
    {
        $trims = ['name', 'employeeRole', 'phone', 'email', 'code', 'search', 'roleSearch', 'newRoleName'];
        if (in_array($field, $trims, true)) {
            $this->$field = trim((string) $this->$field);
        }
        if ($field === 'search') {
            $this->searchExistingUsers();
        }
        if ($field === 'employeeRole') {
            $this->maybeAutoSelectRole();
        }
    }

    public function updatedUserMode(): void
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

    // ─────────────────────────────────────────────────────────
    //  Existing-user search + link
    // ─────────────────────────────────────────────────────────

    public function searchExistingUsers(): void
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

    public function selectExistingUser($userId): void
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
                if ($roleTeamId !== null && (int) $roleTeamId !== (int) $tenantId) {
                    continue;
                }
            }

            $values[] = 'role_' . $role->id;
        }

        $this->selectedRoles = array_values(array_unique($values));
    }

    // ─────────────────────────────────────────────────────────
    //  Computed — roles + permissions
    // ─────────────────────────────────────────────────────────

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

    public function selectAllQuickRolePermissions(): void
    {
        $this->newRolePermissions = $this->allPermissions->pluck('name')->all();
    }

    public function deselectAllQuickRolePermissions(): void
    {
        $this->newRolePermissions = [];
    }

    public function maybeAutoSelectRole(): void
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

    // ─────────────────────────────────────────────────────────
    //  Quick role
    // ─────────────────────────────────────────────────────────

    public function toggleNewRoleForm(): void
    {
        $this->showNewRoleForm = !$this->showNewRoleForm;
        $this->newRoleName = '';
        $this->newRolePermissions = [];
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

    // ─────────────────────────────────────────────────────────
    //  Validation
    // ─────────────────────────────────────────────────────────

    public function rules(): array
    {
        $rules = [
            'name'         => 'required|string|max:255',
            'employeeRole' => 'required|string|max:50',
            'phone'        => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'is_active'    => 'boolean',
            'code'         => ['nullable', 'string', 'max:20',
                Rule::unique('employees', 'code')->where('tenant_id', Auth::user()->tenant_id),
            ],
            'avatar'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
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

    // ─────────────────────────────────────────────────────────
    //  Save
    // ─────────────────────────────────────────────────────────

    public function saveAndAddAnotherEmployee(): void
    {
        $this->saveAndAddAnother = true;
        $this->save();
    }

    public function save()
    {
        $this->validate();

        $newAvatarPath = null;

        /*
         * Store the avatar BEFORE the transaction (§5.7 golden sequence).
         * storeImage() routes the file through ImageCompressionService
         * (context: employees — 512 KB / 800×800). It returns null on
         * storage failure (never throws); that null is promoted to a
         * RuntimeException so the catch block fires and the user sees an
         * error — never an Employee row with avatar => null.
         */
        try {
            if ($this->avatar) {
                $newAvatarPath = $this->storeImage($this->avatar, 'employee-avatars', 'public', 'employees');

                if (! $newAvatarPath) {
                    throw new \RuntimeException('Failed to store the employee avatar.');
                }
            }
        } catch (\Throwable $e) {
            Log::error('Employee avatar store failed: ' . $e->getMessage(), [
                'tenant_id' => Auth::user()->tenant_id,
            ]);
            session()->flash('error', 'Failed to upload the profile picture. Please try again.');
            return null;
        }

        try {
            DB::transaction(function () use ($newAvatarPath): void {
                $userId = null;

                if ($this->user_mode === 'existing') {
                    $userId = $this->existing_user_id;
                    $this->syncUserRoles($userId);

                    // Sync the linked user's is_active — admins exempt.
                    /** @var User|null $existingUser */
                    $existingUser = User::find($userId);
                    if ($existingUser) {
                        $isAdminUser = $existingUser->hasAnyRole(['admin', 'super-admin']);
                        if (! $isAdminUser) {
                            $existingUser->update(['is_active' => $this->is_active]);
                        }
                    }
                } elseif ($this->user_mode === 'new') {
                    $user = User::create([
                        'tenant_id' => Auth::user()->tenant_id,
                        'name'      => $this->name,
                        'email'     => $this->email,
                        'phone'     => $this->phone,
                        'password'  => Hash::make($this->password),
                        'is_active' => $this->is_active,
                    ]);

                    $this->syncUserRoles($user->id);
                    $userId = $user->id;
                }

                Employee::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'user_id'   => $userId,
                    'code'      => $this->code ?: null,
                    'name'      => $this->name,
                    'role'      => $this->employeeRole,
                    'phone'     => $this->phone,
                    'avatar'    => $newAvatarPath,
                    'is_active' => $this->is_active,
                ]);
            });
        } catch (\Exception $e) {
            if ($newAvatarPath && Storage::disk('public')->exists($newAvatarPath)) {
                Storage::disk('public')->delete($newAvatarPath);
            }

            Log::error('Employee creation failed: ' . $e->getMessage(), [
                'tenant_id' => Auth::user()->tenant_id,
                'email'     => $this->email,
            ]);
            session()->flash('error', 'Failed to create employee. Please try again.');
            $this->saveAndAddAnother = false;
            return null;
        }

        session()->flash('message', 'Employee created successfully.');

        if ($this->saveAndAddAnother) {
            $this->saveAndAddAnother = false;
            $this->reset([
                'name', 'employeeRole', 'phone', 'avatar',
                'user_mode', 'existing_user_id', 'email',
                'password', 'password_confirmation',
                'selectedRoles', 'roleSearch', 'roleTypeFilter',
                'showNewRoleForm', 'newRoleName', 'newRolePermissions', 'newRolePermissionSearch',
            ]);
            $this->dispatch('employee-form-reset');
            $this->generateCode();
            $this->is_active = true;
            return null;
        }

        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }

    /**
     * Sync the picked roles onto the linked user.
     *
     * All `selectedRoles` values are `role_<id>` (unified with the
     * edit-employee component). The `availableRoles` lookup acts as an
     * implicit allow-list: any role value not on the tenant-scoped list
     * is discarded, matching the security model enforced elsewhere.
     */
    protected function syncUserRoles(int $userId): void
    {
        $user = User::find($userId);
        if (!$user) return;

        $roleNames = [];

        foreach ($this->selectedRoles as $roleValue) {
            if (! is_string($roleValue) || ! str_starts_with($roleValue, 'role_')) {
                continue;
            }

            $selected = $this->availableRoles->firstWhere('value', $roleValue);
            if (! $selected) {
                continue;
            }

            $roleId = (int) substr($selected['value'], strlen('role_'));

            $roleModel = Role::query()
                ->where('id', $roleId)
                ->where('guard_name', 'web')
                ->first();

            if ($roleModel) {
                $roleNames[] = $roleModel->name;
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

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Employees</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Add Employee
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Register a new team member and assign their access level.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Employees</span>
        </a>
    </div>

    
    <form wire:submit="save" class="space-y-6">

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
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
                            :src="previewUrl || ''"
                            :class="previewUrl ? 'block' : 'hidden'"
                            alt="Profile picture preview"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                        >

                        
                        <label
                            for="employee-avatar-input"
                            :class="previewUrl ? 'hidden' : 'flex'"
                            class="absolute inset-0 flex-col items-center justify-center p-3 text-center cursor-pointer"
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

                        <div :class="previewUrl ? 'flex' : 'hidden'" class="flex-wrap items-center gap-2">
                            <label for="employee-avatar-input"
                                   class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                          border border-gray-300 dark:border-gray-600
                                          bg-white dark:bg-gray-800
                                          text-gray-700 dark:text-gray-200
                                          text-xs font-semibold cursor-pointer
                                          transition-all duration-200 active:scale-95
                                          hover:bg-gray-50 dark:hover:bg-gray-700
                                          focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>Replace</span>
                            </label>

                            <button type="button"
                                    wire:click="$set('avatar', null)"
                                    x-on:click="clear()"
                                    class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                           border border-rose-300 dark:border-rose-500/40
                                           bg-white dark:bg-gray-800
                                           text-rose-700 dark:text-rose-300
                                           text-xs font-semibold
                                           transition-all duration-200 active:scale-95
                                           hover:bg-rose-50 dark:hover:bg-rose-500/10
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>Remove</span>
                            </button>
                        </div>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['avatar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-name" wire:model="name" class="input" placeholder="e.g. Jane Dela Cruz">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label for="field-role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Job Title <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-role" wire:model.live.debounce.300ms="employeeRole" class="input" placeholder="e.g. Receptionist, Guide">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['employeeRole'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="field-code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Employee Code
                    </label>
                    <div class="flex gap-2">
                        <input type="text" id="field-code" wire:model="code" class="input flex-1 font-mono">
                        <button type="button"
                                wire:click="generateCode"
                                aria-label="Regenerate employee code"
                                title="Regenerate"
                                class="inline-flex items-center justify-center h-11 w-11 rounded-xl shrink-0
                                       border border-gray-300 dark:border-gray-600
                                       bg-white dark:bg-gray-800
                                       text-gray-700 dark:text-gray-200
                                       transition-all duration-200 active:scale-95
                                       hover:bg-gray-50 dark:hover:bg-gray-700
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label for="field-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Phone <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="field-phone" wire:model="phone" inputmode="tel" class="input" placeholder="09123456789">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                <label class="inline-flex items-center gap-3 cursor-pointer select-none">
                    <span class="relative inline-flex items-center shrink-0">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <span class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full
                                     peer peer-checked:bg-primary-600
                                     after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                     after:bg-white after:rounded-full after:h-5 after:w-5
                                     after:transition-all peer-checked:after:translate-x-full"></span>
                    </span>
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        Active
                        <span class="text-gray-400 dark:text-gray-500">— the employee can sign in</span>
                    </span>
                </label>
            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    User Account
                </h2>
            </div>

            
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    ['none', 'No Account', 'Employee record only'],
                    ['existing', 'Link Existing', 'Attach to a current user'],
                    ['new', 'Create New', 'New login for this employee'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$val, $label, $hint]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <label class="cursor-pointer" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'user-mode-'.e($val).''; ?>wire:key="user-mode-<?php echo e($val); ?>">
                        <input type="radio" wire:model.live="user_mode" value="<?php echo e($val); ?>" class="sr-only peer">
                        <div class="rounded-xl border-2 p-3.5 transition-all duration-200 active:scale-[0.98] cursor-pointer
                                    <?php echo e($user_mode === $val
                                       ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10 shadow-sm'
                                       : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'); ?>">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white"><?php echo e($label); ?></p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5"><?php echo e($hint); ?></p>
                        </div>
                    </label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user_mode === 'existing'): ?>
                <div class="space-y-3 pt-1">
                    <div>
                        <label for="field-search-user" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Search Existing User
                        </label>
                        <div class="relative">
                            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <input type="text"
                                   id="field-search-user"
                                   wire:model.live.debounce.300ms="search"
                                   placeholder="Type a name, email, or phone…"
                                   enterkeyhint="search"
                                   aria-label="Search existing users"
                                   class="input pl-10 w-full">
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($existingUserResults) > 0): ?>
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl max-h-56 overflow-y-auto shadow-sm">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $existingUserResults; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <button type="button"
                                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'existing-user-'.e($user->id).''; ?>wire:key="existing-user-<?php echo e($user->id); ?>"
                                        wire:click="selectExistingUser(<?php echo e($user->id); ?>)"
                                        class="w-full text-left px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors
                                               focus-visible:outline-none focus-visible:bg-gray-100 dark:focus-visible:bg-gray-700
                                               border-b border-gray-100 dark:border-gray-700/60 last:border-b-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($user->name); ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($user->email ?? $user->phone); ?></p>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['existing_user_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user_mode === 'new'): ?>
                <div class="space-y-4 pt-1">
                    <div>
                        <label for="field-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Email <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" id="field-email" wire:model="email" autocomplete="email" class="input w-full">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="field-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" id="field-password" wire:model="password" autocomplete="new-password" class="input w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label for="field-password-confirm" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Confirm Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" id="field-password-confirm" wire:model="password_confirmation" autocomplete="new-password" class="input w-full">
                        </div>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user_mode === 'new' || $user_mode === 'existing'): ?>
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            <?php echo e($user_mode === 'existing' ? 'Additional Roles' : 'System Roles'); ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user_mode === 'new'): ?> <span class="text-rose-500">*</span> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </h2>
                    </div>

                    <button type="button"
                            wire:click="toggleNewRoleForm"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span><?php echo e($showNewRoleForm ? 'Cancel new role' : 'New Role'); ?></span>
                    </button>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user_mode === 'existing'): ?>
                    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-3">
                        Existing roles are preserved. Checked roles will be added.
                    </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showNewRoleForm): ?>
                    <div class="p-4 bg-gray-50 dark:bg-gray-700/30 rounded-xl border border-gray-200 dark:border-gray-700 space-y-4">
                        <div>
                            <label for="field-new-role-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Role Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" id="field-new-role-name" wire:model="newRoleName" class="input" placeholder="e.g. Front Desk">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newRoleName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Permissions <span class="text-rose-500">*</span>
                                </label>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                                    <?php echo e(count($newRolePermissions)); ?> of <?php echo e($this->allPermissions->count()); ?> selected
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <button type="button"
                                        wire:click="selectAllQuickRolePermissions"
                                        class="inline-flex items-center justify-center h-9 px-3.5 rounded-lg
                                               border border-gray-300 dark:border-gray-600
                                               bg-white dark:bg-gray-800
                                               text-gray-700 dark:text-gray-200
                                               text-xs font-semibold
                                               transition-all duration-200 active:scale-95
                                               hover:bg-gray-50 dark:hover:bg-gray-700
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                    <span>Select All</span>
                                </button>
                                <button type="button"
                                        wire:click="deselectAllQuickRolePermissions"
                                        class="inline-flex items-center justify-center h-9 px-3.5 rounded-lg
                                               border border-gray-300 dark:border-gray-600
                                               bg-white dark:bg-gray-800
                                               text-gray-700 dark:text-gray-200
                                               text-xs font-semibold
                                               transition-all duration-200 active:scale-95
                                               hover:bg-gray-50 dark:hover:bg-gray-700
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
                                           class="w-full h-9 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg pl-9 pr-3 text-xs text-gray-900 dark:text-white placeholder-gray-400
                                                  focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
                                </div>
                            </div>
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->filteredGroupedPermissions->isEmpty()): ?>
                            <div class="text-center py-6 text-xs text-gray-500 dark:text-gray-400">
                                No permissions match your search.
                            </div>
                        <?php else: ?>
                            <div class="space-y-3 max-h-72 overflow-y-auto pr-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->filteredGroupedPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module => $permissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'quick-group-'.e(md5($module)).''; ?>wire:key="quick-group-<?php echo e(md5($module)); ?>"
                                         class="border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-white dark:bg-gray-800">
                                        <h4 class="font-semibold text-xs text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                            <span class="w-1 h-4 bg-primary-600 rounded-full"></span>
                                            <?php echo e($module); ?>

                                            <span class="text-[10px] font-normal text-gray-500 dark:text-gray-400 tabular-nums">(<?php echo e($permissions->count()); ?>)</span>
                                        </h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $perm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <label <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'quick-perm-'.e($perm->id).''; ?>wire:key="quick-perm-<?php echo e($perm->id); ?>"
                                                       class="flex items-center gap-2 p-1.5 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                                    <input type="checkbox"
                                                           wire:model.live="newRolePermissions"
                                                           value="<?php echo e($perm->name); ?>"
                                                           class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 cursor-pointer">
                                                    <span class="text-xs text-gray-700 dark:text-gray-300">
                                                        <?php echo e(ucwords(str_replace(['-', '_'], ' ', $perm->name))); ?>

                                                    </span>
                                                </label>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newRolePermissions'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->availableRoles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <label class="cursor-pointer" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'role-'.e($role['value']).''; ?>wire:key="role-<?php echo e($role['value']); ?>">
                                <input type="checkbox" wire:model.live="selectedRoles" value="<?php echo e($role['value']); ?>" class="sr-only peer">
                                <div class="rounded-xl border-2 p-3 transition-all duration-200 active:scale-[0.98]
                                            <?php echo e(in_array($role['value'], $selectedRoles)
                                               ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10 shadow-sm'
                                               : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'); ?>">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-semibold text-sm text-gray-900 dark:text-white"><?php echo e($role['label']); ?></p>
                                        <span class="text-[9px] font-bold uppercase tracking-wider
                                                     <?php echo e($role['type'] === 'global'
                                                        ? 'text-gray-500 dark:text-gray-400'
                                                        : 'text-primary-600 dark:text-primary-400'); ?>">
                                            <?php echo e($role['type']); ?>

                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-1 mt-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = array_slice($role['permissions'], 0, 3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $perm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-[10px] font-medium">
                                                <?php echo e($perm); ?>

                                            </span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                            <span class="text-[10px] text-gray-400 italic">No permissions</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($role['permissions']) > 3): ?>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 tabular-nums">+<?php echo e(count($role['permissions']) - 3); ?> more</span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </label>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <p class="col-span-2 text-sm text-gray-400 dark:text-gray-500 text-center py-6">No roles match your filters.</p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedRoles'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-5 border-t border-gray-200 dark:border-gray-700">
            <a href="<?php echo e(route('tenant.employees.index')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <span>Cancel</span>
            </a>

            <button type="button"
                    wire:click="saveAndAddAnotherEmployee"
                    wire:loading.attr="disabled"
                    wire:target="saveAndAddAnotherEmployee"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span>Save &amp; Add Another</span>
            </button>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Save Employee</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    
    <?php if (isset($component)) { $__componentOriginalb992f09e6b42df8ffd0c7230daf18927 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.image-crop-modal','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('image-crop-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $attributes = $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $component = $__componentOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\employee\⚡create-employee.blade.php ENDPATH**/ ?>