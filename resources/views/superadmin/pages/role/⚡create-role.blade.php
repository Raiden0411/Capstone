<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

new
#[Layout('superadmin.layouts.app')]
#[Title('Add Role')]
class extends Component {

    public string $name = '';
    public array $selectedPermissions = [];
    public string $permissionSearch = '';

    public function mount(): void
    {
        // Defensive: the route already uses IsSuperAdmin middleware, but
        // if a non-super-admin somehow reaches this component, reject.
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');
    }

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:255',
                // Scope uniqueness by guard_name — otherwise a same-named
                // role on another guard would falsely block this one.
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    // Reserve every variant of admin / super-admin. Handles
                    // all common separators: space, hyphen, underscore, dot.
                    if (preg_match('/(^|[\s\-_.])(super[\s\-_.]?admin|admin)($|[\s\-_.])/i', (string) $value)) {
                        $fail('Role names containing "admin" or "super-admin" are reserved.');
                    }
                },
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => [
                Rule::exists('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function updatedName(string $value): void
    {
        $this->name = trim($value);
    }

    /**
     * All permissions in the system — super-admin can grant any of them,
     * since platform-wide roles may carry any capability.
     *
     * @return Collection<int, Permission>
     */
    #[Computed]
    public function allPermissions(): Collection
    {
        return Permission::query()
            ->select('id', 'name')
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get();
    }

    /**
     * Permissions grouped by their trailing noun (e.g. "view bookings" →
     * "Bookings"). Single-word permissions fall under "General".
     *
     * @return Collection<string, Collection<int, Permission>>
     */
    #[Computed]
    public function groupedPermissions(): Collection
    {
        return $this->allPermissions
            ->groupBy(function (Permission $permission): string {
                $parts = explode(' ', str_replace(['-', '_'], ' ', $permission->name));

                return count($parts) >= 2 ? ucwords(end($parts)) : 'General';
            })
            ->sortKeys();
    }

    /**
     * Permissions narrowed by the search box, preserving group structure.
     *
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
        $this->selectedPermissions = $this->allPermissions->pluck('name')->all();
    }

    public function deselectAll(): void
    {
        $this->selectedPermissions = [];
    }

    public function store()
    {
        $this->name = trim($this->name);
        $this->validate();

        // Defensive re-check before any mutation.
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        try {
            DB::transaction(function (): void {
                $role = Role::create([
                    'name'       => $this->name,
                    'guard_name' => 'web',
                ]);

                if (!empty($this->selectedPermissions)) {
                    $role->syncPermissions($this->selectedPermissions);
                }
            });
        } catch (\Spatie\Permission\Exceptions\RoleAlreadyExists $e) {
            session()->flash('error', "A role named '{$this->name}' already exists.");
            return null;
        } catch (\Throwable $e) {
            Log::error('Superadmin role creation failed: ' . $e->getMessage(), [
                'role_name' => $this->name,
                'actor_id'  => Auth::id(),
            ]);
            session()->flash('error', 'Failed to create role. Please try again.');
            return null;
        }

        // Clear Spatie's cache AFTER the transaction commits, so a rollback
        // doesn't leave us with a cleared cache + untouched database.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        session()->flash('message', "Role '{$this->name}' created successfully.");

        return $this->redirectRoute('superadmin.roles.index', navigate: true);
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
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform Roles</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Add <em class="italic text-primary-600 dark:text-primary-400">Role</em>
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Create a platform-wide role that any tenant can assign.</p>
        </div>
        <a href="{{ route('superadmin.roles.index') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Roles
        </a>
    </div>

    <form wire:submit="store" class="space-y-6">

        {{-- Role Name --}}
        <div class="card p-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role Name *</label>
            <input type="text" wire:model.live.debounce.400ms="name"
                   class="input"
                   placeholder="e.g. Booking Manager">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Must be unique. Cannot contain "admin" or "super-admin". This role will be available to all tenants.
            </p>
        </div>

        {{-- Permissions Section --}}
        <div class="card p-6">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Permissions</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ count($selectedPermissions) }} of {{ $this->allPermissions->count() }} selected
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
                                            {{ ucwords(str_replace(['-', '_'], ' ', $permission->name)) }}
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

        {{-- Form Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="store"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="store">Create Role</span>
                <span wire:loading wire:target="store" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
            <a href="{{ route('superadmin.roles.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>