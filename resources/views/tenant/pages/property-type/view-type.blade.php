<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\PropertyType;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('tenant.layouts.app')]
#[Title('Property Types')]
class extends Component {
    use WithPagination;

    public string $search = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function delete(int $id)
    {
        $type = PropertyType::findOrFail($id);
        
        if ($type->tenant_id !== Auth::user()->tenant_id) {
            session()->flash('error', 'You cannot delete global property types.');
            return;
        }

        $typeName = $type->name;
        $type->delete();
        session()->flash('success', "Property type '{$typeName}' deleted.");
    }

    #[Computed]
    public function types()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->withCount('properties')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function stats()
    {
        $tenantId = Auth::user()->tenant_id;
        return [
            'total' => PropertyType::availableForTenant($tenantId)->count(),
            'global' => PropertyType::whereNull('tenant_id')->count(),
            'custom' => PropertyType::where('tenant_id', $tenantId)->count(),
        ];
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-6xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">Property Types</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Manage your custom property categories. Global types are available for use but cannot be edited.</p>
        </div>
        <a href="{{ route('tenant.property-types.create') }}" wire:navigate class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Custom Type
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

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Available Types</p>
            <p class="text-2xl font-bold">{{ $this->stats['total'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Global Standards</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['global'] }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-[#0b0f19] border border-gray-200 dark:border-slate-700/50 p-5 shadow-sm">
            <p class="text-sm text-gray-500 dark:text-slate-400">Your Custom Types</p>
            <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $this->stats['custom'] }}</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative max-w-md">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." 
               class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 rounded-lg focus:ring-blue-500 focus:border-blue-500 shadow-sm">
        <svg class="absolute left-3 top-2.5 w-5 h-5 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="bg-gray-50 dark:bg-slate-800/50 border-b border-gray-200 dark:border-slate-700/50">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Type Name</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Scope</th>
                        <th class="px-4 sm:px-6 py-4 text-center text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Properties</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-slate-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-slate-700/30 text-gray-700 dark:text-slate-300">
                    @forelse($this->types as $type)
                        <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $type->name }}</span>
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                @if(is_null($type->tenant_id))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/20 text-amber-800 dark:text-amber-400">
                                        Global
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-500/20 text-blue-800 dark:text-blue-400">
                                        Custom
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-center">
                                <span class="text-sm text-gray-600 dark:text-slate-400">{{ $type->properties_count }}</span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(!is_null($type->tenant_id))
                                        <a href="{{ route('tenant.property-types.edit', $type->id) }}" wire:navigate class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <button wire:click="delete({{ $type->id }})" 
                                                wire:confirm="Delete this custom type? It will no longer be available for new properties." 
                                                class="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-slate-500 italic">Read-only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-slate-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-slate-700 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span class="text-sm">No property types found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->types->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>