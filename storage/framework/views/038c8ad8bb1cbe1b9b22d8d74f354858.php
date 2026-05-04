<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use App\Models\Tenant;
?>

<div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" data-navigate-once />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" data-navigate-once></script>

    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" data-navigate-once>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.dark.css" rel="stylesheet" data-navigate-once>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js" data-navigate-once></script>

    <div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto text-gray-900 dark:text-white space-y-6">

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Master Map Control</h1>
                <p class="text-gray-500 dark:text-slate-400 mt-1">Plot and view all business locations simultaneously.</p>
            </div>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
            <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
                <p class="text-sm text-green-700 dark:text-green-400 font-medium"><?php echo e(session('message')); ?></p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6"
            x-data="{
                map: null,
                activeMarker: null,
                layerGroup: null,
                globalTenants: <?php echo \Illuminate\Support\Js::from($this->allMappedTenants)->toHtml() ?>,
                
                init() {
                    let checkInterval = setInterval(() => {
                        if (typeof L !== 'undefined') {
                            clearInterval(checkInterval);
                            this.initMap();
                        }
                    }, 100);

                    window.addEventListener('fly-to-location', (e) => {
                        let lat = parseFloat(e.detail.lat);
                        let lng = parseFloat(e.detail.lng);
                        if(this.activeMarker) {
                            this.activeMarker.setLatLng([lat, lng]);
                            this.activeMarker.setOpacity(1);
                        }
                        this.map.flyTo([lat, lng], 16, { animate: true, duration: 1.5 });
                    });

                    window.addEventListener('reset-map', () => {
                        if(this.activeMarker) this.activeMarker.setOpacity(0);
                    });
                },
                
                initMap() {
                    let lat = parseFloat($wire.latitude) || 10.6765;
                    let lng = parseFloat($wire.longitude) || 122.9509;

                    this.map = L.map($refs.mapContainer).setView([lat, lng], 12);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(this.map);

                    this.layerGroup = L.layerGroup().addTo(this.map);
                    this.plotGlobalPins();

                    let activeIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    this.activeMarker = L.marker([lat, lng], { 
                        draggable: true, 
                        icon: activeIcon,
                        opacity: $wire.tenant_id ? 1 : 0 
                    }).addTo(this.map);

                    this.activeMarker.on('dragend', (e) => {
                        if(!$wire.tenant_id) return;
                        let pos = e.target.getLatLng();
                        $wire.latitude = pos.lat.toFixed(6);
                        $wire.longitude = pos.lng.toFixed(6);
                    });

                    this.map.on('click', (e) => {
                        if(!$wire.tenant_id) return;
                        this.activeMarker.setLatLng(e.latlng);
                        $wire.latitude = e.latlng.lat.toFixed(6);
                        $wire.longitude = e.latlng.lng.toFixed(6);
                        this.activeMarker.setOpacity(1);
                    });

                    setTimeout(() => { this.map.invalidateSize(); }, 300);
                },

                plotGlobalPins() {
                    this.layerGroup.clearLayers();
                    let staticIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-grey.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    this.globalTenants.forEach(tenant => {
                        if($wire.tenant_id == tenant.id) return; 

                        L.marker([tenant.latitude, tenant.longitude], { icon: staticIcon })
                            .bindPopup(`<b>${tenant.name}</b>`)
                            .addTo(this.layerGroup);
                    });
                },

                getLocation() {
                    if (!$wire.tenant_id) return alert('Please select a tenant first!');
                    
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            (position) => {
                                let lat = position.coords.latitude;
                                let lng = position.coords.longitude;
                                this.activeMarker.setLatLng([lat, lng]);
                                this.activeMarker.setOpacity(1);
                                this.map.flyTo([lat, lng], 17);
                                $wire.latitude = lat.toFixed(6);
                                $wire.longitude = lng.toFixed(6);
                            },
                            (error) => alert('GPS failed. Please check permissions.')
                        );
                    }
                }
            }"
        >
            <div class="lg:col-span-8 order-2 lg:order-1">
                <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-2 h-full min-h-[600px] relative">
                    <div class="absolute top-4 right-4 z-[400] bg-white dark:bg-slate-800 px-3 py-2 rounded shadow text-xs text-gray-600 dark:text-slate-300 border border-gray-200 dark:border-slate-600">
                        <div class="flex items-center gap-2 mb-1"><div class="w-3 h-3 bg-red-500 rounded-full"></div> Active Pin</div>
                        <div class="flex items-center gap-2"><div class="w-3 h-3 bg-gray-400 dark:bg-slate-500 rounded-full"></div> Existing Spots</div>
                    </div>
                    <div wire:ignore class="h-full">
                        <div x-ref="mapContainer" class="h-full rounded-lg border border-gray-200 dark:border-slate-700/50" style="min-height: 600px; z-index: 10;"></div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4 order-1 lg:order-2 space-y-6">
                
                <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-4 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Set Location</h2>
                    
                    <form wire:submit="store" class="space-y-4">
                        <div x-data="{
                                ts: null,
                                init() {
                                    const isDark = document.documentElement.classList.contains('dark');
                                    this.ts = new TomSelect(this.$refs.select, {
                                        create: false,
                                        placeholder: 'Search establishments...',
                                        sortField: { field: 'text', direction: 'asc' },
                                        className: isDark ? 'dark' : '',
                                        onChange: (value) => {
                                            $wire.tenant_id = value;
                                        }
                                    });

                                    $wire.$watch('tenant_id', (value) => {
                                        this.ts.setValue(value, true); 
                                    });
                                }
                            }"
                        >
                            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Establishment / Tenant</label>
                            <div wire:ignore>
                                <select x-ref="select" class="w-full text-sm">
                                    <option value="">Search establishments...</option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->availableTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenantOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <option value="<?php echo e($tenantOption->id); ?>"><?php echo e($tenantOption->name); ?></option>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </select>
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tenant_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Lat</label>
                                <input type="text" wire:model.live="latitude" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500" <?php echo e(!$tenant_id ? 'disabled' : ''); ?>>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Lng</label>
                                <input type="text" wire:model.live="longitude" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-gray-50 dark:bg-slate-700 text-gray-900 dark:text-slate-200 text-sm focus:ring-blue-500 focus:border-blue-500" <?php echo e(!$tenant_id ? 'disabled' : ''); ?>>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>

                        <button type="button" @click="getLocation()" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 flex items-center disabled:opacity-50" <?php echo e(!$tenant_id ? 'disabled' : ''); ?>>
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Sync to My GPS
                        </button>

                        <div class="pt-4 flex gap-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-4 rounded-xl shadow-sm transition-colors flex-1 disabled:opacity-50 disabled:cursor-not-allowed" <?php echo e(!$tenant_id ? 'disabled' : ''); ?>>
                                Save Location
                            </button>
                            <button type="button" wire:click="resetFields" class="bg-gray-100 dark:bg-slate-800 hover:bg-gray-200 dark:hover:bg-slate-700 text-gray-700 dark:text-slate-300 font-medium py-2.5 px-4 rounded-xl shadow-sm transition-colors disabled:opacity-50" <?php echo e(!$tenant_id ? 'disabled' : ''); ?>>
                                Clear
                            </button>
                        </div>
                    </form>
                </div>

                
                <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm flex flex-col h-[400px]">
                    <div class="p-4 border-b border-gray-200 dark:border-slate-700/50">
                        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Filter list..." 
                               class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 text-sm">
                    </div>
                    
                    <div class="flex-1 overflow-y-auto p-4 space-y-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="p-3 border border-gray-200 dark:border-slate-700/50 rounded-lg hover:border-blue-200 dark:hover:border-blue-500/50 hover:bg-blue-50 dark:hover:bg-blue-500/10 transition-colors <?php echo e($tenant_id == $tenant->id ? 'border-blue-300 dark:border-blue-500/50 bg-blue-50 dark:bg-blue-500/10' : ''); ?>">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-medium text-sm text-gray-900 dark:text-white"><?php echo e($tenant->name); ?></div>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->latitude && $tenant->longitude): ?>
                                            <span class="text-[10px] font-medium text-green-600 dark:text-green-400 flex items-center mt-1">
                                                <div class="w-1.5 h-1.5 rounded-full bg-green-500 mr-1"></div> Mapped
                                            </span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-medium text-gray-500 dark:text-slate-400 flex items-center mt-1">
                                                <div class="w-1.5 h-1.5 rounded-full bg-gray-300 dark:bg-slate-500 mr-1"></div> Unmapped
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <button wire:click="edit(<?php echo e($tenant->id); ?>)" class="text-xs bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 px-2 py-1 rounded shadow-sm hover:bg-gray-50 dark:hover:bg-slate-700 text-blue-600 dark:text-blue-400">Set Pin</button>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->latitude): ?>
                                            <button wire:click="removeLocation(<?php echo e($tenant->id); ?>)" wire:confirm="Remove this pin?" class="text-[10px] text-red-500 dark:text-red-400 hover:underline text-right">Remove</button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            <div class="text-center text-gray-500 dark:text-slate-400 text-sm py-4">No businesses found.</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    
                    <div class="p-3 border-t border-gray-200 dark:border-slate-700/50 bg-gray-50 dark:bg-slate-800/50 rounded-b-xl">
                        <?php echo e($this->tenants->links(data: ['scrollTo' => false])); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/f9bc58c9.blade.php ENDPATH**/ ?>