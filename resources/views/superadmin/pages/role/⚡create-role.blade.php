<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Create Role')] 
class extends Component {
    
    #[Validate('required|string|max:255|unique:roles,name|not_in:super-admin')]
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
            ]
        ];
    }

    public function messages()
    {
        return [
            'name.not_in' => 'The name "super-admin" is reserved and cannot be used.'
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

    /**
     * Trim the role name input on update.
     */
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

<div class="p-4 sm:p-6 lg:p-10 max-w-5xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Create New Role</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Define a system role and assign permissions.</p>
        </div>
        <a href="{{ route('superadmin.roles.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Back to Roles
        </a>
    </div>

    <form wire:submit="store" class="space-y-6">
        {{-- Role Name --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-2">Role Name</label>
            <input type="text" wire:model="name" 
                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500 shadow-sm" 
                   placeholder="e.g. Booking Manager">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-400 dark:text-slate-500 mt-2">This role will be available to all tenants.</p>
        </div>

        {{-- Permissions Section with Alpine controls --}}
        <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6"
             x-data="{
                search: @entangle('permissionSearch'),
                selectedCount: {{ count($selectedPermissions) }},
                totalCount: {{ $this->allPermissions->count() }},
                selectAll() {
                    $wire.selectAll();
                },
                deselectAll() {
                    $wire.deselectAll();
                }
             }">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Assign Permissions</h3>
                <div class="flex items-center gap-2">
                    <button type="button" @click="selectAll" 
                            class="text-sm bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 px-3 py-1.5 rounded-lg transition-colors">
                        Select All
                    </button>
                    <button type="button" @click="deselectAll" 
                            class="text-sm bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 px-3 py-1.5 rounded-lg transition-colors">
                        Clear All
                    </button>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.200ms="permissionSearch" placeholder="Filter permissions..." 
                               class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 rounded-lg focus:ring-blue-500">
                        <svg class="absolute left-2.5 top-2 w-4 h-4 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            @if($this->filteredGroupedPermissions->isEmpty())
                <div class="text-center py-8 text-gray-500 dark:text-slate-400">
                    No permissions match your search.
                </div>
            @else
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @foreach($this->filteredGroupedPermissions as $module => $permissions)
                        <div class="border border-gray-200 dark:border-slate-700/50 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-1 h-5 bg-blue-500 rounded-full"></span>
                                {{ $module }}
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($permissions as $permission)
                                    <label class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" 
                                               class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500 dark:bg-slate-700 dark:checked:bg-blue-600">
                                        <span class="text-sm text-gray-700 dark:text-slate-300">
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
        <div class="flex items-center gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                <span class="in-data-loading:hidden">Create Role</span>
                <span class="not-in-data-loading:hidden flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.roles.index') }}" wire:navigate class="bg-white dark:bg-slate-800 text-gray-700 dark:text-slate-300 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>