<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Service;

new 
#[Layout('tenant.layouts.app')]
#[Title('Services')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    public function toggleActive(int $id)
    {
        $service = Service::findOrFail($id);
        $service->update(['is_active' => !$service->is_active]);
    }

    public function delete(int $id)
    {
        Service::findOrFail($id)->delete();
        session()->flash('message', 'Service deleted.');
    }

    #[Computed]
    public function services()
    {
        return Service::query()
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy('name')
            ->paginate(10);
    }
};
?>

<div class="p-6 sm:p-10 max-w-7xl mx-auto">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Services</h1>
            <p class="text-slate-500">Manage additional services offered to guests.</p>
        </div>
        <a href="{{ route('tenant.services.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Service
        </a>
    </div>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">{{ session('message') }}</div>
    @endif

    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search services..." 
               class="w-full md:w-1/3 rounded-lg border-slate-300">
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Name</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Price</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Status</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($this->services as $service)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 font-medium">{{ $service->name }}</td>
                        <td class="px-6 py-4">₱{{ number_format($service->price, 2) }}</td>
                        <td class="px-6 py-4">
                            <button wire:click="toggleActive({{ $service->id }})" class="text-xs px-2 py-1 rounded-full {{ $service->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $service->is_active ? 'Active' : 'Inactive' }}
                            </button>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('tenant.services.edit', $service->id) }}" wire:navigate class="text-blue-600 hover:underline mr-3">Edit</a>
                            <button wire:click="delete({{ $service->id }})" wire:confirm="Delete this service?" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No services found.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($this->services->hasPages())
            <div class="px-6 py-4 border-t">{{ $this->services->links() }}</div>
        @endif
    </div>
</div>