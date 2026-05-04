<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
?>

<div class="flex flex-col h-[calc(100vh-64px)] bg-[#F7F6F1] dark:bg-[#0a0f1e] transition-colors duration-300">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        window.tenantData = <?php echo json_encode($this->tenants, 15, 512) ?>;
        window.tenantColor = (index) => 'hsl(' + ((index * 137) % 360) + ', 65%, 55%)';

        function haversine(lat1, lng1, lat2, lng2) {
            const R = 6371;
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLng = (lng2 - lng1) * Math.PI / 180;
            const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLng / 2) * Math.sin(dLng / 2);
            return (R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))).toFixed(1);
        }

        window.distanceHtml = (tenant, userCoords) => {
            if (!userCoords) return '';
            const dist = haversine(userCoords.lat, userCoords.lng, parseFloat(tenant.latitude), parseFloat(tenant.longitude));
            return '<span style="font-size:12px; color:#3b82f6; display:block; margin-bottom:8px;">📏 <strong>' + dist + ' km</strong> away</span>';
        };

        window.popupHtml = (index, userCoords) => {
            const t = window.tenantData[index];
            if (!t) return '';
            const dist = window.distanceHtml(t, userCoords);
            return '<div style="min-width:220px">' +
                '<h3 style="font-size:15px; font-weight:700; margin-bottom:4px;">' + t.name + '</h3>' +
                '<p style="font-size:12px; color:#64748b; margin-bottom:12px;">' + (t.address || '') + '</p>' +
                (dist || '') +
                '<a href="/business/' + t.slug + '" wire:navigate ' +
                'style="display:flex; align-items:center; justify-content:center; width:100%; padding:8px 0; font-size:12px; font-weight:600; border-radius:8px; background:#2563eb; color:white; text-decoration:none; margin-bottom:8px;">View Business</a>' +
                '<button onclick="window.requestDirections(' + index + ')" ' +
                'style="display:flex; align-items:center; justify-content:center; width:100%; padding:6px 0; font-size:12px; font-weight:600; border-radius:8px; border:2px solid #2563eb; color:#2563eb; background:transparent; cursor:pointer; transition:all 0.2s;" ' +
                'onmouseover="this.style.background=\'#2563eb\'; this.style.color=\'white\'" onmouseout="this.style.background=\'transparent\'; this.style.color=\'#2563eb\'">Get Directions</button>' +
                '</div>';
        };
    </script>

    <style>
        .leaflet-container { background: #e8e4d9 !important; }
        .dark .leaflet-container { background: #0f172a !important; }
        .leaflet-popup-content-wrapper { 
            background: white !important; 
            color: #1e293b !important; 
            border-radius: 1rem !important; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.12) !important; 
            padding: 12px 14px !important; 
            border: 1px solid #e2e8f0 !important;
        }
        .dark .leaflet-popup-content-wrapper { 
            background: #1e293b !important; 
            color: #e2e8f0 !important; 
            border: 1px solid #334155 !important; 
        }
        .leaflet-popup-tip { background: white !important; }
        .dark .leaflet-popup-tip { background: #1e293b !important; }
        .leaflet-popup-content { margin: 0 !important; font-size: 14px !important; line-height: 1.5 !important; }
        .leaflet-control-zoom a { 
            background: white !important; 
            color: #1e293b !important; 
            border: 1px solid #e2e8f0 !important; 
            border-radius: 0.5rem !important; 
            margin: 4px !important; 
            font-weight: bold !important;
        }
        .dark .leaflet-control-zoom a { 
            background: #334155 !important; 
            color: #e2e8f0 !important; 
            border-color: #475569 !important; 
        }
        .leaflet-pane.leaflet-tile-pane    { z-index: 1  !important; }
        .leaflet-pane.leaflet-overlay-pane { z-index: 2  !important; }
        .leaflet-pane.leaflet-shadow-pane  { z-index: 3  !important; }
        .leaflet-pane.leaflet-marker-pane  { z-index: 4  !important; }
        .leaflet-pane.leaflet-tooltip-pane { z-index: 5  !important; }
        .leaflet-pane.leaflet-popup-pane   { z-index: 6  !important; }
        .custom-pin { display: flex; align-items: center; justify-content: center; }
        .pin-dot { width: 14px; height: 14px; border-radius: 50%; border: 3px solid #ffffffcc; box-shadow: 0 0 10px currentColor; transition: transform 0.2s; }
        .pin-dot:hover { transform: scale(1.3); }
        .direction-panel { 
            background: white; 
            border-radius: 0.75rem; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.15); 
            padding: 10px 14px; 
            font-size: 13px; 
            font-weight: 600; 
            color: #1e293b;
        }
        .dark .direction-panel { 
            background: #1e293b; 
            color: #e2e8f0; 
        }
    </style>

    <div class="flex-1 flex">
        
        <div x-data="{ open: window.innerWidth >= 1024 }"
             @resize.window="open = window.innerWidth >= 1024"
             class="relative z-50">
            <button @click="open = !open"
                    class="lg:hidden fixed top-[80px] left-4 z-40 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl px-4 py-2.5 shadow-lg text-sm font-semibold text-gray-700 dark:text-slate-200 flex items-center gap-2">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <span x-text="open ? 'Close' : 'Destinations'"></span>
            </button>

            <div x-show="open" x-transition:enter="transition-transform duration-300" x-transition:leave="transition-transform duration-200"
                 :class="open ? 'translate-x-0' : '-translate-x-full'"
                 class="lg:translate-x-0 fixed top-[64px] bottom-0 left-0 w-80 lg:w-96 bg-white dark:bg-[#0b0f19] border-r border-gray-200 dark:border-slate-700/50 shadow-2xl lg:shadow-none z-30 flex flex-col">
                
                <div class="p-5 border-b border-gray-200 dark:border-slate-700/50">
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white">Explore Map</h2>
                    <a href="<?php echo e(route('home')); ?>" wire:navigate class="text-sm text-blue-600 dark:text-blue-400 hover:underline mt-1 inline-block">&larr; Back to Home</a>
                </div>

                <div class="p-4 border-b border-gray-200 dark:border-slate-700/50">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search destinations..."
                           class="w-full rounded-lg border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 py-2.5 px-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                <div class="flex-1 overflow-y-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tenant-'.e($tenant->id).''; ?>wire:key="tenant-<?php echo e($tenant->id); ?>"
                             wire:click="flyToTenant(<?php echo e($tenant->id); ?>)"
                             class="flex items-center gap-3 px-4 py-3 mx-2 my-1 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors">
                            <span class="size-3 rounded-full shrink-0" style="background: hsl(<?php echo e(($index * 137) % 360); ?>, 65%, 55%); box-shadow: 0 0 6px hsl(<?php echo e(($index * 137) % 360); ?>, 65%, 55%);"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($tenant->name); ?></p>
                                <p class="text-xs text-gray-500 dark:text-slate-400"><?php echo e($tenant->typeOfTenant->type ?? 'Business'); ?></p>
                            </div>
                            <button onclick="window.requestDirections(<?php echo e($index); ?>)"
                                    class="text-xs px-2 py-1 rounded-lg border border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400 hover:bg-blue-600 hover:text-white dark:hover:bg-blue-500 transition"
                                    title="Get directions">
                                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </button>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        <p class="text-center text-gray-500 dark:text-slate-400 py-8">No destinations found.</p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        
        <div x-data="{
                map: null,
                markers: [],
                tileLayer: null,
                routeLayer: null,
                userMarker: null,
                directionControl: null,
                userCoords: null,
                init() {
                    let check = setInterval(() => {
                        if (typeof L !== 'undefined') { clearInterval(check); this.initMap(); }
                    }, 100);
                    window.addEventListener('fly-to-tenant', (e) => this.flyToTenant(e.detail.tenant));
                    window.requestDirections = (index) => this.requestDirections(index);
                    window.clearDirections = () => this.clearDirections();
                },
                initMap() {
                    let cLat = 10.900977766937142, cLng = 123.07055771888716;
                    if (window.tenantData.length) { cLat = parseFloat(window.tenantData[0].latitude); cLng = parseFloat(window.tenantData[0].longitude); }
                    this.map = L.map(this.$refs.mapContainer, { center: [cLat, cLng], zoom: 11, zoomControl: true });
                    this.tileLayer = L.tileLayer('', {
                        maxZoom: 19,
                        attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a> &copy; <a href=&quot;https://carto.com/&quot;>CARTO</a>'
                    }).addTo(this.map);
                    this.updateTileLayer();
                    this.plotMarkers();
                    setTimeout(() => this.map.invalidateSize(), 300);
                    new MutationObserver(() => this.updateTileLayer()).observe(document.documentElement, { attributes: true });

                    // Try silent location – show user pin + update distances
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            pos => {
                                this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                                this.placeUserMarker();
                                this.updatePopupDistances();
                            },
                            err => {}
                        );
                    }
                },
                updateTileLayer() {
                    const d = document.documentElement.classList.contains('dark');
                    this.tileLayer.setUrl(d ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png' : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png');
                },
                plotMarkers() {
                    this.markers.forEach(m => this.map.removeLayer(m));
                    this.markers = [];
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
                            mk.addTo(this.map);
                            this.markers.push(mk);
                        }
                    });
                    if (window.tenantData.length > 1) {
                        this.map.fitBounds(L.latLngBounds(window.tenantData.map(t => [parseFloat(t.latitude), parseFloat(t.longitude)])), { padding: [50, 50], maxZoom: 14 });
                    } else if (window.tenantData.length === 1) {
                        this.map.setView([parseFloat(window.tenantData[0].latitude), parseFloat(window.tenantData[0].longitude)], 15);
                    }
                },
                placeUserMarker() {
                    if (!this.userCoords) return;
                    if (this.userMarker) this.map.removeLayer(this.userMarker);
                    // Rock‑solid CircleMarker that survives any zoom level
                    this.userMarker = L.circleMarker([this.userCoords.lat, this.userCoords.lng], {
                        radius: 8,
                        fillColor: '#3b82f6',
                        fillOpacity: 1,
                        color: '#ffffff',
                        weight: 3,
                        opacity: 1
                    }).bindPopup('<b>Your Location</b>').addTo(this.map);
                    // Optionally open popup so the user sees it immediately
                    this.userMarker.openPopup();
                },
                updatePopupDistances() {
                    this.markers.forEach((mk, i) => {
                        const t = window.tenantData[i];
                        if (t) mk.setPopupContent(window.popupHtml(i, this.userCoords));
                    });
                },
                flyToTenant(t) {
                    if (!t.latitude || !t.longitude) return;
                    const lat = parseFloat(t.latitude), lng = parseFloat(t.longitude);
                    this.map.flyTo([lat, lng], 16, { duration: 1.5 });
                    const mk = this.markers.find(m => { const p = m.getLatLng(); return Math.abs(p.lat - lat) < 0.0001 && Math.abs(p.lng - lng) < 0.0001; });
                    if (mk) setTimeout(() => mk.openPopup(), 1200);
                },
                requestDirections(index) {
                    const tenant = window.tenantData[index];
                    if (!tenant) return;
                    if (!navigator.geolocation) {
                        alert('Geolocation is not supported.');
                        return;
                    }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            this.userCoords = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                            this.placeUserMarker();
                            this.updatePopupDistances();

                            const ulat = this.userCoords.lat, ulng = this.userCoords.lng;
                            const dlat = parseFloat(tenant.latitude), dlng = parseFloat(tenant.longitude);

                            if (this.routeLayer) { this.map.removeLayer(this.routeLayer); this.routeLayer = null; }

                            this.map.fitBounds(L.latLngBounds([[ulat, ulng], [dlat, dlng]]), { padding: [60, 60] });

                            const url = `https://router.project-osrm.org/route/v1/driving/${ulng},${ulat};${dlng},${dlat}?overview=full&geometries=geojson`;
                            fetch(url)
                                .then(r => r.json())
                                .then(data => {
                                    if (data.code === 'Ok' && data.routes.length) {
                                        const route = data.routes[0];
                                        const distance = (route.distance / 1000).toFixed(1);
                                        const duration = Math.round(route.duration / 60);
                                        if (this.routeLayer) this.map.removeLayer(this.routeLayer);
                                        this.routeLayer = L.geoJSON(route.geometry, {
                                            style: { color: '#3b82f6', weight: 5, opacity: 0.8 }
                                        }).addTo(this.map);
                                        this.showDirectionPanel(distance, duration);
                                    } else {
                                        const dist = haversine(ulat, ulng, dlat, dlng);
                                        this.showDirectionPanel(dist, null);
                                    }
                                })
                                .catch(() => {
                                    const dist = haversine(ulat, ulng, dlat, dlng);
                                    this.showDirectionPanel(dist, null);
                                });
                        },
                        (err) => alert('Location permission denied.')
                    );
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
                }
             }"
             class="flex-1 h-full w-full lg:pl-96">
            <div wire:ignore class="h-full w-full">
                <div x-ref="mapContainer" class="h-full w-full"></div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/edb49f3a.blade.php ENDPATH**/ ?>