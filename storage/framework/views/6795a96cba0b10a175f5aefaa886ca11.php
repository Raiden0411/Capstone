<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
?>

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                Super Admin · Tenants
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Edit Business</h1>
            <p class="text-xs font-mono text-gray-400 dark:text-white/40 mt-1">Updating — <?php echo e($name); ?></p>
        </div>
        <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate class="text-sm font-medium text-gray-500 dark:text-white/50 hover:text-brand-600 dark:hover:text-brand-400 transition-colors flex items-center gap-1">
            &larr; Back to tenants
        </a>
    </div>

    <form wire:submit="update" class="grid grid-cols-1 lg:grid-cols-2 gap-8"
        x-data="{
            map: null, marker: null,
            init() {
                let check = setInterval(() => {
                    if (typeof L !== 'undefined') { clearInterval(check); this.initMap(); }
                }, 100);
            },
            initMap() {
                let lat = parseFloat($wire.latitude) || 10.6765;
                let lng = parseFloat($wire.longitude) || 122.9509;
                this.map = L.map($refs.mapEl).setView([lat, lng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19, attribution:'© OpenStreetMap' }).addTo(this.map);
                this.marker = L.marker([lat, lng], { draggable:true }).addTo(this.map);
                setTimeout(() => { this.map.invalidateSize(); }, 300);
                this.marker.on('dragend', (e) => {
                    let p = e.target.getLatLng();
                    $wire.latitude = p.lat; $wire.longitude = p.lng;
                });
                this.map.on('click', (e) => {
                    this.marker.setLatLng(e.latlng);
                    $wire.latitude = e.latlng.lat; $wire.longitude = e.latlng.lng;
                });
                $watch('$wire.latitude', () => this.syncMap());
                $watch('$wire.longitude', () => this.syncMap());
            },
            syncMap() {
                let lat = parseFloat($wire.latitude), lng = parseFloat($wire.longitude);
                if (!isNaN(lat) && !isNaN(lng) && this.marker) {
                    this.marker.setLatLng([lat, lng]);
                    this.map.setView([lat, lng]);
                }
            },
            gps() {
                if (!navigator.geolocation) { alert('Geolocation not supported.'); return; }
                navigator.geolocation.getCurrentPosition(
                    (p) => {
                        this.marker.setLatLng([p.coords.latitude, p.coords.longitude]);
                        this.map.flyTo([p.coords.latitude, p.coords.longitude], 16);
                        $wire.latitude = p.coords.latitude; $wire.longitude = p.coords.longitude;
                    },
                    () => alert('Could not get GPS location.')
                );
            }
        }"
    >
        
        <div class="space-y-6">
            <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center gap-3">
                    <span class="w-6 h-6 rounded-md bg-brand-100 dark:bg-brand-500/20 border border-brand-200 dark:border-brand-400/20 flex items-center justify-center font-mono text-xs font-medium text-brand-600 dark:text-brand-400 shrink-0">1</span>
                    <h2 class="font-display text-base font-semibold text-gray-900 dark:text-white">Business details</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Business name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live.debounce.300ms="name"
                                   class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">URL slug</label>
                            <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-white/10 bg-gray-100 dark:bg-white/5">
                                <span class="py-2.5 px-3 bg-gray-200 dark:bg-white/10 text-xs font-mono text-gray-500 dark:text-white/40 border-r border-gray-300 dark:border-white/10">spot/</span>
                                <input type="text" wire:model="slug" readonly
                                       class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm text-gray-500 dark:text-white/50 cursor-default outline-none">
                            </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['slug'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Business type <span class="text-red-500">*</span></label>
                            <select wire:model="type_of_tenant_id"
                                    class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                                <option value="">— Select type —</option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenantTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <option value="<?php echo e($type->id); ?>"><?php echo e($type->type); ?></option>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </select>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['type_of_tenant_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Contact number</label>
                            <input type="text" wire:model="contact_number"
                                   class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['contact_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Business email <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="email"
                               class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Headquarters address <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="address"
                               class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 dark:text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">GPS coordinates <span class="text-red-500">*</span></label>
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <input type="text" wire:model.live="latitude"
                                       class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition font-mono"
                                       placeholder="Latitude">
                            </div>
                            <div class="flex-1">
                                <input type="text" wire:model.live="longitude"
                                       class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition font-mono"
                                       placeholder="Longitude">
                            </div>
                        </div>
                        <button type="button" @click="gps()" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Use my current GPS location
                        </button>
                    </div>
                </div>
            </div>

            
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-3 px-6 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                <span wire:loading.remove>
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save changes
                </span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" opacity=".75"/></svg>
                    Saving…
                </span>
            </button>
        </div>

        
        <div class="lg:sticky lg:top-6 h-fit">
            <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <h3 class="font-display text-base font-semibold text-gray-900 dark:text-white">Business location</h3>
                    <p class="text-xs text-gray-500 dark:text-white/40 mt-1">Drag the marker, click the map, or use the GPS button to update coordinates.</p>
                </div>
                <div class="p-2" wire:ignore>
                    <div x-ref="mapEl" style="height:500px;width:100%;border-radius:6px;"></div>
                </div>
            </div>
        </div>
    </form>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/f79d5dc3.blade.php ENDPATH**/ ?>