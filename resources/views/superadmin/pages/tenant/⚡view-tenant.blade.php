<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Manage Tenants')]
class extends Component {
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }

    #[Computed]
    public function tenants()
    {
        return Tenant::with('typeOfTenant')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== 'all', function ($query) {
                $query->where('is_active', $this->statusFilter === 'active');
            })
            ->latest()
            ->paginate(10);
    }

    public function deleteTenant($id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();
        session()->flash('message', 'Business Location successfully deleted.');
    }

    public function approve(int $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => true]);

        $user = $tenant->users()->first();
        if ($user) {
            $user->update(['is_active' => true]);
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }

        session()->flash('message', "{$tenant->name} has been approved and is now active.");
    }
};
?>

@push('styles')
<style>
    /* Fix invisible options in glass-style selects */
    select option {
        background: #1e293b;
        color: #e2e8f0;
    }
</style>
@endpush

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                Super Admin · Tenants
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Manage Businesses</h1>
        </div>
        <a href="{{ route('superadmin.tenants.create') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-105">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Register Business
        </a>
    </div>

    {{-- Flash Message --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Panel --}}
    <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">

        {{-- Filters --}}
        <div class="flex flex-wrap gap-4 p-4 border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 items-center">
            <div class="relative flex-1 min-w-[200px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search name or email…"
                       class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
            </div>
            <select wire:model.live="statusFilter"
                    class="bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-700 dark:text-white/80 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                <option value="all">All status</option>
                <option value="active">Active</option>
                <option value="inactive">Pending approval</option>
            </select>
            <div wire:loading wire:target="search,statusFilter" class="text-xs font-mono text-brand-600 dark:text-brand-400">Filtering…</div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead class="border-b border-gray-200 dark:border-white/10">
                    <tr>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-white/40">Business</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-white/40 hidden sm:table-cell">Contact</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-white/40 hidden lg:table-cell">Location</th>
                        <th class="px-4 sm:px-6 py-4 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-white/40">Status</th>
                        <th class="px-4 sm:px-6 py-4 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-white/40">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5 text-gray-700 dark:text-white/80">
                    @forelse($this->tenants as $tenant)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition-colors" wire:key="row-{{ $tenant->id }}">
                            <td class="px-4 sm:px-6 py-4">
                                <div class="font-medium text-gray-900 dark:text-white text-sm">{{ $tenant->name }}</div>
                                <div class="flex items-center gap-2 mt-1 flex-wrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-brand-100 dark:bg-brand-500/20 text-brand-700 dark:text-brand-300 border border-brand-200 dark:border-brand-400/20">
                                        {{ $tenant->typeOfTenant->type ?? 'Uncategorized' }}
                                    </span>
                                    <span class="text-xs font-mono text-gray-400 dark:text-white/30">#{{ $tenant->id }}</span>
                                </div>
                                <div class="sm:hidden mt-1 space-y-0.5">
                                    <div class="text-xs text-gray-600 dark:text-white/60">{{ $tenant->email }}</div>
                                    <div class="text-xs text-gray-400 dark:text-white/40">{{ $tenant->contact_number ?? '—' }}</div>
                                </div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden sm:table-cell">
                                <div class="text-sm text-gray-600 dark:text-white/70">{{ $tenant->email }}</div>
                                <div class="text-xs text-gray-400 dark:text-white/40 mt-0.5">{{ $tenant->contact_number ?? '—' }}</div>
                            </td>
                            <td class="px-4 sm:px-6 py-4 hidden lg:table-cell">
                                <span class="text-sm text-gray-600 dark:text-white/60 max-w-[200px] truncate block" title="{{ $tenant->address }}">
                                    {{ $tenant->address }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-4">
                                @if($tenant->is_active)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 shadow-[0_0_6px_var(--color-green-500)]"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-mono font-medium bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Pending
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 sm:px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if(!$tenant->is_active)
                                        <button wire:click="approve({{ $tenant->id }})"
                                                wire:confirm="Approve this business and activate its owner account?"
                                                class="text-xs font-mono bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 hover:bg-green-200 dark:hover:bg-green-500/30 px-3 py-1 rounded-md transition">
                                            Approve
                                        </button>
                                    @endif
                                    <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate class="text-xs font-mono text-brand-600 dark:text-brand-400 border border-brand-200 dark:border-brand-400/20 hover:bg-brand-50 dark:hover:bg-brand-500/10 px-3 py-1 rounded-md transition">
                                        Edit
                                    </a>
                                    <button wire:click="deleteTenant({{ $tenant->id }})"
                                            wire:confirm="Delete this business? This will also remove all their properties and bookings."
                                            class="text-xs font-mono text-gray-500 dark:text-white/40 border border-gray-200 dark:border-white/10 hover:border-red-300 dark:hover:border-red-500/30 hover:text-red-600 dark:hover:text-red-400 px-3 py-1 rounded-md transition">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <div class="text-center py-12">
                                    <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-white/10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    <p class="font-display text-base text-gray-500 dark:text-white/50 mb-1">No businesses found</p>
                                    <p class="text-xs font-mono text-gray-400 dark:text-white/40">Try adjusting the search or register a new tenant.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($this->tenants->hasPages())
            <div class="px-4 sm:px-6 py-4 border-t border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                {{ $this->tenants->links() }}
            </div>
        @endif

    </div>
</div>