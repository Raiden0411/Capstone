

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! request()->routeIs('explore.map')): ?>

    <?php
        /*
         * Cached batch read — one cache entry covers all five keys.
         *
         * Contact info now lives in SiteSetting with sensible fallbacks.
         * Today there's no editor UI for these three keys, so they'll
         * always resolve to the defaults — but the moment an editor is
         * added, they become editable with zero Blade changes and zero
         * extra cache reads (the batch is already being fetched).
         *
         * Invalidated instantly when an admin writes any of these keys
         * via SiteSetting::setValue() (see SiteSetting::getBatch).
         */
        $settings = \App\Models\SiteSetting::getBatch(
            [
                'site_name',
                'site_logo',
                'contact_office',
                'contact_address',
                'contact_email',
            ],
            'footer_branding'
        );

        $siteName  = $settings['site_name']  ?? config('app.name', 'Victorias City Tourism');
        $logoPath  = $settings['site_logo']  ?? null;

        // Only build a logo URL when the file actually exists on disk.
        // Falling back to `time()` when the file was missing produced a
        // unique URL per request and defeated browser caching entirely.
        $logoUrl = null;
        if ($logoPath) {
            $fullPath = public_path('storage/' . $logoPath);
            if (file_exists($fullPath)) {
                $logoUrl = asset('storage/' . $logoPath) . '?v=' . filemtime($fullPath);
            }
        }

        $contactOffice  = $settings['contact_office']  ?? 'Victorias City Tourism Office';
        $contactAddress = $settings['contact_address'] ?? "City Hall Complex,\nVictorias City, Negros Occidental, Philippines";
        $contactEmail   = $settings['contact_email']   ?? 'tourism@victoriascity.gov.ph';

        // Business registration is behind auth middleware. Guests clicking
        // these links would be silently bounced to /login with no context.
        // Route them through account creation first, preserving
        // /register-business as the return target.
        $registerBusinessUrl = Auth::check()
            ? route('register_business')
            : route('register', ['redirect' => route('register_business')]);

        // Canonical link class (Rule 93 focus stack + Rule 8 press feedback).
        // Kept as a variable so all 18 nav links stay in lockstep.
        $linkClass = 'inline-block transition-all duration-200 active:scale-95 hover:text-primary-600 dark:hover:text-primary-400 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900 rounded';
    ?>

    
    <footer x-data="revealOnScroll"
            class="border-t border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900
                   pt-12
                   pb-[calc(3rem+env(safe-area-inset-bottom))]
                   ps-[max(1rem,env(safe-area-inset-left))]
                   pe-[max(1rem,env(safe-area-inset-right))]
                   sm:ps-[max(1.5rem,env(safe-area-inset-left))]
                   sm:pe-[max(1.5rem,env(safe-area-inset-right))]
                   lg:ps-[max(4rem,env(safe-area-inset-left))]
                   lg:pe-[max(4rem,env(safe-area-inset-right))]">
        <div class="mx-auto max-w-[90rem]">

            
            <div data-reveal class="pb-10 mb-10 border-b border-gray-200 dark:border-gray-700">
                <a href="<?php echo e(route('home')); ?>" wire:navigate
                   class="group mb-4 inline-flex items-center gap-3 rounded-lg
                          transition-all duration-200 active:scale-95
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                        <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($siteName); ?> logo"
                             width="40" height="40"
                             loading="lazy" decoding="async"
                             class="h-10 w-10 shrink-0 object-contain rounded-lg opacity-90 transition-opacity group-hover:opacity-100">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="font-display text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        <?php echo e($siteName); ?>

                    </span>
                </a>

                
                <p class="mb-1.5 inline-flex items-center gap-2
                          text-xs font-bold uppercase tracking-[0.2em]
                          text-amber-600 dark:text-amber-400">
                    <span class="h-px w-4 bg-amber-500"></span>
                    Discover the Sweet City of the North
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed max-w-2xl">
                    Nature, heritage, and warm hospitality — everything you need to plan a memorable visit to Victorias City, Negros Occidental.
                </p>
            </div>

            
            <div class="pb-12 mb-12 border-b border-gray-200 dark:border-gray-700 grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">

                
                <nav aria-label="Explore" data-reveal>
                    <p class="mb-4 inline-flex items-center gap-2
                              text-xs font-bold uppercase tracking-[0.2em]
                              text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-500"></span>
                        Explore
                    </p>
                    <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                        <li>
                            <a href="<?php echo e(route('tourist-spots.index')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Tourist Spots &amp; Landmarks
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Dining &amp; Local Cuisine
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('about')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Heritage &amp; Architecture
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Local Markets &amp; Shops
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Nature &amp; Escapes
                            </a>
                        </li>
                    </ul>
                </nav>

                
                <nav aria-label="Community" data-reveal style="--reveal-delay: 80ms">
                    <p class="mb-4 inline-flex items-center gap-2
                              text-xs font-bold uppercase tracking-[0.2em]
                              text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-500"></span>
                        Community
                    </p>
                    <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                        <li>
                            <a href="<?php echo e(route('about')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Local Artisans &amp; Makers
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('about')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Local Stories &amp; Narratives
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('events')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Travel Guides &amp; Bulletins
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e(route('events')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Festivals &amp; Events Calendar
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e($registerBusinessUrl); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Submit a Place
                            </a>
                        </li>
                    </ul>
                </nav>

                
                <nav aria-label="About" data-reveal style="--reveal-delay: 160ms">
                    <p class="mb-4 inline-flex items-center gap-2
                              text-xs font-bold uppercase tracking-[0.2em]
                              text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-500"></span>
                        About
                    </p>
                    <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                        <li>
                            <a href="<?php echo e(route('about')); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                The Victorias Story
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo e($registerBusinessUrl); ?>" wire:navigate class="<?php echo e($linkClass); ?>">
                                Partnerships &amp; Linkages
                            </a>
                        </li>
                    </ul>
                </nav>

                
                <div data-reveal style="--reveal-delay: 240ms">
                    <p class="mb-4 inline-flex items-center gap-2
                              text-xs font-bold uppercase tracking-[0.2em]
                              text-primary-600 dark:text-primary-400">
                        <span class="h-px w-4 bg-primary-500"></span>
                        Contact
                    </p>
                    <address class="text-sm leading-relaxed text-gray-600 dark:text-gray-300 space-y-2.5 not-italic">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-900 dark:text-white">
                            <?php echo e($contactOffice); ?>

                        </p>
                        <p class="text-xs">
                            <?php echo nl2br(e($contactAddress)); ?>

                        </p>
                        <p class="text-xs">
                            <a href="mailto:<?php echo e($contactEmail); ?>" class="<?php echo e($linkClass); ?> break-all">
                                <?php echo e($contactEmail); ?>

                            </a>
                        </p>
                    </address>
                </div>
            </div>

            
            <div data-reveal class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                <p class="tabular-nums">
                    &copy; <?php echo e(date('Y')); ?> <?php echo e($siteName); ?>. All rights reserved.
                </p>
                <p class="inline-flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Made in Victorias City
                </p>
            </div>
        </div>
    </footer>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH C:\laragon\www\Capstone\resources\views/components/footers/public-footer.blade.php ENDPATH**/ ?>