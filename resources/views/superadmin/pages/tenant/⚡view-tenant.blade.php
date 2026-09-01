{{-- resources/views/superadmin/pages/tenant/⚡view-tenant.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use Illuminate\Support\Facades\DB;

new
#[Layout('superadmin.layouts.app')]
#[Title('Tenants')]
class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = 'all';
    public ?int $typeFilter = null;
    public ?string $startDate = null;
    public ?string $endDate = null;
    public string $sortOption = 'latest';
    public int $perPage = 12;
    public array $selected = [];
    public bool $recommendedFilter = false;

    // Quick stats (reactive)
    public int $totalCount = 0;
    public int $activeCount = 0;
    public int $pendingCount = 0;
    public int $recommendedCount = 0;

    public function mount()
    {
        $this->refreshStats();
    }

    public function refreshStats()
    {
        // Optimized: Single query for all aggregate stats instead of 3 separate queries
        $stats = Tenant::select(
            DB::raw('COUNT(*) as total'),
            DB::raw('COALESCE(SUM(is_active), 0) as active_count'),
            DB::raw('COALESCE(SUM(is_recommended), 0) as recommended_count')
        )->first();

        $this->totalCount = $stats->total ?? 0;
        $this->activeCount = $stats->active_count ?? 0;
        $this->pendingCount = $this->totalCount - $this->activeCount;
        $this->recommendedCount = $stats->recommended_count ?? 0;
    }

    public function updatedSearch() { $this->resetPage(); }
    public function updatedStatusFilter() { $this->resetPage(); }
    public function updatedTypeFilter() { $this->resetPage(); }
    public function updatedStartDate() { $this->resetPage(); }
    public function updatedEndDate() { $this->resetPage(); }
    public function updatedSortOption() { $this->resetPage(); }
    public function updatedPerPage() { $this->resetPage(); }
    public function updatedRecommendedFilter() { $this->resetPage(); }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'typeFilter', 'startDate', 'endDate', 'sortOption', 'perPage', 'recommendedFilter']);
        $this->resetPage();
        $this->dispatch('toast', message: 'All filters cleared.', type: 'info');
    }

    #[Computed]
    public function tenantTypes()
    {
        // Return collection of TypeOfTenant models with only needed columns
        return TypeOfTenant::query()->select('id', 'type')->get();
    }

    public function getBaseQuery()
    {
        return Tenant::with([
                'typeOfTenant:id,type',
                'users:id,tenant_id,name,email,is_active' // only necessary columns
            ])
            ->withCount(['properties', 'bookings'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', "%{$this->search}%")
                      ->orWhere('email', 'like', "%{$this->search}%")
                      ->orWhere('contact_number', 'like', "%{$this->search}%")
                      ->orWhere('address', 'like', "%{$this->search}%")
                      ->orWhere('slug', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('is_active', $this->statusFilter === 'active'))
            ->when($this->typeFilter, fn($q) => $q->where('type_of_tenant_id', $this->typeFilter))
            ->when($this->recommendedFilter, fn($q) => $q->where('is_recommended', true))
            ->when($this->startDate && $this->endDate, function ($query) {
                $query->whereBetween('created_at', [
                    \Carbon\Carbon::parse($this->startDate)->startOfDay(),
                    \Carbon\Carbon::parse($this->endDate)->endOfDay(),
                ]);
            });
    }

    #[Computed]
    public function tenants()
    {
        return $this->getBaseQuery()
            ->when($this->sortOption === 'name_asc', fn($q) => $q->orderBy('name', 'asc'))
            ->when($this->sortOption === 'name_desc', fn($q) => $q->orderBy('name', 'desc'))
            ->when($this->sortOption === 'oldest', fn($q) => $q->orderBy('created_at', 'asc'))
            ->when($this->sortOption === 'latest', fn($q) => $q->orderBy('created_at', 'desc'))
            ->paginate($this->perPage);
    }

    #[Computed]
    public function hasInactiveSelected(): bool
    {
        return !empty($this->selected) && Tenant::whereIn('id', $this->selected)->where('is_active', false)->exists();
    }

    public function toggleRecommended(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_recommended' => !$tenant->is_recommended]);
        $this->refreshStats();
        $this->dispatch('toast', message: $tenant->is_recommended ? 'Marked as Recommended' : 'Removed from Recommended', type: 'success');
    }

    public function markRecommendedSelected(): void
    {
        if (empty($this->selected)) {
            $this->dispatch('toast', message: 'No businesses selected.', type: 'error');
            return;
        }

        Tenant::whereIn('id', $this->selected)->update(['is_recommended' => true]);
        $this->refreshStats();
        $this->selected = [];
        $this->dispatch('toast', message: 'Selected businesses marked as Recommended.', type: 'success');
    }

    public function unmarkRecommendedSelected(): void
    {
        if (empty($this->selected)) {
            $this->dispatch('toast', message: 'No businesses selected.', type: 'error');
            return;
        }

        Tenant::whereIn('id', $this->selected)->update(['is_recommended' => false]);
        $this->refreshStats();
        $this->selected = [];
        $this->dispatch('toast', message: 'Selected businesses removed from Recommended.', type: 'success');
    }

    public function approve(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => true]);

        if ($user = $tenant->users()->first()) {
            $user->update(['is_active' => true]);
            if (!$user->hasRole('admin')) {
                $user->assignRole('admin');
            }
        }
        $this->refreshStats();
        $this->dispatch('toast', message: "{$tenant->name} has been approved and is now active.", type: 'success');
    }

    public function deactivate(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => false]);
        $this->refreshStats();
        $this->dispatch('toast', message: "{$tenant->name} has been suspended.", type: 'info');
    }

    public function deleteTenant(int $id): void
    {
        $tenant = Tenant::findOrFail($id);
        $tenantName = $tenant->name;
        $tenant->delete();
        $this->refreshStats();
        $this->dispatch('toast', message: "Business {$tenantName} successfully deleted.", type: 'success');
    }

    public function approveSelected(): void
    {
        if (empty($this->selected)) {
            $this->dispatch('toast', message: 'No businesses selected.', type: 'error');
            return;
        }

        $tenants = Tenant::with('users')->whereIn('id', $this->selected)->get();
        
        DB::transaction(function () use ($tenants) {
            foreach ($tenants as $tenant) {
                $tenant->update(['is_active' => true]);
                if ($user = $tenant->users->first()) {
                    $user->update(['is_active' => true]);
                    if (!$user->hasRole('admin')) {
                        $user->assignRole('admin');
                    }
                }
            }
        });

        $count = $tenants->count();
        $this->selected = [];
        $this->refreshStats();
        $this->dispatch('toast', message: "{$count} business(es) approved and activated.", type: 'success');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            $this->dispatch('toast', message: 'No businesses selected.', type: 'error');
            return;
        }

        $count = count($this->selected);
        Tenant::whereIn('id', $this->selected)->delete();
        $this->selected = [];
        $this->refreshStats();
        $this->dispatch('toast', message: "{$count} business(es) deleted.", type: 'success');
    }

    public function exportCsv()
    {
        $filename = 'tenants-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Business', 'Type', 'Email', 'Contact', 'Address', 'Admin Name', 'Admin Email', 'Properties', 'Bookings', 'Status', 'Recommended', 'Created']);
            
            foreach ($this->getBaseQuery()->cursor() as $t) {
                $admin = $t->users->first();
                fputcsv($out, [
                    $t->id,
                    $t->name,
                    $t->typeOfTenant->type ?? '',
                    $t->email,
                    $t->contact_number,
                    $t->address,
                    $admin->name ?? '',
                    $admin->email ?? '',
                    $t->properties_count,
                    $t->bookings_count,
                    $t->is_active ? 'Active' : 'Pending',
                    $t->is_recommended ? 'Yes' : 'No',
                    $t->created_at->format('Y-m-d'),
                ]);
            }
            fclose($out);
        }, $filename);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Toast notifications --}}
    <div
        x-data="{ toasts: [] }"
        x-on:toast.window="
            const id = Date.now() + Math.random();
            toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
            setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
        "
        class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-green-50 border-green-200 text-green-800 dark:bg-green-500/10 dark:border-green-500/30 dark:text-green-300': toast.type === 'success',
                    'bg-red-50 border-red-200 text-red-800 dark:bg-red-500/10 dark:border-red-500/30 dark:text-red-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }"
            >
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Tenants</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage all businesses registered on the platform.</p>
        </div>
        <a href="{{ route('superadmin.tenants.create') }}" wire:navigate
           class="btn-primary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            Add Tenant
        </a>
    </div>

    {{-- Quick Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-white mt-2">{{ $totalCount }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active</p>
            <p class="text-2xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $activeCount }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pending</p>
            <p class="text-2xl font-bold text-amber-600 dark:text-amber-400 mt-2">{{ $pendingCount }}</p>
        </div>
        <div class="card p-4">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">Recommended</p>
            <p class="text-2xl font-bold text-primary-600 dark:text-primary-400 mt-2">{{ $recommendedCount }}</p>
        </div>
    </div>

    {{-- Filters Panel --}}
    <div class="card p-4 space-y-4">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" wire:model.live.debounce.300ms="search"
                       placeholder="Search..."
                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            </div>
            <select wire:model.live="statusFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="all">All status</option>
                <option value="active">Active</option>
                <option value="inactive">Pending</option>
            </select>
            <select wire:model.live="typeFilter"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="">All types</option>
                @foreach($this->tenantTypes as $type)
                    <option value="{{ $type->id }}">{{ $type->type }}</option>
                @endforeach
            </select>
            <input type="date" wire:model.live="startDate"
                   class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            <span class="text-gray-500 dark:text-gray-400 text-sm">to</span>
            <input type="date" wire:model.live="endDate"
                   class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
            <select wire:model.live="sortOption"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="latest">Newest first</option>
                <option value="oldest">Oldest first</option>
                <option value="name_asc">Name A–Z</option>
                <option value="name_desc">Name Z–A</option>
            </select>
            <select wire:model.live="perPage"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                <option value="12">12</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                <input type="checkbox" wire:model.live="recommendedFilter" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                Recommended Only
            </label>
            <button type="button" wire:click="clearFilters"
                    class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Clear
            </button>
        </div>

        {{-- Bulk Actions & Summary --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-900 dark:text-white">{{ $this->tenants->total() }}</span> tenants
            </div>
            <div class="flex gap-2 flex-wrap">
                <button type="button" wire:click="exportCsv" wire:loading.attr="disabled"
                        class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                    <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Exporting…
                    </span>
                </button>
                @if(count($selected) > 0)
                    @if($this->hasInactiveSelected)
                        <button type="button" wire:click="approveSelected" wire:confirm="Activate selected businesses?"
                                wire:loading.attr="disabled"
                                class="px-4 py-2 rounded-xl bg-green-100 dark:bg-green-500/15 border border-green-200 dark:border-green-500/30 text-green-700 dark:text-green-300 text-sm font-semibold hover:bg-green-200 dark:hover:bg-green-500/25 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-green-500/50">
                            Activate Selected ({{ count($selected) }})
                        </button>
                    @endif
                    <button type="button" wire:click="markRecommendedSelected"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-xl bg-amber-100 dark:bg-amber-500/15 border border-amber-200 dark:border-amber-500/30 text-amber-700 dark:text-amber-300 text-sm font-semibold hover:bg-amber-200 dark:hover:bg-amber-500/25 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-amber-500/50 inline-flex items-center gap-1">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Mark Recommended ({{ count($selected) }})
                    </button>
                    <button type="button" wire:click="unmarkRecommendedSelected"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-xl bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        Remove Recommended
                    </button>
                    <button type="button" wire:click="deleteSelected" wire:confirm="Delete selected businesses permanently?"
                            wire:loading.attr="disabled"
                            class="px-4 py-2 rounded-xl bg-red-100 dark:bg-red-500/15 border border-red-200 dark:border-red-500/30 text-red-700 dark:text-red-300 text-sm font-semibold hover:bg-red-200 dark:hover:bg-red-500/25 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50">
                        Delete Selected ({{ count($selected) }})
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Tenant Cards Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" wire:loading.class="opacity-50">
        @forelse($this->tenants as $tenant)
            @php
                $admin = $tenant->users->first();
                $coordinates = is_string($tenant->coordinates) ? json_decode($tenant->coordinates, true) : ($tenant->coordinates ?? []);
                $markerNames = collect($coordinates)->pluck('name')->filter()->implode(', ');
            @endphp
            <div class="card p-5 hover:shadow-md transition relative" wire:key="card-{{ $tenant->id }}">
                {{-- Checkbox --}}
                <div class="absolute top-4 left-4">
                    <input type="checkbox" wire:model.live="selected" value="{{ $tenant->id }}"
                           class="rounded bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500">
                </div>

                <div class="flex flex-col h-full">
                    <div class="flex items-start gap-3 mb-3 pl-8">
                        {{-- Logo / Avatar --}}
                        <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 flex items-center justify-center shrink-0 overflow-hidden">
                            @if($tenant->logo)
                                <img src="{{ asset('storage/' . $tenant->logo) }}" class="w-full h-full object-cover" alt="{{ $tenant->name }}">
                            @else
                                <span class="text-lg font-medium text-blue-700 dark:text-blue-300">{{ strtoupper(substr($tenant->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900 dark:text-white truncate flex items-center gap-1">
                                {{ $tenant->name }}
                                @if($tenant->is_recommended)
                                    <span class="text-amber-500 text-sm shrink-0" title="Recognized Tourist Attraction">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    </span>
                                @endif
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">#{{ $tenant->id }} · {{ $tenant->typeOfTenant->type ?? 'Uncategorized' }}</p>
                        </div>
                        <div class="flex items-center gap-1">
                            {{-- Recommended Toggle Button --}}
                            <button type="button"
                                    wire:click="toggleRecommended({{ $tenant->id }})"
                                    wire:key="recommend-btn-{{ $tenant->id }}"
                                    class="text-sm {{ $tenant->is_recommended ? 'text-amber-500 hover:text-amber-600' : 'text-gray-300 dark:text-gray-500 hover:text-amber-400' }} transition focus-visible:ring-2 focus-visible:ring-amber-500/50 rounded-full p-1 active:scale-95"
                                    title="{{ $tenant->is_recommended ? 'Remove from Recommended' : 'Mark as Recommended' }}">
                                <svg class="w-5 h-5" fill="{{ $tenant->is_recommended ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.98 10.1c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </button>

                            @if($tenant->is_active)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30">Active</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">Pending</span>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-2 text-sm flex-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Email</span>
                            <span class="text-gray-900 dark:text-white truncate ml-4">{{ $tenant->email }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Contact</span>
                            <span class="text-gray-900 dark:text-white">{{ $tenant->contact_number ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Admin</span>
                            <span class="text-gray-900 dark:text-white">{{ $admin ? $admin->name : 'Not assigned' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Properties</span>
                            <span class="text-gray-900 dark:text-white">{{ $tenant->properties_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Bookings</span>
                            <span class="text-gray-900 dark:text-white">{{ $tenant->bookings_count }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Created</span>
                            <span class="text-gray-900 dark:text-white text-xs">{{ $tenant->created_at->format('M d, Y') }}</span>
                        </div>
                        @if($markerNames)
                        <div class="flex justify-between">
                            <span class="text-gray-500 dark:text-gray-400">Markers</span>
                            <span class="text-gray-900 dark:text-white text-xs truncate ml-4">{{ $markerNames }}</span>
                        </div>
                        @endif
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-wrap gap-2">
                        @if(!$tenant->is_active)
                            <button type="button" wire:click="approve({{ $tenant->id }})"
                                    wire:confirm="Approve this business and activate its owner account?"
                                    class="text-xs font-medium bg-green-100 dark:bg-green-500/15 text-green-700 dark:text-green-300 border border-green-200 dark:border-green-500/30 hover:bg-green-200 dark:hover:bg-green-500/25 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-green-500/50">
                                Approve
                            </button>
                        @else
                            <button type="button" wire:click="deactivate({{ $tenant->id }})"
                                    wire:confirm="Suspend this business? Its owner will lose access."
                                    class="text-xs font-medium bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30 hover:bg-amber-200 dark:hover:bg-amber-500/25 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-amber-500/50">
                                Suspend
                            </button>
                        @endif
                        <a href="{{ route('superadmin.tenants.edit', $tenant->id) }}" wire:navigate
                           class="text-xs font-medium text-primary-600 dark:text-primary-400 border border-primary-200 dark:border-primary-500/30 hover:bg-primary-50 dark:hover:bg-primary-500/10 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                            Edit
                        </a>
                        <button type="button" wire:click="deleteTenant({{ $tenant->id }})"
                                wire:confirm="Delete this business permanently? This will remove all properties, bookings, and users."
                                class="text-xs font-medium text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/30 hover:bg-red-50 dark:hover:bg-red-500/10 px-3 py-1.5 rounded-lg transition active:scale-95 focus-visible:ring-2 focus-visible:ring-red-500/50">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 card">
                <svg class="mx-auto h-12 w-12 text-gray-300 dark:text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                <p class="text-lg text-gray-500 dark:text-gray-400 mb-1">No tenants found</p>
                <p class="text-xs text-gray-400 dark:text-gray-500">Try adjusting the search or filters.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($this->tenants->hasPages())
        <div class="card px-4 py-3">
            {{ $this->tenants->links() }}
        </div>
    @endif
</div>