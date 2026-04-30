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

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-2 relative z-10"
    x-data="{
        map: null,
        marker: null,
        isReadonly: <?php echo e($readonly ? 'true' : 'false'); ?>,
        init() {
            // Wait for Leaflet library to load
            let checkInterval = setInterval(() => {
                if (typeof L !== 'undefined') {
                    clearInterval(checkInterval);
                    this.initMap();
                }
            }, 100);
        },
        initMap() {
            // Fix default icon paths (required when using Vite)
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            });

            let lat = parseFloat($wire.get('latitude')) || 10.900977766937142;
            let lng = parseFloat($wire.get('longitude')) || 123.07055771888716;

            this.map = L.map($refs.mapContainer).setView([lat, lng], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>'
            }).addTo(this.map);

            this.marker = L.marker([lat, lng], { draggable: !this.isReadonly }).addTo(this.map);

            setTimeout(() => { this.map.invalidateSize(); }, 300);

            if (!this.isReadonly) {
                this.marker.on('dragend', (e) => {
                    let pos = e.target.getLatLng();
                    $wire.set('latitude', parseFloat(pos.lat).toFixed(6), false);
                    $wire.set('longitude', parseFloat(pos.lng).toFixed(6), false);
                });

                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    $wire.set('latitude', parseFloat(e.latlng.lat).toFixed(6), false);
                    $wire.set('longitude', parseFloat(e.latlng.lng).toFixed(6), false);
                });

                // Watch for manual coordinate changes
                this.$watch('$wire.latitude', () => this.updateMarkerFromInput());
                this.$watch('$wire.longitude', () => this.updateMarkerFromInput());
            }
        },
        updateMarkerFromInput() {
            if (this.isReadonly) return;
            let lat = parseFloat($wire.latitude);
            let lng = parseFloat($wire.longitude);
            if (!isNaN(lat) && !isNaN(lng) && this.marker) {
                this.marker.setLatLng([lat, lng]);
                this.map.setView([lat, lng]);
            }
        },
        getLocation() {
            if (this.isReadonly) return;
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        let lat = position.coords.latitude;
                        let lng = position.coords.longitude;
                        
                        this.marker.setLatLng([lat, lng]);
                        this.map.flyTo([lat, lng], 16);
                        
                        $wire.set('latitude', parseFloat(lat).toFixed(6), false);
                        $wire.set('longitude', parseFloat(lng).toFixed(6), false);
                    },
                    (error) => alert('Could not get GPS location. Please check browser permissions.')
                );
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }
    }"
>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$readonly): ?>
    <div class="p-2 text-sm text-slate-500 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-1">
        <span>Drag marker, click map, or type coordinates to set location.</span>
        <button type="button" @click="getLocation()" class="text-blue-600 hover:text-blue-800 flex items-center font-medium bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
            Use My GPS
        </button>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div wire:ignore>
        <div x-ref="mapContainer" style="height: <?php echo e($height); ?>; width: 100%; position: relative; z-index: 10;" class="rounded-lg border border-slate-200"></div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/location-map.blade.php ENDPATH**/ ?>