<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Employee;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

new 
#[Layout('tenant.layouts.app')]
#[Title('Create Employee')]
class extends Component {
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string|max:50')]
    public $employeeRole = ''; // e.g., receptionist, caretaker (free text for display)
    
    #[Validate('nullable|string|max:20')]
    public $phone = '';
    
    #[Validate('boolean')]
    public $is_active = true;
    
    // Optional user account
    public $create_user = false;
    public $email = '';
    public $password = '';
    public $selectedRole = ''; // Spatie role name

    public function getAvailableRolesProperty()
    {
        // 排除 super-admin 和 admin，租户不应分配这些高权限角色
        return Role::whereNotIn('name', ['super-admin', 'admin'])
            ->orderBy('name')
            ->get();
    }

    public function save()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'employeeRole' => 'nullable|string|max:50',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
        ];

        if ($this->create_user) {
            $rules['email'] = 'required|email|unique:users,email';
            $rules['password'] = 'required|min:8';
            $rules['selectedRole'] = 'required|exists:roles,name';
        }

        $this->validate($rules);
        
        $userId = null;
        if ($this->create_user) {
            $user = User::create([
                'tenant_id' => Auth::user()->tenant_id,
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
                'is_active' => true,
            ]);
            $user->assignRole($this->selectedRole);
            $userId = $user->id;
        }
        
        Employee::create([
            'tenant_id' => Auth::user()->tenant_id,
            'user_id' => $userId,
            'name' => $this->name,
            'role' => $this->employeeRole,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
        ]);
        
        session()->flash('message', 'Employee created successfully.');
        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Add Employee</h1>
    <form wire:submit="save" class="bg-white p-6 rounded-xl shadow space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Full Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300">
            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Job Title / Role</label>
                <input type="text" wire:model="employeeRole" placeholder="e.g. Receptionist, Housekeeping" class="w-full rounded-lg border-slate-300">
                <p class="text-xs text-slate-400 mt-1">Display only, does not affect permissions.</p>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Phone</label>
                <input type="text" wire:model="phone" class="w-full rounded-lg border-slate-300">
            </div>
        </div>
        <div class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
            <label class="text-sm">Active</label>
        </div>
        
        <div class="border-t pt-4">
            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model.live="create_user">
                <span class="font-medium">Create user account for this employee</span>
            </label>
            @if($create_user)
                <div class="grid grid-cols-1 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Email *</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300">
                        @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Password *</label>
                        <input type="password" wire:model="password" class="w-full rounded-lg border-slate-300">
                        @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Assign System Role *</label>
                        <select wire:model="selectedRole" class="w-full rounded-lg border-slate-300">
                            <option value="">-- Select Role --</option>
                            @foreach($this->availableRoles as $role)
                                <option value="{{ $role->name }}">{{ ucfirst($role->name) }}</option>
                            @endforeach
                        </select>
                        @error('selectedRole') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        <p class="text-xs text-slate-400 mt-1">This determines what the employee can access in the system.</p>
                    </div>
                </div>
            @endif
        </div>
        
        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Save Employee</button>
            <a href="{{ route('tenant.employees.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>