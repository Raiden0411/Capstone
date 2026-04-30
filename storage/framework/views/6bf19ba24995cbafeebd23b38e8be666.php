<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\Tenant;
?>

<div class="min-h-screen bg-slate-50">
    
    <header class="bg-white border-b border-slate-200 shadow-sm py-4">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-slate-800">Explore Map</h1>
            <a href="<?php echo e(route('home')); ?>" class="text-blue-600 hover:underline">&larr; Back to Home</a>
        </div>
    </header>

    
    <div class="bg-white border-b border-slate-200 py-3">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center gap-4">
            
            <div class="relative w-64">
                <select wire:model.live="selectedTenantId" class="w-full rounded-lg border-slate-300 text-sm py-2 pl-3 pr-8 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">-- Select a business --</option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <option value="<?php echo e($tenant->id); ?>"><?php echo e($tenant->name); ?></option>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </select>
            </div>
        </div>
    </div>

    
    <div class="relative h-[calc(100vh-140px)] w-full"
         x-data="{
            map: null,
            markers: [],
            tenantData: <?php echo \Illuminate\Support\Js::from($this->tenants)->toHtml() ?>,
            init() {
                let check = setInterval(() => {
                    if (typeof L !== 'undefined') {
                        clearInterval(check);
                        this.initMap();
                    }
                }, 100);
            },
            initMap() {
                // Fix default icon paths
                delete L.Icon.Default.prototype._getIconUrl;
                L.Icon.Default.mergeOptions({
                    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                });

                let centerLat = 10.900977766937142;
                let centerLng = 123.07055771888716;
                if (this.tenantData.length > 0) {
                    centerLat = parseFloat(this.tenantData[0].latitude);
                    centerLng = parseFloat(this.tenantData[0].longitude);
                }

                this.map = L.map($refs.mapContainer).setView([centerLat, centerLng], 12);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>'
                }).addTo(this.map);

                this.plotMarkers(this.tenantData);

                window.addEventListener('fly-to-tenant', (e) => {
                    this.flyToTenant(e.detail.tenant);
                });
            },
            plotMarkers(tenants) {
                this.markers.forEach(m => this.map.removeLayer(m));
                this.markers = [];

                tenants.forEach(tenant => {
                    if (tenant.latitude && tenant.longitude) {
                        let marker = L.marker([parseFloat(tenant.latitude), parseFloat(tenant.longitude)])
                            .bindPopup(`
                                <b>${tenant.name}</b><br>
                                ${tenant.address || ''}<br>
                                <a href=&quot;/business/${tenant.slug}&quot; class=&quot;text-blue-600 hover:underline text-sm&quot;>View Details</a>
                            `);
                        marker.addTo(this.map);
                        this.markers.push(marker);
                    }
                });

                if (tenants.length > 0) {
                    let bounds = L.latLngBounds(tenants.map(t => [parseFloat(t.latitude), parseFloat(t.longitude)]));
                    this.map.fitBounds(bounds, { padding: [50, 50] });
                }
            },
            flyToTenant(tenant) {
                if (!tenant.latitude || !tenant.longitude) return;
                let lat = parseFloat(tenant.latitude);
                let lng = parseFloat(tenant.longitude);
                this.map.flyTo([lat, lng], 15);
                
                // Find and open popup for this tenant
                let marker = this.markers.find(m => {
                    let pos = m.getLatLng();
                    return pos.lat === lat && pos.lng === lng;
                });
                if (marker) {
                    marker.openPopup();
                }
            }
         }">
        <div wire:ignore class="h-full w-full">
            <div x-ref="mapContainer" class="h-full w-full"></div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/edb49f3a.blade.php ENDPATH**/ ?>