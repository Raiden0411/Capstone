{{-- resources/views/tenant/pages/property-type/⚡view-type.blade.php --}}
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

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Property Types</h1>
        </div>
        <a href="{{ route('tenant.property-types.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Custom Type
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 border-l-4 border-l-red-500 p-4 rounded-md text-sm text-red-700 dark:text-red-300 font-medium flex items-center gap-3">
            <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 10.293a1 1 0 011.414 0L12 10.586l.293-.293a1 1 0 111.414 1.414L13.414 12l.293.293a1 1 0 01-1.414 1.414L12 13.414l-.293.293a1 1 0 01-1.414-1.414L10.586 12l-.293-.293a1 1 0 010-1.414z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="card p-5">
            <p class="text-sm text-gray-500 dark:text-gray-400">Available Types</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $this->stats['total'] }}</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-amber-600 dark:text-amber-400">Global Standards</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $this->stats['global'] }}</p>
        </div>
        <div class="card p-5">
            <p class="text-sm text-primary-600 dark:text-primary-400">Your Custom Types</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $this->stats['custom'] }}</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative max-w-md">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." 
               class="input pl-10">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Table --}}
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Type Name</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Scope</th>
                        <th class="px-4 sm:px-6 py-4 text-center text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Properties</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-700 dark:text-gray-200">
                    @forelse($this->types as $type)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <span class="font-medium text-gray-900 dark:text-white">{{ $type->name }}</span>
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                @if(is_null($type->tenant_id))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                                        Global
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border border-primary-200 dark:border-primary-500/30">
                                        Custom
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-center">
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $type->properties_count }}</span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(!is_null($type->tenant_id))
                                        <a href="{{ route('tenant.property-types.edit', $type->id) }}" wire:navigate
                                           class="p-1.5 text-primary-600 dark:text-primary-400 hover:text-white hover:bg-primary-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                        <button wire:click="delete({{ $type->id }})" 
                                                wire:confirm="Delete this custom type? It will no longer be available for new properties." 
                                                class="p-1.5 text-red-600 dark:text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">Read-only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="text-sm">No property types found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->types->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>