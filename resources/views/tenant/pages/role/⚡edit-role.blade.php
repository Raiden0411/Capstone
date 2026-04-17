<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\TenantSetting;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Custom Role')]
class extends Component {
    public $index;
    
    #[Validate('required|string|max:255')]
    public $29 = '';
    
    public $selectedPermissions = [];
    public $customRoles = [];

    public function mount($index)
    {
        $this->index = (int) $index;
        $setting = TenantSetting::where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();
        $this->customRoles = $setting ? $setting->value : [];
        
        if (!isset($this->customRoles[$this->index])) {
            abort(404);
        }
        
        $role = $this->customRoles[$this->index];
        $this->name = $role['name'];
        $this->selectedPermissions = $role['permissions'];
    }

    public function getAvailablePermissionsProperty()
    {
        return Permission::where('name', 'not like', '%delete%')
            ->where('name', 'not like', '%user%')
            ->orderBy('name')
            ->get();
    }

    public function update()
    {
        $this->validate();
        
        foreach ($this->customRoles as $i => $role) {
            if ($i != $this->index && strtolower($role['name']) === strtolower($this->name)) {
                $this->addError('name', 'A role with this name already exists.');
                return;
            }
        }

        $this->customRoles[$this->index] = [
            'name' => $this->name,
            'permissions' => $this->selectedPermissions,
        ];

        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'custom_roles'],
            ['value' => $this->customRoles]
        );

        session()->flash('message', 'Custom role updated successfully.');
        return $this->redirectRoute('tenant.roles.index', navigate: true);
    }
}
?>

<div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Custom Role</h1>
    <form wire:submit="update" class="bg-white p-6 rounded-xl shadow space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Role Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300">
            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium mb-2">Assign Permissions</label>
            <div class="grid grid-cols-2 gap-2 max-h-80 overflow-y-auto border rounded-lg p-3">
                @foreach($this->availablePermissions as $permission)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="selectedPermissions" value="{{ $permission->name }}" class="rounded border-slate-300">
                        {{ ucwords(str_replace(['-', '_'], ' ', $permission->name)) }}
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Update Role</button>
            <a href="{{ route('tenant.roles.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>