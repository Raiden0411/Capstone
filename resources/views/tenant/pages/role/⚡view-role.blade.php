{{-- resources/views/tenant/pages/role/⚡view-role.blade.php --}}
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
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Custom Roles</h1>
        </div>
        <a href="{{ route('tenant.roles.create') }}" wire:navigate
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-[#376df1] hover:bg-blue-700 text-white text-sm font-semibold shadow-lg shadow-blue-500/20 transition hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Create Role
        </a>
    </div>

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Roles Table --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Role Name</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Permissions</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($customRoles as $index => $role)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $role['name'] }}</td>
                            <td class="px-4 sm:px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @foreach($role['permissions'] as $perm)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-600">{{ $perm }}</span>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('tenant.roles.edit', $index) }}" wire:navigate
                                       class="text-[#376df1] dark:text-blue-400 hover:text-blue-700 font-medium text-sm transition">Edit</a>
                                    <button wire:click="deleteRole({{ $index }})" wire:confirm="Delete this role?"
                                            class="text-red-600 dark:text-red-400 hover:text-red-700 font-medium text-sm transition">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span class="text-sm">No custom roles yet.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>