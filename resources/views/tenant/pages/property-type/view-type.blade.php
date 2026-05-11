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

<div class="p-4 sm:p-6 lg:p-10 max-w-6xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Property Types</h1>
            <p class="text-white/60 mt-1">Manage your custom property categories. Global types are available for use but cannot be edited.</p>
        </div>
        <a href="{{ route('tenant.property-types.create') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Add Custom Type
        </a>
    </div>

    {{-- Flash Messages --}}
    @if (session()->has('success'))
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="glass-card border-l-4 border-l-red-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.293 10.293a1 1 0 011.414 0L12 10.586l.293-.293a1 1 0 111.414 1.414L13.414 12l.293.293a1 1 0 01-1.414 1.414L12 13.414l-.293.293a1 1 0 01-1.414-1.414L10.586 12l-.293-.293a1 1 0 010-1.414z"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-white/50">Available Types</p>
            <p class="text-2xl font-bold text-white">{{ $this->stats['total'] }}</p>
        </div>
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-amber-400">Global Standards</p>
            <p class="text-2xl font-bold text-amber-400">{{ $this->stats['global'] }}</p>
        </div>
        <div class="glass-card !rounded-xl p-5">
            <p class="text-sm text-brand-400">Your Custom Types</p>
            <p class="text-2xl font-bold text-brand-400">{{ $this->stats['custom'] }}</p>
        </div>
    </div>

    {{-- Search --}}
    <div class="relative max-w-md">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search types..." 
               class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
    </div>

    {{-- Table --}}
    <div class="glass-card !rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-white/10">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase">Type Name</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold text-white/50 uppercase">Scope</th>
                        <th class="px-4 sm:px-6 py-4 text-center text-xs font-semibold text-white/50 uppercase">Properties</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold text-white/50 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5 text-white/80">
                    @forelse($this->types as $type)
                        <tr class="hover:bg-white/5 transition-colors">
                            <td class="px-4 sm:px-6 py-4">
                                <span class="font-medium text-white">{{ $type->name }}</span>
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                @if(is_null($type->tenant_id))
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-500/20 text-amber-300">
                                        Global
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-brand-500/20 text-brand-300">
                                        Custom
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-center">
                                <span class="text-sm text-white/60">{{ $type->properties_count }}</span>
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(!is_null($type->tenant_id))
                                        <a href="{{ route('tenant.property-types.edit', $type->id) }}" wire:navigate class="p-1.5 text-brand-400 hover:text-white hover:bg-brand-500/20 rounded-lg transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                        </a>
                                        <button wire:click="delete({{ $type->id }})" 
                                                wire:confirm="Delete this custom type? It will no longer be available for new properties." 
                                                class="p-1.5 text-red-400 hover:text-white hover:bg-red-500/20 rounded-lg transition" title="Delete">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    @else
                                        <span class="text-xs text-white/30 italic">Read-only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-white/40">
                                <svg class="mx-auto h-12 w-12 text-white/10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span class="text-sm">No property types found{{ $search ? ' matching "' . $search . '"' : '' }}.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($this->types->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-white/10">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>