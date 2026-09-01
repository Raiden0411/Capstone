<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use App\Models\Tenant;
use App\Models\Event;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
?>




<div>
    <div class="relative z-10">

        
        <section class="relative w-full h-screen min-h-[600px] overflow-hidden bg-gray-900">
            <img src="<?php echo e($this->heroBackgroundUrl); ?>"
                 alt="<?php echo e($this->heroSubtitle); ?>"
                 class="absolute inset-0 w-full h-full object-cover scale-105">
            <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-black/80"></div>

            <div class="relative z-10 flex flex-col items-center justify-center h-full px-4 sm:px-6 lg:px-12 text-center">
                <p class="text-yellow-400 font-semibold tracking-[0.35em] uppercase text-sm md:text-base mb-4">
                    <?php echo e($this->heroTitle); ?>

                </p>
                <h1 class="text-white font-display text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold leading-tight max-w-4xl">
                    <?php echo e($this->heroSubtitle); ?>

                </h1>

                <form wire:submit.prevent="search"
                      class="mt-10 w-full max-w-3xl bg-white/95 dark:bg-gray-800/95 backdrop-blur border-2 border-primary-600 dark:border-blue-500 shadow-2xl rounded-full pl-6 pr-2 h-16 md:h-20 flex items-center">
                    <svg class="shrink-0 text-gray-400 dark:text-gray-500 mr-3 w-5 h-5"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text"
                           wire:model="searchQuery"
                           placeholder="Search destinations, attractions, or activities..."
                           class="w-full h-full text-base md:text-lg text-gray-900 dark:text-white outline-none bg-transparent placeholder-gray-500 dark:placeholder-gray-400">
                    <button type="submit"
                            wire:loading.attr="disabled"
                            wire:target="search"
                            class="ml-4 px-8 py-3 md:px-10 md:py-3.5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm md:text-base font-bold shrink-0 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed active:scale-95">
                        <span wire:loading.remove wire:target="search">Search</span>
                        <span wire:loading wire:target="search" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Searching…
                        </span>
                    </button>
                </form>

                <div class="mt-10 animate-bounce w-6 h-10 border-2 border-white/40 rounded-full flex items-start justify-center p-1">
                    <svg class="w-2 h-3 text-white/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </div>
            </div>
        </section>

        
        <section class="max-w-6xl px-4 sm:px-6 lg:px-8 mx-auto mt-16 md:mt-24 mb-12 md:mb-16">
            <div class="flex items-center justify-between mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">Popular Destinations</h2>
                <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                   class="inline-flex items-center gap-1 text-sm font-semibold text-primary-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-all duration-200 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95">
                    View All
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->popularDestinations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $cardImage = $tenant->logo
                            ? asset('storage/' . $tenant->logo)
                            : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop';
                    ?>
                    <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" wire:navigate
                       class="group relative block rounded-2xl overflow-hidden aspect-[4/5] bg-gray-200 dark:bg-gray-800 shadow-sm hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1 active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:outline-none">
                        <img src="<?php echo e($cardImage); ?>" alt="<?php echo e($tenant->name); ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition duration-700"
                             loading="lazy"
                             onerror="this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800&auto=format&fit=crop'">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                        <div class="absolute bottom-0 p-4 md:p-5 text-left">
                            <h3 class="text-white font-bold text-lg md:text-xl"><?php echo e($tenant->name); ?></h3>
                            <p class="text-white/80 text-xs md:text-sm"><?php echo e($tenant->typeOfTenant?->type ?? 'Destination'); ?></p>
                        </div>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <p class="text-gray-500 dark:text-gray-400 col-span-full text-center py-8">
                        No recommended destinations yet.
                    </p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>

        
        <section class="px-4 sm:px-6 lg:px-8 py-16 md:py-24 text-white relative overflow-hidden">
            <img src="https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1600&q=80"
                 alt="Discover Victorias"
                 class="absolute inset-0 object-cover w-full h-full opacity-20">
            <div class="absolute inset-0 bg-gradient-to-br from-[#2b5fb3] to-[#1e3a5f]"></div>
            <div class="relative z-10 grid items-center max-w-6xl grid-cols-1 md:grid-cols-2 gap-8 md:gap-12 mx-auto">
                <div>
                    <h2 class="mb-4 text-2xl md:text-3xl font-display font-bold leading-snug"><?php echo e($this->discoverTitle); ?></h2>
                    <p class="max-w-sm text-sm md:text-base leading-relaxed text-blue-100"><?php echo e($this->discoverDescription); ?></p>
                </div>
                <div class="grid grid-cols-2 gap-3 md:gap-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->heroSideImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $image): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <img src="<?php echo e($image); ?>"
                             alt="Discover Victorias"
                             class="object-cover w-full h-28 md:h-36 rounded-xl shadow-lg">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section class="w-full py-16 md:py-20 bg-white dark:bg-gray-900"
                 x-data="{
                    items: <?php echo \Illuminate\Support\Js::from($this->carouselItems->isNotEmpty() ? $this->carouselItems->toArray() : [
                        ['name' => 'Gawahon Eco-Park', 'image' => 'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=800&q=80', 'slug' => ''],
                        ['name' => 'Victorias Milling Co.', 'image' => 'https://images.unsplash.com/photo-1449034446853-66c86144b0ad?auto=format&fit=crop&w=800&q=80', 'slug' => ''],
                        ['name' => 'The Ecotrail', 'image' => 'https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=800&q=80', 'slug' => ''],
                        ['name' => 'Nature Reserve', 'image' => 'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=800&q=80', 'slug' => ''],
                        ['name' => 'Highlands', 'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?auto=format&fit=crop&w=800&q=80', 'slug' => ''],
                    ])->toHtml() ?>,
                    active: 0,
                    interval: null,
                    touchStartX: 0,
                    touchEndX: 0,
                    init() {
                        this.startAutoPlay();
                    },
                    startAutoPlay() {
                        if (this.interval) clearInterval(this.interval);
                        this.interval = setInterval(() => this.goTo(this.active + 1), 4000);
                    },
                    stopAutoPlay() {
                        if (this.interval) clearInterval(this.interval);
                    },
                    goTo(i) {
                        this.active = (i + this.items.length) % this.items.length;
                    },
                    handleTouchStart(e) {
                        this.touchStartX = e.changedTouches[0].screenX;
                        this.stopAutoPlay();
                    },
                    handleTouchEnd(e) {
                        this.touchEndX = e.changedTouches[0].screenX;
                        const diff = this.touchStartX - this.touchEndX;
                        if (Math.abs(diff) > 50) {
                            if (diff > 0) this.goTo(this.active + 1);
                            else this.goTo(this.active - 1);
                        }
                        this.startAutoPlay();
                    },
                    getPositionStyle(index) {
                        const length = this.items.length;
                        const rel = (index - this.active + length) % length;
                        const styles = {
                            0: { left: '50%', width: '60%', height: '100%', transform: 'translate(-50%, -50%)', zIndex: 30, opacity: 1 },
                            1: { right: '8%', width: '28%', height: '80%', transform: 'translateY(-50%)', zIndex: 20, opacity: 0.85 },
                            2: { right: '0%', width: '20%', height: '60%', transform: 'translate(20%, -50%)', zIndex: 10, opacity: 0.5 },
                            3: { left: '8%', width: '28%', height: '80%', transform: 'translateY(-50%)', zIndex: 20, opacity: 0.85 },
                            4: { left: '0%', width: '20%', height: '60%', transform: 'translate(-20%, -50%)', zIndex: 10, opacity: 0.5 },
                        };
                        const style = styles[rel] || { left: '50%', width: '0%', height: '0%', transform: 'translate(-50%, -50%)', zIndex: 0, opacity: 0 };
                        return {
                            position: 'absolute',
                            top: '50%',
                            transition: 'all 0.5s ease',
                            overflow: 'hidden',
                            ...style,
                        };
                    }
                 }"
                 @mouseenter="stopAutoPlay()"
                 @mouseleave="startAutoPlay()"
                 @touchstart="handleTouchStart($event)"
                 @touchend="handleTouchEnd($event)">

            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center mb-10 md:mb-14">
                <p class="text-amber-500 font-bold tracking-[0.2em] uppercase text-xs mb-2">Popular Spots</p>
                <h2 class="text-2xl md:text-3xl font-display font-bold text-gray-900 dark:text-white">Most Visited Places</h2>
                <div class="w-24 h-1 bg-primary-600 dark:bg-blue-500 mx-auto rounded-full mt-5"></div>
                <p class="max-w-xl mx-auto mt-3 text-sm md:text-base font-medium text-gray-600 dark:text-gray-300">
                    Discover the most popular destinations in Victorias City and experience the places visitors love the most.
                </p>
            </div>

            <div class="relative flex items-center justify-center max-w-6xl mx-auto h-[280px] sm:h-[350px] md:h-[450px] px-4 sm:px-6 lg:px-8">
                <template x-for="(item, index) in items" :key="index">
                    <div class="absolute rounded-3xl overflow-hidden shadow-xl transition-all duration-500"
                         :style="getPositionStyle(index)">
                        <a :href="item.slug ? '/business/' + item.slug + '/offerings' : '#'" class="block h-full w-full focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded-3xl">
                            <img :src="item.image" :alt="item.name" class="object-cover w-full h-full">
                            <div x-show="index === active"
                                 class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end justify-center pb-4">
                                <span class="text-white font-semibold text-sm md:text-lg" x-text="item.name"></span>
                            </div>
                        </a>
                    </div>
                </template>
            </div>

            <div class="flex justify-center items-center gap-4 mt-8">
                <button type="button" @click="goTo(active - 1)"
                        class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-blue-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                        aria-label="Previous">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>

                <div class="flex items-center gap-2">
                    <template x-for="(item, index) in items" :key="`dot-${index}`">
                        <button type="button" @click="goTo(index)"
                                :class="index === active ? 'w-3 h-3 bg-primary-600' : 'w-2 h-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400'"
                                class="rounded-full transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"></button>
                    </template>
                </div>

                <button type="button" @click="goTo(active + 1)"
                        class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-blue-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95"
                        aria-label="Next">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>
        </section>

        
        <section class="max-w-7xl px-4 sm:px-6 lg:px-8 mx-auto mb-16 md:mb-24">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
                <div>
                    <span class="text-sm font-bold uppercase tracking-widest text-primary-600 dark:text-primary-400 mb-2 block">
                        Interactive Directory
                    </span>
                    <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight">
                        Explore Victorias City
                    </h2>
                </div>
                <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                   class="group inline-flex items-center justify-center gap-2 px-5 py-2.5 text-sm font-bold text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 hover:bg-primary-100 dark:hover:bg-primary-900/50 rounded-full transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    Open Full Map
                    <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
            </div>

            <div class="grid items-start grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
                <div class="relative bg-white dark:bg-gray-800 rounded-[2rem] p-3 md:p-4 shadow-xl border border-gray-100 dark:border-gray-700 h-[350px] sm:h-[450px] md:h-[500px] group">
                    <?php if (isset($component)) { $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\Map::resolve(['center' => $this->homeMapCenter,'zoom' => $this->homeMapZoom,'height' => '100%','provider' => 'carto-voyager','theme' => 'auto','class' => 'rounded-3xl overflow-hidden shadow-inner bg-gray-50 dark:bg-gray-900'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\Map::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'home-map','@map:load' => '$event.detail.map?.setRenderWorldCopies(false); $event.detail.map?.setMaxBounds([[122.0, 9.5], [124.0, 11.8]]); $event.detail.map?.setMinZoom(12); $event.detail.map?.setZoom(13); $event.detail.map?.setCenter([123.07391289720677, 10.900736693923502]);']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                        <?php if (isset($component)) { $__componentOriginal30d4ce5150bc700b8142cf87b21ef225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapControls::resolve(['zoom' => true,'compass' => true,'locate' => false,'fullscreen' => true,'scale' => false,'position' => 'top-right'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
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

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->mapLocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locIndex => $loc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $loc['coordinates']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $coordIdx => $coord): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $isParent = $coordIdx === 0 || ($coord['type'] ?? '') === 'parent';
                                    $isActive = $homeHighlightedLocation === $locIndex;

                                    $childType = $coord['type'] ?? 'other';
                                    $category = collect($this->markerCategories)->firstWhere('key', $childType);
                                    $childColor = $isParent ? $loc['color'] : ($category['color'] ?? '#94a3b8');
                                    $childIconSvg = $isParent ? null : ($category['icon_svg'] ?? null);
                                ?>
                                <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $coord['lat'],'lng' => $coord['lng'],'color' => $childColor,'id' => 'home-marker-'.e($locIndex).'-'.e($coordIdx).'','anchor' => 'bottom'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['key' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('home-marker-'.$locIndex.'-'.$coordIdx),'wire:key' => 'home-marker-'.e($locIndex).'-'.e($coordIdx).'']); ?>
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

                                        <div class="flex flex-col items-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isParent): ?>
                                                <div class="flex h-12 w-12 items-center justify-center rounded-full border-4 bg-white dark:bg-gray-900 shadow-xl transition-all duration-300
                                                            <?php echo e($isActive ? 'ring-4 ring-primary-500/30 scale-110' : ''); ?>"
                                                     style="border-color: <?php echo e($loc['color']); ?>; cursor: pointer;"
                                                     @click="$wire.flyToLocation(<?php echo e($locIndex); ?>, <?php echo e($coordIdx); ?>)">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loc['logo']): ?>
                                                        <img src="<?php echo e($loc['logo']); ?>" alt="<?php echo e($loc['name']); ?>"
                                                             class="h-full w-full rounded-full object-cover">
                                                    <?php else: ?>
                                                        <span class="text-sm font-black text-gray-800 dark:text-white tracking-tighter">
                                                            <?php echo e(strtoupper(substr($loc['name'], 0, 2))); ?>

                                                        </span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="relative flex h-10 w-10 items-center justify-center
                                                            transform-gpu will-change-transform transition-transform duration-200
                                                            group-hover:scale-110 active:scale-95
                                                            <?php echo e($isActive ? 'ring-4 ring-primary-500/30 scale-110' : ''); ?>"
                                                     style="cursor: pointer;"
                                                     @click="$wire.flyToLocation(<?php echo e($locIndex); ?>, <?php echo e($coordIdx); ?>)">
                                                    <svg class="absolute inset-0 size-10 drop-shadow-md
                                                                fill-white dark:fill-gray-900
                                                                stroke-slate-400 dark:stroke-slate-600 stroke-1"
                                                         viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                    </svg>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($childIconSvg): ?>
                                                        <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                            <?php echo str_replace('<svg ', '<svg class="size-full stroke-current fill-none" ', $childIconSvg); ?>

                                                        </div>
                                                    <?php else: ?>
                                                        <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">
                                                            <?php echo e(strtoupper(substr($childType, 0, 1))); ?>

                                                        </span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <div class="w-0 h-0 border-l-[6px] border-r-[6px] border-t-[8px] border-l-transparent border-r-transparent drop-shadow-sm"
                                                 style="border-top-color: <?php echo e($isParent ? $loc['color'] : $childColor); ?>;"></div>
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

                                        <div class="min-w-[220px] p-4 bg-white/95 dark:bg-gray-800/95 backdrop-blur-md rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700">
                                            <h3 class="font-extrabold text-gray-900 dark:text-white text-base leading-tight mb-1"><?php echo e($coord['name'] ?? $loc['name']); ?></h3>
                                            <p class="text-xs font-bold uppercase tracking-wider text-primary-600 dark:text-primary-400">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isParent): ?>
                                                    <?php echo e($loc['type']); ?>

                                                <?php else: ?>
                                                    <?php echo e($category['label'] ?? 'Sub-location'); ?>

                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </p>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loc['logo'] && $isParent): ?>
                                                <div class="mt-3 overflow-hidden rounded-xl h-24 w-full">
                                                    <img src="<?php echo e($loc['logo']); ?>" alt="<?php echo e($loc['name']); ?>" class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
                                                </div>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <div class="mt-4 flex gap-2">
                                                <a href="<?php echo e(route('business.offerings', $loc['slug'])); ?>"
                                                   wire:navigate
                                                   class="flex-1 rounded-xl bg-gray-900 dark:bg-white px-3 py-2 text-center text-xs font-bold text-white dark:text-gray-900 shadow-sm hover:bg-gray-800 dark:hover:bg-gray-100 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                                                    View
                                                </a>
                                                <a href="<?php echo e(route('explore.map', ['marker' => $loc['id']])); ?>"
                                                   wire:navigate
                                                   class="flex-1 rounded-xl border-2 border-gray-200 dark:border-gray-600 px-3 py-2 text-center text-xs font-bold text-gray-700 dark:text-gray-300 hover:border-gray-900 dark:hover:border-white transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                                                    Route
                                                </a>
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

                    <div class="absolute bottom-6 left-6 z-10">
                        <a href="<?php echo e(route('explore.map')); ?>"
                           wire:navigate
                           class="inline-flex items-center gap-2 rounded-2xl bg-white/90 dark:bg-gray-900/90 backdrop-blur-md px-5 py-3 text-sm font-bold text-gray-900 dark:text-white shadow-lg border border-white/20 dark:border-gray-700/50 hover:scale-105 hover:bg-white dark:hover:bg-gray-900 transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                            <div class="p-1.5 bg-primary-100 dark:bg-primary-900/50 rounded-lg text-primary-600 dark:text-primary-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            </div>
                            Interactive Map
                        </a>
                    </div>
                </div>

                
                <div class="flex flex-col gap-5">
                    <p class="text-sm md:text-base font-medium leading-relaxed text-gray-700 dark:text-gray-300">
                        Find your way around and discover the places, attractions, and hidden gems that make Victorias City special.
                    </p>

                    <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        <?php
                            $legendTypes = collect($this->mapLocations)->pluck('type')->unique();
                            $legendColors = collect($this->mapLocations)->pluck('color', 'type')->unique();
                        ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $legendTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <span class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full" style="background: <?php echo e($legendColors[$type] ?? '#64748b'); ?>"></span>
                                <?php echo e($type); ?>

                            </span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>

                    <div>
                        <p class="text-gray-500 dark:text-gray-400 text-xs font-bold tracking-widest uppercase mb-3">
                            Nearby Destinations
                        </p>

                        <div class="hidden lg:flex flex-col gap-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->mapLocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locIndex => $loc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $subBranches = array_slice($loc['coordinates'], 1);
                                    $hasSubBranches = count($subBranches) > 0;
                                    $isActive = $homeHighlightedLocation === $locIndex;
                                ?>
                                <div x-data="{ expanded: false }" class="group">
                                    <button wire:click="flyToLocation(<?php echo e($locIndex); ?>, 0)"
                                            class="flex items-center gap-3 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200 text-left w-full focus:outline-none focus:ring-1 focus:ring-primary-600/50 active:scale-[0.98] <?php echo e($isActive ? 'ring-2 ring-primary-600/50 bg-blue-50 dark:bg-blue-900/20' : ''); ?>">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loc['logo']): ?>
                                            <img src="<?php echo e($loc['logo']); ?>" class="w-9 h-9 rounded-full object-cover border border-gray-200 dark:border-gray-600 shrink-0" alt="<?php echo e($loc['name']); ?>">
                                        <?php else: ?>
                                            <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-xs font-bold text-white" style="background: <?php echo e($loc['color']); ?>;"><?php echo e(strtoupper(substr($loc['name'], 0, 1))); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm text-gray-900 dark:text-white font-semibold truncate"><?php echo e($loc['name']); ?></p>
                                        </div>
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium shrink-0"><?php echo e($loc['type']); ?></span>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSubBranches): ?>
                                            <span @click.stop="expanded = !expanded" class="shrink-0 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 cursor-pointer">
                                                <svg class="w-3.5 h-3.5 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </button>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSubBranches): ?>
                                        <div x-show="expanded" x-collapse class="ml-10 mt-1 space-y-1">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $subBranches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subIndex => $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                                <?php
                                                    $subType = $sub['type'] ?? 'other';
                                                    $subCategory = collect($this->markerCategories)->firstWhere('key', $subType);
                                                    $subColor = $subCategory['color'] ?? '#94a3b8';
                                                    $subIconSvg = $subCategory['icon_svg'] ?? null;
                                                ?>
                                                <button wire:click="flyToLocation(<?php echo e($locIndex); ?>, <?php echo e($subIndex + 1); ?>)"
                                                        class="w-full text-left text-xs text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.98] <?php echo e($homeHighlightedLocation === $locIndex ? 'bg-blue-50 dark:bg-blue-900/20' : ''); ?>">
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subIconSvg): ?>
                                                        <span class="inline-block mr-1 align-middle text-gray-800 dark:text-white">
                                                            <?php echo str_replace('<svg ', '<svg class="h-4 w-4 stroke-current fill-none" ', $subIconSvg); ?>

                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-block w-1.5 h-1.5 rounded-full mr-2" style="background: <?php echo e($subColor); ?>"></span>
                                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                    <?php echo e($sub['name'] ?? 'Sub-location '.($subIndex + 1)); ?>

                                                </button>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>

                        <div class="lg:hidden flex overflow-x-auto gap-3 pb-2 -mx-1 px-1 snap-x">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->mapLocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $locIndex => $loc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $mainCoord = $loc['coordinates'][0] ?? null;
                                    $subBranches = array_slice($loc['coordinates'], 1);
                                    $isActive = $homeHighlightedLocation === $locIndex;
                                ?>
                                <button wire:click="flyToLocation(<?php echo e($locIndex); ?>, 0)"
                                        class="shrink-0 w-44 snap-start bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-3.5 text-left transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 <?php echo e($isActive ? 'ring-2 ring-primary-600/50 bg-blue-50 dark:bg-blue-900/20' : ''); ?>">
                                    <div class="flex items-center gap-2.5 mb-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loc['logo']): ?>
                                            <img src="<?php echo e($loc['logo']); ?>" class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-gray-600 shrink-0" alt="<?php echo e($loc['name']); ?>">
                                        <?php else: ?>
                                            <span class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 text-xs font-bold text-white" style="background: <?php echo e($loc['color']); ?>;"><?php echo e(strtoupper(substr($loc['name'], 0, 1))); ?></span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <span class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($loc['name']); ?></span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($loc['type']); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($subBranches) > 0): ?>
                                        <span class="block mt-1.5 text-[10px] text-primary-600 dark:text-blue-400 font-bold uppercase">
                                            <?php echo e(count($subBranches)); ?> sub-locations
                                        </span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="py-16 md:py-20 bg-gray-50 dark:bg-gray-800/50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-10 md:mb-12">
                    <p class="text-primary-600 dark:text-blue-400 font-bold tracking-[0.2em] uppercase text-xs mb-2">Don't Miss Out</p>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Featured Events</h2>
                    <div class="w-24 h-1 bg-primary-600 dark:bg-blue-500 mx-auto rounded-full mt-5"></div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->featuredEvents->isNotEmpty()): ?>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->featuredEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <a href="<?php echo e(route('events', ['event' => $event->id])); ?>" wire:navigate
                               class="group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl transition-all duration-300 block focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.98]">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->image_path): ?>
                                    <div class="h-52 overflow-hidden">
                                        <img src="<?php echo e(asset('storage/' . $event->image_path)); ?>" alt="<?php echo e($event->name); ?>"
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy">
                                    </div>
                                <?php else: ?>
                                    <div class="h-52 bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <svg class="h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="p-5 md:p-6">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-semibold uppercase tracking-wider text-primary-600 dark:text-blue-400"><?php echo e($event->type); ?></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($event->start_date->format('M d, Y')); ?></span>
                                    </div>
                                    <h3 class="font-display text-lg md:text-xl font-semibold text-gray-900 dark:text-white mb-2"><?php echo e($event->name); ?></h3>
                                    <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed"><?php echo e(Str::limit($event->description, 80)); ?></p>
                                    <span class="mt-4 inline-block text-primary-600 dark:text-blue-400 hover:underline text-sm font-medium">Learn more →</span>
                                </div>
                            </a>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-500 dark:text-gray-400">No featured events yet.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="text-center mt-10">
                    <a href="<?php echo e(route('events')); ?>" wire:navigate
                       class="inline-flex items-center gap-2 py-3 px-6 rounded-full bg-primary-600 hover:bg-primary-700 text-white font-semibold shadow-lg shadow-blue-500/20 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                        View All Events
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>
        </section>

        
        <section class="relative py-16 md:py-24 bg-gray-900">
            <img src="https://images.unsplash.com/photo-1542314831-c6a4d14db54d?auto=format&fit=crop&w=1920&q=80"
                 alt="Plan Your Visit"
                 class="absolute inset-0 object-cover w-full h-full opacity-40">
            <div class="relative z-10 flex flex-col items-center max-w-2xl px-4 sm:px-6 mx-auto text-center text-white">
                <h2 class="mb-4 text-3xl md:text-5xl font-display font-bold">Plan Your Visit</h2>
                <p class="mb-8 text-sm md:text-base text-gray-200">Start your journey today! Discover the best places, experiences, and adventures Victorias City has to offer.</p>
                <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                   class="w-full sm:w-auto px-10 py-3.5 text-sm md:text-base font-bold text-white transition bg-primary-600 rounded-full hover:bg-primary-700 text-center shadow-lg shadow-blue-500/20 focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                    Explore Now
                </a>
            </div>
        </section>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/bb4a5881.blade.php ENDPATH**/ ?>