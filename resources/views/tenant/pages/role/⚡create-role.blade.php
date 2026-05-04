<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\TenantSetting;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Custom Role')]
class extends Component {
    
    #[Validate]
    public $name = '';
    
    public $selectedPermissions = [];
    public $customRoles = [];

    public function mount()
    {
        $this->loadExistingRoles();
    }

    protected function loadExistingRoles()
    {
        $setting = TenantSetting::where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();
        $this->customRoles = $setting ? $setting->value : [];
    }

    public function updated($property)
    {
        if ($property === 'name') {
            $this->name = trim($this->name);
        }
    }

    public function rules()
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) {
                    foreach ($this->customRoles as $role) {
                        if (strtolower($role['name']) === strtolower($value)) {
                            $fail('A custom role with this name already exists.');
                            return;
                        }
                    }
                },
                Rule::notIn(['super-admin', 'admin']),
                function ($attribute, $value, $fail) {
                    if (stripos($value, 'admin') !== false) {
                        $fail('Role names containing "admin" are reserved and cannot be used.');
                    }
                },
            ],
            'selectedPermissions' => 'array|min:1',
        ];
    }

    public function messages()
    {
        return [
            'selectedPermissions.min' => 'Please select at least one permission.',
            'name.not_in' => 'The role name ":input" is reserved and cannot be used.',
        ];
    }

    #[Computed]
    public function availablePermissions()
    {
        $excludePatterns = [
            'delete%',
            '%user%',
            'role%',
            'permission%',
            '%super-admin%',
            '%admin%',
            'tenant%',
            'platform%',
        ];

        $query = Permission::orderBy('name');
        
        foreach ($excludePatterns as $pattern) {
            $query->where('name', 'not like', $pattern);
        }

        return $query->get();
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {
            $this->customRoles[] = [
                'name' => $this->name,
                'permissions' => $this->selectedPermissions,
            ];

            TenantSetting::updateOrCreate(
                ['tenant_id' => Auth::user()->tenant_id, 'key' => 'custom_roles'],
                ['value' => $this->customRoles]
            );
        });

        session()->flash('message', 'Custom role created successfully.');
        return $this->redirectRoute('tenant.roles.index', navigate: true);
    }
};
?>
<div class="p-4 sm:p-6 lg:p-10 max-w-3xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Flash Message (shown after redirect) --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold">Create Custom Role</h1>
        <a href="{{ route('tenant.roles.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Back to Roles
        </a>
    </div>

    <form wire:submit="save" class="space-y-6 bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        {{-- Role Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Role Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" placeholder="e.g. Front Desk Manager">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Cannot use "admin" or "super-admin".</p>
        </div>

        {{-- Permissions --}}
        <div>
            <div class="flex justify-between items-center mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300">Assign Permissions *</label>
                <span class="text-xs text-gray-500 dark:text-slate-400">{{ count($selectedPermissions) }} selected</span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-80 overflow-y-auto border border-gray-200 dark:border-slate-700/50 rounded-lg p-3">
                @forelse($this->availablePermissions as $permission)
                    <label class="flex items-center gap-2 text-sm hover:bg-gray-50 dark:hover:bg-slate-800/50 p-1.5 rounded cursor-pointer transition-colors">
                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500 dark:bg-slate-700 dark:checked:bg-blue-600">
                        <span class="text-gray-700 dark:text-slate-300">{{ ucwords(str_replace(['-', '_'], ' ', $permission->name)) }}</span>
                    </label>
                @empty
                    <p class="text-gray-500 dark:text-slate-400 col-span-2 text-center py-4">No assignable permissions available.</p>
                @endforelse
            </div>
            @error('selectedPermissions') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-slate-700/50">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                <span class="in-data-loading:hidden">Save Role</span>
                <span class="not-in-data-loading:hidden flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.roles.index') }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>