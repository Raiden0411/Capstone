{{-- resources/views/tenant/pages/employee/⚡edit-employee.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Employee;
use App\Models\User;
use App\Models\TenantSetting;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Employee')]
class extends Component {
    use WithFileUploads;

    public Employee $employee;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|max:50')]
    public $employeeRole = '';

    public $phone = '';

    #[Validate('boolean')]
    public $is_active = true;

    #[Validate('nullable|string|max:20')]
    public $code = '';

    #[Validate('nullable|image|max:2048')]
    public $avatar;

    public array $selectedRoles = [];

    public string $roleSearch = '';
    public string $roleTypeFilter = 'all';

    public bool $showNewRoleForm = false;
    public string $newRoleName = '';
    public array $newRolePermissions = [];

    public function mount($employee)
    {
        if (!$employee instanceof Employee) {
            $employee = Employee::findOrFail($employee);
        }

        if ($employee->tenant_id !== Auth::user()->tenant_id) {
            abort(403, 'Unauthorized.');
        }

        $this->employee = $employee;
        $this->name = $employee->name;
        $this->employeeRole = $employee->role ?? '';
        $this->phone = $employee->phone ?? '';
        $this->is_active = (bool) $employee->is_active;
        $this->code = $employee->code ?? '';
        $this->avatar = null;

        // Initialize selected roles from linked user
        if ($employee->user_id) {
            $user = $employee->user;
            $this->selectedRoles = $user->getRoleNames()->toArray();

            // Also select custom roles where user has all required permissions
            $setting = TenantSetting::where('tenant_id', '=', Auth::user()->tenant_id, 'and')
                ->where('key', '=', 'custom_roles', 'and')
                ->first();
            $customRoles = $setting ? $setting->value : [];

            foreach ($customRoles as $index => $customRole) {
                $perms = $customRole['permissions'] ?? [];
                if (!empty($perms) && $user->hasAllPermissions($perms)) {
                    $this->selectedRoles[] = 'custom_' . $index;
                }
            }

            $this->selectedRoles = array_values(array_unique($this->selectedRoles));
        }
    }

    public function updated($field)
    {
        $trims = ['name', 'employeeRole', 'phone', 'code', 'roleSearch', 'newRoleName'];
        if (in_array($field, $trims)) {
            $this->$field = trim($this->$field);
        }
    }

    public function getAvailableRolesProperty()
    {
        $roles = collect();

        $globalRoles = Role::whereNotIn('name', ['super-admin', 'admin'])
            ->orderBy('name')
            ->get()
            ->map(fn($role) => [
                'type'        => 'global',
                'value'       => $role->name,
                'label'       => ucfirst($role->name),
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ]);

        $roles = $roles->concat($globalRoles);

        $setting = TenantSetting::where('tenant_id', '=', Auth::user()->tenant_id, 'and')
            ->where('key', '=', 'custom_roles', 'and')
            ->first();
        $customRoles = $setting ? $setting->value : [];

        foreach ($customRoles as $index => $customRole) {
            $roles->push([
                'type'        => 'custom',
                'value'       => 'custom_' . $index,
                'label'       => $customRole['name'],
                'permissions' => $customRole['permissions'] ?? [],
            ]);
        }

        return $roles
            ->when($this->roleSearch !== '', function ($collection) {
                return $collection->filter(fn($role) => stripos($role['label'], $this->roleSearch) !== false);
            })
            ->when($this->roleTypeFilter !== 'all', function ($collection) {
                return $collection->where('type', $this->roleTypeFilter);
            })
            ->values();
    }

    public function getAllPermissionsProperty()
    {
        return Permission::orderBy('name')->get();
    }

    public function toggleNewRoleForm()
    {
        $this->showNewRoleForm = !$this->showNewRoleForm;
        $this->newRoleName = '';
        $this->newRolePermissions = [];
    }

    public function createQuickRole()
    {
        $this->validate([
            'newRoleName' => 'required|string|max:50',
            'newRolePermissions' => 'array',
        ]);

        $setting = TenantSetting::where('tenant_id', '=', Auth::user()->tenant_id, 'and')
            ->where('key', '=', 'custom_roles', 'and')
            ->first();

        $customRoles = $setting ? $setting->value : [];
        $customRoles[] = [
            'name' => $this->newRoleName,
            'permissions' => $this->newRolePermissions,
        ];

        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'custom_roles'],
            ['value' => $customRoles]
        );

        $this->showNewRoleForm = false;
        $this->newRoleName = '';
        $this->newRolePermissions = [];

        $newIndex = count($customRoles) - 1;
        $this->selectedRoles[] = 'custom_' . $newIndex;

        session()->flash('message', 'Custom role created and selected.');
    }

    public function rules()
    {
        return [
            'name'         => 'required|string|max:255',
            'employeeRole' => 'required|string|max:50',
            'phone'        => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'is_active'    => 'boolean',
            'code'         => 'nullable|string|max:20|unique:employees,code,' . $this->employee->id,
            'avatar'       => 'nullable|image|max:2048',
            'selectedRoles' => 'array',
        ];
    }

    public function update()
    {
        $this->validate();

        DB::transaction(function () {
            // Avatar upload
            $avatarPath = $this->employee->avatar;
            if ($this->avatar) {
                $avatarPath = $this->avatar->store('employee-avatars', 'public');
            }

            $this->employee->update([
                'code'      => $this->code ?: ('EMP-' . strtoupper(Str::random(6))),
                'name'      => $this->name,
                'role'      => $this->employeeRole,
                'phone'     => $this->phone,
                'avatar'    => $avatarPath,
                'is_active' => $this->is_active,
            ]);

            // Update linked user details and roles/permissions
            if ($this->employee->user_id) {
                $user = User::find($this->employee->user_id);
                if ($user) {
                    $user->update([
                        'name'  => $this->name,
                        'phone' => $this->phone,
                    ]);

                    $rolesToAssign = [];
                    $permissionsToAssign = [];

                    foreach ($this->selectedRoles as $roleValue) {
                        $selected = $this->availableRoles->firstWhere('value', $roleValue);
                        if (!$selected) continue;

                        if ($selected['type'] === 'global') {
                            $rolesToAssign[] = $selected['value'];
                        } else {
                            $permissionsToAssign = array_merge($permissionsToAssign, $selected['permissions']);
                        }
                    }

                    $user->syncRoles($rolesToAssign);
                    $user->syncPermissions(array_unique($permissionsToAssign));
                }
            }
        });

        session()->flash('message', 'Employee updated successfully.');
        return $this->redirectRoute('tenant.employees.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6">

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            ✔ {{ session('message') }}
        </div>
    @endif

    <div class="flex items-center justify-between pb-6 border-b border-gray-200 dark:border-gray-700">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit Employee</h1>
        <a href="{{ route('tenant.employees.index') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-[#376df1] dark:hover:text-blue-400 transition-colors">
            &larr; Back to Employees
        </a>
    </div>

    <form wire:submit="update" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

        {{-- Employee Code --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Employee Code</label>
            <input type="text" wire:model="code"
                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
            @error('code') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Avatar --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Picture</label>
            @if($employee->avatar)
                <img src="{{ asset('storage/'. $employee->avatar) }}" class="h-20 w-20 object-cover rounded-lg border border-gray-200 dark:border-gray-700 mb-2">
            @endif
            <input type="file" wire:model="avatar" accept="image/*"
                   class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 dark:file:bg-blue-500/20 file:text-blue-700 dark:file:text-blue-300 hover:file:bg-blue-100 dark:hover:file:bg-blue-500/30 transition">
            @error('avatar') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            @if($avatar)
                <img src="{{ $avatar->temporaryUrl() }}" class="mt-2 h-20 w-20 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
            @endif
        </div>

        {{-- Name & Job Title --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                <input type="text" wire:model="name"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Job Title / Role *</label>
                <input type="text" wire:model="employeeRole" placeholder="e.g. Receptionist, Guide"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                @error('employeeRole') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Phone & Active --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                <input type="text" wire:model="phone" placeholder="09123456789"
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 focus:border-[#376df1] transition">
                @error('phone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div class="flex items-center pt-6">
                <input type="checkbox" wire:model="is_active"
                       class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                <label class="ml-2 text-sm text-gray-700 dark:text-gray-300">Active</label>
            </div>
        </div>

        {{-- Linked User / Roles & Permissions --}}
        <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">User Account & Roles</h2>

            @if($employee->user_id)
                <div class="mb-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Linked User</p>
                    <p class="text-gray-900 dark:text-white font-medium">{{ $employee->user->name }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $employee->user->email }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">You cannot change the linked user from this page.</p>
                </div>

                {{-- Quick role create --}}
                <div class="flex items-center justify-between mb-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Assign System Roles *</label>
                    <button type="button" wire:click="toggleNewRoleForm"
                            class="text-xs text-[#376df1] dark:text-blue-400 hover:underline">+ New Role</button>
                </div>

                @if($showNewRoleForm)
                    <div class="mb-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Role Name</label>
                            <input type="text" wire:model="newRoleName"
                                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                            @error('newRoleName') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Permissions</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-1 max-h-40 overflow-y-auto">
                                @foreach($this->allPermissions as $perm)
                                    <label class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <input type="checkbox" wire:model.live="newRolePermissions" value="{{ $perm->name }}"
                                               class="rounded border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-[#376df1] focus:ring-[#376df1]">
                                        {{ $perm->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" wire:click="createQuickRole"
                                    class="px-4 py-2 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold transition">
                                Create Role
                            </button>
                            <button type="button" wire:click="toggleNewRoleForm"
                                    class="px-4 py-2 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition text-sm">
                                Cancel
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Search / filter --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                    <input type="text" wire:model.live="roleSearch" placeholder="Search roles…"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition">
                    <select wire:model.live="roleTypeFilter"
                            class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#376df1]/50 transition appearance-none">
                        <option value="all">All Roles</option>
                        <option value="global">Global Only</option>
                        <option value="custom">Custom Only</option>
                    </select>
                </div>

                {{-- Role cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-72 overflow-y-auto">
                    @foreach($this->availableRoles as $role)
                        <label class="cursor-pointer">
                            <input type="checkbox" wire:model.live="selectedRoles" value="{{ $role['value'] }}" class="sr-only peer">
                            <div class="border-2 border-gray-200 dark:border-gray-700 rounded-xl p-3 transition cursor-pointer
                                        {{ in_array($role['value'], $selectedRoles) ? 'border-[#376df1] bg-blue-50 dark:bg-blue-500/10' : 'hover:border-gray-300 dark:hover:border-gray-600' }}">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $role['label'] }}</p>
                                <div class="flex flex-wrap gap-1 mt-2">
                                    @foreach($role['permissions'] as $perm)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 text-[10px] font-medium">
                                            {{ $perm }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </label>
                    @endforeach
                </div>
                @error('selectedRoles') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            @else
                <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 text-sm text-gray-600 dark:text-gray-400">
                    No user account linked. You can link a user from the Create Employee page.
                </div>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex flex-wrap items-center gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="bg-[#376df1] hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-blue-500/20 transition flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Update Employee</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('tenant.employees.index') }}" wire:navigate
               class="px-6 py-3 rounded-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 font-medium transition">
                Cancel
            </a>
        </div>
    </form>
</div>