{{-- resources/views/components/footers/public-footer.blade.php --}}
{{--
    Hidden on /explore/map. That page sizes itself to `100dvh - header` and
    fills edge-to-edge with a live MapLibre canvas; a footer below it would
    push the map up, add a scrollbar, and place marketing content directly
    under the canvas. Everywhere else, the footer renders normally.
--}}
@if(! request()->routeIs('explore.map'))

    @php
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

        $siteName  = $settings['site_name'] ?? config('app.name', 'Victorias City Tourism');
        $logoPath  = $settings['site_logo'] ?? null;

        // Rule J: relative /storage path. Falls back to null when the file
        // is missing — a time()-based cache-buster produced a unique URL
        // per request and defeated browser caching entirely.
        $logoUrl = null;
        if ($logoPath) {
            $fullPath = public_path('storage/' . ltrim($logoPath, '/'));
            if (file_exists($fullPath)) {
                $logoUrl = '/storage/' . ltrim($logoPath, '/') . '?v=' . filemtime($fullPath);
            }
        }

        $contactOffice  = $settings['contact_office']  ?? 'Victorias City Tourism Office';
        $contactAddress = $settings['contact_address'] ?? "City Hall Complex,\nVictorias City, Negros Occidental, Philippines";
        $contactEmail   = $settings['contact_email']   ?? 'tourism@victoriascity.gov.ph';

        // /register-business sits behind auth middleware. A guest clicking
        // the link would be silently bounced to /login with no context —
        // route them through account creation first, preserving the return.
        $registerBusinessUrl = Auth::check()
            ? route('register_business')
            : route('register', ['redirect' => route('register_business')]);

        $linkClass = 'relative inline-block rounded transition-all duration-200 active:scale-95
                      hover:text-primary-600 dark:hover:text-primary-400
                      before:absolute before:content-[\'\'] before:-inset-2 before:rounded
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                      focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900';

        $eyebrow       = 'inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em]';
        $eyebrowPrimary = $eyebrow . ' text-primary-600 dark:text-primary-400';
        $eyebrowAmber   = $eyebrow . ' text-amber-600 dark:text-amber-400';
    @endphp

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
                <a href="{{ route('home') }}" wire:navigate
                   class="group mb-4 inline-flex items-center gap-3 rounded-lg
                          transition-all duration-200 active:scale-95
                          focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                          focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo"
                             width="40" height="40"
                             loading="lazy" decoding="async"
                             class="h-10 w-10 shrink-0 rounded-full object-contain ring-1 ring-black/5 shadow-sm dark:ring-white/10
                                    opacity-90 transition-opacity group-hover:opacity-100">
                    @endif
                    <span class="font-display text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ $siteName }}
                    </span>
                </a>

                <p class="{{ $eyebrowAmber }} mb-1.5">
                    <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                    Discover the Sweet City of the North
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed max-w-2xl">
                    Nature, heritage, and warm hospitality — everything you need to plan a memorable visit to Victorias City, Negros Occidental.
                </p>
            </div>

            {{-- Each link column is a <nav> so assistive tech announces the
                 landmark. Column headings use the §5.14 eyebrow pattern. --}}
            <div class="pb-12 mb-12 border-b border-gray-200 dark:border-gray-700
                        grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-8">

                <nav aria-label="Explore" data-reveal>
                    <p class="{{ $eyebrowPrimary }} mb-4">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                        Explore
                    </p>
                    <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                        <li><a href="{{ route('tourist-spots.index') }}" wire:navigate class="{{ $linkClass }}">Tourist Spots &amp; Landmarks</a></li>
                        <li><a href="{{ route('explore.map') }}" wire:navigate class="{{ $linkClass }}">Dining &amp; Local Cuisine</a></li>
                        <li><a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">Heritage &amp; Architecture</a></li>
                        <li><a href="{{ route('explore.map') }}" wire:navigate class="{{ $linkClass }}">Local Markets &amp; Shops</a></li>
                        <li><a href="{{ route('explore.map') }}" wire:navigate class="{{ $linkClass }}">Nature &amp; Escapes</a></li>
                    </ul>
                </nav>

                <nav aria-label="Community" data-reveal style="--reveal-delay: 80ms">
                    <p class="{{ $eyebrowPrimary }} mb-4">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                        Community
                    </p>
                    <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                        <li><a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">Local Artisans &amp; Makers</a></li>
                        <li><a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">Local Stories &amp; Narratives</a></li>
                        <li><a href="{{ route('events') }}" wire:navigate class="{{ $linkClass }}">Travel Guides &amp; Bulletins</a></li>
                        <li><a href="{{ route('events') }}" wire:navigate class="{{ $linkClass }}">Festivals &amp; Events Calendar</a></li>
                        <li><a href="{{ $registerBusinessUrl }}" wire:navigate class="{{ $linkClass }}">Submit a Place</a></li>
                    </ul>
                </nav>

                <nav aria-label="About" data-reveal style="--reveal-delay: 160ms">
                    <p class="{{ $eyebrowPrimary }} mb-4">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                        About
                    </p>
                    <ul class="space-y-2.5 text-sm text-gray-600 dark:text-gray-300">
                        <li><a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">The Victorias Story</a></li>
                        <li><a href="{{ $registerBusinessUrl }}" wire:navigate class="{{ $linkClass }}">Partnerships &amp; Linkages</a></li>
                    </ul>
                </nav>

                <div data-reveal style="--reveal-delay: 240ms">
                    <p class="{{ $eyebrowPrimary }} mb-4">
                        <span class="h-px w-4 bg-amber-500" aria-hidden="true"></span>
                        Contact
                    </p>
                    <address class="text-sm leading-relaxed text-gray-600 dark:text-gray-300 space-y-2.5 not-italic">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-900 dark:text-white">
                            {{ $contactOffice }}
                        </p>
                        <p class="text-xs">
                            {!! nl2br(e($contactAddress)) !!}
                        </p>
                        <p class="text-xs">
                            <a href="mailto:{{ $contactEmail }}" class="{{ $linkClass }} break-all">
                                {{ $contactEmail }}
                            </a>
                        </p>
                    </address>
                </div>
            </div>

            <div data-reveal class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
                <p class="tabular-nums">
                    &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
                </p>
                <p class="inline-flex items-center gap-1.5">
                    <span class="w-1 h-1 rounded-full bg-emerald-500" aria-hidden="true"></span>
                    Made in Victorias City
                </p>
            </div>
        </div>
    </footer>

@endif