<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\TypeOfTenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Manage Tenant Types')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch() { $this->resetPage(); }

    public function delete(int $id)
    {
        $type = TypeOfTenant::withCount('tenants')->findOrFail($id);
        if ($type->tenants_count > 0) {
            session()->flash('error', "Cannot delete '{$type->type}' because it is used by {$type->tenants_count} tenant(s).");
            return;
        }
        $type->delete();
        session()->flash('success', "Tenant type '{$type->type}' deleted.");
    }

    #[Computed]
    public function types()
    {
        return TypeOfTenant::withCount('tenants')
            ->when($this->search, fn($q) => $q->where('type', 'like', '%' . $this->search . '%'))
            ->orderBy('type')
            ->paginate(10);
    }
};
?>

<div class="p-6 sm:p-10 max-w-5xl mx-auto">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-slate-800">Tenant Types</h1>
            <p class="text-slate-500 mt-1">Manage the categories used to classify businesses.</p>
        </div>
        <a href="{{ route('superadmin.tenant-types.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-lg shadow-sm transition flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Type
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">{{ session('error') }}</div>
    @endif

    {{-- Search --}}
    <div class="mb-4">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." class="w-full md:w-1/3 rounded-lg border-slate-300">
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Type</th>
                    <th class="px-6 py-4 text-xs font-semibold text-slate-500 uppercase">Description</th>
                    <th class="px-6 py-4 text-center text-xs font-semibold text-slate-500 uppercase">Tenants</th>
                    <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($this->types as $type)
                    <tr>
                        <td class="px-6 py-4 font-medium">{{ $type->type }}</td>
                        <td class="px-6 py-4 text-slate-600">{{ $type->description ?? '—' }}</td>
                        <td class="px-6 py-4 text-center">{{ $type->tenants_count }}</td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('superadmin.tenant-types.edit', $type->id) }}" class="text-blue-600 hover:underline mr-3">Edit</a>
                            <button wire:click="delete({{ $type->id }})" wire:confirm="Delete this tenant type?" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-6 py-8 text-center text-slate-500">No tenant types found.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t">{{ $this->types->links() }}</div>
    </div>
</div>