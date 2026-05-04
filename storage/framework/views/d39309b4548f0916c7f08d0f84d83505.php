<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
?>

<div class="bg-[#F7F6F1] dark:bg-[#0a0f1e] transition-colors duration-300">
    <!-- ========== DYNAMIC HERO CAROUSEL (USING LOGO) ========== -->
    <div class="w-full"
         x-data="{
            initCarousel: function() {
                const container = this.$el.querySelector('[data-hs-carousel]');
                if (container && window.HSCarousel) {
                    if (container._hsCarousel) container._hsCarousel.destroy();
                    new window.HSCarousel(container, JSON.parse(container.getAttribute('data-hs-carousel')));
                }
            }
         }"
         x-init="
            $nextTick(() => initCarousel());
            document.addEventListener('livewire:navigated', () => { $nextTick(() => initCarousel()); });
         ">
        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'carousel-key-'.e($this->carouselKey).''; ?>wire:key="carousel-key-<?php echo e($this->carouselKey); ?>">
            <div data-hs-carousel='{"loadingClasses":"opacity-0","isAutoPlay":true}' class="relative">
                <div class="hs-carousel relative overflow-hidden w-full h-[60vh] md:h-screen bg-gray-100 dark:bg-slate-900">
                    <div class="hs-carousel-body absolute top-0 bottom-0 start-0 flex flex-nowrap transition-transform duration-700 opacity-0">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->carouselTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php
                                $logoUrl = $tenant->logo ? Storage::url($tenant->logo) : asset('images/default-logo.jpg');
                                $tagline = $tenant->typeOfTenant->type ?? 'Discover';
                                $shortDescription = Str::limit($tenant->address ?? "Experience the beauty of {$tenant->name}", 120);
                            ?>
                            <div class="hs-carousel-slide">
                                <div class="h-full flex flex-col bg-cover bg-center bg-no-repeat"
                                     style="background-image: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.8)), url('<?php echo e($logoUrl); ?>');">
                                    <div class="mt-auto w-full md:max-w-2xl ps-8 pb-12 md:ps-16 md:pb-20">
                                        <span class="block text-white font-medium tracking-widest uppercase text-sm mb-2"><?php echo e($tagline); ?></span>
                                        <span class="block text-white text-3xl md:text-6xl font-bold leading-tight"><?php echo e($tenant->name); ?></span>
                                        <p class="text-white/90 text-sm md:text-base mt-3 max-w-md"><?php echo e($shortDescription); ?></p>
                                        <div class="mt-8">
                                            <a class="py-3 px-8 inline-flex items-center gap-x-2 text-sm font-semibold rounded-xl bg-white text-gray-800 dark:bg-slate-800 dark:text-white hover:bg-gray-100 dark:hover:bg-slate-700 transition-all focus:outline-none" href="<?php echo e(route('tenant.show', $tenant->slug)); ?>" wire:navigate>
                                                Explore <?php echo e($tenant->name); ?>

                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            
                            <div class="hs-carousel-slide">
                                <div class="h-full flex flex-col bg-[linear-gradient(rgba(0,0,0,0.4),rgba(0,0,0,0.8)),url('https://images.pexels.com/photos/37129973/pexels-photo-37129973.jpeg')] bg-cover bg-center bg-no-repeat">
                                    <div class="mt-auto w-full md:max-w-2xl ps-8 pb-12 md:ps-16 md:pb-20">
                                        <span class="block text-white font-medium tracking-widest uppercase text-sm mb-2">Welcome</span>
                                        <span class="block text-white text-3xl md:text-6xl font-bold leading-tight">Discover Victorias</span>
                                        <p class="text-white/90 text-sm md:text-base mt-3 max-w-md">Explore beautiful destinations, nature escapes, and cultural heritage.</p>
                                        <div class="mt-8">
                                            <a class="py-3 px-8 inline-flex items-center gap-x-2 text-sm font-semibold rounded-xl bg-white text-gray-800 dark:bg-slate-800 dark:text-white hover:bg-gray-100 dark:hover:bg-slate-700 transition-all focus:outline-none" href="<?php echo e(route('explore.map')); ?>" wire:navigate>
                                                Start Exploring
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <button type="button" class="hs-carousel-prev hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 start-0 inline-flex justify-center items-center w-16 h-full text-white hover:bg-white/10 focus:outline-none transition-colors">
                    <svg class="shrink-0 size-8" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    <span class="sr-only">Previous</span>
                </button>
                <button type="button" class="hs-carousel-next hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 end-0 inline-flex justify-center items-center w-16 h-full text-white hover:bg-white/10 focus:outline-none transition-colors">
                    <span class="sr-only">Next</span>
                    <svg class="shrink-0 size-8" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                
                <div class="hs-carousel-pagination flex justify-center absolute bottom-6 start-0 end-0 space-x-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($i = 0; $i < max(1, $this->carouselTenants->count()); $i++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <span class="hs-carousel-active:bg-white hs-carousel-active:border-white size-3 border-2 border-white/50 rounded-full cursor-pointer transition-all"></span>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== POPULAR DESTINATIONS (unchanged) ========== -->
    <section class="py-20 bg-[#F7F6F1] dark:bg-[#0b0f19] transition-colors duration-300">
        <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-between items-end mb-8 gap-6">
                <div>
                    <p class="text-[#7E8A74] dark:text-slate-400 font-bold tracking-[0.2em] uppercase text-xs mb-2">Curated Experiences</p>
                    <h2 class="text-4xl md:text-5xl font-extrabold text-[#1B261D] dark:text-white tracking-tight leading-tight">Popular Destinations</h2>
                </div>
                <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="py-3 px-7 inline-flex items-center gap-x-2 text-sm font-semibold rounded-full bg-[#1B261D] dark:bg-blue-600 text-white hover:bg-[#2d4a35] dark:hover:bg-blue-700 transition-colors duration-200 focus:outline-none">
                    View All Sites
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $cardImage = $tenant->logo ? Storage::url($tenant->logo) : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800';
                    ?>
                    <div class="group flex flex-col bg-white dark:bg-slate-800 rounded-3xl overflow-hidden shadow-sm hover:shadow-xl dark:hover:shadow-slate-900/50 transition-all duration-500 hover:-translate-y-1.5">
                        <div class="relative h-56 overflow-hidden">
                            <img src="<?php echo e($cardImage); ?>" alt="<?php echo e($tenant->name); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800'">
                            <span class="absolute top-4 left-4 bg-[#2d7a52]/90 dark:bg-blue-600/90 text-white px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                                <?php echo e($tenant->typeOfTenant->type ?? 'Stay'); ?>

                            </span>
                        </div>
                        <div class="flex flex-col flex-1 p-6">
                            <h3 class="text-lg font-extrabold text-[#1B261D] dark:text-white leading-snug mb-2"><?php echo e($tenant->name); ?></h3>
                            <p class="text-sm text-[#4A554E] dark:text-slate-400 leading-relaxed mb-6 flex-1"><?php echo e(Str::limit($tenant->address, 100)); ?></p>
                            <div class="flex justify-between items-center gap-2 pt-4 border-t border-[#f0efe8] dark:border-slate-700">
                                <span class="text-[11px] text-[#7E8A74] dark:text-slate-500 font-semibold"><?php echo e($tenant->contact_number); ?></span>
                                <a href="<?php echo e(route('tenant.show', $tenant->slug)); ?>" wire:navigate class="shrink-0 py-2 px-5 text-xs font-bold rounded-full border-2 border-[#1B261D] dark:border-blue-400 text-[#1B261D] dark:text-blue-400 hover:bg-[#1B261D] dark:hover:bg-blue-600 hover:text-white dark:hover:text-white transition-all duration-200 focus:outline-none">
                                    Explore
                                </a>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ========== INTERACTIVE MAP ========== -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <style>
        .leaflet-container { background: #162019 !important; border-radius: 1rem; }
        .dark .leaflet-container { background: #0f172a !important; }
        .leaflet-popup-content-wrapper { background: #2d4a35 !important; color: white !important; border-radius: 0.5rem !important; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.3) !important; }
        .dark .leaflet-popup-content-wrapper { background: #1e293b !important; }
        .leaflet-popup-tip { background: #2d4a35 !important; }
        .dark .leaflet-popup-tip { background: #1e293b !important; }
        .leaflet-popup-content { color: #e8f5ec !important; font-size: 13px !important; margin: 12px !important; }
        .dark .leaflet-popup-content { color: #cbd5e1 !important; }
        .leaflet-control-zoom a { background: #1B261D !important; color: #8fc99a !important; border-color: rgba(255,255,255,0.1) !important; }
        .dark .leaflet-control-zoom a { background: #334155 !important; color: #94a3b8 !important; }
        .leaflet-control-attribution { background: rgba(0,0,0,0.4) !important; color: rgba(255,255,255,0.3) !important; font-size: 10px !important; }
        .leaflet-control-attribution a { color: rgba(255,255,255,0.4) !important; }
        .custom-pin { display: flex; align-items: center; justify-content: center; }
        .pin-dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; }
    </style>

    <section class="py-12 lg:py-20 bg-[#1B261D] dark:bg-[#0b0f19] transition-colors duration-300">
        <div class="max-w-[85rem] mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-5 gap-10 items-start">
                <div class="lg:col-span-3 order-2 lg:order-1">
                    <p class="text-[#8fc99a] dark:text-slate-400 font-bold tracking-[0.2em] uppercase text-xs mb-2 flex items-center gap-2">
                        <span class="inline-block w-5 h-px bg-[#8fc99a] dark:bg-slate-500"></span>
                        Explore the Region
                    </p>
                    <h3 class="text-3xl font-extrabold text-white mb-5 leading-snug">Interactive Map</h3>
                    <div id="map" class="w-full h-[450px] lg:h-[500px] rounded-2xl border border-white/10 dark:border-slate-700/50 overflow-hidden shadow-2xl"></div>
                </div>
                <div class="lg:col-span-2 pt-2 order-1 lg:order-2">
                    <p class="text-white/40 dark:text-slate-500 text-[10px] font-bold tracking-widest uppercase mb-4">Nearby Destinations</p>
                    <div id="location-list" class="flex flex-col gap-2 mb-8">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->mapLocations(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $loc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <button onclick="focusLocation(<?php echo e($index); ?>)" class="group flex items-center gap-3 bg-white/5 hover:bg-white/10 dark:bg-slate-800/50 dark:hover:bg-slate-800 border border-white/8 dark:border-slate-700 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200 text-left w-full focus:outline-none focus:ring-1 focus:ring-opacity-50">
                                <span class="size-2.5 rounded-full shrink-0" style="background: <?php echo e($loc['color']); ?>"></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-[13px] text-white/80 dark:text-slate-300 font-semibold truncate"><?php echo e($loc['name']); ?></p>
                                </div>
                                <span class="text-[11px] text-white/35 dark:text-slate-500 font-medium shrink-0"><?php echo e($loc['type']); ?></span>
                            </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                    <p class="text-[#9CA8A3] dark:text-slate-400 text-sm leading-relaxed mb-6">
                        Click on any map pin or the locations above to discover historical sites and nature escapes. Plan your full‑day route in minutes.
                    </p>
                    <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="inline-flex items-center gap-x-2 py-3 px-6 text-sm font-semibold rounded-full bg-white dark:bg-blue-600 text-[#1B261D] dark:text-white hover:bg-[#e8f5ec] dark:hover:bg-blue-700 transition-colors duration-200 focus:outline-none">
                        Explore Full Map
                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ========== CTA BANNER ========== -->
    <section class="relative py-24 overflow-hidden bg-[#F7F6F1] dark:bg-[#0b0f19] transition-colors duration-300">
        <div class="absolute inset-0 opacity-60 dark:opacity-30" style="background-image: radial-gradient(circle, rgba(27,38,29,0.07) 1px, transparent 1px); background-size: 28px 28px;"></div>
        <div class="relative max-w-2xl mx-auto px-4 text-center">
            <p class="text-[#7E8A74] dark:text-slate-400 font-bold tracking-[0.2em] uppercase text-xs mb-3">Ready to Visit?</p>
            <h2 class="text-4xl md:text-5xl font-extrabold text-[#1B261D] dark:text-white tracking-tight leading-tight mb-4">Plan your perfect day in Victorias</h2>
            <p class="text-[#4A554E] dark:text-slate-400 text-base md:text-lg leading-relaxed mb-8 max-w-lg mx-auto">
                Whether you're chasing history, flavors, or fresh air — let us help you build an itinerary that fits your pace.
            </p>
            <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="py-3 px-8 inline-flex items-center gap-x-2 text-sm font-semibold rounded-full bg-[#1B261D] dark:bg-blue-600 text-white hover:bg-[#2d4a35] dark:hover:bg-blue-700 transition-colors duration-200 focus:outline-none">
                Explore Now
                <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </a>
        </div>
    </section>
</div>

<!-- Initialise carousel and map on load and after Livewire navigation --><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/bb4a5881.blade.php ENDPATH**/ ?>