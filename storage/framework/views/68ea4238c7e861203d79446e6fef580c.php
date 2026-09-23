
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\TenantSetting;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

new
#[Layout('tenant.layouts.app')]
#[Title('Create Custom Role')]
class extends Component {

    public string $name = '';
    public array $selectedPermissions = [];

    /** @var array<int, array{role_id: int, name: string, permissions: array<int, string>}> */
    public array $customRoles = [];

    public string $permissionSearch = '';

    protected array $excludedPermissions = [
        'manage roles',
        'manage permissions',
        'manage tenants',
        'manage platform',
        'manage users',
        'delete tenants',
        'delete users',
    ];

    public function mount(): void
    {
        $this->authorizeManageRoles();

        if (!Auth::user()?->tenant_id) {
            abort(403, 'Your account is not linked to a business.');
        }

        $this->loadExistingRoles();
    }

    public function hydrate(): void
    {
        $this->authorizeManageRoles();
        abort_unless(Auth::user()?->tenant_id, 403);

        $this->loadExistingRoles();
    }

    protected function authorizeManageRoles(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage roles');

        abort_unless($canManage, 403, 'You are not authorized to create roles.');
    }

    protected function loadExistingRoles(): void
    {
        $setting = TenantSetting::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();

        $this->customRoles = ($setting && is_array($setting->value)) ? $setting->value : [];
    }

    public function updatedName(string $value): void
    {
        $this->name = trim($value);
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach ($this->customRoles as $role) {
                        if (isset($role['name']) && strcasecmp($role['name'], (string) $value) === 0) {
                            $fail('A custom role with this name already exists.');
                            return;
                        }
                    }

                    $query = Role::query()
                        ->where('name', $value)
                        ->where('guard_name', 'web');

                    if (config('permission.teams')) {
                        $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                        $query->where($teamKey, Auth::user()->tenant_id);
                    }

                    if ($query->exists()) {
                        $fail('This role name is already taken.');
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (preg_match('/(^|[\s\-_.])(super[\s\-_.]?admin|admin)($|[\s\-_.])/i', (string) $value)) {
                        $fail('Role names containing "admin" or "super-admin" are reserved.');
                    }
                },
            ],
            'selectedPermissions'   => ['required', 'array', 'min:1'],
            'selectedPermissions.*' => [
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'selectedPermissions.required' => 'Please select at least one permission.',
            'selectedPermissions.min'      => 'Please select at least one permission.',
        ];
    }

    #[Computed]
    public function availablePermissions(): Collection
    {
        return Permission::query()
            ->whereNotIn('name', $this->excludedPermissions)
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function groupedPermissions(): Collection
    {
        return $this->availablePermissions
            ->groupBy(function (Permission $permission): string {
                $parts = explode(' ', str_replace(['-', '_'], ' ', $permission->name));

                return count($parts) >= 2 ? ucwords(end($parts)) : 'General';
            })
            ->sortKeys();
    }

    #[Computed]
    public function filteredGroupedPermissions(): Collection
    {
        if ($this->permissionSearch === '') {
            return $this->groupedPermissions;
        }

        $search = strtolower($this->permissionSearch);

        return $this->groupedPermissions
            ->map(fn (Collection $permissions) => $permissions->filter(
                fn (Permission $permission) => str_contains(strtolower($permission->name), $search),
            ))
            ->filter(fn (Collection $permissions) => $permissions->isNotEmpty());
    }

    public function selectAll(): void
    {
        $this->selectedPermissions = $this->availablePermissions->pluck('name')->all();
    }

    public function deselectAll(): void
    {
        $this->selectedPermissions = [];
    }

    public function save()
    {
        $this->name = trim($this->name);
        $this->validate();

        $this->authorizeManageRoles();

        $user     = Auth::user();
        $tenantId = (int) $user->tenant_id;

        try {
            DB::transaction(function () use ($user, $tenantId): void {
                $guardName = 'web';
                $usesTeams = (bool) config('permission.teams');

                $roleData = [
                    'name'       => $this->name,
                    'guard_name' => $guardName,
                ];

                if ($usesTeams) {
                    $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                    $roleData[$teamKey] = $tenantId;
                }

                $role = Role::create($roleData);

                $role->syncPermissions($this->selectedPermissions);

                $setting = TenantSetting::query()
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'custom_roles')
                    ->first();

                $tracker = ($setting && is_array($setting->value)) ? $setting->value : [];

                $tracker[] = [
                    'role_id'     => $role->id,
                    'name'        => $this->name,
                    'permissions' => $this->selectedPermissions,
                ];

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => 'custom_roles'],
                    ['value' => $tracker],
                );
            });
        } catch (\Spatie\Permission\Exceptions\RoleAlreadyExists $e) {
            session()->flash('error', 'This role name already exists. Please choose a different name.');
            return null;
        } catch (\Throwable $e) {
            Log::error('Tenant role creation failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'actor_id'  => $user->id,
                'role_name' => $this->name,
            ]);
            session()->flash('error', 'Failed to create role. Please try again.');
            return null;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('message', 'Custom role created successfully.');

        return $this->redirectRoute('tenant.roles.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Roles</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Create Custom Role
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Define a new role and assign its permissions.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Roles</span>
        </a>
    </div>

    
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

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 6000)"
             :class="show ? '' : 'hidden'"
             class="flex items-start justify-between gap-3 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl">
            <div class="flex items-start gap-2.5 min-w-0">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div class="text-xs sm:text-sm text-rose-800 dark:text-rose-300 min-w-0">
                    <p class="font-semibold mb-1">Please fix the following:</p>
                    <ul class="list-disc list-inside space-y-0.5">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $err): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'err-'.e($loop->index).''; ?>wire:key="err-<?php echo e($loop->index); ?>"><?php echo e($err); ?></li>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </ul>
                </div>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-7 w-7 shrink-0 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="save" class="space-y-6">

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Role Name</h2>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Name <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model.live.debounce.400ms="name"
                       class="input w-full"
                       placeholder="e.g. Front Desk Manager">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">
                    Must be unique within your business. Cannot contain "admin" or "super-admin".
                </p>
            </div>
        </div>

        
        <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <div>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Assign Permissions</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 tabular-nums">
                            <?php echo e(count($selectedPermissions)); ?> of <?php echo e($this->availablePermissions->count()); ?> selected
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="selectAll"
                            class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Select All
                    </button>
                    <button type="button" wire:click="deselectAll"
                            class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-xs font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Clear All
                    </button>

                    <div class="relative w-full sm:w-56">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               wire:model.live.debounce.200ms="permissionSearch"
                               placeholder="Filter permissions…"
                               class="input w-full text-sm"
                               style="padding-left: 2.25rem;">
                    </div>
                </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->filteredGroupedPermissions->isEmpty()): ?>
                <div class="text-center py-10 bg-gray-50 dark:bg-gray-900/40 rounded-xl border border-dashed border-gray-300 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No permissions match your search.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4 max-h-[520px] overflow-y-auto pr-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->filteredGroupedPermissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $module => $permissions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'group-'.e(md5($module)).''; ?>wire:key="group-<?php echo e(md5($module)); ?>"
                             class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50/70 dark:bg-gray-900/40">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-1 h-4 rounded-full bg-primary-600"></span>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300">
                                    <?php echo e($module); ?>

                                </h3>
                                <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                             bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                    <?php echo e($permissions->count()); ?>

                                </span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $permissions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <label <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'permission-'.e($permission->id).''; ?>wire:key="permission-<?php echo e($permission->id); ?>"
                                           class="flex items-center gap-2 p-2 rounded-lg cursor-pointer
                                                  hover:bg-white dark:hover:bg-gray-800/60
                                                  transition-colors">
                                        <input type="checkbox"
                                               wire:model="selectedPermissions"
                                               value="<?php echo e($permission->name); ?>"
                                               class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500 focus-visible:outline-none">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            <?php echo e(Str::headline($permission->name)); ?>

                                        </span>
                                    </label>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedPermissions'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-2 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['selectedPermissions.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-2 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-5 mt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="<?php echo e(route('tenant.roles.index')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                Cancel
            </a>
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Save Role
                </span>
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
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\tenant\pages\role\⚡create-role.blade.php ENDPATH**/ ?>