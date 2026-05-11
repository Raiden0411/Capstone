<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
?>


<div>
    <div class="relative z-10">
        
        <!-- (Hero section unchanged) -->
        <section class="relative min-h-screen w-full overflow-hidden bg-[#071412] dark:bg-[#071412] text-white">
            <div class="absolute inset-0 z-0">
                <img src="https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&q=80&w=1600"
                     alt="Victorias City Forest"
                     class="w-full h-full object-cover scale-110 opacity-40 dark:opacity-30">
                <div class="absolute inset-0 bg-gradient-to-r from-[#071412] via-[#071412]/70 to-transparent"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-[#071412] via-[#071412]/50 to-transparent"></div>
            </div>

            <div class="relative z-10 flex flex-col lg:flex-row items-center w-full min-h-screen">
                <div class="flex flex-col justify-center px-6 py-20 sm:p-12 lg:p-24 lg:w-3/5 w-full">
                    <div class="space-y-4 mb-8">
                        <span class="block text-brand-400 font-semibold tracking-[0.4em] uppercase text-xs sm:text-sm">Welcome to the North</span>
                        <h1 class="font-display text-7xl md:text-8xl lg:text-9xl xl:text-[11rem] leading-[0.8] text-white tracking-tighter drop-shadow-lg">
                            <span class="italic font-light">Victorias</span> <br>
                            <span class="ml-4 md:ml-12">City</span>
                        </h1>
                    </div>
                    <div class="max-w-lg">
                        <p class="text-lg sm:text-xl text-white/70 leading-relaxed mb-10 font-light">
                            Escape into a world where the air is scented with sugar cane and the mountains hum with hidden waterfalls. A breathtaking sanctuary in Negros Occidental.
                        </p>
                        <div class="relative inline-block group">
                            <div class="absolute -inset-1 bg-brand-500/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                            <a href="<?php echo e(route('explore.map')); ?>" wire:navigate
                               class="relative flex items-center gap-6 py-5 px-10 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-bold tracking-widest uppercase transition-all duration-300 shadow-lg shadow-brand-500/20 border border-brand-400/20">
                                Explore the Wild
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:translate-x-2 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0-4 4m4-4H3" /></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="flex flex-row items-center justify-center gap-4 sm:gap-6 px-6 pb-24 lg:pb-0 lg:w-2/5 w-full">
                    <div class="flex flex-col items-center gap-4 mt-12 animate-float">
                        <div class="group relative rounded-2xl overflow-hidden shadow-2xl w-24 sm:w-36 lg:w-40 aspect-[3/4] border border-white/10">
                            <img src="https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&q=80&w=600" class="w-full h-full object-cover grayscale-[0.5] group-hover:grayscale-0 transition-all duration-700" />
                        </div>
                        <span class="text-brand-400/60 text-[9px] tracking-widest uppercase">Nature</span>
                    </div>
                    <div class="flex flex-col items-center gap-4 z-20">
                        <div class="group relative rounded-3xl overflow-hidden shadow-[0_30px_60px_rgba(0,0,0,0.8)] w-36 sm:w-56 lg:w-64 aspect-[3/5] border border-white/20">
                            <img src="https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&q=80&w=600" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-1000" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex items-end p-6">
                                <p class="text-white text-xs font-bold tracking-widest uppercase">Gawahon Falls</p>
                            </div>
                        </div>
                        <span class="text-white text-xs tracking-[0.4em] uppercase font-bold drop-shadow-lg">The Sanctuary</span>
                    </div>
                    <div class="flex flex-col items-center gap-4 mt-12 animate-float" style="animation-delay: 2s;">
                        <div class="group relative rounded-2xl overflow-hidden shadow-2xl w-24 sm:w-36 lg:w-40 aspect-[3/4] border border-white/10">
                            <img src="https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?auto=format&fit=crop&q=80&w=600" class="w-full h-full object-cover grayscale-[0.5] group-hover:grayscale-0 transition-all duration-700" />
                        </div>
                        <span class="text-brand-400/60 text-[9px] tracking-widest uppercase">Culture</span>
                    </div>
                </div>
            </div>
        </section>

        
        <div class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-6">
                    <p class="text-brand-600 dark:text-brand-400 font-bold tracking-widest uppercase text-xs">Discover Victorias</p>
                    <h2 class="font-display text-4xl md:text-5xl font-bold text-gray-900 dark:text-white leading-tight">The City of Smiles & Heritage</h2>
                    <p class="text-gray-600 dark:text-white/60 text-lg">
                        Victorias is more than just an industrial hub; it is a blend of natural sanctuary, deep-rooted history, and warm hospitality. Experience the unique charm that makes this city a hidden gem in Western Visayas.
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-3">
                            <span class="mt-1 bg-brand-100 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400 p-1 rounded-full">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <div><h4 class="font-semibold text-gray-900 dark:text-white">World-Class Heritage</h4><p class="text-sm text-gray-600 dark:text-white/50">Home to the iconic 'Angry Christ' mural and the world's largest sugar mill.</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 bg-brand-100 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400 p-1 rounded-full">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <div><h4 class="font-semibold text-gray-900 dark:text-white">Eco-Tourism Haven</h4><p class="text-sm text-gray-600 dark:text-white/50">Untouched highlands and sprawling trails perfect for nature lovers.</p></div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="mt-1 bg-brand-100 dark:bg-brand-500/20 text-brand-600 dark:text-brand-400 p-1 rounded-full">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            </span>
                            <div><h4 class="font-semibold text-gray-900 dark:text-white">Warm Hospitality</h4><p class="text-sm text-gray-600 dark:text-white/50">Experience the genuine Filipino warmth that defines our culture.</p></div>
                        </li>
                    </ul>
                    <div class="pt-4">
                        <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="inline-flex items-center gap-x-2 py-3 px-6 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-semibold shadow-lg shadow-brand-500/20 transition">
                            Plan Your Visit
                        </a>
                    </div>
                </div>

                
                <div data-hs-carousel='{"loadingClasses": "opacity-0", "isAutoPlay": true}' class="relative group rounded-3xl overflow-hidden shadow-2xl">
                    <div class="hs-carousel relative overflow-hidden w-full h-[500px] md:h-[600px] bg-gray-100 dark:bg-white/5">
                        <div class="hs-carousel-body absolute top-0 bottom-0 start-0 flex flex-nowrap transition-transform duration-1000 opacity-0">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->carouselTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="hs-carousel-slide">
                                    <div class="h-full bg-cover bg-center flex items-end p-8"
                                         style="background-image: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.7)), url('<?php echo e(Storage::url($tenant->logo)); ?>');">
                                        <h3 class="text-white text-2xl font-bold"><?php echo e($tenant->name); ?></h3>
                                    </div>
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <div class="hs-carousel-slide"><div class="h-full bg-[url('https://images.unsplash.com/photo-1501785888041-af3ef285b470?q=80&w=800&auto=format&fit=crop')] bg-cover bg-center flex items-end p-8"><h3 class="text-white text-2xl font-bold">Gawahon Eco-Park</h3></div></div>
                                <div class="hs-carousel-slide"><div class="h-full bg-[url('https://images.unsplash.com/photo-1449034446853-66c86144b0ad?q=80&w=800&auto=format&fit=crop')] bg-cover bg-center flex items-end p-8"><h3 class="text-white text-2xl font-bold">Victorias Milling Co.</h3></div></div>
                                <div class="hs-carousel-slide"><div class="h-full bg-[url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?q=80&w=800&auto=format&fit=crop')] bg-cover bg-center flex items-end p-8"><h3 class="text-white text-2xl font-bold">The Ecotrail</h3></div></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->carouselTenants->count() > 1): ?>
                    <button type="button" class="hs-carousel-prev hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 start-0 inline-flex justify-center items-center w-12 h-full text-white hover:bg-white/10 transition-colors">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" class="hs-carousel-next hs-carousel-disabled:opacity-50 disabled:pointer-events-none absolute inset-y-0 end-0 inline-flex justify-center items-center w-12 h-full text-white hover:bg-white/10 transition-colors">
                        <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 18 6-6-6-6"/></svg>
                    </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </div>

        
        <section class="py-20 bg-gray-50 dark:bg-white/5">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-5xl md:text-6xl font-bold text-gray-900 dark:text-white mb-4">Discover the Wonders</h2>
                    <div class="w-24 h-1 bg-brand-500 mx-auto rounded-full mb-6"></div>
                    <p class="text-gray-600 dark:text-white/50 text-lg max-w-xl mx-auto">A curated journey through the heart of Victorias City. Explore, breathe, and belong.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $cardImage = $tenant->logo ? Storage::url($tenant->logo) : 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800';
                        ?>
                        <div class="group bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-3xl overflow-hidden shadow-sm dark:shadow-none hover:shadow-xl dark:hover:shadow-lg hover:-translate-y-1 transition-all duration-300">
                            <div class="h-56 overflow-hidden">
                                <img src="<?php echo e($cardImage); ?>" alt="<?php echo e($tenant->name); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-700" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?q=80&w=800'">
                            </div>
                            <div class="p-6">
                                <h3 class="font-display text-2xl font-semibold text-gray-900 dark:text-white mb-2 group-hover:text-brand-600 dark:group-hover:text-brand-400 transition"><?php echo e($tenant->name); ?></h3>
                                <p class="text-gray-600 dark:text-white/50 text-sm leading-relaxed line-clamp-3 mb-6"><?php echo e(Str::limit($tenant->address ?? 'Discover this spot.', 100)); ?></p>
                                <a href="<?php echo e(route('tenant.show', $tenant->slug)); ?>" wire:navigate class="block w-full py-3 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-semibold text-center shadow-lg shadow-brand-500/20 transition">
                                    Explore <?php echo e($tenant->name); ?>

                                </a>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </section>

        
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

        <style>
            .leaflet-container { background: #071412 !important; border-radius: 1rem; }
            .dark .leaflet-container { background: #071412 !important; }
            .leaflet-popup-content-wrapper {
                background: rgba(30,41,59,0.9) !important;
                backdrop-filter: blur(16px);
                color: white !important;
                border-radius: 0.8rem !important;
                border: 1px solid rgba(255,255,255,0.12);
            }
            .leaflet-popup-tip { background: rgba(30,41,59,0.9) !important; }
            .leaflet-popup-content { color: #cbd5e1 !important; }
            .leaflet-control-zoom a {
                background: rgba(255,255,255,0.06) !important;
                backdrop-filter: blur(16px);
                color: white !important;
                border: 1px solid rgba(255,255,255,0.12) !important;
            }
            .leaflet-control-zoom a:hover { background: rgba(255,255,255,0.12) !important; }
            .leaflet-control-attribution { background: rgba(0,0,0,0.5) !important; color: rgba(255,255,255,0.4) !important; }
            .leaflet-control-attribution a { color: rgba(255,255,255,0.6) !important; }
            .custom-pin { display: flex; align-items: center; justify-content: center; }
            .pin-dot { width: 12px; height: 12px; border-radius: 50%; border: 2px solid white; }
        </style>

        <section class="py-20 bg-gray-900 dark:bg-[#071412]">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-5 gap-10 items-start">
                    <div class="lg:col-span-3 order-2 lg:order-1">
                        <p class="text-brand-400 font-bold tracking-[0.2em] uppercase text-xs mb-2 flex items-center gap-2">
                            <span class="inline-block w-5 h-px bg-brand-400"></span> Explore the Region
                        </p>
                        <h3 class="font-display text-3xl font-bold text-white mb-5">Interactive Map</h3>

                        
                        <div class="bg-transparent rounded-xl border border-white/10 shadow-2xl p-2 relative z-10"
                             x-data="mapComponent"
                             x-init="init()"
                        >
                            <div wire:ignore>
                                <div x-ref="mapContainer" style="height: 480px; width: 100%; position: relative; z-index: 10;" class="rounded-lg"></div>
                            </div>
                        </div>
                    </div>
                    <div class="lg:col-span-2 pt-2 order-1 lg:order-2">
                        <p class="text-white/40 text-[10px] font-bold tracking-widest uppercase mb-4">Nearby Destinations</p>
                        <div id="location-list" class="flex flex-col gap-2 mb-8">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->mapLocations(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $loc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <button onclick="focusLocation(<?php echo e($index); ?>)" class="group flex items-center gap-3 bg-white/5 hover:bg-white/10 border border-white/10 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200 text-left w-full focus:outline-none focus:ring-1 focus:ring-brand-400/50">
                                    <span class="size-2.5 rounded-full shrink-0" style="background: <?php echo e($loc['color']); ?>"></span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-[13px] text-white/80 font-semibold truncate"><?php echo e($loc['name']); ?></p>
                                    </div>
                                    <span class="text-[11px] text-white/40 font-medium shrink-0"><?php echo e($loc['type']); ?></span>
                                </button>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>
                        <p class="text-white/50 text-sm leading-relaxed mb-6">
                            Click on any map pin or the locations above to discover historical sites and nature escapes. Plan your full‑day route in minutes.
                        </p>
                        <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="inline-flex items-center gap-x-2 py-3 px-6 text-sm font-semibold rounded-full bg-white dark:bg-brand-600 text-gray-900 dark:text-white hover:bg-gray-100 dark:hover:bg-brand-500 transition-colors shadow-lg shadow-brand-500/20">
                            Explore Full Map
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="relative py-24 overflow-hidden bg-gray-50 dark:bg-white/5">
            <div class="absolute inset-0 opacity-40 dark:opacity-20" style="background-image: radial-gradient(circle, rgba(34,197,94,0.15) 1px, transparent 1px); background-size: 28px 28px;"></div>
            <div class="relative max-w-4xl mx-auto px-4 text-center">
                <p class="text-brand-600 dark:text-brand-400 font-bold tracking-[0.2em] uppercase text-xs mb-3">Ready to Visit?</p>
                <h2 class="font-display text-4xl md:text-5xl font-bold text-gray-900 dark:text-white mb-4">Plan your perfect day in Victorias</h2>
                <p class="text-gray-600 dark:text-white/50 text-lg max-w-lg mx-auto mb-8">Whether you're chasing history, flavors, or fresh air — let us help you build an itinerary that fits your pace.</p>
                <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="inline-flex items-center gap-x-2 py-3 px-8 text-sm font-semibold rounded-full bg-brand-600 hover:bg-brand-500 text-white shadow-lg shadow-brand-500/20 transition">
                    Explore Now
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                </a>
            </div>
        </section>
    </div>

    
    <?php $__env->startPush('scripts'); ?>
    <script>
        // Carousel fix
        function initCarousel() {
            const carouselEl = document.querySelector('[data-hs-carousel]');
            if (!carouselEl) return;
            if (!window.HSCarousel) { setTimeout(initCarousel, 100); return; }
            if (carouselEl._hsCarousel) { carouselEl._hsCarousel.destroy(); delete carouselEl._hsCarousel; }
            const body = carouselEl.querySelector('.hs-carousel-body');
            if (body) body.classList.remove('opacity-0');
            try {
                new window.HSCarousel(carouselEl, JSON.parse(carouselEl.getAttribute('data-hs-carousel') || '{}'));
            } catch (e) { console.error(e); }
        }

        // Focus location helper
        window.focusLocation = function(index) {
            if (!window.mapInstance || !window.mapMarkers || !window.mapMarkers[index]) return;
            const loc = window.mapMarkers[index].getLatLng();
            window.mapInstance.flyTo(loc, 15, { duration: 1.5 });
            setTimeout(() => window.mapMarkers[index].openPopup(), 1200);
        };

        // Map Alpine component (no inline HTML attribute, cleanly defined here)
        document.addEventListener('alpine:init', () => {
            Alpine.data('mapComponent', () => ({
                map: null,
                markers: [],
                
                init() {
                    // Wait for Leaflet to be ready, then build the map
                    const ready = () => {
                        if (typeof L !== 'undefined') {
                            this.initMap();
                        } else {
                            setTimeout(ready, 100);
                        }
                    };
                    ready();
                },

                initMap() {
                    // Fix default icon paths
                    delete L.Icon.Default.prototype._getIconUrl;
                    L.Icon.Default.mergeOptions({
                        iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                        iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    });

                    const locations = <?php echo json_encode($this->mapLocations(), 15, 512) ?>;

                    if (!locations || locations.length === 0) {
                        this.$refs.mapContainer.innerHTML =
                            '<div class="flex items-center justify-center h-full text-white/50">No locations to display</div>';
                        return;
                    }

                    const centerLat = locations[0].lat;
                    const centerLng = locations[0].lng;
                    const zoom = locations.length === 1 ? 12 : 10;

                    this.map = L.map(this.$refs.mapContainer, {
                        center: [centerLat, centerLng],
                        zoom: zoom,
                        scrollWheelZoom: true,
                    });

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
                        maxZoom: 19,
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> &copy; CARTO',
                    }).addTo(this.map);

                    this.markers = [];
                    locations.forEach((loc, idx) => {
                        const color = loc.color;
                        const icon = L.divIcon({
                            className: 'custom-pin',
                            html: `<div class="pin-dot" style="background: ${color}; box-shadow: 0 0 10px ${color};"></div>`,
                            iconSize: [20, 20],
                            iconAnchor: [10, 10],
                        });
                        const marker = L.marker([loc.lat, loc.lng], { icon })
                            .bindPopup(`<strong>${loc.name}</strong><br/>${loc.type}<br/><a href="/business/${loc.slug}" class="text-brand-400 underline" wire:navigate>Visit business</a>`)
                            .addTo(this.map);
                        this.markers.push(marker);
                    });

                    // Expose globally for focusLocation
                    window.mapMarkers = this.markers;
                    window.mapInstance = this.map;

                    if (locations.length > 1) {
                        const bounds = L.latLngBounds(locations.map(l => [l.lat, l.lng]));
                        this.map.fitBounds(bounds, { padding: [50, 50] });
                    }

                    setTimeout(() => this.map.invalidateSize(), 200);
                }
            }));
        });

        // Initialisation
        document.addEventListener('DOMContentLoaded', () => setTimeout(initCarousel, 200));
        document.addEventListener('livewire:navigated', () => setTimeout(initCarousel, 200));
    </script>
    <?php $__env->stopPush(); ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/bb4a5881.blade.php ENDPATH**/ ?>