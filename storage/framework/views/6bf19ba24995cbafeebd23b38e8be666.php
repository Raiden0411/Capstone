<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
?>




<div class="relative z-10 flex h-[calc(100vh-64px)] overflow-hidden"
     x-data="{
        mobileOpen: false,
        sidebarOpen: <?php if ((object) ('sidebarOpen') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('sidebarOpen'->value()); ?>')<?php echo e('sidebarOpen'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('sidebarOpen'); ?>')<?php endif; ?>,
        locating: false,
        followMode: <?php if ((object) ('followMode') instanceof \Livewire\WireDirective) : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('followMode'->value()); ?>')<?php echo e('followMode'->hasModifier('live') ? '.live' : ''); ?><?php else : ?>window.Livewire.find('<?php echo e($__livewire->getId()); ?>').entangle('<?php echo e('followMode'); ?>')<?php endif; ?>,
        online: navigator.onLine,
        helpOpen: false,
        helpTrigger: null,
        viewport: { lat: null, lng: null, zoom: null },
        toasts: [],
        pendingDirectionRequest: false,
        userHeading: null,

        addToast(type, message) {
            const id = Date.now() + Math.random();
            const duration = 4200;
            const toast = { id, type, message, duration, remaining: duration, paused: false, _timer: null, _tick: null };
            toast._timer = setTimeout(() => this.removeToast(id), duration);
            toast._tick = setInterval(() => {
                if (!toast.paused) toast.remaining = Math.max(0, toast.remaining - 100);
            }, 100);
            this.toasts.push(toast);
            if (this.toasts.length > 4) {
                const oldest = this.toasts.shift();
                clearTimeout(oldest._timer);
                clearInterval(oldest._tick);
            }
        },
        pauseToast(toast) {
            toast.paused = true;
            clearTimeout(toast._timer);
        },
        resumeToast(toast) {
            toast.paused = false;
            toast._timer = setTimeout(() => this.removeToast(toast.id), Math.max(toast.remaining, 300));
        },
        removeToast(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (toast) { clearTimeout(toast._timer); clearInterval(toast._tick); }
            this.toasts = this.toasts.filter(t => t.id !== id);
        },

        locate() {
            if (!navigator.geolocation) {
                $wire.locationFailed('unavailable');
                return;
            }
            this.locating = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.locating = false;
                    this.userHeading = pos.coords.heading || 0;
                    $wire.setUserLocation(pos.coords.latitude, pos.coords.longitude);
                    if (this.pendingDirectionRequest) {
                        this.pendingDirectionRequest = false;
                        setTimeout(() => this.startFollowMode(), 500);
                    }
                },
                (err) => {
                    this.locating = false;
                    this.pendingDirectionRequest = false;
                    const reason = err.code === err.PERMISSION_DENIED ? 'denied'
                        : err.code === err.TIMEOUT ? 'timeout' : 'unavailable';
                    $wire.locationFailed(reason);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
            );
        },

        startFollowMode() {
            if (this.followMode) return;
            if (!navigator.geolocation) {
                $wire.locationFailed('unavailable');
                return;
            }
            this.followMode = true;
            $wire.toggleFollowMode(true);
            let lastUpdate = 0;
            this.watchId = navigator.geolocation.watchPosition(
                (pos) => {
                    const now = Date.now();
                    if (now - lastUpdate < 2000) return;
                    lastUpdate = now;
                    this.userHeading = pos.coords.heading || 0;
                    $wire.dispatch('map:fly-to', {
                        center: [pos.coords.longitude, pos.coords.latitude],
                        zoom: 16,
                        essential: true
                    });
                },
                (err) => {
                    this.followMode = false;
                    $wire.toggleFollowMode(false);
                    $wire.locationFailed(err.code === err.PERMISSION_DENIED ? 'denied' : 'unavailable');
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        },

        stopFollowMode() {
            this.followMode = false;
            $wire.toggleFollowMode(false);
            if (this.watchId) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
        },

        async copyText(text) {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
                this.addToast('success', 'Link copied to clipboard.');
            } catch (e) {
                this.addToast('error', 'Could not copy the link automatically.');
            }
        },
     }"
     x-init="
        $watch('mobileOpen', (open) => document.body.classList.toggle('overflow-hidden', open));
        $watch('sidebarOpen', (open) => {
            document.cookie = 'hs_sidebar_open=' + (open ? '1' : '0') + ';path=/;max-age=31536000;samesite=Lax';
            setTimeout(() => $wire.dispatch('map:resize'), 320);
        });
        window.addEventListener('online', () => online = true);
        window.addEventListener('offline', () => online = false);
     "
     x-on:notify.window="addToast($event.detail.type, $event.detail.message)"
     x-on:copy-to-clipboard.window="copyText($event.detail.text)"
     x-on:request-location-for-distance.window="locate()"
     x-on:locate-me-for-directions.window="pendingDirectionRequest = true; locate()"
     x-on:tenant-viewed.window="addRecent($event.detail)"
     x-on:print-map.window="window.print()"
     x-on:map:center-changed.window="
        viewport.lat = $event.detail.lat;
        viewport.lng = $event.detail.lng;
        $wire.updateViewport(viewport.lat, viewport.lng, viewport.zoom);
     "
     x-on:map:zoom-changed.window="
        viewport.zoom = $event.detail.zoom;
        $wire.updateViewport(viewport.lat, viewport.lng, viewport.zoom);
     "
     x-on:keydown.window="
        const typing = ['INPUT','TEXTAREA'].includes(document.activeElement?.tagName);
        const hasModifier = $event.ctrlKey || $event.metaKey || $event.altKey;

        if ($event.key === '/' && !typing) {
            $event.preventDefault();
            $refs.searchInput && $refs.searchInput.focus();
            return;
        }

        if ($event.key === 'Escape') {
            if (helpOpen) { helpOpen = false; helpTrigger?.focus(); helpTrigger = null; }
            else if (typing && document.activeElement === $refs.searchInput) { $refs.searchInput.blur(); }
            else if (mobileOpen) { mobileOpen = false; }
            return;
        }

        if (typing || hasModifier) return;

        switch ($event.key.toLowerCase()) {
            case 'l': locate(); break;
            case 'f': followMode ? stopFollowMode() : startFollowMode(); break;
            case 's': $wire.toggleSatellite(); break;
            case 'p': $wire.printMap(); break;
            case 'r': $wire.resetFilters(); break;
            case '?': helpOpen = true; break;
        }
     ">

    
    <div
        class="fixed inset-0 z-[1090] bg-black/50 transition-opacity duration-300 motion-reduce:transition-none lg:hidden print:hidden"
        :class="mobileOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
        @click="mobileOpen = false"
        aria-hidden="true"
    ></div>

    
    <aside
        class="fixed inset-y-0 left-0 z-[1100] w-[85%] max-w-[360px] -translate-x-full border-r border-gray-200 bg-white shadow-2xl transition-transform duration-300 ease-out motion-reduce:transition-none dark:border-gray-700 dark:bg-gray-900 lg:static lg:z-auto lg:max-w-none lg:translate-x-0 lg:overflow-hidden lg:transition-[width] lg:duration-300 print:hidden"
        :class="[
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            sidebarOpen ? 'lg:w-[360px]' : 'lg:w-0',
        ]"
        role="dialog"
        aria-modal="true"
        aria-label="Destinations sidebar"
    >
        <div class="relative h-full w-[85vw] max-w-[360px] lg:w-[360px]">
            <button
                @click="mobileOpen = false"
                class="absolute right-3 top-3 z-10 flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-200 lg:hidden"
                aria-label="Close destinations list"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <?php echo $__env->make('livewire.partials.explore-sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
    </aside>

    
    <button
        @click="sidebarOpen = !sidebarOpen"
        class="absolute left-0 top-1/2 z-[1000] hidden h-16 w-6 -translate-y-1/2 items-center justify-center rounded-r-xl border border-l-0 border-gray-200 bg-white text-gray-500 shadow-lg transition-[left] duration-300 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-blue-400 lg:flex print:hidden"
        :style="`left: ${sidebarOpen ? 360 : 0}px`"
        :aria-expanded="sidebarOpen.toString()"
        aria-label="Toggle sidebar"
    >
        <svg x-show="sidebarOpen" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        <svg x-show="!sidebarOpen" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>

    
    <div class="relative h-full min-w-0 flex-1 bg-gray-100 dark:bg-gray-800 print:bg-white">

        <div class="absolute inset-0 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 print:hidden" aria-hidden="true"></div>

        <div
            x-show="!online"
            x-transition
            class="absolute inset-x-0 top-0 z-[1200] flex items-center justify-center gap-2 bg-amber-500 px-4 py-2 text-center text-[12px] font-semibold text-white print:hidden"
            role="status"
        >
            ⚠️ You're offline — map tiles and directions may not load until your connection returns.
        </div>

        <div class="pointer-events-none absolute left-4 top-4 z-[50] hidden rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-900 print:block">
            Victorias City · Explore Map — <?php echo e(now()->format('F j, Y')); ?> · <?php echo e($this->tenants->count()); ?> <?php echo e(Str::plural('destination', $this->tenants->count())); ?> shown
        </div>

        <div
            x-data="{ show: true }"
            x-init="
                const hide = () => { show = false };
                window.addEventListener('map:loaded', hide, { once: true });
                setTimeout(hide, 6000);
            "
            x-show="show"
            x-transition:leave="transition-opacity duration-500 ease-in motion-reduce:transition-none"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 z-[900] flex items-center justify-center bg-gray-100 dark:bg-gray-900 print:hidden"
        >
            <div class="text-center">
                <div class="mx-auto mb-3 h-12 w-12 animate-spin motion-reduce:animate-none rounded-full border-4 border-primary-600 border-t-transparent"></div>
                <p class="text-sm text-gray-600 dark:text-gray-300">Loading map…</p>
            </div>
        </div>

        <div
            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tourist-map-'.e($satellite ? 'satellite' : 'normal').'-'.e($locationVersion).'-'.e($filtersHash).'-'.e($routeVersion).''; ?>wire:key="tourist-map-<?php echo e($satellite ? 'satellite' : 'normal'); ?>-<?php echo e($locationVersion); ?>-<?php echo e($filtersHash); ?>-<?php echo e($routeVersion); ?>"
            class="absolute inset-0"
        >
            <?php if (isset($component)) { $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\Map::resolve(['center' => $this->initialCenter,'zoom' => $this->initialZoom,'height' => '100%','provider' => $satellite ? 'custom' : 'carto-voyager','style' => $satellite ? route('map.satellite.style') : null,'lightStyle' => $satellite ? route('map.satellite.style') : null,'darkStyle' => $satellite ? route('map.satellite.style') : null,'theme' => 'auto','maxZoom' => $satellite ? 19 : 22,'class' => 'h-full w-full'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\Map::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'tourist-map']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                <?php if (isset($component)) { $__componentOriginal30d4ce5150bc700b8142cf87b21ef225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapControls::resolve(['zoom' => true,'compass' => true,'locate' => false,'fullscreen' => true,'scale' => true,'position' => 'top-right'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-controls'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapControls::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $attributes = $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $component = $__componentOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userLat && $userLng): ?>
                    <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $userLat,'lng' => $userLng,'color' => '#22c55e','id' => 'user-location'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['key' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('user-location'),'wire:key' => 'marker-user-location']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <?php if (isset($component)) { $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerContent::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-content'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerContent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            <div class="relative flex h-10 w-10 items-center justify-center">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($routeCoords)): ?>
                                    <span class="absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75 animate-ping"></span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="relative flex h-8 w-8 items-center justify-center rounded-full border-2 shadow-lg transition-colors"
                                     :class="{ 'bg-blue-500 border-white': <?php echo \Illuminate\Support\Js::from(!empty($routeCoords))->toHtml() ?>, 'bg-green-500 border-white': <?php echo \Illuminate\Support\Js::from(empty($routeCoords))->toHtml() ?> }">
                                    <svg x-show="userHeading !== null && userHeading !== undefined"
                                         :style="'transform: rotate(' + (userHeading || 0) + 'deg)'"
                                         class="h-4 w-4 text-white"
                                         fill="currentColor"
                                         viewBox="0 0 24 24">
                                        <path d="M12 2 L19 21 L12 17 L5 21 Z" />
                                    </svg>
                                    <svg x-show="!userHeading"
                                         class="h-3 w-3 text-white"
                                         fill="currentColor"
                                         viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="4" />
                                    </svg>
                                </div>
                            </div>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $attributes = $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $component = $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
                        <?php if (isset($component)) { $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerPopup::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerPopup::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            <div class="p-2">
                                <strong class="text-gray-900 dark:text-white">
                                    <?php echo e(!empty($routeCoords) ? 'Route Start' : 'You are here'); ?>

                                </strong>
                                <button @click="$wire.shareLocation()" class="mt-1 block text-[11px] font-semibold text-primary-600 hover:text-blue-700 dark:text-blue-400">🔗 Share this location</button>
                            </div>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $attributes = $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $component = $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $attributes = $__attributesOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__attributesOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $component = $__componentOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__componentOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $hue = ($loop->index * 137) % 360;
                        $tenantColor = 'hsl(' . $hue . ', 65%, 55%)';
                        $isRouteDestination = $routeTenantId === $tenant->id && !empty($routeCoords);
                    ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tenant->coordinates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $coordIndex => $coord): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $isParent = $coordIndex === 0 || ($coord['type'] ?? '') === 'parent';
                            $logoUrl = $tenant->logo ? asset('storage/' . $tenant->logo) : null;

                            // Determine sub-marker icon if type is set
                            $coordType = $coord['type'] ?? null;
                            $isCategoryType = !$isParent && $coordType && isset($this->markerTypes[$coordType]);
                        ?>

                        <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $coord['lat'],'lng' => $coord['lng'],'color' => $isParent ? $tenantColor : ($isCategoryType ? $this->markerColors[$coordType] : $tenantColor),'id' => 'tenant-'.e($tenant->id).'-'.e($coordIndex).''] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['key' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('tenant-'.$tenant->id.'-'.$coordIndex),'wire:key' => 'marker-'.e($tenant->id).'-'.e($coordIndex).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                            <?php if (isset($component)) { $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerContent::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-content'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerContent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isParent): ?>
                                    <div class="flex h-11 w-11 items-center justify-center rounded-full border-2 bg-white shadow-lg
                                                <?php echo e($isRouteDestination ? 'ring-4 ring-blue-300/50' : ''); ?>"
                                         style="border-color: <?php echo e($tenantColor); ?>;">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                                            <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($tenant->name); ?>"
                                                 class="h-full w-full rounded-full object-cover"
                                                 loading="lazy">
                                        <?php else: ?>
                                            <span class="text-sm font-black text-gray-800">
                                                <?php echo e(strtoupper(substr($tenant->name, 0, 2))); ?>

                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                <?php elseif($isCategoryType): ?>
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full border-2 bg-white shadow-lg text-xl
                                                <?php echo e($isRouteDestination ? 'ring-4 ring-blue-300/50' : ''); ?>"
                                         style="border-color: <?php echo e($this->markerColors[$coordType]); ?>;">
                                        <span class="leading-none"><?php echo e($this->markerEmojis[$coordType]); ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="flex h-5 w-5 items-center justify-center rounded-full border-2 bg-white shadow
                                                <?php echo e($isRouteDestination ? 'ring-4 ring-blue-300/50' : ''); ?>"
                                         style="border-color: <?php echo e($tenantColor); ?>;">
                                        <span class="block h-2.5 w-2.5 rounded-full" style="background: <?php echo e($tenantColor); ?>;"></span>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $attributes = $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $component = $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>

                            <?php if (isset($component)) { $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerPopup::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerPopup::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <div class="min-w-[240px] p-3">
                                    <h3 class="font-bold text-gray-900 dark:text-white"><?php echo e($coord['name'] ?? $tenant->name); ?></h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <?php echo e($isParent ? ($tenant->typeOfTenant?->type ?? 'Business') : ($isCategoryType ? $this->markerTypes[$coordType] : 'Sub-location')); ?>

                                    </p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userLat && $userLng): ?>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            📍 <?php echo e($this->formatDistance($this->calculateDistance($coord['lat'], $coord['lng']))); ?> away
                                        </p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->address): ?>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?php echo e($tenant->address); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="mt-3 flex gap-2">
                                        <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" class="flex-1 rounded-lg bg-primary-600 px-3 py-2 text-center text-xs font-semibold text-white transition hover:bg-blue-700">View</a>
                                        <button wire:click="<?php echo e($isParent ? 'getDirectionsTo('.$tenant->id.')' : 'getDirectionsToCoord('.$tenant->id.','.$coordIndex.')'); ?>" class="flex-1 rounded-lg border border-primary-600 px-3 py-2 text-xs font-semibold text-primary-600 transition hover:bg-blue-50 dark:hover:bg-blue-500/10">Directions</button>
                                    </div>
                                </div>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $attributes = $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $component = $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $attributes = $__attributesOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__attributesOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $component = $__componentOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__componentOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($routeCoords)): ?>
                    <?php if (isset($component)) { $__componentOriginale85b8191a188645a4bea4a69496eba23 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale85b8191a188645a4bea4a69496eba23 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapRoute::resolve(['id' => ''.e($routeId).'','coordinates' => [$routeCoords['start'], $routeCoords['end']],'fetchDirections' => true,'alternatives' => true,'directionsProfile' => $directionsProfile,'color' => '#22c55e','width' => 5,'withStops' => true,'alternativeColor' => '#06b6d4'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-route'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapRoute::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:key' => 'route-primary-'.e(md5(serialize($routeCoords))).'-'.e($directionsProfile).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale85b8191a188645a4bea4a69496eba23)): ?>
<?php $attributes = $__attributesOriginale85b8191a188645a4bea4a69496eba23; ?>
<?php unset($__attributesOriginale85b8191a188645a4bea4a69496eba23); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale85b8191a188645a4bea4a69496eba23)): ?>
<?php $component = $__componentOriginale85b8191a188645a4bea4a69496eba23; ?>
<?php unset($__componentOriginale85b8191a188645a4bea4a69496eba23); ?>
<?php endif; ?>

                    <?php if (isset($component)) { $__componentOriginale85b8191a188645a4bea4a69496eba23 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginale85b8191a188645a4bea4a69496eba23 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapRoute::resolve(['coordinates' => [$routeCoords['start'], $routeCoords['end']],'color' => '#f59e0b','width' => 3,'opacity' => 0.7,'dashArray' => [8, 6]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-route'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapRoute::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:key' => 'route-reference-'.e(md5(serialize($routeCoords))).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginale85b8191a188645a4bea4a69496eba23)): ?>
<?php $attributes = $__attributesOriginale85b8191a188645a4bea4a69496eba23; ?>
<?php unset($__attributesOriginale85b8191a188645a4bea4a69496eba23); ?>
<?php endif; ?>
<?php if (isset($__componentOriginale85b8191a188645a4bea4a69496eba23)): ?>
<?php $component = $__componentOriginale85b8191a188645a4bea4a69496eba23; ?>
<?php unset($__componentOriginale85b8191a188645a4bea4a69496eba23); ?>
<?php endif; ?>

                    <?php if (isset($component)) { $__componentOriginalede5fe535ba1970408683e8d99dc05f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalede5fe535ba1970408683e8d99dc05f6 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapRouteList::resolve(['routeId' => ''.e($routeId).'','mapId' => 'tourist-map','title' => 'Available Routes','width' => 'w-60','position' => 'bottom-left','containerClass' => 'z-[850] print:hidden'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-route-list'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapRouteList::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalede5fe535ba1970408683e8d99dc05f6)): ?>
<?php $attributes = $__attributesOriginalede5fe535ba1970408683e8d99dc05f6; ?>
<?php unset($__attributesOriginalede5fe535ba1970408683e8d99dc05f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalede5fe535ba1970408683e8d99dc05f6)): ?>
<?php $component = $__componentOriginalede5fe535ba1970408683e8d99dc05f6; ?>
<?php unset($__componentOriginalede5fe535ba1970408683e8d99dc05f6); ?>
<?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $attributes = $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $component = $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
        </div>

        <div class="absolute left-3 top-3 z-[1000] flex flex-col gap-1.5 sm:left-4 sm:top-4 sm:gap-2 print:hidden" role="toolbar" aria-label="Map tools">
            <button @click="mobileOpen = true" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400 lg:hidden" aria-label="Open destinations list" title="Destinations">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <button type="button" @click="locate()" :disabled="locating" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 disabled:cursor-wait disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400" aria-label="Use my location" title="Use my location (L)">
                <svg x-show="!locating" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21c-4.5-4.5-7.5-8.24-7.5-11.5A7.5 7.5 0 0112 2a7.5 7.5 0 017.5 7.5c0 3.26-3 7-7.5 11.5z"/><circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                <svg x-show="locating" class="h-4 w-4 animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
            </button>

            
            <button type="button" @click="followMode ? stopFollowMode() : startFollowMode()" aria-pressed="<?php echo e($followMode ? 'true' : 'false'); ?>" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border shadow-lg transition <?php echo e($followMode ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400'); ?>" aria-label="Toggle follow mode" title="Follow my location (F)">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M12 2 L21 21 L12 17 L3 21 Z" fill="currentColor" stroke="none"/>
                </svg>
            </button>

            <button wire:click="fitAllLocations" <?php if(count($this->geoJsonData) < 2): echo 'disabled'; endif; ?> class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:disabled:hover:text-gray-300" aria-label="Show all destinations" title="Fit all destinations">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4h4M4 4l5 5M16 4h4v4M20 4l-5 5M4 16v4h4M4 20l5-5M16 20h4v-4M20 20l-5-5"/></svg>
            </button>

            <button wire:click="toggleSatellite" aria-pressed="<?php echo e($satellite ? 'true' : 'false'); ?>" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border text-lg shadow-lg transition <?php echo e($satellite ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-200 bg-white text-gray-600 hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400'); ?>" aria-label="Toggle satellite view" title="Satellite view (S)">🛰️</button>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($routeCoords)): ?>
                <button wire:click="clearRoute" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-red-200 bg-white text-red-500 shadow-lg transition hover:bg-red-50 dark:border-red-500/30 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-red-500/10" aria-label="Cancel route" title="Cancel route">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
                <button @click="open = !open" :aria-expanded="open.toString()" aria-haspopup="true" class="flex h-9 w-9 sm:h-11 sm:w-11 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-lg transition hover:text-primary-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:text-blue-400" aria-label="More map options" title="More options">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="19" cy="12" r="1.5" fill="currentColor" stroke="none"/></svg>
                </button>
                <div x-show="open" x-transition:enter="transition ease-out duration-120" x-transition:enter-start="opacity-0 -translate-x-1" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 -translate-x-1" style="display: none;" class="absolute left-full top-0 ml-2 w-52 overflow-hidden rounded-xl border border-gray-200 bg-white py-1.5 shadow-xl dark:border-gray-700 dark:bg-gray-800" role="menu">
                    <button role="menuitem" @click="open = false; $wire.printMap()" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">🖨️ Print map</button>
                    <button role="menuitem" @click="open = false; $wire.shareLocation()" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">🔗 Share my location</button>
                    <button role="menuitem" @click="open = false; $wire.resetView()" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">🎯 Recenter map</button>
                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                    <button role="menuitem" @click="open = false; helpTrigger = $el; helpOpen = true" class="flex w-full items-center gap-2.5 px-3.5 py-2 text-left text-[12.5px] font-medium text-gray-700 transition hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/60">❓ Help &amp; shortcuts</button>
                </div>
            </div>
        </div>
    </div>

    
    <div
        class="pointer-events-none fixed z-[1300] flex flex-col gap-2 print:hidden
               inset-x-4 top-20 items-center
               sm:inset-x-auto sm:right-4 sm:top-4 sm:items-end"
        aria-live="polite"
        aria-atomic="false"
    >
        <template x-for="toast in toasts" :key="toast.id">
            <div @mouseenter="pauseToast(toast)" @mouseleave="resumeToast(toast)"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="pointer-events-auto w-full max-w-sm overflow-hidden rounded-xl border bg-white/95 shadow-xl backdrop-blur dark:bg-gray-800/95 sm:w-auto"
                 :class="{
                    'border-emerald-200 dark:border-emerald-500/30': toast.type === 'success',
                    'border-red-200 dark:border-red-500/30': toast.type === 'error',
                    'border-blue-200 dark:border-blue-500/30': toast.type === 'info',
                    'border-amber-200 dark:border-amber-500/30': toast.type === 'warning'
                 }"
            >
                <div class="flex items-center gap-2.5 px-4 py-3 text-[13px] font-medium"
                     :class="{
                        'text-emerald-800 dark:text-emerald-300': toast.type === 'success',
                        'text-red-800 dark:text-red-300': toast.type === 'error',
                        'text-blue-800 dark:text-blue-300': toast.type === 'info',
                        'text-amber-800 dark:text-amber-300': toast.type === 'warning'
                     }"
                >
                    <span x-show="toast.type === 'success'">✅</span>
                    <span x-show="toast.type === 'error'">⚠️</span>
                    <span x-show="toast.type === 'info'">ℹ️</span>
                    <span x-show="toast.type === 'warning'">🔶</span>
                    <span class="flex-1" x-text="toast.message"></span>
                    <button @click="removeToast(toast.id)" class="text-gray-400 transition hover:text-gray-700 dark:hover:text-gray-200" aria-label="Dismiss notification">✕</button>
                </div>
                <div class="h-0.5 w-full bg-black/5 dark:bg-white/5">
                    <div class="h-full transition-[width] duration-100 ease-linear motion-reduce:transition-none"
                         :class="{
                            'bg-emerald-400': toast.type === 'success',
                            'bg-red-400': toast.type === 'error',
                            'bg-blue-400': toast.type === 'info',
                            'bg-amber-400': toast.type === 'warning'
                         }"
                         :style="'width: ' + ((toast.remaining / toast.duration) * 100) + '%'"
                    ></div>
                </div>
            </div>
        </template>
    </div>

    
    <div x-show="helpOpen" x-transition.opacity style="display: none;" class="fixed inset-0 z-[1500] flex items-center justify-center bg-black/50 p-4 print:hidden" role="dialog" aria-modal="true" aria-labelledby="help-modal-title" @click.self="helpOpen = false; helpTrigger?.focus(); helpTrigger = null" x-init="$watch('helpOpen', (open) => { if (open) $nextTick(() => $refs.helpCloseBtn && $refs.helpCloseBtn.focus()) })">
        <div x-show="helpOpen" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100" class="w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-800">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                <h2 id="help-modal-title" class="text-[15px] font-extrabold text-gray-900 dark:text-white">Legend &amp; shortcuts</h2>
                <button x-ref="helpCloseBtn" @click="helpOpen = false; helpTrigger?.focus(); helpTrigger = null" class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-700 dark:hover:text-gray-200" aria-label="Close help">✕</button>
            </div>
            <div class="max-h-[70vh] overflow-y-auto px-5 py-4">
                <section>
                    <h3 class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Map legend</h3>
                    <ul class="space-y-2 text-[12.5px] text-gray-600 dark:text-gray-300">
                        <li class="flex items-center gap-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span> Your current location</li>
                        <li class="flex items-center gap-2.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-primary-600"></span> Destinations &amp; clusters</li>
                        <li class="flex items-center gap-2.5"><span class="h-1 w-4 shrink-0 rounded-full bg-emerald-500"></span> Active route</li>
                        <li class="flex items-center gap-2.5"><span class="h-1 w-4 shrink-0 rounded-full bg-cyan-500"></span> Alternative route</li>
                        <li class="flex items-center gap-2.5"><span class="h-1 w-4 shrink-0 rounded-full border-t-2 border-dashed border-amber-500"></span> Straight-line reference</li>
                    </ul>
                </section>
                <section class="mt-5">
                    <h3 class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Keyboard shortcuts</h3>
                    <dl class="space-y-1.5 text-[12.5px]">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = ['/' => 'Focus search','L' => 'Use my location','F' => 'Toggle follow mode','S' => 'Toggle satellite view','P' => 'Print map','R' => 'Clear filters','Esc' => 'Close panels','?' => 'Toggle this help']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600 dark:text-gray-300"><?php echo e($label); ?></dt>
                                <dd><kbd class="rounded-md border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-gray-600 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300"><?php echo e($key); ?></kbd></dd>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </dl>
                </section>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/edb49f3a.blade.php ENDPATH**/ ?>