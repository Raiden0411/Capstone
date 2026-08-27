<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['readonly' => false, 'height' => '480px']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['readonly' => false, 'height' => '480px']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="glass-card !rounded-2xl p-2 relative z-10"
    x-data="{
        map: null,
        marker: null,
        isReadonly: <?php echo e($readonly ? 'true' : 'false'); ?>,
        initializing: false,

        init() {
            this.initializeMap();
        },

        initializeMap() {
            if (this.initializing || this.map) return;
            this.initializing = true;

            const loadLeaflet = () => {
                if (typeof L !== 'undefined') {
                    this.initializing = false;
                    this.initMap();
                } else {
                    setTimeout(loadLeaflet, 100);
                }
            };
            loadLeaflet();
        },

        initMap() {
            const container = this.$refs.mapContainer;
            if (!container || container.offsetHeight === 0) {
                this.initializing = false;
                setTimeout(() => this.initializeMap(), 200);
                return;
            }

            // Fix default icon paths
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            });

            const lat = parseFloat($wire.get('latitude')) || 10.900977766937142;
            const lng = parseFloat($wire.get('longitude')) || 123.07055771888716;

            // Remove existing map if any
            if (this.map) {
                this.map.remove();
                this.map = null;
            }

            this.map = L.map(container, {
                center: [lat, lng],
                zoom: 13,
                scrollWheelZoom: !this.isReadonly,
            });

            // Light/dark tile based on current theme
            const isDark = document.documentElement.classList.contains('dark');
            const tileUrl = isDark
                ? 'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png'
                : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png';

            L.tileLayer(tileUrl, {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CARTO',
            }).addTo(this.map);

            this.marker = L.marker([lat, lng], { draggable: !this.isReadonly }).addTo(this.map);

            setTimeout(() => this.map.invalidateSize(), 300);

            if (!this.isReadonly) {
                this.marker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    $wire.set('latitude', parseFloat(pos.lat).toFixed(6), false);
                    $wire.set('longitude', parseFloat(pos.lng).toFixed(6), false);
                });

                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    $wire.set('latitude', parseFloat(e.latlng.lat).toFixed(6), false);
                    $wire.set('longitude', parseFloat(e.latlng.lng).toFixed(6), false);
                });

                this.$watch('$wire.latitude', () => this.updateMarkerFromInput());
                this.$watch('$wire.longitude', () => this.updateMarkerFromInput());
            }

            // Re-init on Livewire navigation (if component persists)
            window.addEventListener('livewire:navigated', () => {
                setTimeout(() => this.initializeMap(), 50);
            });
        },

        updateMarkerFromInput() {
            if (this.isReadonly || !this.marker || !this.map) return;
            const lat = parseFloat($wire.latitude);
            const lng = parseFloat($wire.longitude);
            if (!isNaN(lat) && !isNaN(lng)) {
                this.marker.setLatLng([lat, lng]);
                this.map.setView([lat, lng]);
            }
        },

        getLocation() {
            if (this.isReadonly) return;
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    if (this.marker) this.marker.setLatLng([lat, lng]);
                    this.map?.flyTo([lat, lng], 16);
                    $wire.set('latitude', parseFloat(lat).toFixed(6), false);
                    $wire.set('longitude', parseFloat(lng).toFixed(6), false);
                },
                () => alert('Could not get GPS location. Please check browser permissions.')
            );
        }
    }"
    x-init="init()">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$readonly): ?>
        <div class="p-2 text-sm text-white/70 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-1">
            <span>Drag marker, click map, or type coordinates to set location.</span>
            <button type="button" @click="getLocation()"
                    class="text-brand-400 hover:text-brand-300 flex items-center font-medium bg-brand-500/10 px-3 py-1.5 rounded-lg border border-brand-500/20 transition">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Use My GPS
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div wire:ignore>
        <div x-ref="mapContainer" style="height: <?php echo e($height); ?>; width: 100%; position: relative; z-index: 10;" class="rounded-lg"></div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/location-map.blade.php ENDPATH**/ ?>