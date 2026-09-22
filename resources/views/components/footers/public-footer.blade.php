{{-- resources/views/components/footers/public-footer.blade.php --}}
@php
    // Cached batch read — one cache entry covers both keys. Invalidated
    // instantly when an admin writes site_name or site_logo via
    // SiteSetting::setValue() (see SiteSetting::getBatch).
    $settings = \App\Models\SiteSetting::getBatch(
        ['site_name', 'site_logo'],
        'footer_branding'
    );

    $siteName = $settings['site_name'] ?? config('app.name', 'Victorias City');
    $logoPath = $settings['site_logo'] ?? null;
    $logoUrl  = $logoPath ? asset('storage/' . $logoPath) : null;

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
@endphp

<footer class="border-t border-gray-200 bg-white py-10 dark:border-gray-700 dark:bg-gray-900 px-4 sm:px-6 lg:px-16">
    <div class="mx-auto max-w-[90rem]">

        {{-- Top Section: Brand --}}
        <div class="pb-8 mb-8 border-b border-gray-200 dark:border-gray-700">
            <a href="{{ route('home') }}" wire:navigate
               class="group mb-3 inline-flex items-center gap-3 rounded-lg
                      transition-all duration-200 active:scale-95
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo"
                         width="40" height="40"
                         loading="lazy" decoding="async"
                         class="h-10 w-10 shrink-0 object-contain rounded-lg opacity-90 transition-opacity group-hover:opacity-100">
                @endif
                <span class="font-display text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                    {{ $siteName }}
                </span>
            </a>
            <p class="text-xs font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                Discover the Sweet City of the North — Nature, Heritage &amp; Warm Hospitality
            </p>
        </div>

        {{-- Middle Section: Navigation + Contact --}}
        <div class="pb-12 mb-12 border-b border-gray-200 dark:border-gray-700 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">

            {{-- Column 1: Explore --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">Explore</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <a href="{{ route('tourist-spots.index') }}" wire:navigate class="{{ $linkClass }}">
                            Tourist Spots &amp; Landmarks
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('explore.map') }}" wire:navigate class="{{ $linkClass }}">
                            Dining &amp; Local Cuisine
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">
                            Heritage &amp; Architecture
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('explore.map') }}" wire:navigate class="{{ $linkClass }}">
                            Local Markets &amp; Shops
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('explore.map') }}" wire:navigate class="{{ $linkClass }}">
                            Nature &amp; Escapes
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 2: Community --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">Community</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">
                            Local Artisans &amp; Makers
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">
                            Local Stories &amp; Narratives
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('events') }}" wire:navigate class="{{ $linkClass }}">
                            Travel Guides &amp; Bulletins
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('events') }}" wire:navigate class="{{ $linkClass }}">
                            Festivals &amp; Events Calendar
                        </a>
                    </li>
                    <li>
                        <a href="{{ $registerBusinessUrl }}" wire:navigate class="{{ $linkClass }}">
                            Submit a Place
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 3: About --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">About</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <a href="{{ route('about') }}" wire:navigate class="{{ $linkClass }}">
                            The Victorias Story
                        </a>
                    </li>
                    <li>
                        <a href="{{ $registerBusinessUrl }}" wire:navigate class="{{ $linkClass }}">
                            Partnerships &amp; Linkages
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 4: Contact --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">Contact</h3>
                <div class="text-sm leading-relaxed text-gray-600 dark:text-gray-300 space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-900 dark:text-white">
                        Victorias City Tourism Office
                    </p>
                    <p class="text-xs">
                        City Hall Complex,<br>
                        Victorias City, Negros Occidental, Philippines
                    </p>
                    <p class="text-xs">
                        <a href="mailto:tourism@victoriascity.gov.ph" class="{{ $linkClass }} break-all">
                            tourism@victoriascity.gov.ph
                        </a>
                    </p>
                </div>
            </div>
        </div>

        {{-- Bottom Section: Copyright --}}
        <div class="text-center text-xs text-gray-500 dark:text-gray-400">
            &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
        </div>
    </div>
</footer>