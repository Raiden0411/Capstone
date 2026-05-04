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

<div class="p-4 sm:p-6 lg:p-10 max-w-5xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Tenant Types</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Manage the categories used to classify businesses.</p>
        </div>
        <a href="{{ route('superadmin.tenant-types.create') }}" wire:navigate class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add New Type
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('success') }}</p>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="p-4 bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 rounded-md shadow-sm">
            <p class="text-sm text-red-700 dark:text-red-400 font-medium">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Search --}}
    <div class="relative w-full md:w-1/3">
        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
            <svg class="w-4 h-4 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
        </div>
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." 
               class="w-full pl-10 rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500 text-sm py-2.5">
    </div>

    {{-- Table Card --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50 text-gray-600 dark:text-slate-400 uppercase text-xs font-semibold">
                    <tr>
                        <th class="px-4 sm:px-6 py-4">Type</th>
                        <th class="px-4 sm:px-6 py-4 hidden sm:table-cell">Description</th>
                        <th class="px-4 sm:px-6 py-4 text-center">Tenants</th>
                        <th class="px-4 sm:px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->types as $type)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $type->type }}</td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell text-gray-600 dark:text-slate-400">{{ $type->description ?? '—' }}</td>
                            <td class="px-4 sm:px-6 py-4 text-center font-semibold">{{ $type->tenants_count }}</td>
                            <td class="px-4 sm:px-6 py-4 text-right whitespace-nowrap space-x-2">
                                <a href="{{ route('superadmin.tenant-types.edit', $type->id) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:underline font-medium">Edit</a>
                                <button wire:click="delete({{ $type->id }})" wire:confirm="Delete this tenant type?" class="text-red-600 dark:text-red-400 hover:underline font-medium">Delete</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-400 dark:text-slate-500">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-slate-400">No tenant types found.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($this->types->hasPages())
            <div class="p-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>