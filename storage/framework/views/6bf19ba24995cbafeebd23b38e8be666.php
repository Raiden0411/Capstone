<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
?>




<div class="relative z-10 h-[calc(100vh-64px)] flex overflow-hidden">

    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css"/>

    <style>
        /* ── Leaflet theme overrides ── */
        .leaflet-container { background: transparent !important; }

        .leaflet-popup-content-wrapper {
            background    : rgba(12, 17, 29, 0.94) !important;
            backdrop-filter: blur(20px) !important;
            border-radius : 16px !important;
            border        : 1px solid rgba(255,255,255,0.09) !important;
            box-shadow    : 0 24px 48px rgba(0,0,0,0.55) !important;
            color         : #f1f5f9 !important;
        }
        .leaflet-popup-tip        { background: rgba(12,17,29,0.94) !important; }
        .leaflet-popup-content    { margin: 14px 16px !important; }
        .leaflet-popup-close-button {
            color    : #94a3b8 !important;
            font-size: 18px !important;
            top      : 10px !important;
            right    : 12px !important;
            transition: color .15s;
        }
        .leaflet-popup-close-button:hover { color: #fff !important; }

        .leaflet-control-zoom {
            border       : none !important;
            border-radius: 14px !important;
            overflow     : hidden;
            box-shadow   : 0 4px 24px rgba(0,0,0,0.45) !important;
        }
        .leaflet-control-zoom a {
            background    : rgba(12,17,29,0.88) !important;
            backdrop-filter: blur(8px) !important;
            color         : #cbd5e1 !important;
            border        : 1px solid rgba(255,255,255,0.10) !important;
            transition    : all .2s !important;
        }
        .leaflet-control-zoom a:hover {
            background : rgba(34,197,94,0.20) !important;
            color      : #22c55e !important;
        }

        /* Cluster colours */
        .marker-cluster-small      { background: rgba(34,197,94,0.15) !important; }
        .marker-cluster-small  div { background: #22c55e !important; color:#fff !important; font-weight:700; border-radius:50%; }
        .marker-cluster-medium     { background: rgba(6,182,212,0.18) !important; }
        .marker-cluster-medium div { background: #06b6d4 !important; color:#fff !important; font-weight:700; border-radius:50%; }
        .marker-cluster-large      { background: rgba(245,158,11,0.22) !important; }
        .marker-cluster-large  div { background: #f59e0b !important; color:#000 !important; font-weight:700; border-radius:50%; }

        /* Custom map pins */
        .map-pin-wrap {
            display        : flex;
            align-items    : center;
            justify-content: center;
            width          : 26px;
            height         : 26px;
            cursor         : pointer;
        }
        .map-pin-dot {
            width        : 12px;
            height       : 12px;
            border-radius: 50%;
            border       : 2.5px solid rgba(255,255,255,0.85);
            box-shadow   : 0 0 0 3px var(--pin-color,#22c55e), 0 0 14px var(--pin-color,#22c55e);
            transition   : transform .2s ease, box-shadow .2s ease;
        }
        .map-pin-wrap:hover .map-pin-dot,
        .map-pin-dot.active {
            transform  : scale(1.55);
            box-shadow : 0 0 0 4px rgba(255,255,255,.85), 0 0 22px var(--pin-color,#22c55e);
        }

        /* Route direction panel */
        .route-panel {
            background    : rgba(12,17,29,0.93);
            backdrop-filter: blur(18px);
            border        : 1px solid rgba(34,197,94,0.30);
            border-radius : 16px;
            color         : #f1f5f9;
            padding       : 12px 18px;
        }

        /* User location pulse */
        @keyframes userPulse {
            0%   { box-shadow: 0 0 0 0   rgba(34,197,94,.55); }
            70%  { box-shadow: 0 0 0 14px rgba(34,197,94,.0);  }
            100% { box-shadow: 0 0 0 0   rgba(34,197,94,.0);  }
        }
        .user-location-icon { animation: userPulse 2s infinite; }

        /* Sidebar scrollbar */
        .sb-scroll::-webkit-scrollbar       { width: 4px; }
        .sb-scroll::-webkit-scrollbar-track { background: transparent; }
        .sb-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 4px; }
    </style>

    
    <script>
        window.__mapTenants = <?php echo json_encode($this->tenants, 15, 512) ?>;

        window.__pinColor = function(idx) {
            const hues = [142, 200, 35, 280, 48, 330, 15, 170, 210, 260, 95, 55, 310, 120, 190];
            return `hsl(${hues[idx % hues.length]}, 68%, 62%)`;
        };

        window.__haversine = function(lat1, lng1, lat2, lng2) {
            const R  = 6371;
            const dL = (lat2 - lat1) * Math.PI / 180;
            const dN = (lng2 - lng1) * Math.PI / 180;
            const a  = Math.sin(dL/2)**2 +
                       Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dN/2)**2;
            return (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))).toFixed(1);
        };

        /**
         * Build popup HTML. Lives in <script> so we can use real template literals
         * without any &quot; escaping nonsense.
         */
        window.__buildPopup = function(tenantIdx, userCoords) {
            const t = window.__mapTenants[tenantIdx];
            if (!t) return '';

            const color = window.__pinColor(tenantIdx);

            const avatar = t.logo
                ? `<img src="/storage/${t.logo}" alt="${t.name}"
                        style="width:42px;height:42px;border-radius:50%;object-fit:cover;
                               border:2px solid ${color};flex-shrink:0;">`
                : `<div style="width:42px;height:42px;border-radius:50%;background:${color};
                               display:flex;align-items:center;justify-content:center;
                               color:#fff;font-weight:700;font-size:13px;flex-shrink:0;">
                       ${t.name.substring(0,2).toUpperCase()}
                   </div>`;

            const distRow = userCoords
                ? `<p style="font-size:11px;color:${color};margin:0 0 10px;
                              display:flex;align-items:center;gap:4px;">
                       <svg width="11" height="11" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2.5">
                           <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/>
                           <circle cx="12" cy="10" r="3"/>
                       </svg>
                       ${window.__haversine(userCoords.lat, userCoords.lng,
                           parseFloat(t.latitude), parseFloat(t.longitude))} km from you
                   </p>`
                : '';

            return `
<div style="min-width:248px;max-width:284px;">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
    ${avatar}
    <div style="min-width:0;">
      <h3 style="font-size:15px;font-weight:700;margin:0 0 3px;color:#f1f5f9;
                 white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${t.name}</h3>
      <p style="font-size:11px;color:#94a3b8;margin:0;">${t.type_of_tenant?.type ?? 'Business'}</p>
    </div>
  </div>
  ${t.address ? `<p style="font-size:11px;color:#64748b;margin:0 0 8px;line-height:1.6;">${t.address}</p>` : ''}
  ${distRow}
  <a href="/business/${t.slug}/offerings"
     style="display:block;width:100%;padding:9px 0;text-align:center;
            font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
            border-radius:10px;background:${color};color:#fff;text-decoration:none;
            margin-bottom:8px;transition:opacity .18s;"
     onmouseover="this.style.opacity='.8'" onmouseout="this.style.opacity='1'">
     View Offerings →
  </a>
  <button onclick="window.__mapCtrl?.getDirections(${tenantIdx})"
     style="display:block;width:100%;padding:8px 0;font-size:11.5px;font-weight:700;
            text-transform:uppercase;letter-spacing:.07em;border-radius:10px;
            border:1.5px solid ${color};color:${color};background:transparent;cursor:pointer;
            transition:all .18s;"
     onmouseover="this.style.background='${color}';this.style.color='#fff'"
     onmouseout="this.style.background='transparent';this.style.color='${color}'">
     Get Directions
  </button>
</div>`;
        };
    </script>

    
    
    
    <div x-data="{
             open: window.innerWidth >= 1024,
             handleResize() { if (window.innerWidth >= 1024) this.open = true; }
         }"
         @resize.window.debounce.120ms="handleResize()"
         class="relative z-50 flex-shrink-0">

        
        <div x-show="open && window.innerWidth < 1024"
             x-transition:enter="transition-opacity duration-200"
             x-transition:leave="transition-opacity duration-150"
             @click="open = false"
             class="lg:hidden fixed inset-0 z-20 bg-black/60 backdrop-blur-sm"></div>

        
        <button @click="open = !open"
                class="lg:hidden fixed top-[76px] left-4 z-40
                       flex items-center gap-2 px-4 py-2.5 rounded-xl shadow-xl
                       bg-[rgba(12,17,29,0.92)] backdrop-blur-xl border border-white/[0.10]
                       text-white text-sm font-semibold transition hover:bg-white/[0.08]">
            <svg class="w-4 h-4 transition-transform duration-200"
                 :class="open ? 'rotate-90' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path x-show="!open" stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                <path x-show="open"  stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <span x-text="open ? 'Close' : 'Destinations'"></span>
        </button>

        
        <aside x-show="open"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="-translate-x-full opacity-0"
               x-transition:enter-end="translate-x-0 opacity-100"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="translate-x-0 opacity-100"
               x-transition:leave-end="-translate-x-full opacity-0"
               class="fixed top-[64px] bottom-0 left-0 z-30 lg:relative lg:top-auto lg:h-full
                      w-[340px] lg:w-[380px] flex flex-col shadow-2xl
                      bg-[rgba(10,14,24,0.96)] backdrop-blur-2xl border-r border-white/[0.06]">

            
            <div class="px-5 pt-5 pb-4 border-b border-white/[0.06]">
                <div class="flex items-start justify-between mb-3">
                    <div>
                        <h2 class="text-[17px] font-bold text-white tracking-tight">Explore Victorias</h2>
                        <p class="text-xs text-zinc-600 mt-0.5">Discover local destinations</p>
                    </div>
                    <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-full
                                bg-emerald-500/10 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-wider">
                            <?php echo e($this->tenants->count()); ?> active
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <a href="<?php echo e(route('home')); ?>" wire:navigate
                       class="flex items-center gap-1 text-zinc-500 hover:text-white transition">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Home
                    </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                        <span class="w-px h-3 bg-white/10"></span>
                        <a href="<?php echo e(route('my-bookings')); ?>" wire:navigate
                           class="flex items-center gap-1 text-emerald-500 hover:text-emerald-400 transition">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            Reservations
                        </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div class="px-5 py-4 border-b border-white/[0.06] space-y-3">
                
                <div class="relative">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-zinc-600 pointer-events-none"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model.live.debounce.300ms="search"
                           placeholder="Search destinations…"
                           class="w-full bg-white/[0.04] border border-white/[0.08] rounded-xl
                                  py-2.5 pl-10 pr-9 text-sm text-white placeholder-zinc-700
                                  focus:outline-none focus:border-emerald-500/50
                                  focus:ring-1 focus:ring-emerald-500/25 transition">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?>
                        <button wire:click="$set('search','')"
                                class="absolute right-3 top-1/2 -translate-y-1/2
                                       text-zinc-600 hover:text-white transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="flex gap-1.5 overflow-x-auto pb-0.5 scrollbar-none">
                    <button wire:click="$set('categoryFilter','')"
                            class="shrink-0 px-3 py-1.5 rounded-full text-[11px] font-bold
                                   uppercase tracking-wider transition-all border
                                   <?php echo e($categoryFilter === ''
                                       ? 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                                       : 'border-white/[0.08] text-zinc-600 hover:border-white/20 hover:text-white'); ?>">
                        All
                    </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <button wire:click="$set('categoryFilter','<?php echo e($cat); ?>')"
                                class="shrink-0 px-3 py-1.5 rounded-full text-[11px] font-bold
                                       uppercase tracking-wider transition-all border
                                       <?php echo e($categoryFilter === $cat
                                           ? 'bg-emerald-600 border-emerald-600 text-white shadow-lg shadow-emerald-500/20'
                                           : 'border-white/[0.08] text-zinc-600 hover:border-white/20 hover:text-white'); ?>">
                            <?php echo e($cat); ?>

                        </button>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                
                <div class="flex gap-2 bg-white/[0.03] rounded-xl p-1 border border-white/[0.06]">
                    <button wire:click="$set('sortBy','name')"
                            class="flex-1 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition
                                   <?php echo e($sortBy === 'name' ? 'bg-white/10 text-white' : 'text-zinc-600 hover:text-zinc-300'); ?>">
                        A – Z
                    </button>
                    <button wire:click="$set('sortBy','distance')"
                            class="flex-1 py-1.5 rounded-lg text-[11px] font-bold uppercase tracking-wider transition
                                   <?php echo e($sortBy === 'distance' ? 'bg-white/10 text-white' : 'text-zinc-600 hover:text-zinc-300'); ?>"
                            title="Nearest uses your live location">
                        Nearest
                    </button>
                </div>
            </div>

            
            <div class="flex-1 overflow-y-auto sb-scroll py-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dest-'.e($tenant->id).''; ?>wire:key="dest-<?php echo e($tenant->id); ?>"
                         wire:click="flyToTenant(<?php echo e($tenant->id); ?>)"
                         class="group cursor-pointer mx-3 my-0.5 px-3 py-3
                                rounded-xl flex items-center gap-3 transition-all duration-150
                                border hover:bg-white/[0.035]
                                <?php echo e($highlightedId === $tenant->id
                                    ? 'bg-emerald-500/[0.07] border-emerald-500/20'
                                    : 'border-transparent hover:border-white/[0.06]'); ?>">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->logo): ?>
                            <img src="<?php echo e(asset('storage/'.$tenant->logo)); ?>" alt="<?php echo e($tenant->name); ?>"
                                 class="w-11 h-11 rounded-full object-cover border border-white/10 flex-shrink-0">
                        <?php else: ?>
                            <?php $hue = ($index * 137) % 360; ?>
                            <div class="w-11 h-11 rounded-full flex items-center justify-center
                                        text-sm font-bold flex-shrink-0 text-white"
                                 style="background:hsl(<?php echo e($hue); ?>,58%,44%);
                                        box-shadow:0 0 16px hsl(<?php echo e($hue); ?>,58%,44%,.35);">
                                <?php echo e(strtoupper(substr($tenant->name, 0, 2))); ?>

                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="flex-1 min-w-0">
                            <p class="text-[13.5px] font-semibold text-white truncate leading-snug">
                                <?php echo e($tenant->name); ?>

                            </p>
                            <p class="text-[11px] text-zinc-600 mt-0.5">
                                <?php echo e($tenant->typeOfTenant?->type ?? 'Business'); ?>

                            </p>
                        </div>

                        
                        <button onclick="event.stopPropagation(); window.__mapCtrl?.getDirections(<?php echo e($index); ?>)"
                                class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-lg
                                       border border-transparent text-zinc-700
                                       hover:text-emerald-400 hover:border-emerald-500/25
                                       hover:bg-emerald-500/[0.08]
                                       opacity-0 group-hover:opacity-100 transition-all duration-150"
                                title="Get directions">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                      d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0
                                         011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553
                                         2.276A1 1 0 0021 18.382V7.618a1 1 0
                                         00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                        </button>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="text-center py-16 px-6">
                        <div class="w-14 h-14 rounded-2xl bg-white/[0.03] border border-white/[0.06]
                                    flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-zinc-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            </svg>
                        </div>
                        <p class="text-zinc-500 text-sm">No destinations found.</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search || $categoryFilter): ?>
                            <button wire:click="$set('search',''); $set('categoryFilter','')"
                                    class="mt-3 text-emerald-500 hover:text-emerald-400 text-xs transition">
                                ← Clear filters
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="px-5 py-3 border-t border-white/[0.06] flex justify-between">
                <span class="text-[11px] text-zinc-700">
                    <?php echo e($this->tenants->count()); ?> destination<?php echo e($this->tenants->count() !== 1 ? 's' : ''); ?>

                </span>
                <span class="text-[11px] text-zinc-700">
                    <?php echo e($this->categories->count()); ?> <?php echo e(Str::plural('category', $this->categories->count())); ?>

                </span>
            </div>
        </aside>
    </div>

    
    
    
    <div x-data="mapExplorer()"
         x-init="boot()"
         @fly-to-tenant.window="flyTo($event.detail.tenant)"
         class="flex-1 relative min-w-0 h-full">

        
        <div x-show="!mapReady"
             x-transition:leave="transition-opacity duration-700 ease-in"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 z-[900] flex flex-col items-center justify-center gap-4
                    bg-[rgba(10,14,24,0.97)]">
            <div class="w-11 h-11 rounded-full border-2 border-zinc-800 border-t-emerald-500 animate-spin"></div>
            <p class="text-sm text-zinc-600 font-medium tracking-wide">Rendering map…</p>
        </div>

        
        <div wire:ignore x-ref="mapEl" class="absolute inset-0 w-full h-full"></div>

        
        <div class="absolute top-4 right-4 z-[800] flex gap-1.5">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['dark' => ['🌑','Dark'], 'light' => ['🗺️','Light'], 'satellite' => ['🛰️','Satellite']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s => $meta): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <button @click="setStyle('<?php echo e($s); ?>')"
                        :class="mapStyle === '<?php echo e($s); ?>'
                            ? 'bg-emerald-600 border-emerald-600 text-white shadow-emerald-500/20 shadow-lg'
                            : 'bg-[rgba(12,17,29,0.88)] border-white/[0.10] text-zinc-400 hover:bg-white/[0.06] hover:text-white'"
                        class="w-9 h-9 rounded-xl border backdrop-blur-xl text-sm flex items-center
                               justify-center transition-all shadow-lg"
                        title="<?php echo e($meta[1]); ?>">
                    <?php echo e($meta[0]); ?>

                </button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        
        <div class="absolute bottom-6 right-4 z-[800] flex flex-col gap-2">

            
            <button @click="locateMe()"
                    :class="locating
                        ? 'text-emerald-400 border-emerald-500/50 bg-emerald-500/[0.10]'
                        : 'text-zinc-400 border-white/[0.10] hover:text-emerald-400 hover:border-emerald-500/25 hover:bg-emerald-500/[0.06]'"
                    class="w-11 h-11 rounded-xl bg-[rgba(12,17,29,0.90)] backdrop-blur-xl border
                           flex items-center justify-center shadow-lg transition-all"
                    title="Locate me">
                <svg class="w-5 h-5" :class="locating ? 'animate-pulse' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>

            
            <button @click="fitAll()"
                    class="w-11 h-11 rounded-xl bg-[rgba(12,17,29,0.90)] backdrop-blur-xl border border-white/[0.10]
                           text-zinc-400 hover:text-white hover:border-white/20 hover:bg-white/[0.06]
                           flex items-center justify-center shadow-lg transition-all"
                    title="Fit all destinations">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                </svg>
            </button>

            
            <button @click="toggleCompass()"
                    :class="compassActive
                        ? 'border-emerald-500/40 bg-emerald-500/[0.06]'
                        : 'border-white/[0.10] hover:border-white/20'"
                    class="w-11 h-11 rounded-xl bg-[rgba(12,17,29,0.90)] backdrop-blur-xl border
                           flex flex-col items-center justify-center shadow-lg transition-all
                           cursor-pointer relative overflow-hidden"
                    title="Compass (device orientation)">
                <div x-ref="compassRose" class="w-7 h-7 transition-transform duration-75">
                    <svg viewBox="0 0 28 28" class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="14,3 12.5,14 14,12.5 15.5,14" fill="#ef4444"/>
                        <polygon points="14,25 12.5,14 14,15.5 15.5,14" fill="#e2e8f0" opacity=".35"/>
                        <circle cx="14" cy="14" r="2.2" fill="#f8fafc"/>
                        <text x="14" y="1.5" text-anchor="middle" fill="#ef4444"
                              font-size="3.5" font-weight="700">N</text>
                        <text x="14" y="27.5" text-anchor="middle" fill="#94a3b8"
                              font-size="3" opacity=".5">S</text>
                    </svg>
                </div>
                <span class="text-[9px] font-bold leading-none mt-0.5"
                      :class="compassActive ? 'text-emerald-400' : 'text-zinc-700'"
                      x-text="compassActive ? compassDeg + '°' : ''"></span>
            </button>
        </div>

        
        <div x-show="routeInfo"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-3"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-3"
             class="absolute bottom-6 left-4 z-[800]">
            <div class="route-panel flex items-center gap-4">
                <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0
                             13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0
                             00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <div>
                    <p class="text-base font-bold text-white leading-tight"
                       x-text="(routeInfo?.distance ?? '?') + ' km'"></p>
                    <p class="text-xs text-zinc-500 mt-0.5"
                       x-text="routeInfo?.duration ? '~' + routeInfo.duration + ' min drive' : 'straight-line estimate'"></p>
                </div>
                <div class="w-px h-8 bg-white/[0.08] flex-shrink-0"></div>
                <div class="min-w-0 flex-1">
                    <p class="text-[10px] uppercase tracking-wider text-zinc-600">To</p>
                    <p class="text-xs font-semibold text-white truncate mt-0.5" x-text="routeInfo?.name ?? ''"></p>
                </div>
                <button @click="clearRoute()"
                        class="flex-shrink-0 w-7 h-7 rounded-lg bg-white/[0.05] border border-white/[0.08]
                               flex items-center justify-center text-zinc-500
                               hover:text-white hover:bg-white/[0.10] transition"
                        title="Clear route">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                              d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    
    <script>
    function mapExplorer() {
        return {
            // ── State ──────────────────────────────────────────
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

            // ── Boot ───────────────────────────────────────────
            boot() {
                // Expose controller so popup buttons can call getDirections()
                window.__mapCtrl = this;

                const poll = setInterval(() => {
                    if (typeof L !== 'undefined' && typeof L.markerClusterGroup !== 'undefined') {
                        clearInterval(poll);
                        this.$nextTick(() => this.initMap());
                    }
                }, 80);
            },

            // ── Map initialisation ─────────────────────────────
            initMap() {
                const el = this.$refs.mapEl;
                if (!el || el.offsetHeight < 10) {
                    setTimeout(() => this.initMap(), 200);
                    return;
                }

                this.map = L.map(el, {
                    center     : [10.9010, 123.0706],
                    zoom       : 12,
                    zoomControl: true,
                });

                this.tileLayer = L.tileLayer('', {
                    maxZoom    : 19,
                    attribution: '© <a href="https://carto.com">CARTO</a>',
                }).addTo(this.map);

                this.setStyle(this.mapStyle);
                this.plotMarkers();

                // Ready flag — set after a short delay as fallback
                setTimeout(() => { this.mapReady = true; }, 1200);

                // Sync tile style with dark-mode class changes
                new MutationObserver(() => {
                    if (this.mapStyle !== 'satellite') {
                        const dark = document.documentElement.classList.contains('dark');
                        this.tileLayer.setUrl(dark
                            ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                            : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png');
                    }
                }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

                window.addEventListener('resize', () => {
                    setTimeout(() => this.map?.invalidateSize(), 120);
                });

                // Silent background geolocation
                navigator.geolocation?.getCurrentPosition(
                    pos => {
                        this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        this.placeUserMarker(false);
                    },
                    () => {}
                );
            },

            // ── Tile style ─────────────────────────────────────
            setStyle(style) {
                const urls = {
                    dark     : 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                    light    : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                    satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                };
                this.mapStyle = style;
                this.tileLayer?.setUrl(urls[style] ?? urls.dark);
            },

            // ── Plot all tenant markers ────────────────────────
            plotMarkers() {
                if (this.clusters) this.map.removeLayer(this.clusters);

                this.clusters = L.markerClusterGroup({
                    maxClusterRadius  : 55,
                    showCoverageOnHover: false,
                    spiderfyOnMaxZoom : true,
                });

                window.__mapTenants.forEach((t, i) => {
                    if (!t.latitude || !t.longitude) return;

                    const color = window.__pinColor(i);
                    // FIX: template literal — no HTML entities, no &quot;
                    const icon = L.divIcon({
                        className : 'map-pin-wrap',
                        html      : `<div class="map-pin-dot" style="--pin-color:${color};background:${color};"></div>`,
                        iconSize  : [26, 26],
                        iconAnchor: [13, 13],
                    });

                    const marker = L.marker(
                        [parseFloat(t.latitude), parseFloat(t.longitude)],
                        { icon }
                    ).bindPopup(
                        // Lazy popup: built at open-time so userCoords is current
                        () => window.__buildPopup(i, this.userCoords),
                        { maxWidth: 300, minWidth: 256 }
                    );

                    this.clusters.addLayer(marker);
                });

                this.map.addLayer(this.clusters);
                this.$nextTick(() => this.fitAll());
            },

            // ── Fit map to all markers ─────────────────────────
            fitAll() {
                const pts = window.__mapTenants
                    .filter(t => t.latitude && t.longitude)
                    .map(t => [parseFloat(t.latitude), parseFloat(t.longitude)]);

                if (!pts.length) return;
                if (pts.length === 1) { this.map.setView(pts[0], 15); return; }

                const bounds = L.latLngBounds(pts);
                if (bounds.isValid()) {
                    this.map.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
                }
            },

            // ── Fly to a specific tenant ───────────────────────
            flyTo(tenant) {
                if (!tenant.latitude || !tenant.longitude) return;
                const lat = parseFloat(tenant.latitude);
                const lng = parseFloat(tenant.longitude);

                this.map.flyTo([lat, lng], 16, { duration: 1.4, easeLinearity: 0.28 });

                setTimeout(() => {
                    this.clusters?.eachLayer(mk => {
                        const p = mk.getLatLng();
                        if (Math.abs(p.lat - lat) < 0.0003 && Math.abs(p.lng - lng) < 0.0003) {
                            mk.openPopup();
                        }
                    });
                }, 1350);
            },

            // ── Locate me ─────────────────────────────────────
            locateMe() {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by your browser.');
                    return;
                }
                this.locating = true;
                navigator.geolocation.getCurrentPosition(
                    pos => {
                        this.locating = false;
                        this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        this.placeUserMarker(false);
                        this.map.flyTo([pos.coords.latitude, pos.coords.longitude], 14, { duration: 1.2 });
                    },
                    () => {
                        this.locating = false;
                        alert('Location access denied. Please enable location services.');
                    }
                );
            },

            // ── Place / refresh the user marker ───────────────
            placeUserMarker(panTo) {
                if (!this.userCoords) return;
                const { lat, lng } = this.userCoords;

                if (this.userMarker) this.map.removeLayer(this.userMarker);

                // FIX: template literal in divIcon
                const icon = L.divIcon({
                    className: '',
                    html: `<div style="
                        width:14px;height:14px;border-radius:50%;
                        background:#22c55e;border:3px solid #fff;
                        box-shadow:0 0 0 5px rgba(34,197,94,.28);
                        animation:userPulse 2s infinite;
                    "></div>`,
                    iconSize  : [14, 14],
                    iconAnchor: [7, 7],
                });

                this.userMarker = L.marker([lat, lng], { icon, zIndexOffset: 1000 })
                    .bindPopup('<span style="color:#f1f5f9;font-weight:700;font-size:13px;">📍 Your Location</span>')
                    .addTo(this.map);

                if (panTo) this.map.panTo([lat, lng]);
            },

            // ── Directions ────────────────────────────────────
            getDirections(tenantIdx) {
                const t = window.__mapTenants[tenantIdx];
                if (!t?.latitude || !t?.longitude) return;

                const proceed = () => this._fetchRoute(t);

                if (!this.userCoords) {
                    this.locating = true;
                    navigator.geolocation.getCurrentPosition(
                        pos => {
                            this.locating = false;
                            this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                            this.placeUserMarker(false);
                            proceed();
                        },
                        () => {
                            this.locating = false;
                            alert('Location access denied.');
                        }
                    );
                } else {
                    proceed(t);
                }
            },

            _fetchRoute(t) {
                if (!this.userCoords) return;
                const { lat: uLat, lng: uLng } = this.userCoords;
                const dLat = parseFloat(t.latitude);
                const dLng = parseFloat(t.longitude);

                // Fit to bounding box while we wait
                this.map.fitBounds(
                    L.latLngBounds([[uLat, uLng], [dLat, dLng]]),
                    { padding: [80, 80] }
                );

                const osrmUrl = `https://router.project-osrm.org/route/v1/driving/`
                              + `${uLng},${uLat};${dLng},${dLat}`
                              + `?overview=full&geometries=geojson`;

                fetch(osrmUrl)
                    .then(r => r.json())
                    .then(data => {
                        if (this.routeLayer) this.map.removeLayer(this.routeLayer);

                        if (data.code === 'Ok' && data.routes?.length) {
                            const route = data.routes[0];
                            this.routeLayer = L.geoJSON(route.geometry, {
                                style: {
                                    color  : '#22c55e',
                                    weight : 5,
                                    opacity: 0.85,
                                    lineCap: 'round',
                                    lineJoin: 'round',
                                },
                            }).addTo(this.map);

                            this.routeInfo = {
                                distance: (route.distance / 1000).toFixed(1),
                                duration: Math.round(route.duration / 60),
                                name    : t.name,
                            };
                        } else {
                            // Fallback: dashed straight line
                            this.routeLayer = L.polyline([[uLat, uLng], [dLat, dLng]], {
                                color    : '#f59e0b',
                                weight   : 3,
                                dashArray: '10 8',
                                opacity  : 0.75,
                            }).addTo(this.map);

                            this.routeInfo = {
                                distance: window.__haversine(uLat, uLng, dLat, dLng),
                                duration: null,
                                name    : t.name,
                            };
                        }
                    })
                    .catch(() => {
                        this.routeInfo = {
                            distance: window.__haversine(uLat, uLng, dLat, dLng),
                            duration: null,
                            name    : t.name,
                        };
                    });
            },

            clearRoute() {
                if (this.routeLayer) { this.map.removeLayer(this.routeLayer); this.routeLayer = null; }
                this.routeInfo = null;
            },

            // ── Compass (real DeviceOrientationEvent) ──────────
            // No fake simulation. Either real heading or nothing.
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
                        // webkitCompassHeading (iOS) or derive from alpha (Android)
                        const heading = e.webkitCompassHeading != null
                            ? e.webkitCompassHeading
                            : (e.alpha != null ? 360 - e.alpha : null);

                        if (heading == null) return;
                        this.compassDeg = Math.round(heading);
                        if (this.$refs.compassRose) {
                            this.$refs.compassRose.style.transform = `rotate(${-heading}deg)`;
                        }
                    };
                    window.addEventListener('deviceorientation', this._orientFn, true);
                };

                // iOS 13+ requires an explicit permission request
                if (typeof DeviceOrientationEvent?.requestPermission === 'function') {
                    DeviceOrientationEvent.requestPermission()
                        .then(result => { if (result === 'granted') activate(); })
                        .catch(() => {});
                } else {
                    // Android / desktop — no permission needed
                    activate();
                }
            },
        };
    }
    </script>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/edb49f3a.blade.php ENDPATH**/ ?>