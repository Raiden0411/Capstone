{{-- resources/views/tenant/pages/role/⚡edit-role.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\TenantSetting;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Custom Role')]
class extends Component {
    
    public int $index;
    
    #[Validate]
    public $name = '';
    
    public $selectedPermissions = [];
    public $customRoles = [];

    public function mount($index)
    {
        $this->index = (int) $index;
        $this->loadCustomRoles();
        
        if (!isset($this->customRoles[$this->index])) {
            abort(404, 'Custom role not found.');
        }
        
        $role = $this->customRoles[$this->index];
        $this->name = $role['name'];
        $this->selectedPermissions = $role['permissions'];
    }

    protected function loadCustomRoles()
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
                    foreach ($this->customRoles as $i => $role) {
                        if ($i != $this->index && strtolower($role['name']) === strtolower($value)) {
                            $fail('A custom role with this name already exists.');
                            return;
                        }
                    }
                },
                Rule::notIn(['super-admin', 'admin']),
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

    public function getAvailablePermissionsProperty()
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

    public function update()
    {
        $this->validate();

        DB::transaction(function () {
            $this->customRoles[$this->index] = [
                'name' => $this->name,
                'permissions' => $this->selectedPermissions,
            ];

            TenantSetting::updateOrCreate(
                ['tenant_id' => Auth::user()->tenant_id, 'key' => 'custom_roles'],
                ['value' => $this->customRoles]
            );
        });

        session()->flash('message', 'Custom role updated successfully.');
        return $this->redirectRoute('tenant.roles.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-700">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Custom Role</h1>
        <a href="{{ route('tenant.roles.index') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Roles
        </a>
    </div>

    <form wire:submit="update" class="space-y-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm">

        {{-- Role Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role Name *</label>
            <input type="text" wire:model="name"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cannot use "admin" or "super-admin".</p>
        </div>

        {{-- Permissions --}}
        <div>
            <div class="flex justify-between items-center mb-2">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Assign Permissions *</label>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($selectedPermissions) }} selected</span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-80 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-xl p-3 bg-gray-50 dark:bg-gray-700/50">
                @forelse($this->availablePermissions as $permission)
                    <label class="flex items-center gap-2 text-sm hover:bg-gray-100 dark:hover:bg-gray-700 p-2 rounded-lg cursor-pointer transition-colors">
                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}"
                               class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                        <span class="text-gray-700 dark:text-gray-300">{{ ucwords(str_replace(['-', '_'], ' ', $permission->name)) }}</span>
                    </label>
                @empty
                    <p class="text-gray-400 dark:text-gray-500 col-span-2 text-center py-4">No assignable permissions available.</p>
                @endforelse
            </div>
            @error('selectedPermissions') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-blue-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Update Role</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.roles.index') }}" wire:navigate
               class="px-6 py-3 rounded-full bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Cancel
            </a>
        </div>
    </form>
</div>