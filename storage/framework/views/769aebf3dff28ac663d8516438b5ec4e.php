
<div class="flex h-full flex-col" x-data="{ filtersOpen: window.innerWidth >= 768 }">

    
    <div class="border-b border-gray-200 pl-4 pr-3 pt-3 pb-2 dark:border-gray-700 lg:pr-4">
        <div class="mb-2 flex items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="mb-0.5 flex items-center gap-1 text-[9px] font-bold uppercase tracking-[.16em] text-primary-600 dark:text-blue-400">
                    <span class="h-1 w-1 animate-pulse rounded-full bg-primary-600 dark:bg-blue-400"></span>
                    Victorias City
                </p>
                <h2 class="text-base font-extrabold leading-tight tracking-tight text-gray-900 dark:text-white">
                    Explore Destinations
                </h2>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-1">
                <div class="flex flex-wrap items-center justify-end gap-1">
                    <div class="flex items-center gap-1 rounded-full border border-blue-200 bg-blue-100 px-2 py-0.5 dark:border-blue-500/20 dark:bg-blue-500/10">
                        <span class="text-[10px] font-bold tabular-nums text-blue-700 dark:text-blue-300"><?php echo e($this->tenants->count()); ?></span>
                        <span class="text-[9px] font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">spots</span>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($favorites) > 0): ?>
                        <button
                            wire:click="$set('favoritesOnly', <?php echo e($favoritesOnly ? 'false' : 'true'); ?>)"
                            aria-pressed="<?php echo e($favoritesOnly ? 'true' : 'false'); ?>"
                            class="flex items-center gap-0.5 rounded-full border px-2 py-0.5 transition
                                   <?php echo e($favoritesOnly ? 'border-rose-200 bg-rose-100 dark:border-rose-500/20 dark:bg-rose-500/10' : 'border-gray-200 bg-gray-100 hover:border-rose-200 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-rose-500/30'); ?>"
                        >
                            <span class="text-[10px]">❤️</span>
                            <span class="text-[10px] font-bold tabular-nums <?php echo e($favoritesOnly ? 'text-rose-700 dark:text-rose-300' : 'text-gray-500 dark:text-gray-400'); ?>">
                                <?php echo e(count($favorites)); ?>

                            </span>
                        </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="flex items-center gap-1 rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 dark:border-gray-700 dark:bg-gray-800">
                    <span class="text-[9px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <?php echo e($this->categories->count()); ?> categories
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-2 text-[10px]">
            <a href="<?php echo e(route('home')); ?>" wire:navigate
               class="flex items-center gap-1 font-medium text-gray-500 transition hover:text-primary-600 dark:text-gray-400 dark:hover:text-blue-400">
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                Home
            </a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <span class="h-2.5 w-px bg-gray-300 dark:bg-gray-700"></span>
                <a href="<?php echo e(route('my-bookings')); ?>" wire:navigate
                   class="flex items-center gap-1 font-medium text-primary-600 transition hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300">
                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Reservations
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="lg:hidden border-b border-gray-200 dark:border-gray-700 px-3 py-1.5">
        <button
            @click="filtersOpen = !filtersOpen"
            class="flex w-full items-center justify-between rounded-lg px-2 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800"
            aria-expanded="filtersOpen"
        >
            <span>Filters</span>
            <svg class="h-3.5 w-3.5 transition-transform" :class="filtersOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
        </button>
    </div>

    
    <div x-show="filtersOpen"
         x-collapse
         x-cloak
         class="border-b border-gray-200 dark:border-gray-700 lg:block lg:!h-auto"
    >
        <div class="space-y-2 px-3 py-2.5">
            
            <div class="relative">
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>

                <input
                    type="text"
                    x-ref="searchInput"
                    wire:model.live.debounce.280ms="search"
                    placeholder="Search destinations…"
                    aria-label="Search destinations"
                    class="w-full rounded-lg border border-gray-300 bg-gray-50 py-2 pl-8 pr-7 text-xs text-gray-900 placeholder-gray-400 transition focus:border-primary-600 focus:outline-none focus:ring-1 focus:ring-primary-600/50 dark:border-gray-600 dark:bg-gray-800 dark:text-white dark:placeholder-gray-500 dark:focus:border-blue-500 dark:focus:ring-blue-500/30"
                >

                <div wire:loading wire:target="search" class="absolute right-7 top-1/2 -translate-y-1/2" aria-hidden="true">
                    <svg class="h-3 w-3 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?>
                    <button
                        wire:click="$set('search','')"
                        class="absolute right-1.5 top-1/2 flex h-5 w-5 -translate-y-1/2 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                        aria-label="Clear search"
                    >
                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="flex gap-1 overflow-x-auto pb-0.5 snap-x" style="-ms-overflow-style:none;scrollbar-width:none;" role="group" aria-label="Filter by category">
                <button
                    wire:click="$set('categoryFilter','')"
                    aria-pressed="<?php echo e($categoryFilter === '' ? 'true' : 'false'); ?>"
                    class="shrink-0 snap-start rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide transition-all
                           <?php echo e($categoryFilter === '' ? 'border-primary-600 bg-primary-600 text-white shadow-sm shadow-blue-500/20' : 'border-gray-300 text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200'); ?>"
                >
                    All
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button
                        wire:click="$set('categoryFilter','<?php echo e($cat->type); ?>')"
                        aria-pressed="<?php echo e($categoryFilter === $cat->type ? 'true' : 'false'); ?>"
                        class="shrink-0 snap-start whitespace-nowrap rounded-full border px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide transition-all
                               <?php echo e($categoryFilter === $cat->type ? 'border-primary-600 bg-primary-600 text-white shadow-sm shadow-blue-500/20' : 'border-gray-300 text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200'); ?>"
                    >
                        <?php echo e($cat->type); ?>

                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            
            <div class="flex flex-wrap gap-x-3 gap-y-1">
                <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="openNow" class="rounded border-gray-300 bg-white text-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700">
                    Open Now
                </label>

                <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="hasOfferings" class="rounded border-gray-300 bg-white text-primary-600 focus:ring-primary-600 dark:border-gray-600 dark:bg-gray-700">
                    Has Offerings
                </label>

                <label class="inline-flex cursor-pointer items-center gap-1.5 text-[11px] text-gray-600 dark:text-gray-300">
                    <input type="checkbox" wire:model.live="favoritesOnly" class="rounded border-gray-300 bg-white text-rose-500 focus:ring-rose-400 dark:border-gray-600 dark:bg-gray-700">
                    ❤️ Favorites Only
                </label>
            </div>

            
            <div class="flex gap-0.5 rounded-lg border border-gray-200 bg-gray-50 p-0.5 dark:border-gray-700 dark:bg-gray-800">
                <button
                    wire:click="$set('sortBy','name')"
                    aria-pressed="<?php echo e($sortBy === 'name' ? 'true' : 'false'); ?>"
                    class="flex-1 rounded-md py-1 text-[10px] font-bold uppercase tracking-wide transition-all
                           <?php echo e($sortBy === 'name' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'); ?>"
                >
                    A–Z
                </button>

                <button
                    wire:click="$set('sortBy','distance')"
                    aria-pressed="<?php echo e($sortBy === 'distance' ? 'true' : 'false'); ?>"
                    class="flex-1 rounded-md py-1 text-[10px] font-bold uppercase tracking-wide transition-all
                           <?php echo e($sortBy === 'distance' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'); ?>"
                >
                    Near Me
                </button>

                <button
                    wire:click="$set('sortBy','newest')"
                    aria-pressed="<?php echo e($sortBy === 'newest' ? 'true' : 'false'); ?>"
                    class="flex-1 rounded-md py-1 text-[10px] font-bold uppercase tracking-wide transition-all
                           <?php echo e($sortBy === 'newest' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'); ?>"
                >
                    New
                </button>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                <div class="flex flex-wrap items-center gap-1 pt-0.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-0.5 pl-2 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                            “<?php echo e(Str::limit($search, 14)); ?>”
                            <button wire:click="$set('search','')" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-500/20" aria-label="Clear search filter">✕</button>
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($categoryFilter): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-0.5 pl-2 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                            <?php echo e($categoryFilter); ?>

                            <button wire:click="$set('categoryFilter','')" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-500/20" aria-label="Clear category filter">✕</button>
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($openNow): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-0.5 pl-2 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                            Open Now
                            <button wire:click="$set('openNow', false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-500/20" aria-label="Clear open now filter">✕</button>
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasOfferings): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 py-0.5 pl-2 pr-1 text-[10px] font-semibold text-blue-700 dark:border-blue-500/20 dark:bg-blue-500/10 dark:text-blue-300">
                            Has Offerings
                            <button wire:click="$set('hasOfferings', false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-blue-200/60 dark:hover:bg-blue-500/20" aria-label="Clear offerings filter">✕</button>
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($favoritesOnly): ?>
                        <span class="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 py-0.5 pl-2 pr-1 text-[10px] font-semibold text-rose-700 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-300">
                            ❤️ Favorites
                            <button wire:click="$set('favoritesOnly', false)" class="flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-rose-200/60 dark:hover:bg-rose-500/20" aria-label="Clear favorites filter">✕</button>
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <button wire:click="resetFilters" class="text-[10px] font-bold text-gray-400 underline underline-offset-2 transition hover:text-red-500 dark:hover:text-red-400">
                        Clear all
                    </button>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div wire:loading.flex wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,sortBy" class="flex-col gap-2 px-3 py-2" aria-hidden="true">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($i = 0; $i < 5; $i++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <div class="flex animate-pulse items-center gap-2">
                <div class="h-9 w-9 shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700"></div>
                <div class="flex-1 space-y-1">
                    <div class="h-2.5 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                    <div class="h-2 w-1/3 rounded bg-gray-200 dark:bg-gray-700"></div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    </div>

    
    <div wire:loading.remove wire:target="search,categoryFilter,openNow,hasOfferings,favoritesOnly,sortBy" class="flex-1 overflow-y-auto py-1 sb-scroll">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php
                $hue = ($index * 137) % 360;
                $coordinates = $tenant->coordinates ?? [];
                $mainCoord = $coordinates[0] ?? null;
                $subBranches = array_slice($coordinates, 1);
                $hasSubBranches = count($subBranches) > 0;
                $distance = null;
                $isFavorite = in_array($tenant->id, $favorites, true);

                if ($mainCoord && $userLat && $userLng) {
                    $distance = $this->calculateDistance($mainCoord['lat'], $mainCoord['lng']);
                }
            ?>

            <div
                x-data="{ expanded: false }"
                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'dest-'.e($tenant->id).''; ?>wire:key="dest-<?php echo e($tenant->id); ?>"
                class="group mx-1.5 my-0.5 rounded-lg border transition-all duration-150
                       <?php echo e($highlightedId === $tenant->id ? 'border-primary-600/40 bg-blue-50 dark:border-blue-500/40 dark:bg-blue-500/10' : 'border-transparent hover:border-gray-200 hover:bg-gray-50 dark:hover:border-gray-700 dark:hover:bg-gray-800/50'); ?>"
            >

                
                <div
                    class="flex cursor-pointer items-center gap-2 px-2.5 py-2"
                    wire:click="flyToTenant(<?php echo e($tenant->id); ?>)"
                    wire:loading.class="opacity-60"
                    wire:target="flyToTenant(<?php echo e($tenant->id); ?>)"
                    @click="mobileOpen = false"
                >
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tenant->logo): ?>
                        <img
                            src="<?php echo e(asset('storage/'.$tenant->logo)); ?>"
                            alt="<?php echo e($tenant->name); ?>"
                            loading="lazy"
                            decoding="async"
                            class="h-9 w-9 shrink-0 rounded-lg border border-gray-200 object-cover dark:border-gray-700"
                        >
                    <?php else: ?>
                        <div
                            class="flex h-9 w-9 shrink-0 select-none items-center justify-center rounded-lg text-xs font-black text-white"
                            style="background: linear-gradient(135deg, hsl(<?php echo e($hue); ?>,55%,38%) 0%, hsl(<?php echo e($hue); ?>,60%,52%) 100%); box-shadow:0 3px 10px hsl(<?php echo e($hue); ?>,55%,38%,.35);"
                        >
                            <?php echo e(strtoupper(substr($tenant->name, 0, 2))); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold leading-snug text-gray-900 dark:text-white"><?php echo e($tenant->name); ?></p>
                        <p class="mt-0.5 text-[10px] font-medium text-gray-500 dark:text-gray-400"><?php echo e($tenant->typeOfTenant?->type ?? 'Business'); ?></p>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($mainCoord && $distance !== null): ?>
                            <span class="mt-0.5 inline-block text-[9px] font-bold" style="color: hsl(<?php echo e($hue); ?>,65%,60%)">
                                📍 <?php echo e(number_format($distance, 1)); ?> km
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSubBranches): ?>
                            <span class="mt-0.5 inline-flex items-center gap-1 text-[8px] font-bold uppercase tracking-wider text-primary-600 dark:text-blue-400">
                                <?php echo e(count($subBranches)); ?> sub-locations
                            </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="ml-auto flex shrink-0 items-center gap-1">
                        
                        <button
                            wire:click.stop="toggleFavorite(<?php echo e($tenant->id); ?>)"
                            wire:loading.attr="disabled"
                            wire:target="toggleFavorite(<?php echo e($tenant->id); ?>)"
                            class="flex h-7 w-7 items-center justify-center rounded-full border transition
                                   <?php echo e($isFavorite
                                        ? 'border-rose-200 bg-rose-50 text-rose-500 hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300'
                                        : 'border-gray-200 bg-white text-gray-400 hover:border-rose-200 hover:text-rose-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-rose-500/30 dark:hover:text-rose-300'); ?>"
                            aria-label="<?php echo e($isFavorite ? 'Remove from favorites' : 'Save to favorites'); ?>"
                            title="<?php echo e($isFavorite ? 'Remove from favorites' : 'Save to favorites'); ?>"
                        >
                            <?php echo e($isFavorite ? '❤️' : '🤍'); ?>

                        </button>

                        
                        <button
                            wire:click.stop="getDirectionsTo(<?php echo e($tenant->id); ?>)"
                            wire:loading.attr="disabled"
                            wire:target="getDirectionsTo(<?php echo e($tenant->id); ?>)"
                            @click="mobileOpen = false"
                            class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 transition-all duration-150 hover:border-primary-600/40 hover:bg-blue-50 hover:text-primary-600 focus-visible:ring-2 focus-visible:ring-primary-600/30 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-500 dark:hover:border-blue-500/40 dark:hover:bg-blue-500/10 dark:hover:text-blue-400"
                            title="Get directions"
                            aria-label="Get directions to <?php echo e($tenant->name); ?>"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </button>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSubBranches): ?>
                            <button
                                @click.stop="expanded = !expanded"
                                :aria-expanded="expanded.toString()"
                                class="flex h-7 w-7 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-500 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                                aria-label="Toggle sub-locations for <?php echo e($tenant->name); ?>"
                            >
                                <svg class="h-3 w-3 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSubBranches): ?>
                    <div
                        x-show="expanded"
                        x-collapse
                        x-cloak
                        class="ml-11 space-y-0.5 border-t border-gray-100 px-2 pb-1.5 pt-1.5 dark:border-gray-700"
                    >
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $subBranches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $subIndex => $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <?php $subActualIndex = $subIndex + 1; ?>
                            <div class="flex items-center justify-between gap-1.5 rounded-md px-1.5 py-1 transition hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <button
                                    wire:click="flyToTenantCoord(<?php echo e($tenant->id); ?>, <?php echo e($subActualIndex); ?>)"
                                    @click="mobileOpen = false"
                                    class="min-w-0 flex-1 truncate text-left text-[10px] text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                >
                                    <span class="mr-1.5 inline-block h-1.5 w-1.5 rounded-full" style="background: hsl(<?php echo e($hue); ?>,65%,60%)"></span>
                                    <?php echo e($sub['name'] ?? 'Sub-location '.($subIndex + 1)); ?>

                                </button>

                                <button
                                    wire:click="getDirectionsToCoord(<?php echo e($tenant->id); ?>, <?php echo e($subActualIndex); ?>)"
                                    wire:loading.attr="disabled"
                                    wire:target="getDirectionsToCoord(<?php echo e($tenant->id); ?>, <?php echo e($subActualIndex); ?>)"
                                    @click="mobileOpen = false"
                                    class="shrink-0 rounded-full border border-gray-200 bg-white px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wider text-primary-600 transition hover:border-primary-600/40 hover:bg-blue-50 dark:border-gray-600 dark:bg-gray-800 dark:text-blue-400 dark:hover:border-blue-500/40 dark:hover:bg-blue-500/10"
                                >
                                    Directions
                                </button>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="px-6 py-12 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-xl border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800">
                    <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($favoritesOnly && count($favorites) === 0): ?>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">No favorites saved yet.</p>
                    <p class="mt-1 text-[10px] text-gray-400 dark:text-gray-500">Tap the heart on any destination to save it here.</p>
                <?php else: ?>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">No destinations match.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->hasActiveFilters): ?>
                    <button
                        wire:click="resetFilters"
                        class="mt-3 text-[11px] font-semibold text-primary-600 transition hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                    >
                        ← Clear filters
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div class="flex items-center justify-between border-t border-gray-200 px-3 py-1.5 dark:border-gray-700">
        <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400" aria-live="polite">
            <?php echo e($this->tenants->count()); ?> <?php echo e(Str::plural('place', $this->tenants->count())); ?>

        </span>
        <span class="text-[10px] font-medium text-gray-500 dark:text-gray-400">
            MapLibre · CARTO · OSRM
        </span>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/livewire/partials/explore-sidebar.blade.php ENDPATH**/ ?>