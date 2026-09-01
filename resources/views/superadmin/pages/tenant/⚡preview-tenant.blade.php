{{-- resources/views/superadmin/pages/tenant/⚡preview-tenant.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;

new
#[Layout('superadmin.layouts.app')]
#[Title('Preview Tenant')]
class extends Component
{
    public Tenant $tenant;

    public function mount(Tenant $tenant)
    {
        $this->tenant = $tenant;
    }

    public function approve()
    {
        $this->tenant->update(['is_active' => true]);
        if ($user = $this->tenant->users()->first()) {
            $user->update(['is_active' => true]);
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }
        session()->flash('message', 'Tenant approved and activated.');
        return redirect()->route('superadmin.tenants.index');
    }

    public function reject()
    {
        $this->tenant->delete();
        session()->flash('message', 'Tenant application rejected and removed.');
        return redirect()->route('superadmin.tenants.index');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $tenant->name }}</h1>
        <div class="flex gap-2">
            <button type="button" wire:click="approve" class="btn-primary">Approve</button>
            <button type="button" wire:click="reject" wire:confirm="Reject and delete this application?" class="btn-danger">Reject</button>
        </div>
    </div>

    <div class="card p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <p class="text-sm text-gray-500">Email</p>
            <p class="font-medium text-gray-900 dark:text-white">{{ $tenant->email }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Contact</p>
            <p class="font-medium text-gray-900 dark:text-white">{{ $tenant->contact_number ?? '—' }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Address</p>
            <p class="font-medium text-gray-900 dark:text-white">{{ $tenant->address ?? '—' }}</p>
        </div>
        <div>
            <p class="text-sm text-gray-500">Type</p>
            <p class="font-medium text-gray-900 dark:text-white">{{ $tenant->typeOfTenant->type ?? '—' }}</p>
        </div>
    </div>
</div>