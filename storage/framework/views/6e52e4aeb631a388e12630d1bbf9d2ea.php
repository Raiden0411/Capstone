<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\Booking;
use App\Scopes\TenantScope;
?>




<?php $__env->startPush('styles'); ?>
    <?php if (! $__env->hasRenderedOnce('040c4a98-1e99-4656-a806-6c97b0f38040')): $__env->markAsRenderedOnce('040c4a98-1e99-4656-a806-6c97b0f38040'); ?>
        <style>
            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        </style>
    <?php endif; ?>
<?php $__env->stopPush(); ?>

<div x-data="{
        heroIndex: 0,
        heroImages: JSON.parse($el.dataset.heroImages || '[]'),
        heroTimer: null,
        prefersReduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        onVisibilityChange: null,
        init() {
            // Respect prefers-reduced-motion: do not auto-advance. Users
            // who set this preference see a static first slide. (WCAG 2.2.2)
            if (this.prefersReduced) return;
            if (this.heroImages.length <= 1) return;

            // Only start the interval when the tab is actually visible.
            // Previously this fired unconditionally — a page opened in a
            // background tab (middle-click, bookmark, restore-session)
            // would advance heroIndex while the user wasn't looking, so
            // they'd land on a mid-cycle slide when they finally switched.
            if (!document.hidden) this.startTimer();

            // Pause when the tab is hidden; resume when it returns.
            this.onVisibilityChange = () => {
                if (document.hidden) this.stopTimer();
                else this.startTimer();
            };
            document.addEventListener('visibilitychange', this.onVisibilityChange, { passive: true });
        },
        destroy() {
            this.stopTimer();
            if (this.onVisibilityChange) {
                document.removeEventListener('visibilitychange', this.onVisibilityChange);
                this.onVisibilityChange = null;
            }
        },
        startTimer() {
            if (this.prefersReduced) return;
            if (this.heroImages.length <= 1) return;
            this.stopTimer();
            this.heroTimer = setInterval(() => {
                this.heroIndex = (this.heroIndex + 1) % this.heroImages.length;
            }, 5000);
        },
        stopTimer() {
            if (this.heroTimer) {
                clearInterval(this.heroTimer);
                this.heroTimer = null;
            }
        }
     }"
     data-hero-images="<?php echo e($this->heroImagesJson); ?>"
     class="min-h-screen">

    
    <section class="relative overflow-hidden bg-gray-900 py-14 md:py-20">

        
        <div class="absolute inset-0"
             :class="heroImages.length > 0 ? '' : 'hidden'">
            <template x-for="(img, index) in heroImages" :key="img">
                <img :src="img"
                     class="absolute inset-0 h-full w-full object-cover transition-opacity duration-1000"
                     :class="index === heroIndex ? 'opacity-40' : 'opacity-0'"
                     decoding="async"
                     alt=""
                     aria-hidden="true" />
            </template>
        </div>
        <div class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-800"
             :class="heroImages.length === 0 ? '' : 'hidden'"></div>

        <div class="absolute inset-0 bg-gradient-to-r from-black/70 via-black/50 to-black/70"></div>

        <div class="relative z-10 mx-auto flex max-w-7xl flex-col justify-between gap-8 px-6 md:flex-row md:items-center lg:px-8">
            <div class="max-w-xl">
                <p class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-400">
                    <span class="h-px w-4 bg-primary-400"></span>
                    Victorias City
                </p>
                <h1 class="font-display text-4xl font-semibold leading-tight text-white sm:text-5xl md:text-6xl">
                    Discover Local<br><em class="italic text-primary-300">Wonders</em>
                </h1>
                <p class="mt-4 max-w-md text-sm leading-relaxed text-white/60">
                    Find the perfect destinations, hidden gems, and must-visit attractions throughout the city and its barangays.
                </p>
            </div>

            <div class="flex items-center gap-4 sm:gap-6 rounded-2xl border border-primary-500/20 bg-primary-500/10 p-6 backdrop-blur md:flex-col md:gap-4">
                <div>
                    <div class="font-display text-3xl font-medium text-primary-300 tabular-nums"><?php echo e($this->totalCount); ?></div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-white/50">Destinations</div>
                </div>
                <div class="h-10 w-px bg-primary-500/20 md:h-px md:w-10"></div>
                <div>
                    <div class="font-display text-3xl font-medium text-primary-300 tabular-nums"><?php echo e($this->categories->count()); ?></div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-white/50">Categories</div>
                </div>
                <div class="h-10 w-px bg-primary-500/20 md:h-px md:w-10"></div>
                <div>
                    <div class="font-display text-3xl font-medium text-primary-300 tabular-nums"><?php echo e($this->featured->count()); ?></div>
                    <div class="text-xs font-semibold uppercase tracking-wider text-white/50">Top Picks</div>
                </div>
            </div>
        </div>
    </section>

    
    
    <div class="sticky top-16 md:top-20 z-20 border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="mx-auto flex max-w-7xl flex-col gap-4 px-6 py-4 md:flex-row md:items-center lg:px-8">
            
            <div class="relative max-w-xs flex-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       enterkeyhint="search"
                       autocomplete="off"
                       class="input w-full"
                       style="padding-left: 2.25rem; padding-right: 2.25rem;"
                       placeholder="Search destinations…"
                       aria-label="Search destinations">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?>
                    <button type="button"
                            wire:click="$set('search','')"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'clear-search-btn'; ?>wire:key="clear-search-btn"
                            class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-200 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:hover:bg-gray-700"
                            aria-label="Clear search">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="flex flex-1 gap-2 overflow-x-auto pb-1 scrollbar-hide">
                <?php $allActive = blank($categoryFilter); ?>
                <button type="button"
                        wire:click="$set('categoryFilter','')"
                        <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cat-pill-all'; ?>wire:key="cat-pill-all"
                        aria-pressed="<?php echo e($allActive ? 'true' : 'false'); ?>"
                        class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                               transition-all duration-200 active:scale-95 shrink-0
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                               <?php echo e($allActive
                                    ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                    : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400'); ?>">
                    <span>All</span>
                    <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                 <?php echo e($allActive ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'); ?>">
                        <?php echo e($this->totalCount); ?>

                    </span>
                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php $isActive = $categoryFilter === $cat->type; ?>
                    <button type="button"
                            wire:click="$set('categoryFilter','<?php echo e($cat->type); ?>')"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cat-pill-'.e($cat->id).''; ?>wire:key="cat-pill-<?php echo e($cat->id); ?>"
                            aria-pressed="<?php echo e($isActive ? 'true' : 'false'); ?>"
                            class="inline-flex items-center gap-2 h-9 pl-3.5 pr-1.5 rounded-full text-xs font-semibold uppercase tracking-wide border
                                   transition-all duration-200 active:scale-95 shrink-0
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   <?php echo e($isActive
                                        ? 'bg-primary-600 border-primary-600 text-white shadow-sm'
                                        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-primary-400'); ?>">
                        <span><?php echo e($cat->type); ?></span>
                        <span class="inline-flex items-center justify-center min-w-[22px] h-5 px-1.5 rounded-full text-[10px] font-bold tabular-nums
                                     <?php echo e($isActive ? 'bg-white/20 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'); ?>">
                            <?php echo e($cat->tenants_count); ?>

                        </span>
                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            
            <div class="flex shrink-0 items-center gap-2">

                
                <div class="relative">
                    <select wire:model.live="sortBy"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'sort-select'; ?>wire:key="sort-select"
                            aria-label="Sort destinations"
                            class="appearance-none h-9 pl-3 pr-8 rounded-lg border border-gray-200 dark:border-gray-700
                                   bg-white dark:bg-gray-900
                                   text-xs font-semibold text-gray-700 dark:text-gray-300
                                   focus:outline-none focus:ring-2 focus:ring-primary-500/50
                                   transition cursor-pointer">
                        <option value="name">Alphabetical</option>
                        <option value="newest">Newest first</option>
                        <option value="popular">Most booked</option>
                        <option value="price_asc">Price: low → high</option>
                        <option value="price_desc">Price: high → low</option>
                    </select>
                    <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute right-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m6 9 6 6 6-6"/>
                    </svg>
                </div>

                
                <div class="flex shrink-0 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <button type="button"
                            wire:click="$set('viewMode','grid')"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'view-toggle-grid'; ?>wire:key="view-toggle-grid"
                            aria-pressed="<?php echo e($viewMode === 'grid' ? 'true' : 'false'); ?>"
                            class="flex h-9 w-9 items-center justify-center transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   <?php echo e($viewMode === 'grid'
                                        ? 'bg-primary-600 text-white'
                                        : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'); ?>"
                            title="Grid view"
                            aria-label="Grid view">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    </button>
                    <button type="button"
                            wire:click="$set('viewMode','list')"
                            <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'view-toggle-list'; ?>wire:key="view-toggle-list"
                            aria-pressed="<?php echo e($viewMode === 'list' ? 'true' : 'false'); ?>"
                            class="flex h-9 w-9 items-center justify-center transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   <?php echo e($viewMode === 'list'
                                        ? 'bg-primary-600 text-white'
                                        : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'); ?>"
                            title="List view"
                            aria-label="List view">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$this->hasActiveFilters && $this->featured->isNotEmpty()): ?>
            <section class="mb-12">
                <div class="mb-6 flex items-end justify-between">
                    <div>
                        <p class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                            <span class="h-px w-4 bg-primary-600 dark:bg-primary-400"></span>
                            Featured
                        </p>
                        <h2 class="font-display text-2xl font-semibold text-gray-900 dark:text-white md:text-3xl">Popular <em class="italic text-primary-600 dark:text-primary-400">Picks</em></h2>
                    </div>
                    <div class="font-display text-5xl font-light text-gray-200 dark:text-gray-800 tabular-nums"><?php echo e(str_pad($this->featured->count(), 2, '0', STR_PAD_LEFT)); ?></div>
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->featured; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $img = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                        ?>
                        <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" wire:navigate <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'feat-'.e($tenant->id).''; ?>wire:key="feat-<?php echo e($tenant->id); ?>"
                           class="group relative flex aspect-[4/5] flex-col justify-end overflow-hidden rounded-2xl transition-all duration-300 hover:-translate-y-1 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-[0.99]">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($img): ?>
                                <img src="<?php echo e($img); ?>" alt="<?php echo e($tenant->name); ?>" class="absolute inset-0 h-full w-full object-cover brightness-75 transition duration-700 group-hover:scale-105 group-hover:brightness-90" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="absolute inset-0 bg-gradient-to-br from-gray-900 to-gray-700"></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>
                            <div class="relative z-10 p-5">
                                <span class="mb-2 inline-flex items-center gap-1 rounded bg-primary-500/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary-300">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    Featured
                                </span>
                                <div class="text-xs font-semibold uppercase tracking-wider text-white/50"><?php echo e($tenant->typeOfTenant?->type ?? 'Destination'); ?></div>
                                <h3 class="font-display text-xl font-semibold text-white"><?php echo e($tenant->name); ?></h3>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->address): ?>
                                    <p class="mt-1 flex items-start gap-1 text-xs text-white/50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-3 w-3 shrink-0 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                        <?php echo e($tenant->address); ?>

                                    </p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <span class="mt-3 inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-primary-400 opacity-0 transition group-hover:opacity-100">
                                    Explore
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </span>
                            </div>
                        </a>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>

                <div class="my-10 h-px bg-gradient-to-r from-transparent via-gray-200 to-transparent dark:via-gray-800"></div>
            </section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <section>
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="mb-1 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-600 dark:bg-primary-400"></span>
                        <?php echo e($this->hasActiveFilters ? 'Search Results' : 'All Destinations'); ?>

                    </p>
                    <h2 class="font-display text-2xl font-semibold text-gray-900 dark:text-white md:text-3xl">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                            <em class="italic text-primary-600 dark:text-primary-400"><?php echo e($this->tenants->count()); ?></em> <?php echo e(\Illuminate\Support\Str::plural('Spot', $this->tenants->count())); ?> Found
                        <?php else: ?>
                            Explore <em class="italic text-primary-600 dark:text-primary-400">Everywhere</em>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </h2>
                </div>
                <div class="flex items-center gap-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                        <button type="button"
                                wire:click="resetFilters"
                                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'reset-filters-btn'; ?>wire:key="reset-filters-btn"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800
                                       text-rose-700 dark:text-rose-300
                                       text-xs font-semibold uppercase tracking-wider
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            Clear
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="font-display text-5xl font-light text-gray-200 dark:text-gray-800 tabular-nums"><?php echo e(str_pad($this->tenants->count(), 2, '0', STR_PAD_LEFT)); ?></div>
                </div>
            </div>

            
            <div class="grid gap-6 transition-opacity duration-200 <?php echo e($viewMode === 'list' ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'); ?>"
                 wire:loading.class="opacity-50"
                 wire:target="search,categoryFilter,viewMode,resetFilters,sortBy">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $img  = $tenant->logo ? asset('storage/' . $tenant->logo) : null;
                        $minP = $tenant->properties_min_price;
                    ?>
                    <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" wire:navigate <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dest-'.e($tenant->id).''; ?>wire:key="dest-<?php echo e($tenant->id); ?>"
                       class="group flex overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-800 dark:bg-gray-900 active:scale-[0.99] <?php echo e($viewMode === 'list' ? 'flex-row' : 'flex-col'); ?>">
                        <div class="relative <?php echo e($viewMode === 'list' ? 'h-auto w-32 shrink-0' : 'aspect-[4/3] w-full'); ?>">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($img): ?>
                                <img src="<?php echo e($img); ?>" alt="<?php echo e($tenant->name); ?>" class="h-full w-full object-cover brightness-90 transition duration-700 group-hover:scale-105 group-hover:brightness-100" loading="lazy" decoding="async">
                            <?php else: ?>
                                <div class="flex h-full w-full items-center justify-center bg-gray-100 dark:bg-gray-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->typeOfTenant): ?>
                                <span class="absolute bottom-2 left-2 rounded bg-black/70 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white"><?php echo e($tenant->typeOfTenant->type); ?></span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        <div class="flex flex-1 flex-col p-4">
                            <h3 class="font-display text-lg font-semibold leading-tight text-gray-900 transition group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400"><?php echo e($tenant->name); ?></h3>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->address): ?>
                                <p class="mt-1 flex items-start gap-1 text-xs text-gray-500 dark:text-gray-400">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="mt-0.5 h-3 w-3 shrink-0 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                                    <span class="line-clamp-2"><?php echo e($tenant->address); ?></span>
                                </p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-800">
                                <div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($minP !== null): ?>
                                        <div class="font-display text-lg font-semibold text-gray-900 dark:text-white tabular-nums">₱<?php echo e(number_format($minP, 0)); ?></div>
                                        <div class="text-[10px] uppercase tracking-wider text-gray-400">from / unit</div>
                                    <?php else: ?>
                                        <div class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($tenant->properties_count + $tenant->services_count); ?> offering<?php echo e(($tenant->properties_count + $tenant->services_count) !== 1 ? 's' : ''); ?></div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <span class="inline-flex items-center gap-1 text-xs font-bold uppercase tracking-wider text-primary-600 opacity-0 transition group-hover:opacity-100 dark:text-primary-400">
                                    Explore
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-12 text-center dark:border-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto mb-4 h-12 w-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <h3 class="font-display text-xl italic text-gray-500 dark:text-gray-400">No destinations found.</h3>
                        <p class="mt-2 text-sm text-gray-400 dark:text-gray-500">Try a different keyword or clear your filters.</p>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                            <button type="button"
                                    wire:click="resetFilters"
                                    class="mt-4 inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                           transition-all duration-200 active:scale-95
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                           disabled:opacity-60 disabled:cursor-not-allowed">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Reset Filters
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div wire:loading.block wire:target="search,categoryFilter,viewMode,resetFilters,sortBy" class="py-8 text-center">
                <div class="inline-block h-8 w-8 animate-spin rounded-full border-2 border-primary-600 border-t-transparent motion-reduce:animate-none"></div>
            </div>
        </section>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/bab95d59.blade.php ENDPATH**/ ?>