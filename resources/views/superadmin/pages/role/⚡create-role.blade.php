{{-- resources/views/superadmin/pages/role/⚡create-role.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Add Role')] 
class extends Component {
    
    public $name = '';
    public array $selectedPermissions = [];
    public string $permissionSearch = '';

    public function rules()
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                'unique:roles,name',
                'not_in:super-admin'
            ],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['exists:permissions,name'],
        ];
    }

    public function messages()
    {
        return [
            'name.not_in' => 'The name "super-admin" is reserved and cannot be used.',
        ];
    }

    #[Computed]
    public function allPermissions(): Collection
    {
        return Permission::orderBy('name')->get();
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
        if (empty($this->permissionSearch)) {
            return $this->groupedPermissions;
        }

        $search = strtolower($this->permissionSearch);
        return $this->groupedPermissions->map(function ($permissions) use ($search) {
            return $permissions->filter(function ($permission) use ($search) {
                return str_contains(strtolower($permission->name), $search);
            });
        })->filter(fn($permissions) => $permissions->isNotEmpty());
    }

    public function selectAll()
    {
        $this->selectedPermissions = $this->allPermissions->pluck('name')->toArray();
    }

    public function deselectAll()
    {
        $this->selectedPermissions = [];
    }

    public function updated($property)
    {
        if ($property === 'name') {
            $this->name = trim($this->name);
        }
    }

    public function store()
    {
        $this->validate();

        $role = Role::create(['name' => strtolower($this->name)]);
        $role->syncPermissions($this->selectedPermissions);

        session()->flash('message', "Role '{$role->name}' created successfully.");
        return $this->redirectRoute('superadmin.roles.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Role</h1>
        </div>
        <a href="{{ route('superadmin.roles.index') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
            ← Back to Roles
        </a>
    </div>

    <form wire:submit="store" class="space-y-6">

        {{-- Role Name --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role Name *</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition"
                   placeholder="e.g. Booking Manager">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">This role will be available to all tenants.</p>
        </div>

        {{-- Permissions Section --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm">

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Permissions</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ count($selectedPermissions) }} of {{ $this->allPermissions->count() }} selected
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" wire:click="selectAll"
                            class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Select All
                    </button>
                    <button type="button" wire:click="deselectAll"
                            class="px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Clear All
                    </button>

                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text" wire:model.live.debounce.200ms="permissionSearch"
                               placeholder="Filter permissions..."
                               class="pl-9 pr-3 py-2 text-sm bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition w-48">
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
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 bg-gray-50 dark:bg-gray-700/50">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-1 h-5 bg-primary-600 rounded-full"></span>
                                {{ $module }}
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
        </div>

        {{-- Form Actions --}}
        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-primary-600 hover:bg-primary-700 text-white font-medium py-2.5 px-5 rounded-full shadow-lg shadow-primary-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100 flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Create Role</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.roles.index') }}" wire:navigate
               class="bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium py-2.5 px-5 rounded-full transition-colors hover:scale-[1.02] focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>