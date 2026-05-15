<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
?>


<div class="relative z-10 h-[calc(100vh-64px)] flex flex-col overflow-hidden">

    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>

    
    <script>
        window.tenantData = <?php echo json_encode($this->tenants, 15, 512) ?>;
        window.tenantColor = (index) => {
            const hues = [142, 35, 200, 48, 170, 280, 15, 210, 330, 95, 260, 55, 310, 120, 190];
            const h = hues[index % hues.length];
            return `hsl(${h}, 70%, 60%)`;
        };

        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLng / 2) * Math.sin(dLng / 2);
            return (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))).toFixed(1);
        }

        window.popupHtml = (index, userCoords) => {
            const t = window.tenantData[index];
            if (!t) return '';

            const logoHtml = t.logo 
                ? `<img src="/storage/${t.logo}" alt="${t.name}" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid var(--color-brand-500);margin-right:10px;">`
                : `<div style="width:36px;height:36px;border-radius:50%;background:${window.tenantColor(index)};display:flex;align-items:center;justify-content:center;color:white;font-weight:bold;font-size:14px;margin-right:10px;">${t.name.substring(0,2).toUpperCase()}</div>`;

            const dist = userCoords
                ? `<div style="font-size:12px; color: var(--color-brand-500); display:flex; align-items:center; gap:4px; margin-bottom:8px;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    ${haversine(userCoords.lat, userCoords.lng, parseFloat(t.latitude), parseFloat(t.longitude))} km away
                </div>` : '';

            return `<div style="min-width:230px; color:#fff;">
                <div style="display:flex; align-items:center; margin-bottom:10px;">
                    ${logoHtml}
                    <div>
                        <h3 style="font-size:15px; font-weight:700; margin:0;">${t.name}</h3>
                        <p style="font-size:11px; color:#a0aec0; margin:0;">${t.type_of_tenant?.type || 'Business'}</p>
                    </div>
                </div>
                <p style="font-size:12px; color:#a0aec0; margin-bottom:12px;">${t.address || ''}</p>
                ${dist}
                <a href="/business/${t.slug}" wire:navigate
                   style="display:flex; align-items:center; justify-content:center; width:100%; padding:8px 0; font-size:12px; font-weight:600; border-radius:8px; background:var(--color-brand-600); color:#fff; text-decoration:none; margin-bottom:8px; transition:all 0.2s;"
                   onmouseover="this.style.background='var(--color-brand-500)'" onmouseout="this.style.background='var(--color-brand-600)'">View Business</a>
                <button onclick="window.requestDirections(${index})"
                   style="display:flex; align-items:center; justify-content:center; width:100%; padding:6px 0; font-size:12px; font-weight:600; border-radius:8px; border:1px solid var(--color-brand-500); color:var(--color-brand-500); background:transparent; cursor:pointer; transition:all 0.2s;"
                   onmouseover="this.style.background='var(--color-brand-500)'; this.style.color='#fff'" onmouseout="this.style.background='transparent'; this.style.color='var(--color-brand-500)'">Get Directions</button>
            </div>`;
        };
    </script>

    <style>
        /* Leaflet & cluster overrides – dark/light aware */
        .leaflet-container {
            background: transparent !important;
            border-radius: 1rem;
        }
        .dark .leaflet-container { background: transparent !important; }
        .leaflet-popup-content-wrapper {
            background: rgba(30, 41, 59, 0.9) !important;
            backdrop-filter: blur(16px);
            color: #f1f5f9 !important;
            border-radius: 0.8rem !important;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3) !important;
            border: 1px solid rgba(255,255,255,0.12) !important;
        }
        .dark .leaflet-popup-content-wrapper {
            background: rgba(15, 23, 42, 0.9) !important;
        }
        .leaflet-popup-tip { background: rgba(30, 41, 59, 0.9) !important; }
        .dark .leaflet-popup-tip { background: rgba(15, 23, 42, 0.9) !important; }
        .leaflet-popup-content { color: #cbd5e1 !important; font-size: 13px !important; margin: 12px !important; }
        .leaflet-control-zoom a {
            background: rgba(30,41,59,0.8) !important;
            backdrop-filter: blur(8px);
            color: #fff !important;
            border: 1px solid rgba(255,255,255,0.12) !important;
        }
        .dark .leaflet-control-zoom a { background: rgba(15,23,42,0.8) !important; }
        .leaflet-control-zoom a:hover { background: rgba(34,197,94,0.2) !important; }
        .marker-cluster-small  { background: rgba(34,197,94,0.2) !important; }
        .marker-cluster-small div  { background: #22c55e !important; color: #fff !important; }
        .marker-cluster-medium { background: rgba(6,182,212,0.25) !important; }
        .marker-cluster-medium div { background: #06b6d4 !important; color: #fff !important; }
        .marker-cluster-large  { background: rgba(250,204,21,0.3) !important; }
        .marker-cluster-large div  { background: #facc15 !important; color: #000 !important; }

        /* Custom pins */
        .custom-pin { display: flex; align-items: center; justify-content: center; }
        .pin-dot {
            width: 14px; height: 14px; border-radius: 50%;
            border: 2px solid #fff; box-shadow: 0 0 8px currentColor;
            transition: transform 0.2s; cursor: pointer;
        }
        .pin-dot:hover { transform: scale(1.3); }

        .direction-panel {
            background: rgba(30,41,59,0.9);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 0.75rem;
            color: #fff;
            padding: 8px 14px;
            font-size: 13px; font-weight: 600;
        }
    </style>

    
    <div class="flex-1 flex">

        
        <div x-data="{ open: window.innerWidth >= 1024 }"
             @resize.window="open = window.innerWidth >= 1024"
             class="relative z-50">
            <button @click="open = !open"
                    class="lg:hidden fixed top-[80px] left-4 z-40 glass rounded-xl px-4 py-2.5 shadow-lg text-sm font-semibold text-white flex items-center gap-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span x-text="open ? 'Close' : 'Destinations'"></span>
            </button>

            <div x-show="open" x-transition:enter="transition-transform duration-300" x-transition:leave="transition-transform duration-200"
                 :class="open ? 'translate-x-0' : '-translate-x-full'"
                 class="lg:translate-x-0 fixed top-[64px] bottom-0 left-0 w-80 lg:w-96 bg-black/60 backdrop-blur-xl border-r border-white/10 z-30 flex flex-col shadow-2xl">

                
                <div class="p-5 border-b border-white/10 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white">Explore Map</h2>
                        <div class="flex items-center gap-4 mt-1">
                            <a href="<?php echo e(route('home')); ?>" wire:navigate class="text-xs text-gray-400 hover:text-white transition-colors">← Back to Home</a>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                                <a href="<?php echo e(route('my-bookings')); ?>" wire:navigate class="text-xs text-brand-400 hover:text-white transition-colors">📋 Your Bookings</a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 bg-white/10 backdrop-blur text-white px-3 py-1.5 rounded-full font-bold text-sm">
                        <div class="w-4 h-4 bg-brand-600 rounded-sm flex items-center justify-center text-white text-[10px]">V</div>
                        Victorias
                    </div>
                </div>

                
                <div class="p-4 border-b border-white/10 space-y-3">
                    
                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <button wire:click="$set('categoryFilter','')"
                                class="shrink-0 px-3.5 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                                       <?php echo e($categoryFilter === '' ? 'bg-brand-600 border-brand-600 text-white' : 'border-white/20 text-white/50 hover:border-brand-400 hover:text-white'); ?>">
                            All
                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <button wire:click="$set('categoryFilter','<?php echo e($cat); ?>')"
                                    class="shrink-0 px-3.5 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                                           <?php echo e($categoryFilter === $cat ? 'bg-brand-600 border-brand-600 text-white' : 'border-white/20 text-white/50 hover:border-brand-400 hover:text-white'); ?>">
                                <?php echo e($cat); ?>

                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search destinations…"
                               class="w-full bg-white/5 border border-white/10 rounded-xl py-3 pl-10 pr-4 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-brand-400/50 focus:ring-1 focus:ring-brand-400/50 transition">
                    </div>
                    
                    <div class="flex gap-2">
                        <button wire:click="$set('sortBy','name')"
                                class="flex-1 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                                       <?php echo e($sortBy === 'name' ? 'bg-brand-600 border-brand-600 text-white' : 'border-white/20 text-white/50 hover:border-brand-400 hover:text-white'); ?>">
                            A–Z
                        </button>
                        <button wire:click="$set('sortBy','distance')"
                                class="flex-1 py-2 rounded-full text-xs font-semibold uppercase tracking-wider transition border
                                       <?php echo e($sortBy === 'distance' ? 'bg-brand-600 border-brand-600 text-white' : 'border-white/20 text-white/50 hover:border-brand-400 hover:text-white'); ?>">
                            Nearest
                        </button>
                    </div>
                </div>

                
                <div class="flex-1 overflow-y-auto px-4 py-2 space-y-2 scrollbar-thin">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dest-'.e($tenant->id).''; ?>wire:key="dest-<?php echo e($tenant->id); ?>"
                             wire:click="flyToTenant(<?php echo e($tenant->id); ?>)"
                             class="cursor-pointer rounded-2xl p-3 flex items-center gap-4 transition-all duration-200 border border-transparent hover:bg-white/5 active:bg-white/10">
                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->logo): ?>
                                <img src="<?php echo e(Storage::url($tenant->logo)); ?>" alt="<?php echo e($tenant->name); ?>"
                                     class="w-10 h-10 rounded-full object-cover border-2 border-white/20 shadow-lg shrink-0">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold shrink-0"
                                     style="background: hsl(<?php echo e(($index * 137) % 360); ?>, 60%, 55%); color: #fff; box-shadow: 0 0 10px hsl(<?php echo e(($index * 137) % 360); ?>, 60%, 55%);">
                                    <?php echo e(strtoupper(substr($tenant->name, 0, 2))); ?>

                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="flex-1 min-w-0">
                                <h3 class="text-white font-semibold text-sm truncate"><?php echo e($tenant->name); ?></h3>
                                <p class="text-xs text-gray-400 mt-0.5"><?php echo e($tenant->typeOfTenant->type ?? 'Business'); ?></p>
                            </div>
                            <button onclick="event.stopPropagation(); window.requestDirections(<?php echo e($index); ?>)"
                                    class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg border border-white/10 text-gray-400 hover:text-white hover:bg-white/10 transition-colors"
                                    title="Get directions">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </button>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <div class="text-center py-16 text-gray-400">
                            <p>No destinations found.</p>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search || $categoryFilter): ?>
                                <button wire:click="$set('search','');$set('categoryFilter','')" class="text-brand-400 underline mt-2 text-sm">Clear filters</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div class="p-4 border-t border-white/10 flex items-center justify-between text-xs text-gray-500">
                    <span><?php echo e($this->tenants->count()); ?> waypoints</span>
                    <span><?php echo e($this->categories->count()); ?> realms</span>
                </div>
            </div>
        </div>

        
        <div x-data="{
                map: null,
                clusterGroup: null,
                tileLayer: null,
                routeLayer: null,
                userMarker: null,
                directionControl: null,
                userCoords: null,
                mapStyle: 'dark',
                compassEnabled: false,
                currentHeading: 0,
                compassNeedle: null,
                compassBadge: null,
                orientationHandler: null,

                init() {
                    let check = setInterval(() => {
                        if (typeof L !== 'undefined') { clearInterval(check); this.initMap();
                            this.$nextTick(() => {
                                this.compassNeedle = this.$refs.compassNeedle;
                                this.compassBadge = this.$refs.compassBadge;
                            });
                        }
                    }, 100);
                    window.addEventListener('fly-to-tenant', (e) => this.flyToTenant(e.detail.tenant));
                    window.requestDirections = (index) => this.requestDirections(index);
                    window.clearDirections = () => this.clearDirections();
                },

                initMap() {
                    let cLat = 10.900977766937142, cLng = 123.07055771888716;
                    if (window.tenantData.length) {
                        cLat = parseFloat(window.tenantData[0].latitude);
                        cLng = parseFloat(window.tenantData[0].longitude);
                    }
                    this.map = L.map(this.$refs.mapContainer, { center: [cLat, cLng], zoom: 11, zoomControl: true });
                    this.tileLayer = L.tileLayer('', { maxZoom: 19 }).addTo(this.map);
                    this.updateTileLayer();
                    this.plotMarkers();
                    setTimeout(() => this.map.invalidateSize(), 300);
                    new MutationObserver(() => this.updateTileLayer()).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            pos => {
                                this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                                this.placeUserMarker();
                                this.updatePopupDistances();
                            },
                            () => {}
                        );
                    }
                },

                updateTileLayer() {
                    const d = document.documentElement.classList.contains('dark');
                    this.tileLayer.setUrl(d
                        ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                        : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png');
                },

                setMapStyle(style) {
                    const urls = {
                        dark: 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                        satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                        light: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
                    };
                    this.tileLayer.setUrl(urls[style] || urls['dark']);
                    this.mapStyle = style;
                },

                plotMarkers() {
                    if (this.clusterGroup) this.map.removeLayer(this.clusterGroup);
                    this.clusterGroup = L.markerClusterGroup({ maxClusterRadius: 50 });
                    window.tenantData.forEach((t, i) => {
                        if (t.latitude && t.longitude) {
                            const c = window.tenantColor(i);
                            const ic = L.divIcon({
                                className: 'custom-pin',
                                html: '<div class=&quot;pin-dot&quot; style=&quot;background:' + c + ';color:' + c + ';&quot;></div>',
                                iconSize: [20, 20], iconAnchor: [10, 10]
                            });
                            const mk = L.marker([parseFloat(t.latitude), parseFloat(t.longitude)], { icon: ic })
                                .bindPopup(window.popupHtml(i, this.userCoords));
                            this.clusterGroup.addLayer(mk);
                        }
                    });
                    this.map.addLayer(this.clusterGroup);
                    if (window.tenantData.length > 1) {
                        this.map.fitBounds(L.latLngBounds(window.tenantData.map(t => [parseFloat(t.latitude), parseFloat(t.longitude)])), { padding: [60, 60], maxZoom: 14 });
                    } else if (window.tenantData.length === 1) {
                        this.map.setView([parseFloat(window.tenantData[0].latitude), parseFloat(window.tenantData[0].longitude)], 15);
                    }
                },

                placeUserMarker() {
                    if (!this.userCoords) return;
                    if (this.userMarker) this.map.removeLayer(this.userMarker);
                    this.userMarker = L.circleMarker([this.userCoords.lat, this.userCoords.lng], {
                        radius: 8,
                        fillColor: '#22c55e',
                        fillOpacity: 1,
                        color: '#fff',
                        weight: 3,
                        opacity: 1
                    }).bindPopup('<b style=&quot;color:#fff;&quot;>Your Location</b>').addTo(this.map);
                },

                updatePopupDistances() {
                    this.clusterGroup?.eachLayer(mk => {
                        const i = window.tenantData.findIndex(t => parseFloat(t.latitude) === mk.getLatLng().lat && parseFloat(t.longitude) === mk.getLatLng().lng);
                        if (i >= 0) mk.setPopupContent(window.popupHtml(i, this.userCoords));
                    });
                },

                flyToTenant(t) {
                    if (!t.latitude || !t.longitude) return;
                    const lat = parseFloat(t.latitude), lng = parseFloat(t.longitude);
                    this.map.flyTo([lat, lng], 16, { duration: 1.5 });
                    setTimeout(() => {
                        this.clusterGroup?.eachLayer(mk => {
                            const p = mk.getLatLng();
                            if (Math.abs(p.lat - lat) < 0.0001 && Math.abs(p.lng - lng) < 0.0001) mk.openPopup();
                        });
                    }, 1200);
                },

                requestDirections(index) {
                    const tenant = window.tenantData[index];
                    if (!tenant || !navigator.geolocation) return;
                    navigator.geolocation.getCurrentPosition((pos) => {
                        this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                        this.placeUserMarker();
                        this.updatePopupDistances();
                        const ulat = this.userCoords.lat, ulng = this.userCoords.lng;
                        const dlat = parseFloat(tenant.latitude), dlng = parseFloat(tenant.longitude);
                        if (this.routeLayer) { this.map.removeLayer(this.routeLayer); this.routeLayer = null; }
                        this.map.fitBounds(L.latLngBounds([[ulat, ulng], [dlat, dlng]]), { padding: [60, 60] });
                        fetch(`https://router.project-osrm.org/route/v1/driving/${ulng},${ulat};${dlng},${dlat}?overview=full&geometries=geojson`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.code === 'Ok' && data.routes.length) {
                                    const route = data.routes[0];
                                    const distance = (route.distance / 1000).toFixed(1);
                                    const duration = Math.round(route.duration / 60);
                                    if (this.routeLayer) this.map.removeLayer(this.routeLayer);
                                    this.routeLayer = L.geoJSON(route.geometry, { style: { color: '#22c55e', weight: 5, opacity: 0.8 } }).addTo(this.map);
                                    this.showDirectionPanel(distance, duration);
                                } else {
                                    this.showDirectionPanel(haversine(ulat, ulng, dlat, dlng), null);
                                }
                            })
                            .catch(() => this.showDirectionPanel(haversine(ulat, ulng, dlat, dlng), null));
                    }, () => alert('Location permission denied.'));
                },

                clearDirections() {
                    if (this.routeLayer) { this.map.removeLayer(this.routeLayer); this.routeLayer = null; }
                    if (this.directionControl) { this.map.removeControl(this.directionControl); this.directionControl = null; }
                },

                showDirectionPanel(distance, duration) {
                    if (this.directionControl) this.map.removeControl(this.directionControl);
                    const info = L.control({ position: 'bottomleft' });
                    info.onAdd = () => {
                        const div = L.DomUtil.create('div', 'direction-panel');
                        div.innerHTML = `<strong>${distance} km</strong>` + (duration ? ` &middot; ~${duration} min` : '');
                        return div;
                    };
                    info.addTo(this.map);
                    this.directionControl = info;
                },

                async enableCompass() {
                    if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
                        try { if ((await DeviceOrientationEvent.requestPermission()) !== 'granted') return; } catch (e) { return; }
                    }
                    this.compassEnabled = true;
                    this.$refs.compassWidget.classList.add('compass-live');
                    let sim = 0;
                    this._simInterval = setInterval(() => {
                        sim = (sim + 1.2) % 360;
                        this.currentHeading = sim;
                        this.applyCompassRotation(sim);
                    }, 50);
                },

                applyCompassRotation(heading) {
                    if (this.compassNeedle) this.compassNeedle.style.transform = `rotate(${-heading}deg)`;
                    if (this.compassBadge) this.compassBadge.textContent = Math.round(heading) + '°';
                }
             }"
             class="flex-1 h-full w-full lg:pl-96">

            <div wire:ignore class="h-full w-full relative">
                <div x-ref="mapContainer" class="h-full w-full"></div>

                
                <div class="absolute top-4 right-4 z-[800] flex gap-2">
                    <button @click="setMapStyle('dark')" class="glass px-3 py-1.5 rounded-full text-xs font-semibold text-white/80 hover:bg-white/10 transition">Dark</button>
                    <button @click="setMapStyle('light')" class="glass px-3 py-1.5 rounded-full text-xs font-semibold text-white/80 hover:bg-white/10 transition">Light</button>
                    <button @click="setMapStyle('satellite')" class="glass px-3 py-1.5 rounded-full text-xs font-semibold text-white/80 hover:bg-white/10 transition">Satellite</button>
                </div>

                
                <div x-ref="compassWidget"
                     @click="enableCompass()"
                     class="absolute bottom-6 right-4 z-[800] w-16 h-16 rounded-full bg-black/60 backdrop-blur-xl border border-white/10 flex items-center justify-center cursor-pointer hover:scale-110 transition shadow-lg"
                     title="Enable compass">
                    <div x-ref="compassNeedle" class="w-1 h-8 bg-brand-500 rounded-full origin-center transition-transform" style="transform:rotate(0deg)"></div>
                    <span x-ref="compassBadge" class="absolute -bottom-6 text-xs text-white/60">Tap</span>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/edb49f3a.blade.php ENDPATH**/ ?>