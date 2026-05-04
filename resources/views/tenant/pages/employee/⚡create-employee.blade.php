<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Employee;
use App\Models\User;
use App\Models\TenantSetting;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Employee')]
class extends Component {
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string|max:50')]
    public $employeeRole = ''; // free‑text job title (display only)
    
    #[Validate('nullable|string|max:20')]
    public $phone = '';
    
    #[Validate('boolean')]
    public $is_active = true;
    
    // Optional user account
    public $create_user = false;
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $selectedRole = '';

    public function getAvailableRolesProperty()
    {
        $roles = collect();

        // Global Spatie roles (tenant‑assignable)
        $globalRoles = Role::whereNotIn('name', ['super-admin', 'admin'])
            ->orderBy('name')
            ->get()
            ->map(fn($role) => [
                'type'        => 'global',
                'value'       => $role->name,
                'label'       => ucfirst($role->name) . ' (Global)',
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ]);

        $roles = $roles->concat($globalRoles);

        // Tenant custom roles from tenant_settings
        $setting = TenantSetting::where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();
        $customRoles = $setting ? $setting->value : [];

        foreach ($customRoles as $index => $customRole) {
            $roles->push([
                'type'        => 'custom',
                'value'       => 'custom_' . $index,
                'label'       => $customRole['name'] . ' (Custom)',
                'permissions' => $customRole['permissions'],
            ]);
        }

        return $roles;
    }

    public function rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'employeeRole' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ];

        if ($this->create_user) {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users', 'email'),
            ];
            $rules['password'] = 'required|min:8|confirmed';
            $rules['selectedRole'] = 'required';
        }

        return $rules;
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email', 'phone', 'employeeRole', 'password'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function save()
    {
        $this->validate();

        DB::transaction(function () {
            $userId = null;

            if ($this->create_user) {
                $user = User::create([
                    'tenant_id' => Auth::user()->tenant_id,
                    'name'      => $this->name,
                    'email'     => $this->email,
                    'password'  => Hash::make($this->password),
                    'is_active' => true,
                ]);

                $selected = $this->availableRoles->firstWhere('value', $this->selectedRole);
                if ($selected) {
                    if ($selected['type'] === 'global') {
                        $user->assignRole($selected['value']);
                    } else {
                        $user->syncPermissions($selected['permissions']);
                    }
                }

                $userId = $user->id;
            }

            Employee::create([
                'tenant_id' => Auth::user()->tenant_id,
                'user_id'   => $userId,
                'name'      => $this->name,
                'role'      => $this->employeeRole,
                'phone'     => $this->phone,
                'is_active' => $this->is_active,
            ]);
        });

        session()->flash('message', 'Employee created successfully.');
        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-3xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Flash Message (shown on redirect) --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold">Add Employee</h1>
        <a href="{{ route('tenant.employees.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Back to Employees
        </a>
    </div>

    <form wire:submit="save" class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6 space-y-5">
        {{-- Basic Info --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Full Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Job Title / Role</label>
                <input type="text" wire:model="employeeRole" placeholder="e.g. Receptionist, Housekeeping" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Display only; does not affect permissions.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Phone</label>
                <input type="text" wire:model="phone" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        
        <div class="flex items-center gap-3">
            <input type="checkbox" wire:model="is_active" class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
            <label class="text-sm text-gray-700 dark:text-slate-300">Active</label>
        </div>
        
        {{-- User Account Toggle --}}
        <div class="border-t border-gray-200 dark:border-slate-700/50 pt-5">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" wire:model.live="create_user" class="rounded border-gray-300 dark:border-slate-700 text-blue-600 focus:ring-blue-500">
                <span class="font-medium text-gray-900 dark:text-white">Create user account for this employee</span>
            </label>
            
            @if($create_user)
                <div class="grid grid-cols-1 gap-4 mt-5 pl-8 border-l-2 border-blue-500 dark:border-blue-400">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Email *</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                        @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Password *</label>
                            <input type="password" wire:model="password" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                            @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Confirm Password *</label>
                            <input type="password" wire:model="password_confirmation" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Assign System Role *</label>
                        <select wire:model="selectedRole" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Select Role --</option>
                            @foreach($this->availableRoles as $role)
                                <option value="{{ $role['value'] }}">{{ $role['label'] }}</option>
                            @endforeach
                        </select>
                        @error('selectedRole') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        <p class="text-xs text-gray-400 dark:text-slate-500 mt-1">Determines what the employee can access in the system.</p>
                    </div>
                </div>
            @endif
        </div>
        
        {{-- Form Actions --}}
        <div class="flex items-center gap-3 pt-4 border-t border-gray-200 dark:border-slate-700/50">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                <span class="in-data-loading:hidden">Save Employee</span>
                <span class="not-in-data-loading:hidden flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.employees.index') }}" wire:navigate class="border border-gray-300 dark:border-slate-600 text-gray-700 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-800 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>