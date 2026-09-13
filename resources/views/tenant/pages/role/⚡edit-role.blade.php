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
#[Title('Edit Custom Role')]
class extends Component {

    public int $index;
    public ?int $roleId = null;

    public string $name = '';
    public string $originalName = '';

    /** @var array<int, string> */
    public array $selectedPermissions = [];

    /** @var array<int, array{role_id?: int, name: string, permissions: array}> */
    public array $customRoles = [];

    public string $permissionSearch = '';

    /**
     * Permissions a tenant admin must never delegate to a custom role.
     *
     * @var array<int, string>
     */
    protected array $excludedPermissions = [
        'manage roles',
        'manage permissions',
        'manage tenants',
        'manage platform',
        'manage users',
        'delete tenants',
        'delete users',
    ];

    public function mount(int $index): void
    {
        $this->authorizeManageRoles();

        $user = Auth::user();
        abort_unless($user?->tenant_id, 403, 'Your account is not linked to a business.');

        $this->index = $index;
        $this->loadCustomRoles();

        if (!isset($this->customRoles[$this->index])) {
            abort(404, 'Custom role not found.');
        }

        $role = $this->customRoles[$this->index];

        $this->name                = (string) ($role['name'] ?? '');
        $this->originalName        = $this->name;
        $this->roleId              = isset($role['role_id']) ? (int) $role['role_id'] : null;
        $this->selectedPermissions = $this->normalizePermissions($role['permissions'] ?? []);

        // Legacy records may not have a role_id stored — resolve it by name
        // and backfill so future loads are consistent.
        if (!$this->roleId) {
            $this->resolveLegacyRoleId($user->tenant_id);
        }
    }

    protected function authorizeManageRoles(): void
    {
        $user = Auth::user();

        abort_unless($user, 403);

        $canManage = $user->hasAnyRole(['admin', 'super-admin'])
            || $user->getAllPermissions()->contains('name', 'manage roles');

        abort_unless($canManage, 403, 'You are not authorized to edit roles.');
    }

    protected function loadCustomRoles(): void
    {
        $setting = TenantSetting::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();

        $this->customRoles = ($setting && is_array($setting->value)) ? $setting->value : [];
    }

    /**
     * Normalize a stored permission array into a flat array of permission names.
     *
     * @return array<int, string>
     */
    protected function normalizePermissions(mixed $permissions): array
    {
        if (!is_array($permissions)) {
            return [];
        }

        $names = [];

        foreach ($permissions as $perm) {
            if (is_string($perm)) {
                $names[] = $perm;
            } elseif (is_array($perm) && isset($perm['name'])) {
                $names[] = (string) $perm['name'];
            }
        }

        return array_values(array_unique($names));
    }

    protected function resolveLegacyRoleId(int $tenantId): void
    {
        $query = Role::query()
            ->where('name', $this->name)
            ->where('guard_name', 'web');

        if (config('permission.teams')) {
            $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
            $query->where($teamKey, $tenantId);
        }

        $found = $query->first();

        if (!$found) {
            return;
        }

        $this->roleId = $found->id;
        $this->customRoles[$this->index]['role_id'] = $found->id;

        TenantSetting::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => 'custom_roles'],
            ['value' => $this->customRoles],
        );
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
                    // 1. Case-insensitive check against stored custom roles,
                    //    excluding the current entry.
                    foreach ($this->customRoles as $i => $role) {
                        if ($i === $this->index) {
                            continue;
                        }
                        if (isset($role['name']) && strcasecmp($role['name'], (string) $value) === 0) {
                            $fail('A custom role with this name already exists.');
                            return;
                        }
                    }

                    // 2. Duplicate check against the Spatie roles table.
                    if ($value !== $this->originalName) {
                        $query = Role::query()
                            ->where('name', $value)
                            ->where('guard_name', 'web');

                        if (config('permission.teams')) {
                            $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                            $query->where($teamKey, Auth::user()->tenant_id);
                        }

                        if ($this->roleId) {
                            $query->where('id', '!=', $this->roleId);
                        }

                        if ($query->exists()) {
                            $fail('This role name is already taken.');
                        }
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Skip reserved-name check when the name is unchanged —
                    // allows legacy roles with "admin" in their name to still
                    // have their permissions edited.
                    if (strcasecmp((string) $value, $this->originalName) === 0) {
                        return;
                    }

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

    /**
     * @return Collection<int, Permission>
     */
    #[Computed]
    public function availablePermissions(): Collection
    {
        return Permission::query()
            ->whereNotIn('name', $this->excludedPermissions)
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
        return $this->availablePermissions
            ->groupBy(function (Permission $permission): string {
                $parts = explode(' ', str_replace(['-', '_'], ' ', $permission->name));

                return count($parts) >= 2 ? ucwords(end($parts)) : 'General';
            })
            ->sortKeys();
    }

    /**
     * @return Collection<string, Collection<int, Permission>>
     */
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

    public function update()
    {
        $this->name = trim($this->name);
        $this->validate();

        // Defensive re-check before any mutation.
        $this->authorizeManageRoles();

        if (!$this->roleId) {
            session()->flash('error', 'This role is no longer valid. Please recreate it from the create page.');
            return null;
        }

        $user     = Auth::user();
        $tenantId = (int) $user->tenant_id;

        try {
            DB::transaction(function () use ($user, $tenantId): void {
                /** @var Role|null $role */
                $role = Role::findById($this->roleId, 'web');

                if (!$role) {
                    throw new \RuntimeException('Role no longer exists.');
                }

                // If team scoping is enabled, verify the role belongs to this tenant.
                if (config('permission.teams')) {
                    $teamKey = config('permission.column_names.team_foreign_key', 'team_id');
                    if ((int) $role->{$teamKey} !== $tenantId) {
                        throw new \RuntimeException('Role does not belong to your tenant.');
                    }
                }

                // 1. Rename if changed.
                if ($this->name !== $role->name) {
                    $role->name = $this->name;
                    $role->save();
                }

                // 2. Sync permissions.
                $role->syncPermissions($this->selectedPermissions);

                // 3. Update the JSON tracker.
                $this->customRoles[$this->index] = [
                    'role_id'     => $role->id,
                    'name'        => $this->name,
                    'permissions' => $this->selectedPermissions,
                ];

                TenantSetting::updateOrCreate(
                    ['tenant_id' => $tenantId, 'key' => 'custom_roles'],
                    ['value' => $this->customRoles],
                );
            });
        } catch (\Spatie\Permission\Exceptions\RoleAlreadyExists $e) {
            session()->flash('error', 'This role name already exists. Please choose a different name.');
            return null;
        } catch (\RuntimeException $e) {
            Log::warning('Tenant role edit rejected: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'actor_id'  => $user->id,
                'role_id'   => $this->roleId,
            ]);
            session()->flash('error', 'You are not authorized to modify this role.');
            return null;
        } catch (\Throwable $e) {
            Log::error('Tenant role update failed: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'actor_id'  => $user->id,
                'role_id'   => $this->roleId,
            ]);
            session()->flash('error', 'Failed to update role. Please try again.');
            return null;
        }

        // Clear Spatie's permission cache after the transaction commits.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('message', 'Custom role updated successfully.');

        return $this->redirectRoute('tenant.roles.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    {{-- Flash Messages --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Roles</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Edit <em class="italic text-primary-600 dark:text-primary-400">{{ Str::headline($originalName) }}</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Update the role name or reassign its permissions.</p>
        </div>
        <a href="{{ route('tenant.roles.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Roles
        </a>
    </div>

    <form wire:submit="update" class="space-y-6">

        {{-- Role Name --}}
        <div class="card p-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role Name *</label>
            <input type="text" wire:model.live.debounce.400ms="name"
                   class="input"
                   placeholder="e.g. Front Desk Manager">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Must be unique within your business. Cannot contain "admin" or "super-admin".
            </p>
        </div>

        {{-- Permissions Section --}}
        <div class="card p-6">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Permissions *</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ count($selectedPermissions) }} of {{ $this->availablePermissions->count() }} selected
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="selectAll"
                            class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Select All
                    </button>
                    <button type="button" wire:click="deselectAll"
                            class="btn-secondary text-xs active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Clear All
                    </button>

                    <div class="relative w-full sm:w-48">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" wire:model.live.debounce.200ms="permissionSearch"
                               placeholder="Filter permissions…"
                               class="pl-9 pr-3 py-2 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition w-full">
                    </div>
                </div>
            </div>

            @if($this->filteredGroupedPermissions->isEmpty())
                <div class="text-center py-10 text-gray-500 dark:text-gray-400">
                    No permissions match your search.
                </div>
            @else
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @foreach($this->filteredGroupedPermissions as $module => $permissions)
                        <div wire:key="group-{{ md5($module) }}"
                             class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-gray-700/50">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-1 h-5 bg-primary-600 rounded-full"></span>
                                {{ $module }}
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">({{ $permissions->count() }})</span>
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($permissions as $permission)
                                    <label wire:key="permission-{{ $permission->id }}"
                                           class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer transition-colors">
                                        <input type="checkbox"
                                               wire:model.live="selectedPermissions"
                                               value="{{ $permission->name }}"
                                               class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-primary-600 focus:ring-primary-500">
                                        <span class="text-sm text-gray-700 dark:text-gray-300">
                                            {{ Str::headline($permission->name) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @error('selectedPermissions') <span class="text-red-500 dark:text-red-400 text-xs mt-2 block">{{ $message }}</span> @enderror
            @error('selectedPermissions.*') <span class="text-red-500 dark:text-red-400 text-xs mt-2 block">{{ $message }}</span> @enderror
        </div>

        {{-- Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="update"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="update">Update Role</span>
                <span wire:loading wire:target="update" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('tenant.roles.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>