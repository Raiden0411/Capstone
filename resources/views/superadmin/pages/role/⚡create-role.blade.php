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

<div class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Create New Role</h1>
            <p class="text-gray-500 dark:text-white/50 mt-1">Define a system role and assign permissions.</p>
        </div>
        <a href="{{ route('superadmin.roles.index') }}" wire:navigate class="text-sm font-medium text-gray-500 dark:text-white/50 hover:text-brand-600 dark:hover:text-brand-400 transition-colors flex items-center gap-1">
            &larr; Back to Roles
        </a>
    </div>

    <form wire:submit="store" class="space-y-6">
        {{-- Role Name --}}
        <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-5 sm:p-6 shadow-sm dark:shadow-none">
            <label class="block text-sm font-medium text-gray-700 dark:text-white/70 mb-2">Role Name</label>
            <input type="text" wire:model="name" 
                   class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition shadow-sm" 
                   placeholder="e.g. Booking Manager">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-400 dark:text-white/40 mt-2">This role will be available to all tenants.</p>
        </div>

        {{-- Permissions Section with Alpine controls --}}
        <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl p-5 sm:p-6 shadow-sm dark:shadow-none"
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
                            class="text-sm bg-gray-100 dark:bg-white/10 hover:bg-gray-200 dark:hover:bg-white/20 text-gray-700 dark:text-white/70 px-3 py-1.5 rounded-lg transition-colors">
                        Select All
                    </button>
                    <button type="button" @click="deselectAll" 
                            class="text-sm bg-gray-100 dark:bg-white/10 hover:bg-gray-200 dark:hover:bg-white/20 text-gray-700 dark:text-white/70 px-3 py-1.5 rounded-lg transition-colors">
                        Clear All
                    </button>
                    <div class="relative">
                        <input type="text" wire:model.live.debounce.200ms="permissionSearch" placeholder="Filter permissions..." 
                               class="pl-8 pr-3 py-1.5 text-sm bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-lg text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500">
                        <svg class="absolute left-2.5 top-2 w-4 h-4 text-gray-400 dark:text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            @if($this->filteredGroupedPermissions->isEmpty())
                <div class="text-center py-8 text-gray-500 dark:text-white/40">
                    No permissions match your search.
                </div>
            @else
                <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2">
                    @foreach($this->filteredGroupedPermissions as $module => $permissions)
                        <div class="border border-gray-200 dark:border-white/10 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 dark:text-white mb-2 flex items-center gap-2">
                                <span class="w-1 h-5 bg-brand-500 rounded-full"></span>
                                {{ $module }}
                            </h4>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                @foreach($permissions as $permission)
                                    <label class="flex items-center gap-2 p-2 rounded hover:bg-gray-50 dark:hover:bg-white/5 cursor-pointer transition-colors">
                                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" 
                                               class="rounded border-gray-300 dark:border-white/20 text-brand-600 focus:ring-brand-500 dark:bg-white/5 dark:checked:bg-brand-600">
                                        <span class="text-sm text-gray-700 dark:text-white/70">
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
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-2.5 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Create Role</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.roles.index') }}" wire:navigate class="bg-white dark:bg-white/5 text-gray-700 dark:text-white/70 border border-gray-300 dark:border-white/10 hover:bg-gray-50 dark:hover:bg-white/10 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>