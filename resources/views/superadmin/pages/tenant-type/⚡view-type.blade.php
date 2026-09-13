{{-- resources/views/superadmin/pages/tenant-type/⚡view-type.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use App\Models\TypeOfTenant;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Tenant Types')]
class extends Component {
    use WithPagination;

    #[Url(keep: true)]
    public string $search = '';

    public function mount(): void
    {
        // Defensive: the route already uses IsSuperAdmin middleware,
        // but reject any request that somehow bypassed it.
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403, 'Super-admin access only.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function types()
    {
        return TypeOfTenant::withoutGlobalScope(TenantScope::class)
            ->select('id', 'type', 'description')
            ->withCount(['tenants' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->when($this->search, fn ($q) => $q->where('type', 'like', '%' . trim($this->search) . '%'))
            ->orderBy('type')
            ->paginate(10);
    }

    /**
     * Aggregate stats in a single query to avoid three round-trips.
     */
    #[Computed]
    public function stats(): array
    {
        $agg = TypeOfTenant::withoutGlobalScope(TenantScope::class)
            ->selectRaw("
                COUNT(*) as total_types,
                COALESCE(SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM tenants
                    WHERE tenants.type_of_tenant_id = type_of_tenants.id
                ) THEN 1 ELSE 0 END), 0) as types_in_use
            ")
            ->first();

        return [
            'total_types'   => (int) ($agg->total_types ?? 0),
            'types_in_use'  => (int) ($agg->types_in_use ?? 0),
            'total_tenants' => Tenant::withoutGlobalScope(TenantScope::class)->count(),
        ];
    }

    public function delete(int $id): void
    {
        // Defensive authorization check
        if (!Auth::user()?->hasRole('super-admin')) {
            abort(403);
        }

        $type = TypeOfTenant::withoutGlobalScope(TenantScope::class)
            ->withCount(['tenants' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->find($id);

        if (!$type) {
            session()->flash('error', 'Tenant type not found. It may have already been deleted.');
            return;
        }

        if ($type->tenants_count > 0) {
            session()->flash('error', "Cannot delete '{$type->type}' because it is used by {$type->tenants_count} tenant(s).");
            return;
        }

        try {
            $typeName = $type->type;

            DB::transaction(function () use ($type) {
                $type->delete();
            });

            session()->flash('message', "Tenant type '{$typeName}' successfully deleted.");
        } catch (\Exception $e) {
            Log::error('Tenant type delete failed: ' . $e->getMessage(), [
                'type_id'  => $id,
                'actor_id' => Auth::id(),
            ]);
            session()->flash('error', 'Failed to delete tenant type. Please try again.');
        }
    }

    public function exportCsv()
    {
        $types = TypeOfTenant::withoutGlobalScope(TenantScope::class)
            ->select('id', 'type', 'description')
            ->withCount(['tenants' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)])
            ->when($this->search, fn ($q) => $q->where('type', 'like', '%' . trim($this->search) . '%'))
            ->orderBy('type')
            ->cursor();

        $filename = 'tenant-types-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($types) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Type', 'Description', 'Tenants']);

            foreach ($types as $type) {
                fputcsv($out, [
                    $type->type,
                    $type->description ?? 'N/A',
                    $type->tenants_count,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
    }

    #[Computed]
    public function activeFiltersCount(): int
    {
        return $this->search !== '' ? 1 : 0;
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">Tenant Types</h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Manage categories that classify businesses on the platform.</p>
        </div>
        <a href="{{ route('superadmin.tenant-types.create') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-sm font-semibold shadow-sm shadow-primary-500/20 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add New Type
        </a>
    </div>

    {{-- Auto-dismissing Flash Messages --}}
    @if (session()->has('message'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)"
             x-transition.opacity.duration.500ms
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ session('message') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    @if (session()->has('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             x-transition.opacity.duration.500ms
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Quick Stats --}}
    @php $s = $this->stats; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-primary-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Types</p>
                <div class="p-2 bg-primary-50 dark:bg-primary-950/50 rounded-xl text-primary-600 dark:text-primary-400 border border-primary-100 dark:border-primary-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mt-3">{{ number_format($s['total_types']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-emerald-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Total Tenants</p>
                <div class="p-2 bg-emerald-50 dark:bg-emerald-950/50 rounded-xl text-emerald-600 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-3">{{ number_format($s['total_tenants']) }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800/90 p-5 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm transition-all duration-200 hover:border-amber-500/30">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Types In Use</p>
                <div class="p-2 bg-amber-50 dark:bg-amber-950/50 rounded-xl text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-900/50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-3">{{ number_format($s['types_in_use']) }}</p>
        </div>
    </div>

    {{-- Search & Export Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center justify-between bg-white dark:bg-gray-800/90 p-4 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm">
        <div class="relative flex-1 max-w-md w-full">
            <div class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 dark:text-gray-500">
                <svg wire:loading.remove wire:target="search" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <svg wire:loading wire:target="search" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="Search types…"
                   class="w-full bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-xl py-2 pl-10 pr-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:bg-white dark:focus:bg-gray-900 focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition">
        </div>

        <div class="flex items-center gap-2">
            @if($search !== '')
                <button type="button" wire:click="clearFilters"
                        class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 text-xs font-semibold transition focus:ring-2 focus:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Clear
                </button>
            @endif

            <button wire:click="exportCsv" wire:loading.attr="disabled" wire:target="exportCsv"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gray-900 dark:bg-gray-700 hover:bg-black dark:hover:bg-gray-600 text-white text-xs sm:text-sm font-semibold shadow-sm transition disabled:opacity-50 focus:ring-2 focus:ring-primary-500/50 active:scale-95">
                <svg wire:loading.remove wire:target="exportCsv" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportCsv">Export CSV</span>
                <span wire:loading wire:target="exportCsv" class="inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    Exporting…
                </span>
            </button>
        </div>
    </div>

    {{-- Table Card --}}
    <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm overflow-hidden relative">
        <div wire:loading.flex wire:target="search,clearFilters,gotoPage,nextPage,previousPage"
             class="absolute inset-0 bg-white/60 dark:bg-gray-900/60 backdrop-blur-[1px] z-10 items-center justify-center">
            <svg class="animate-spin h-7 w-7 text-primary-600 dark:text-primary-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm border-collapse">
                <thead class="bg-gray-50/70 dark:bg-gray-900/50 border-b border-gray-200/80 dark:border-gray-700/80 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">
                    <tr>
                        <th class="px-6 py-4">Type</th>
                        <th class="px-6 py-4 hidden sm:table-cell">Description</th>
                        <th class="px-6 py-4 text-center">Tenants</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 text-gray-700 dark:text-gray-200">
                    @forelse($this->types as $type)
                        <tr wire:key="type-{{ $type->id }}" class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">{{ $type->type }}</td>
                            <td class="px-6 py-4 hidden sm:table-cell text-gray-500 dark:text-gray-400 truncate max-w-xs" title="{{ $type->description }}">
                                {{ $type->description ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-center font-medium">
                                <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    {{ $type->tenants_count > 0
                                        ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300 border border-green-200 dark:border-green-500/30'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-700/80 dark:text-gray-400 border border-gray-200 dark:border-gray-600' }}">
                                    {{ $type->tenants_count }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('superadmin.tenant-types.edit', $type->id) }}" wire:navigate
                                       class="p-1.5 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950/50 rounded-lg transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                                       title="Edit">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </a>
                                    <button wire:click="delete({{ $type->id }})"
                                            wire:confirm="Are you sure you want to delete '{{ $type->type }}'? This action cannot be undone."
                                            wire:loading.attr="disabled"
                                            wire:target="delete({{ $type->id }})"
                                            class="p-1.5 text-gray-500 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 disabled:opacity-60"
                                            title="Delete">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center max-w-sm mx-auto">
                                    <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                        {{ $search !== '' ? 'No types match your search' : 'No tenant types yet' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        {{ $search !== '' ? 'Try a different search term or clear the filter.' : 'Get started by creating your first tenant type.' }}
                                    </p>
                                    @if($search !== '')
                                        <button type="button" wire:click="clearFilters" class="mt-3 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold">
                                            Clear active search filter
                                        </button>
                                    @else
                                        <a href="{{ route('superadmin.tenant-types.create') }}" wire:navigate
                                           class="mt-3 inline-flex items-center gap-1.5 text-xs text-primary-600 dark:text-primary-400 hover:underline font-semibold">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            Create your first type
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->types->hasPages())
            <div class="px-6 py-4 border-t border-gray-200/80 dark:border-gray-700/80 bg-gray-50/50 dark:bg-gray-900/50">
                {{ $this->types->links() }}
            </div>
        @endif
    </div>
</div>