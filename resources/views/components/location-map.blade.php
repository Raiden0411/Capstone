@props(['readonly' => false, 'height' => '480px'])

<div class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-2 relative z-10"
    x-data="{
        map: null,
        marker: null,
        isReadonly: {{ $readonly ? 'true' : 'false' }},
        
        init() {
            let checkInterval = setInterval(() => {
                if (typeof L !== 'undefined') {
                    clearInterval(checkInterval);
                    this.initMap();
                }
            }, 100);
        },
        
        initMap() {
            let lat = parseFloat($wire.latitude) || 10.900962455874064;
            let lng = parseFloat($wire.longitude) || 123.0704558644316;

            this.map = L.map($refs.mapContainer, {
                center: [lat, lng],
                zoom: 13,
                zoomControl: true,
                attributionControl: true
            });

            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>'
            }).addTo(this.map);

            this.marker = L.marker([lat, lng], { 
                draggable: !this.isReadonly 
            }).addTo(this.map);

            setTimeout(() => { this.map.invalidateSize(); }, 300);

            if (!this.isReadonly) {
                this.marker.on('dragend', (e) => {
                    let pos = e.target.getLatLng();
                    $wire.latitude = parseFloat(pos.lat).toFixed(6);
                    $wire.longitude = parseFloat(pos.lng).toFixed(6);
                });

                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    $wire.latitude = parseFloat(e.latlng.lat).toFixed(6);
                    $wire.longitude = parseFloat(e.latlng.lng).toFixed(6);
                });

                this.$watch('$wire.latitude', () => this.updateMap());
                this.$watch('$wire.longitude', () => this.updateMap());
            }
        },
        
        updateMap() {
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
                        
                        $wire.latitude = parseFloat(lat).toFixed(6);
                        $wire.longitude = parseFloat(lng).toFixed(6);
                    },
                    (error) => alert('Could not get GPS location.')
                );
            } else {
                alert('Geolocation not supported.');
            }
        }
    }"
>
    @if(!$readonly)
    <div class="p-2 text-sm text-slate-500 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-1">
        <span>Drag marker or click the map to set location.</span>
        <button type="button" @click="getLocation()" class="text-blue-600 hover:text-blue-800 flex items-center font-medium bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Use My GPS
        </button>
    </div>
    @endif

    <div wire:ignore>
        <div x-ref="mapContainer" style="height: {{ $height }}; width: 100%;" class="rounded-lg border border-slate-200"></div>
    </div>
</div>