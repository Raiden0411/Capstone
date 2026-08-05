{{-- resources/views/public/pages/explore-map.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;

new
#[Layout('layouts.app')]
#[Title('Explore Map · Victorias City')]
class extends Component
{
    public string $search         = '';
    public string $categoryFilter = '';
    public string $sortBy         = 'name';
    public ?int   $highlightedId  = null;

    public function getTenantsProperty()
    {
        return Tenant::where('is_active', true)
            ->whereNotNull('coordinates')
            ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->categoryFilter, fn($q) => $q->whereHas(
                'typeOfTenant', fn($sub) => $sub->where('type', $this->categoryFilter)
            ))
            ->orderBy('name')
            ->get();
    }

    public function getCategoriesProperty()
    {
        return \App\Models\TypeOfTenant::has('tenants')->orderBy('type')->pluck('type');
    }

    public function updatedSearch(): void
    {
        $this->dispatch('map-tenants-updated', tenants: $this->tenants->toArray());
    }

    public function updatedCategoryFilter(): void
    {
        $this->dispatch('map-tenants-updated', tenants: $this->tenants->toArray());
    }

    public function flyToTenant(int $id): void
    {
        $tenant = Tenant::find($id);
        if ($tenant?->coordinates && count($tenant->coordinates) > 0) {
            $this->highlightedId = $id;
            $this->dispatch('fly-to-tenant', tenant: $tenant->toArray());
        }
    }
};
?>

<div class="relative z-10 h-[calc(100vh-64px)] flex overflow-hidden">

    {{-- Leaflet CSS --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>

    <style>
        :root {
            --em      : #22c55e;
            --em-dim  : rgba(34,197,94,.18);
            --em-glow : rgba(34,197,94,.30);
            --surface : rgba(10,14,24,0.96);
            --border  : rgba(255,255,255,0.07);
            --border-em: rgba(34,197,94,0.22);
            --text    : #f1f5f9;
            --text-2  : #94a3b8;
            --text-3  : #475569;
        }

        * { box-sizing: border-box; }

        .leaflet-container { background: transparent !important; }

        .leaflet-popup-content-wrapper {
            background      : rgba(8,12,22,0.97) !important;
            backdrop-filter : blur(24px) saturate(180%) !important;
            border-radius   : 18px !important;
            border          : 1px solid var(--border) !important;
            box-shadow      : 0 32px 64px rgba(0,0,0,.65), 0 0 0 1px rgba(255,255,255,.03) !important;
            color           : var(--text) !important;
        }
        .leaflet-popup-tip { background: rgba(8,12,22,0.97) !important; }
        .leaflet-popup-content { margin: 14px 16px !important; }
        .leaflet-popup-close-button {
            color: var(--text-2) !important; font-size: 20px !important;
            top: 8px !important; right: 10px !important;
            width: 24px !important; height: 24px !important;
            display: flex !important; align-items: center !important; justify-content: center !important;
            border-radius: 6px !important; transition: all .15s !important;
        }
        .leaflet-popup-close-button:hover { color: #fff !important; background: rgba(255,255,255,.08) !important; }

        .leaflet-control-zoom {
            border:none !important; border-radius:14px !important; overflow:hidden;
            box-shadow:0 8px 32px rgba(0,0,0,.5) !important;
        }
        .leaflet-control-zoom a {
            background:rgba(8,12,22,0.92) !important; backdrop-filter:blur(12px) !important;
            color:var(--text-2) !important; border:1px solid var(--border) !important;
            transition:all .2s !important;
        }
        .leaflet-control-zoom a:hover { background:var(--em-dim) !important; color:var(--em) !important; border-color:var(--border-em) !important; }

        .marker-cluster-small,
        .marker-cluster-medium,
        .marker-cluster-large { border-radius:50%; display:flex; align-items:center; justify-content:center; }
        .marker-cluster-small      { background: rgba(34,197,94,.12) !important; }
        .marker-cluster-small  div { background: #22c55e !important; color:#fff !important; font-weight:700; font-size:12px; }
        .marker-cluster-medium     { background: rgba(6,182,212,.14) !important; }
        .marker-cluster-medium div { background: #06b6d4 !important; color:#fff !important; font-weight:700; font-size:12px; }
        .marker-cluster-large      { background: rgba(245,158,11,.18) !important; }
        .marker-cluster-large  div { background: #f59e0b !important; color:#000 !important; font-weight:700; font-size:12px; }

        /* ── Logo parent marker ── */
        .logo-marker {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 4px 12px rgba(0,0,0,.4), 0 0 0 3px var(--c, #22c55e);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
            transition: transform .2s ease;
        }
        .logo-marker:hover { transform: scale(1.15); }
        .logo-marker img {
            width: 100%; height: 100%;
            object-fit: cover;
        }
        .logo-marker .fallback {
            font-weight: 800; font-size: 15px;
            color: white;
            background: var(--c, #22c55e);
            width: 100%; height: 100%;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── Child dot marker ── */
        .child-marker {
            width: 18px; height: 18px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 6px rgba(0,0,0,.35);
            display: flex; align-items: center; justify-content: center;
        }
        .child-marker::after {
            content: '';
            width: 10px; height: 10px;
            border-radius: 50%;
            background: var(--c, #22c55e);
            display: block;
        }

        /* ── Route panel ── */
        .route-glass {
            background:rgba(8,12,22,0.94); backdrop-filter:blur(20px) saturate(160%);
            border:1px solid var(--border-em); border-radius:16px;
        }

        .sb-scroll::-webkit-scrollbar       { width:3px; }
        .sb-scroll::-webkit-scrollbar-track { background:transparent; }
        .sb-scroll::-webkit-scrollbar-thumb { background:rgba(255,255,255,.1); border-radius:4px; }

        .dest-row { position:relative; overflow:hidden; }
        .dest-row::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(90deg, var(--em-dim) 0%, transparent 100%);
            opacity:0; transition:opacity .2s; border-radius:14px;
        }
        .dest-row.dest-active::before { opacity:1; }

        #xtoast {
            position:fixed; bottom:28px; left:50%; transform:translateX(-50%) translateY(20px);
            background:rgba(8,12,22,0.96); border:1px solid var(--border);
            border-radius:12px; padding:10px 18px;
            color:var(--text); font-size:13px; font-weight:500;
            pointer-events:none; opacity:0; transition:opacity .25s, transform .25s cubic-bezier(.34,1.56,.64,1);
            z-index:9999; white-space:nowrap;
        }
        #xtoast.show { opacity:1; transform:translateX(-50%) translateY(0); }
    </style>

    {{-- Tenant data bridge --}}
    <script>
        window.__mapTenants = @json($this->tenants);

        window.__pinColor = function(idx) {
            const hues = [142,200,35,280,48,330,15,170,210,260,95,55,310,120,190,65,245,10];
            return `hsl(${hues[idx % hues.length]},68%,62%)`;
        };

        window.__haversine = function(lat1,lng1,lat2,lng2) {
            const R  = 6371;
            const dL = (lat2-lat1)*Math.PI/180;
            const dN = (lng2-lng1)*Math.PI/180;
            const a  = Math.sin(dL/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dN/2)**2;
            return (R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a))).toFixed(1);
        };

        window.__buildPopup = function(tenantIdx, userCoords, coordIdx = 0) {
            const t = window.__mapTenants[tenantIdx];
            if (!t) return '';
            const coord = t.coordinates[coordIdx];
            const color = window.__pinColor(tenantIdx);

            const avatar = t.logo
                ? `<img src="/storage/${t.logo}" alt="${t.name}"
                        style="width:46px;height:46px;border-radius:12px;object-fit:cover;
                               border:2px solid ${color};flex-shrink:0;">`
                : `<div style="width:46px;height:46px;border-radius:12px;
                               background:linear-gradient(135deg,${color}33,${color}99);
                               border:1.5px solid ${color}66;
                               display:flex;align-items:center;justify-content:center;
                               color:#fff;font-weight:800;font-size:14px;flex-shrink:0;
                               font-family:'Outfit',sans-serif;letter-spacing:-.5px;">
                       ${t.name.substring(0,2).toUpperCase()}
                   </div>`;

            const km = userCoords
                ? window.__haversine(userCoords.lat,userCoords.lng,coord.lat,coord.lng)
                : null;

            const distBadge = km
                ? `<span style="display:inline-flex;align-items:center;gap:3px;
                               padding:3px 8px;border-radius:99px;
                               background:${color}18;border:1px solid ${color}33;
                               font-size:10px;font-weight:700;color:${color};
                               font-family:'Outfit',sans-serif;letter-spacing:.03em;">
                       <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                         <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
                       </svg>
                       ${km} km away
                   </span>`
                : '';

            return `
<div style="min-width:252px;max-width:288px;font-family:'Outfit',sans-serif;">
  <div style="height:3px;background:linear-gradient(90deg,${color},${color}44);
              margin:-14px -16px 14px;border-radius:0;"></div>
  <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:10px;">
    ${avatar}
    <div style="min-width:0;flex:1;">
      <h3 style="font-size:15px;font-weight:700;margin:0 0 2px;color:#f1f5f9;
                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.3px;">
        ${coord.name || t.name}
      </h3>
      <p style="font-size:10.5px;color:#64748b;margin:0 0 6px;font-weight:500;">
        ${t.type_of_tenant?.type ?? 'Business'}
      </p>
      ${distBadge}
    </div>
  </div>
  ${t.address ? `<p style="font-size:10.5px;color:#475569;margin:0 0 12px;line-height:1.55;
                            display:flex;align-items:flex-start;gap:4px;">
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="2.5" style="flex-shrink:0;margin-top:2px;">
      <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
    </svg>${t.address}</p>` : ''}
  <a href="/business/${t.slug}/offerings"
     style="display:block;width:100%;padding:9px 0;text-align:center;
            font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;
            border-radius:10px;background:${color};color:#fff;text-decoration:none;
            margin-bottom:7px;transition:opacity .18s;box-shadow:0 4px 14px ${color}40;"
     onmouseover="this.style.opacity='.82'" onmouseout="this.style.opacity='1'">
     View Offerings →
  </a>
  <button onclick="window.__mapCtrl?.getDirections(${tenantIdx},${coordIdx})"
     style="display:block;width:100%;padding:8px 0;font-size:11.5px;font-weight:700;
            text-transform:uppercase;letter-spacing:.08em;border-radius:10px;
            border:1.5px solid ${color}55;color:${color};background:transparent;cursor:pointer;
            transition:all .2s;font-family:'Outfit',sans-serif;"
     onmouseover="this.style.background='${color}18';this.style.borderColor='${color}'"
     onmouseout="this.style.background='transparent';this.style.borderColor='${color}55'">
     Get Directions
  </button>
</div>`;
        };

        function __xToast(msg, ms = 2400) {
            const el = document.getElementById('xtoast');
            if (!el) return;
            el.textContent = msg;
            el.classList.add('show');
            clearTimeout(el.__t);
            el.__t = setTimeout(() => el.classList.remove('show'), ms);
        }
    </script>

    {{-- Sidebar (unchanged) --}}
    <div x-data="{
             open  : window.innerWidth >= 1024,
             resize() { if(window.innerWidth >= 1024) this.open = true; }
         }"
         @resize.window.debounce.120ms="resize()"
         class="relative z-50 flex-shrink-0 hidden lg:block">

        <aside class="h-full w-[360px] flex flex-col shadow-2xl
                      bg-[rgba(8,12,22,0.97)] backdrop-blur-2xl border-r border-white/[0.055]">

            {{-- Header --}}
            <div class="px-5 pt-5 pb-4 border-b border-white/[0.055]">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[.18em] text-emerald-500 mb-1 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Victorias City
                        </p>
                        <h2 class="text-[18px] font-extrabold text-white tracking-tight leading-none">Explore Destinations</h2>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20">
                            <span class="text-[11px] font-bold text-emerald-400 tabular-nums">{{ $this->tenants->count() }}</span>
                            <span class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider">spots</span>
                        </div>
                        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/[.04] border border-white/[.07]">
                            <span class="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">{{ $this->categories->count() }} categories</span>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-[11px]">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-1 text-zinc-600 hover:text-white transition font-medium">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>Home
                    </a>
                    @auth
                        <span class="w-px h-3 bg-white/10"></span>
                        <a href="{{ route('my-bookings') }}" wire:navigate class="flex items-center gap-1 text-emerald-500/80 hover:text-emerald-400 transition font-medium">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>Reservations
                        </a>
                    @endauth
                </div>
            </div>

            {{-- Filters --}}
            <div class="px-4 py-3.5 border-b border-white/[0.055] space-y-2.5">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-zinc-600 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" wire:model.live.debounce.280ms="search" placeholder="Search destinations…"
                           class="w-full bg-white/[.04] border border-white/[.08] rounded-xl py-2.5 pl-9 pr-8 text-[13px] text-white placeholder-zinc-700 focus:outline-none focus:border-emerald-500/40 focus:ring-1 focus:ring-emerald-500/20 transition">
                    @if($search)
                        <button wire:click="$set('search','')" class="absolute right-2.5 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center rounded-md text-zinc-600 hover:text-white hover:bg-white/10 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    @endif
                </div>

                <div class="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-none" style="-ms-overflow-style:none;scrollbar-width:none;">
                    <button wire:click="$set('categoryFilter','')"
                            class="shrink-0 px-3 py-1.5 rounded-full text-[10.5px] font-bold uppercase tracking-[.07em] transition-all border {{ $categoryFilter === '' ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/25' : 'border-white/[.08] text-zinc-600 hover:border-white/20 hover:text-zinc-300' }}">All</button>
                    @foreach($this->categories as $cat)
                        <button wire:click="$set('categoryFilter','{{ $cat }}')"
                                class="shrink-0 px-3 py-1.5 rounded-full text-[10.5px] font-bold uppercase tracking-[.07em] transition-all border whitespace-nowrap {{ $categoryFilter === $cat ? 'bg-emerald-500 border-emerald-500 text-white shadow-lg shadow-emerald-500/25' : 'border-white/[.08] text-zinc-600 hover:border-white/20 hover:text-zinc-300' }}">{{ $cat }}</button>
                    @endforeach
                </div>

                <div class="flex gap-1.5 bg-white/[.025] rounded-xl p-1 border border-white/[.06]">
                    <button wire:click="$set('sortBy','name')"
                            class="flex-1 py-1.5 rounded-lg text-[10.5px] font-bold uppercase tracking-[.07em] transition-all {{ $sortBy === 'name' ? 'bg-white/[.08] text-white shadow-sm' : 'text-zinc-600 hover:text-zinc-400' }}">A – Z</button>
                    <button wire:click="$set('sortBy','distance')" @click="window.__mapCtrl?.enableDistanceSort()"
                            class="flex-1 py-1.5 rounded-lg text-[10.5px] font-bold uppercase tracking-[.07em] transition-all {{ $sortBy === 'distance' ? 'bg-white/[.08] text-white shadow-sm' : 'text-zinc-600 hover:text-zinc-400' }}">Near Me</button>
                </div>
            </div>

            {{-- List --}}
            <div class="flex-1 overflow-y-auto sb-scroll py-2">
                @forelse($this->tenants as $index => $tenant)
                    @php $hue = ($index * 137) % 360; @endphp
                    <div wire:key="dest-{{ $tenant->id }}" wire:click="flyToTenant({{ $tenant->id }})"
                         class="dest-row group cursor-pointer mx-2.5 my-0.5 px-3 py-2.5 rounded-[14px] flex items-center gap-3 transition-all duration-150 border {{ $highlightedId === $tenant->id ? 'dest-active border-emerald-500/25' : 'border-transparent hover:bg-white/[.028] hover:border-white/[.06]' }}"
                         data-tenant-id="{{ $tenant->id }}">
                        @if($tenant->logo)
                            <img src="{{ asset('storage/'.$tenant->logo) }}" alt="{{ $tenant->name }}" class="w-11 h-11 rounded-xl object-cover border border-white/10 flex-shrink-0">
                        @else
                            <div class="w-11 h-11 rounded-xl flex items-center justify-center text-[13px] font-black flex-shrink-0 text-white select-none"
                                 style="background: linear-gradient(135deg, hsl({{ $hue }},55%,38%) 0%, hsl({{ $hue }},60%,52%) 100%); box-shadow:0 4px 14px hsl({{ $hue }},55%,38%,.4);">
                                {{ strtoupper(substr($tenant->name, 0, 2)) }}
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-[13px] font-semibold text-white truncate leading-snug">{{ $tenant->name }}</p>
                            <p class="text-[10.5px] text-zinc-600 mt-0.5 font-medium">{{ $tenant->typeOfTenant?->type ?? 'Business' }}</p>
                            <span class="xdist-badge hidden text-[10px] font-bold mt-0.5 inline-block"
                                  data-lat="{{ $tenant->coordinates[0]['lat'] ?? '' }}"
                                  data-lng="{{ $tenant->coordinates[0]['lng'] ?? '' }}"
                                  style="color: hsl({{ $hue }},65%,60%)"></span>
                        </div>
                        <button onclick="event.stopPropagation(); window.__mapCtrl?.getDirections({{ $index }}, 0)"
                                class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg border border-transparent text-zinc-700 hover:text-emerald-400 hover:border-emerald-500/25 hover:bg-emerald-500/[.07] opacity-0 group-hover:opacity-100 transition-all duration-150" title="Get directions">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="text-center py-16 px-6">
                        <div class="w-14 h-14 rounded-2xl bg-white/[.03] border border-white/[.06] flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                        </div>
                        <p class="text-zinc-500 text-[13px] font-medium">No destinations match.</p>
                        @if($search || $categoryFilter)
                            <button wire:click="$set('search',''); $set('categoryFilter','')" class="mt-3 text-emerald-500 hover:text-emerald-400 text-xs font-semibold transition">← Clear filters</button>
                        @endif
                    </div>
                @endforelse
            </div>

            <div class="px-4 py-2.5 border-t border-white/[.055] flex justify-between items-center">
                <span class="text-[11px] text-zinc-700 font-medium">{{ $this->tenants->count() }} {{ Str::plural('place', $this->tenants->count()) }}</span>
                <span class="text-[11px] text-zinc-700 font-medium">Leaflet · CARTO · OSRM</span>
            </div>
        </aside>
    </div>

    {{-- Map canvas --}}
    <div x-data="mapExplorer()"
         x-init="boot()"
         @fly-to-tenant.window="flyTo($event.detail.tenant)"
         @map-tenants-updated.window="refreshTenants($event.detail.tenants)"
         class="flex-1 relative min-w-0 h-full">

        <div x-show="!mapReady"
             x-transition:leave="transition-opacity duration-700 ease-in"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 z-[900] flex flex-col items-center justify-center gap-5 bg-[rgba(6,9,18,0.98)]">
            <div class="relative w-14 h-14">
                <div class="absolute inset-0 rounded-full border-2 border-emerald-500/20 border-t-emerald-400 animate-spin"></div>
                <div class="absolute inset-2 rounded-full border-2 border-emerald-400/10 border-b-emerald-500/50 animate-spin" style="animation-direction:reverse;animation-duration:1.4s;"></div>
            </div>
            <div class="text-center">
                <p class="text-[13px] font-semibold text-white/70 tracking-wide">Loading map</p>
                <p class="text-[11px] text-zinc-700 mt-1">Victorias City, Philippines</p>
            </div>
        </div>

        <div wire:ignore x-ref="mapEl" class="absolute inset-0 w-full h-full"></div>

        <div class="absolute top-4 right-4 z-[800] flex gap-1.5">
            @foreach(['dark' => ['🌑','Dark'], 'light' => ['🗺️','Light'], 'satellite' => ['🛰️','Satellite']] as $s => $meta)
                <button @click="setStyle('{{ $s }}')"
                        :class="mapStyle === '{{ $s }}' ? 'bg-emerald-500 border-emerald-400 text-white shadow-emerald-500/30 shadow-lg scale-105' : 'bg-[rgba(8,12,22,0.90)] border-white/[.09] text-zinc-500 hover:bg-white/[.06] hover:text-white'"
                        class="w-9 h-9 rounded-xl border backdrop-blur-xl text-[15px] flex items-center justify-center transition-all duration-200 shadow-lg" title="{{ $meta[1] }}">{{ $meta[0] }}</button>
            @endforeach
        </div>

        <div class="absolute bottom-6 right-4 z-[800] flex flex-col gap-2">

            <button @click="locateMe()"
                    :class="locating ? 'text-emerald-400 border-emerald-500/50 bg-emerald-500/[.10]' : 'text-zinc-500 border-white/[.09] hover:text-emerald-400 hover:border-emerald-500/25 hover:bg-emerald-500/[.06]'"
                    class="w-11 h-11 rounded-xl bg-[rgba(8,12,22,0.92)] backdrop-blur-xl border flex items-center justify-center shadow-lg transition-all" title="Locate me">
                <svg class="w-5 h-5" :class="locating ? 'animate-pulse' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v2m0 16v2M2 12h2m16 0h2"/><circle cx="12" cy="12" r="7" stroke-width="1.5" opacity=".5"/></svg>
            </button>

            <button @click="fitAll()"
                    class="w-11 h-11 rounded-xl bg-[rgba(8,12,22,0.92)] backdrop-blur-xl border border-white/[.09] text-zinc-500 hover:text-white hover:border-white/20 hover:bg-white/[.06] flex items-center justify-center shadow-lg transition-all" title="Fit all destinations">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
            </button>

            <button @click="toggleCompass()"
                    :class="compassActive ? 'border-emerald-500/40 bg-emerald-500/[.06]' : 'border-white/[.09] hover:border-white/20'"
                    class="w-11 h-11 rounded-xl bg-[rgba(8,12,22,0.92)] backdrop-blur-xl border flex flex-col items-center justify-center shadow-lg transition-all" title="Compass">
                <div x-ref="compassRose" class="w-7 h-7 transition-transform duration-75">
                    <svg viewBox="0 0 28 28" class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="14,3 12.5,14 14,12.5 15.5,14" fill="#ef4444"/>
                        <polygon points="14,25 12.5,14 14,15.5 15.5,14" fill="#e2e8f0" opacity=".28"/>
                        <circle cx="14" cy="14" r="2.2" fill="#f8fafc"/>
                        <text x="14" y="1.8" text-anchor="middle" fill="#ef4444" font-size="3.5" font-weight="700">N</text>
                    </svg>
                </div>
                <span class="text-[9px] font-bold leading-none mt-0.5" :class="compassActive ? 'text-emerald-400' : 'text-zinc-700'" x-text="compassActive ? compassDeg + '°' : ''"></span>
            </button>
        </div>

        <div x-show="routeInfo"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="opacity-0 translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-180"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-4"
             class="absolute bottom-6 left-4 z-[800] lg:left-4">
            <div class="route-glass flex items-center gap-4 px-4 py-3 max-w-[300px]">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/15 border border-emerald-500/25 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4.5 h-4.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-[10px] uppercase tracking-wider text-zinc-600 font-semibold">Route to</p>
                    <p class="text-[12.5px] font-bold text-white truncate mt-0.5" x-text="routeInfo?.name ?? ''"></p>
                    <p class="text-[11px] font-semibold mt-0.5" :class="routeInfo?.duration ? 'text-emerald-400' : 'text-amber-400'">
                        <span x-text="(routeInfo?.distance ?? '?') + ' km'"></span>
                        <span x-show="routeInfo?.duration" x-text="' · ~' + routeInfo?.duration + ' min'"></span>
                        <span x-show="!routeInfo?.duration" class="text-zinc-600"> (straight-line)</span>
                    </p>
                </div>
                <button @click="clearRoute()"
                        class="flex-shrink-0 w-7 h-7 rounded-lg bg-white/[.05] border border-white/[.08] flex items-center justify-center text-zinc-600 hover:text-white hover:bg-white/[.10] transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Toast --}}
    <div id="xtoast"></div>

    {{-- Leaflet JS --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    <script>
    function mapExplorer() {
        return {
            map          : null,
            clusters     : null,
            tileLayer    : null,
            routeLayer   : null,
            userMarker   : null,
            userCoords   : null,
            mapStyle     : 'dark',
            mapReady     : false,
            locating     : false,
            routeInfo    : null,
            compassActive: false,
            compassDeg   : 0,
            _orientFn    : null,

            boot() {
                window.__mapCtrl = this;
                const poll = setInterval(() => {
                    if (typeof L !== 'undefined' && typeof L.markerClusterGroup !== 'undefined') {
                        clearInterval(poll);
                        this.$nextTick(() => this.initMap());
                    }
                }, 80);
            },

            initMap() {
                const el = this.$refs.mapEl;
                if (!el || el.offsetHeight < 10) { setTimeout(() => this.initMap(), 200); return; }

                this.map = L.map(el, {
                    center: [10.9010, 123.0706],
                    zoom  : 12,
                    zoomControl: true,
                });

                this.tileLayer = L.tileLayer('', {
                    maxZoom    : 19,
                    attribution: '© <a href="https://carto.com">CARTO</a>',
                }).addTo(this.map);

                this.setStyle(this.mapStyle);
                this.plotMarkers();

                setTimeout(() => { this.mapReady = true; }, 1000);

                new MutationObserver(() => {
                    if (this.mapStyle !== 'satellite') {
                        const dark = document.documentElement.classList.contains('dark');
                        this.tileLayer.setUrl(dark
                            ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                            : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png');
                    }
                }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

                window.addEventListener('resize', () => setTimeout(() => this.map?.invalidateSize(), 120));

                navigator.geolocation?.getCurrentPosition(
                    pos => {
                        this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        this.placeUserMarker(false);
                        this._updateDistanceBadges();
                    },
                    () => {}
                );
            },

            setStyle(style) {
                const urls = {
                    dark     : 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                    light    : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                    satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                };
                this.mapStyle = style;
                this.tileLayer?.setUrl(urls[style] ?? urls.dark);
            },

            plotMarkers() {
                if (this.clusters) this.map.removeLayer(this.clusters);

                this.clusters = L.markerClusterGroup({
                    maxClusterRadius  : 52,
                    showCoverageOnHover: false,
                    spiderfyOnMaxZoom : true,
                });

                window.__mapTenants.forEach((t, tenantIdx) => {
                    const coords = t.coordinates;
                    if (!coords?.length) return;

                    coords.forEach((coord, coordIdx) => {
                        const color    = window.__pinColor(tenantIdx);
                        const isParent = coord.type === 'parent' || coordIdx === 0;
                        const logoUrl  = t.logo ? `/storage/${t.logo}` : null;

                        let icon;
                        if (isParent) {
                            // Parent marker: logo or coloured initial
                            const html = logoUrl
                                ? `<div class="logo-marker" style="--c:${color}">
                                       <img src="${logoUrl}" alt="${t.name}" loading="lazy">
                                   </div>`
                                : `<div class="logo-marker" style="--c:${color}">
                                       <div class="fallback" style="background:${color}">
                                           ${t.name.substring(0,2).toUpperCase()}
                                       </div>
                                   </div>`;

                            icon = L.divIcon({
                                className: '',
                                html,
                                iconSize  : [44, 44],
                                iconAnchor: [22, 22],
                            });
                        } else {
                            // Child marker: small coloured dot
                            const html = `<div class="child-marker" style="--c:${color}"></div>`;
                            icon = L.divIcon({
                                className: '',
                                html,
                                iconSize  : [18, 18],
                                iconAnchor: [9, 9],
                            });
                        }

                        const marker = L.marker([coord.lat, coord.lng], { icon })
                            .bindPopup(
                                () => window.__buildPopup(tenantIdx, this.userCoords, coordIdx),
                                { maxWidth: 308, minWidth: 264, closeButton: true }
                            );

                        this.clusters.addLayer(marker);
                    });
                });

                this.map.addLayer(this.clusters);
                this.$nextTick(() => this.fitAll());
            },

            refreshTenants(newTenants) {
                window.__mapTenants = newTenants;
                this.plotMarkers();
                if (this.userCoords) this._updateDistanceBadges();
            },

            fitAll() {
                const pts = [];
                window.__mapTenants.forEach(t => (t.coordinates || []).forEach(c => pts.push([c.lat, c.lng])));
                if (!pts.length) return;
                if (pts.length === 1) { this.map.setView(pts[0], 15); return; }
                const b = L.latLngBounds(pts);
                if (b.isValid()) this.map.fitBounds(b, { padding: [60, 60], maxZoom: 15 });
            },

            flyTo(tenant) {
                if (!tenant.coordinates?.length) return;
                const coord = tenant.coordinates[0];
                this.map.flyTo([coord.lat, coord.lng], 16, { duration: 1.35, easeLinearity: 0.26 });

                setTimeout(() => {
                    this.clusters?.eachLayer(mk => {
                        const p = mk.getLatLng();
                        if (Math.abs(p.lat - coord.lat) < 0.0003 && Math.abs(p.lng - coord.lng) < 0.0003) {
                            mk.openPopup();
                        }
                    });
                }, 1350);
            },

            enableDistanceSort() {
                if (!this.userCoords) {
                    this.locateMe().then(() => this._sortSidebarByDistance());
                } else {
                    this._sortSidebarByDistance();
                }
            },

            _sortSidebarByDistance() {
                if (!this.userCoords) return;
                const rows = [...document.querySelectorAll('[data-tenant-id]')];
                rows.sort((a, b) => {
                    const getKm = el => {
                        const badge = el.querySelector('.xdist-badge');
                        if (!badge) return 9999;
                        const lat = parseFloat(badge.dataset.lat);
                        const lng = parseFloat(badge.dataset.lng);
                        if (isNaN(lat) || isNaN(lng)) return 9999;
                        return parseFloat(window.__haversine(this.userCoords.lat, this.userCoords.lng, lat, lng));
                    };
                    return getKm(a) - getKm(b);
                });
                const list = rows[0]?.parentElement;
                if (list) rows.forEach(r => list.appendChild(r));
                __xToast('Sorted by distance 📍');
            },

            _updateDistanceBadges() {
                if (!this.userCoords) return;
                document.querySelectorAll('.xdist-badge').forEach(el => {
                    const lat = parseFloat(el.dataset.lat);
                    const lng = parseFloat(el.dataset.lng);
                    if (!isNaN(lat) && !isNaN(lng)) {
                        el.textContent = `${window.__haversine(this.userCoords.lat, this.userCoords.lng, lat, lng)} km`;
                        el.classList.remove('hidden');
                    }
                });
            },

            locateMe() {
                return new Promise((resolve, reject) => {
                    if (!navigator.geolocation) {
                        __xToast('Geolocation not supported');
                        return reject();
                    }
                    this.locating = true;
                    navigator.geolocation.getCurrentPosition(
                        pos => {
                            this.locating = false;
                            this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                            this.placeUserMarker(false);
                            this.map.flyTo([pos.coords.latitude, pos.coords.longitude], 14, { duration: 1.2 });
                            this._updateDistanceBadges();
                            __xToast('Location found ✓');
                            resolve(this.userCoords);
                        },
                        () => {
                            this.locating = false;
                            __xToast('Location access denied');
                            reject();
                        }
                    );
                });
            },

            placeUserMarker(panTo) {
                if (!this.userCoords) return;
                const { lat, lng } = this.userCoords;
                if (this.userMarker) this.map.removeLayer(this.userMarker);

                const icon = L.divIcon({
                    className: '',
                    html: `<div class="user-dot" style="
                        width:14px;height:14px;border-radius:50%;
                        background:#22c55e;border:3px solid #fff;
                        box-shadow:0 0 0 4px rgba(34,197,94,.25);
                    "></div>`,
                    iconSize  : [14, 14],
                    iconAnchor: [7, 7],
                });

                this.userMarker = L.marker([lat, lng], { icon, zIndexOffset: 1000 })
                    .bindPopup('<span style="color:#f1f5f9;font-weight:700;font-size:13px;font-family:Outfit,sans-serif;">📍 You are here</span>')
                    .addTo(this.map);

                if (panTo) this.map.panTo([lat, lng]);
            },

            getDirections(tenantIdx, coordIdx = 0) {
                const t = window.__mapTenants[tenantIdx];
                if (!t?.coordinates?.length) return;
                const coord = t.coordinates[coordIdx];
                if (!coord?.lat || !coord?.lng) return;

                const proceed = () => this._fetchRoute(coord, t.name);
                if (!this.userCoords) {
                    this.locateMe().then(proceed).catch(() => {});
                } else {
                    proceed();
                }
            },

            _fetchRoute(coord, name) {
                if (!this.userCoords) return;
                const { lat: uLat, lng: uLng } = this.userCoords;

                this.map.fitBounds(
                    L.latLngBounds([[uLat, uLng], [coord.lat, coord.lng]]),
                    { padding: [80, 80] }
                );

                const osrmUrl = `https://router.project-osrm.org/route/v1/driving/`
                              + `${uLng},${uLat};${coord.lng},${coord.lat}`
                              + `?overview=full&geometries=geojson`;

                fetch(osrmUrl)
                    .then(r => r.json())
                    .then(data => {
                        if (this.routeLayer) this.map.removeLayer(this.routeLayer);

                        if (data.code === 'Ok' && data.routes?.length) {
                            const route = data.routes[0];
                            this.routeLayer = L.geoJSON(route.geometry, {
                                style: {
                                    color   : '#22c55e',
                                    weight  : 5,
                                    opacity : 0.88,
                                    lineCap : 'round',
                                    lineJoin: 'round',
                                },
                            }).addTo(this.map);

                            this.routeInfo = {
                                distance: (route.distance / 1000).toFixed(1),
                                duration: Math.round(route.duration / 60),
                                name,
                            };
                        } else {
                            this.routeLayer = L.polyline([[uLat, uLng], [coord.lat, coord.lng]], {
                                color    : '#f59e0b',
                                weight   : 3,
                                dashArray: '10 8',
                                opacity  : 0.78,
                            }).addTo(this.map);

                            this.routeInfo = {
                                distance: window.__haversine(uLat, uLng, coord.lat, coord.lng),
                                duration: null,
                                name,
                            };
                        }
                    })
                    .catch(() => {
                        this.routeInfo = {
                            distance: window.__haversine(uLat, uLng, coord.lat, coord.lng),
                            duration: null,
                            name,
                        };
                    });
            },

            clearRoute() {
                if (this.routeLayer) { this.map.removeLayer(this.routeLayer); this.routeLayer = null; }
                this.routeInfo = null;
            },

            toggleCompass() {
                if (this.compassActive) {
                    this.compassActive = false;
                    if (this._orientFn) window.removeEventListener('deviceorientation', this._orientFn);
                    this._orientFn = null;
                    if (this.$refs.compassRose) this.$refs.compassRose.style.transform = '';
                    this.compassDeg = 0;
                    return;
                }

                const activate = () => {
                    this.compassActive = true;
                    this._orientFn = (e) => {
                        const heading = e.webkitCompassHeading != null
                            ? e.webkitCompassHeading
                            : (e.alpha != null ? 360 - e.alpha : null);
                        if (heading == null) return;
                        this.compassDeg = Math.round(heading);
                        if (this.$refs.compassRose)
                            this.$refs.compassRose.style.transform = `rotate(${-heading}deg)`;
                    };
                    window.addEventListener('deviceorientation', this._orientFn, true);
                    __xToast('Compass active');
                };

                if (typeof DeviceOrientationEvent?.requestPermission === 'function') {
                    DeviceOrientationEvent.requestPermission()
                        .then(r => { if (r === 'granted') activate(); })
                        .catch(() => {});
                } else {
                    activate();
                }
            },
        };
    }
    </script>
</div>

@push('scripts')
<script>
(function() {
    const params     = new URLSearchParams(window.location.search);
    const tenantId   = params.get('fly_to');
    const directions = params.get('directions');
    if (!tenantId) return;

    function tryAct() {
        const ctrl    = window.__mapCtrl;
        const tenants = window.__mapTenants;
        if (!ctrl || !ctrl.map || !tenants?.length) { setTimeout(tryAct, 200); return; }

        const idx    = tenants.findIndex(t => t.id == tenantId);
        if (idx === -1) return;
        const tenant = tenants[idx];
        const coord  = tenant.coordinates?.[0];
        if (!coord) return;

        ctrl.map.flyTo([coord.lat, coord.lng], 16, { duration: 1.4, easeLinearity: 0.28 });
        setTimeout(() => {
            ctrl.clusters?.eachLayer(mk => {
                const p = mk.getLatLng();
                if (Math.abs(p.lat - coord.lat) < 0.0003 && Math.abs(p.lng - coord.lng) < 0.0003)
                    mk.openPopup();
            });
        }, 1400);

        if (directions === '1' || directions === 'true')
            setTimeout(() => ctrl.getDirections(idx, 0), 1600);
    }

    document.addEventListener('livewire:navigated', tryAct, { once: true });
    if (document.readyState === 'complete') tryAct();
    else window.addEventListener('load', tryAct, { once: true });
})();
</script>
@endpush