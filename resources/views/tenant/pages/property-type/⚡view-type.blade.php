{{-- resources/views/tenant/pages/property-type/⚡view-type.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\PropertyType;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use Illuminate\Support\Facades\Auth;

new
#[Layout('tenant.layouts.app')]
#[Title('Property Types')]
class extends Component {
    use WithPagination;
    use ChecksTenantPermissions;

    public string $search = '';

    public function mount(): void
    {
        $this->authorizeViewTypes();
    }

    public function hydrate(): void
    {
        $this->authorizeViewTypes();
    }

    protected function authorizeViewTypes(): void
    {
        abort_unless(
            Auth::user()?->tenant_id,
            403,
            'No business is linked to your account.'
        );

        $this->requirePermission('view properties');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $this->requirePermission('manage properties');

        /** @var \App\Models\User $user */
        $user = Auth::user();

        $type = PropertyType::withoutGlobalScope(TenantScope::class)->find($id);

        if (! $type) {
            session()->flash('error', 'Property type not found.');
            return;
        }

        if ($type->tenant_id === null || $type->tenant_id !== $user->tenant_id) {
            session()->flash('error', 'You cannot delete this property type.');
            return;
        }

        $propertyCount = $type->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('property_type_id', $type->id)
            ->count();

        if ($propertyCount > 0) {
            session()->flash(
                'error',
                "Cannot delete '{$type->name}' — {$propertyCount} "
                . ($propertyCount === 1 ? 'property is' : 'properties are')
                . ' still using this type. Reassign or remove them first.'
            );
            return;
        }

        $typeName = $type->name;
        $type->delete();

        unset($this->types, $this->stats);

        session()->flash('success', "Property type '{$typeName}' deleted.");
    }

    #[Computed]
    public function types()
    {
        return PropertyType::availableForTenant(Auth::user()->tenant_id)
            ->withCount('properties')
            ->when($this->search !== '', function ($q) {
                $needle = '%' . addcslashes($this->search, '%_\\') . '%';
                $q->where('name', 'like', $needle);
            })
            ->orderByRaw('tenant_id IS NULL DESC')
            ->orderBy('name')
            ->paginate(10);
    }

    #[Computed]
    public function stats(): array
    {
        $tenantId = Auth::user()->tenant_id;

        $global = PropertyType::withoutGlobalScope(TenantScope::class)
            ->whereNull('tenant_id')
            ->count();

        $custom = PropertyType::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();

        return [
            'total'  => $global + $custom,
            'global' => $global,
            'custom' => $custom,
        ];
    }
};
?>

@push('styles')
    @once
        <style>
            .tenant-property-types-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.06) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.05) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.04) 0%, transparent 60%);
            }
            .dark .tenant-property-types-ambient {
                background:
                    radial-gradient(ellipse 70% 50% at 8% 5%,  rgba(245,158,11,.08) 0%, transparent 55%),
                    radial-gradient(ellipse 60% 55% at 95% 15%, rgba(59,130,246,.07) 0%, transparent 55%),
                    radial-gradient(ellipse 80% 60% at 50% 100%, rgba(139,92,246,.06) 0%, transparent 60%);
            }
        </style>
    @endonce
@endpush

@php $s = $this->stats; @endphp

<div class="relative">
    <div class="tenant-property-types-ambient fixed inset-0 -z-10 pointer-events-none" aria-hidden="true"></div>

    <div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto space-y-6
                pb-[max(1rem,env(safe-area-inset-bottom))]">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200/70 dark:border-gray-800/70">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Property Management</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Property Types
                </h1>
                <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Global standards plus your custom types.
                </p>
            </div>
            @if($this->tenantCan('manage properties'))
                <a href="{{ route('tenant.property-types.create') }}" wire:navigate
                   class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                          transition-all duration-200 active:scale-95
                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Add Custom Type</span>
                </a>
            @endif
        </div>

        @if(session()->has('success'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 4000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        @if(session()->has('error'))
            <div x-data="{ show: true }"
                 x-init="setTimeout(() => show = false, 5000)"
                 :class="show ? '' : 'hidden'"
                 class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <span>{{ session('error') }}</span>
                </div>
                <button type="button" @click="show = false"
                        class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                               transition-all duration-200 active:scale-95
                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        @endif

        <div class="grid grid-cols-3 gap-3">
            @php
                $kpis = [
                    ['label' => 'Available Types',   'value' => $s['total'],  'dot' => 'bg-primary-500'],
                    ['label' => 'Global Standards',  'value' => $s['global'], 'dot' => 'bg-amber-500'],
                    ['label' => 'Your Custom Types', 'value' => $s['custom'], 'dot' => 'bg-primary-600'],
                ];
            @endphp
            @foreach($kpis as $kpi)
                <div wire:key="kpi-{{ $loop->index }}"
                     class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                            rounded-xl border border-gray-200/60 dark:border-white/[0.06]
                            shadow-sm p-3.5">
                    <div class="flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full {{ $kpi['dot'] }}"></span>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $kpi['label'] }}</span>
                    </div>
                    <p class="mt-1.5 text-xl font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($kpi['value']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                    shadow-sm p-4">
            <div class="flex flex-wrap gap-2 items-center">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search types…"
                           autocomplete="off"
                           enterkeyhint="search"
                           aria-label="Search property types"
                           class="input w-full"
                           style="padding-left: 2.5rem;">
                </div>

                @if($search !== '')
                    <button type="button" wire:click="$set('search', '')"
                            class="inline-flex items-center gap-1 h-11 sm:h-9 px-3.5 rounded-full text-xs font-semibold uppercase tracking-wide
                                   border border-rose-300 dark:border-rose-500/40
                                   bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   hover:bg-rose-50 dark:hover:bg-rose-500/10
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Clear
                    </button>
                @endif
            </div>
        </div>

        @if($this->types->isEmpty())
            <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                        rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                        shadow-sm p-12 text-center">
                <div class="flex flex-col items-center max-w-md mx-auto">
                    <svg class="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                        No property types found
                    </p>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($search !== '')
                            No types match "{{ $search }}". Try adjusting or clearing your search.
                        @else
                            Get started by creating your first custom property type.
                        @endif
                    </p>
                    <div class="mt-5 flex flex-wrap gap-2 justify-center">
                        @if($search !== '')
                            <button type="button" wire:click="$set('search', '')"
                                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                           transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                Clear Search
                            </button>
                        @endif
                        @if($this->tenantCan('manage properties'))
                            <a href="{{ route('tenant.property-types.create') }}" wire:navigate
                               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                      transition-all duration-200 active:scale-95
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add Custom Type</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        @else
            <div wire:loading.class="opacity-40 pointer-events-none"
                 wire:target="search,delete,gotoPage,nextPage,previousPage"
                 class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4 transition-opacity duration-200">
                @foreach($this->types as $type)
                    @php
                        $isGlobal = is_null($type->tenant_id);
                        $canManage = $this->tenantCan('manage properties');
                    @endphp

                    <article wire:key="ptype-{{ $type->id }}"
                             class="group relative bg-white/70 dark:bg-gray-800/40 backdrop-blur-xl
                                    rounded-2xl border border-gray-200/60 dark:border-white/[0.06]
                                    shadow-sm
                                    hover:shadow-md hover:border-primary-300/80 dark:hover:border-primary-500/40
                                    transition-all duration-200 flex flex-col overflow-hidden">

                        <div class="p-4 pb-3 flex items-start gap-3">
                            <div class="w-11 h-11 rounded-xl shrink-0 flex items-center justify-center
                                        {{ $isGlobal
                                           ? 'bg-amber-50 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                           : 'bg-primary-50 dark:bg-primary-500/15 text-primary-600 dark:text-primary-400' }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900 dark:text-white truncate leading-tight">
                                    {{ $type->name }}
                                </p>
                                <span class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border
                                             {{ $isGlobal
                                                ? 'bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-500/40'
                                                : 'bg-primary-100 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 border-primary-200 dark:border-primary-500/40' }}">
                                    <span class="w-1 h-1 rounded-full bg-current"></span>
                                    {{ $isGlobal ? 'Global' : 'Custom' }}
                                </span>
                            </div>
                        </div>

                        <div class="px-4 py-3 border-t border-gray-100/80 dark:border-white/[0.04]">
                            <div class="flex items-center gap-2 text-xs">
                                <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                </svg>
                                <span class="text-gray-700 dark:text-gray-300 tabular-nums">
                                    {{ $type->properties_count }} {{ \Illuminate\Support\Str::plural('property', (int) $type->properties_count) }} using this type
                                </span>
                            </div>
                        </div>

                        <div class="mt-auto px-3 py-2.5 border-t border-gray-100/80 dark:border-white/[0.04] flex items-center justify-end gap-1">
                            @if(!$isGlobal && $canManage)
                                <a href="{{ route('tenant.property-types.edit', $type->id) }}" wire:navigate
                                   aria-label="Edit {{ $type->name }}"
                                   title="Edit"
                                   class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-500/10
                                          transition-all duration-200 active:scale-95
                                          [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </a>

                                <button type="button"
                                        x-data="{
                                            armed: false,
                                            _t: null,
                                            arm() { this.armed = true; clearTimeout(this._t); this._t = setTimeout(() => { this.armed = false; this._t = null; }, 4000); },
                                            unarm() { clearTimeout(this._t); this._t = null; this.armed = false; },
                                            destroy() { clearTimeout(this._t); }
                                        }"
                                        @click="armed ? (unarm(), $wire.delete({{ $type->id }})) : arm()"
                                        wire:loading.attr="disabled"
                                        wire:target="delete"
                                        :aria-label="armed ? 'Click again to confirm delete' : 'Delete {{ $type->name }}'"
                                        :title="armed ? 'Click again to confirm' : 'Delete'"
                                        :class="armed
                                            ? 'text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-500/20 ring-2 ring-rose-400/60'
                                            : 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10'"
                                        class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg
                                               transition-all duration-200 active:scale-95
                                               [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                               disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg x-show="!armed" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <svg x-show="armed" x-cloak class="w-4 h-4 animate-pulse motion-reduce:animate-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </button>
                            @elseif($isGlobal)
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 italic uppercase tracking-wider font-semibold">Read-only</span>
                            @else
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 italic uppercase tracking-wider font-semibold">View only</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if($this->types->hasPages())
                <div class="pt-2">
                    {{ $this->types->links() }}
                </div>
            @endif
        @endif
    </div>
</div>