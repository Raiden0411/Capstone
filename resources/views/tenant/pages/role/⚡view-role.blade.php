<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Custom Roles')]
class extends Component {
    public $customRoles = [];

    public function mount()
    {
        $this->loadRoles();
    }

    public function loadRoles()
    {
        $setting = TenantSetting::where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'custom_roles')
            ->first();
        $this->customRoles = $setting ? $setting->value : [];
    }

    public function deleteRole($index)
    {
        if (isset($this->customRoles[$index])) {
            unset($this->customRoles[$index]);
            $this->customRoles = array_values($this->customRoles);
            $this->saveRoles();
            session()->flash('message', 'Role deleted successfully.');
        }
    }

    protected function saveRoles()
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'custom_roles'],
            ['value' => $this->customRoles]
        );
    }
}
?>

<div class="p-6 sm:p-10 max-w-5xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Custom Roles</h1>
            <p class="text-slate-500">Create and manage roles specific to your business.</p>
        </div>
        <a href="{{ route('tenant.roles.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create Role
        </a>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Role Name</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Permissions</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($customRoles as $index => $role)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium">{{ $role['name'] }}</td>
                        <td class="px-6 py-4">
                            <div class="flex flex-wrap gap-1">
                                @foreach($role['permissions'] as $perm)
                                    <span class="text-xs bg-slate-100 px-2 py-1 rounded">{{ $perm }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('tenant.roles.edit', $index) }}" wire:navigate class="text-blue-600 hover:underline mr-3">Edit</a>
                            <button wire:click="deleteRole({{ $index }})" wire:confirm="Delete this role?" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-6 py-8 text-center text-slate-500">No custom roles yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>