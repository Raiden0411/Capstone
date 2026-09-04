{{-- resources/views/components/footers/public-footer.blade.php --}}
@php
    $settings = \App\Models\SiteSetting::whereIn('key', ['site_name', 'site_logo'])
        ->pluck('value', 'key');

    $siteName = $settings['site_name'] ?? config('app.name', 'Victorias City');
    $logoPath = $settings['site_logo'] ?? null;
    $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;
@endphp

<footer class="border-t border-gray-200 bg-white py-10 font-sans dark:border-gray-700 dark:bg-gray-900 px-4 sm:px-6 lg:px-16 w-full">
    <div class="mx-auto max-w-[90rem]">

        {{-- Top Section: Brand & Badge --}}
        <div class="mb-8 flex flex-col items-start justify-between gap-6 md:flex-row md:items-end">
            <div>
                <a href="{{ route('home') }}" wire:navigate
                   class="group mb-3 flex items-center gap-3 rounded-lg transition-transform focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 active:scale-[0.98]">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo"
                             class="h-10 w-10 shrink-0 object-contain rounded-lg opacity-90 transition-opacity group-hover:opacity-100">
                    @endif
                    <h2 class="font-display text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        {{ $siteName }}
                    </h2>
                </a>
                <p class="text-xs font-medium uppercase tracking-[0.2em] text-gray-500 dark:text-gray-400">
                    Discover the Sweet City of the North — Nature, Heritage & Warm Hospitality
                </p>
            </div>

            <div class="px-4 py-2 text-[10px] font-medium uppercase tracking-widest text-gray-500 dark:text-gray-400 border border-gray-300 dark:border-gray-600 rounded-full">
                Est. 1998 // N° 01
            </div>
        </div>

        <hr class="mb-8 border-gray-200 dark:border-gray-700">

        {{-- Middle Section: 4 Columns --}}
        <div class="mb-12 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-6">

            {{-- Column 1: Explore --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-blue-400">Explore</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <a href="{{ route('tourist-spots.index') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Tourist Spots & Landmarks
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('explore.map') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Dining & Local Cuisine
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Heritage & Architecture
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('explore.map') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Local Markets & Shops
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('explore.map') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Nature & Escapes
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 2: Community --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-blue-400">Community</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <a href="{{ route('about') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Local Artisans & Makers
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('about') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Local Stories & Narratives
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('events') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Travel Guides & Bulletins
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('events') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Festivals & Events Calendar
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('register_business') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Submit a Place
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 3: About --}}
            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-blue-400">About</h3>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                    <li>
                        <a href="{{ route('about') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            The Victorias Story
                        </a>
                    </li>
                    <li>
                        <span class="inline-block cursor-not-allowed opacity-60" title="Coming soon">Tourism Office</span>
                    </li>
                    <li>
                        <span class="inline-block cursor-not-allowed opacity-60" title="Coming soon">Press & Media Kit</span>
                    </li>
                    <li>
                        <span class="inline-block cursor-not-allowed opacity-60" title="Coming soon">Careers & Opportunities</span>
                    </li>
                    <li>
                        <a href="{{ route('register_business') }}" wire:navigate
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            Partnerships & Linkages
                        </a>
                    </li>
                </ul>
            </div>

            {{-- Column 4: Visitor Dispatch --}}
            <div x-data="{ subscribed: false }">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-primary-600 dark:text-blue-400">Visitor Dispatch</h3>
                <p class="mb-4 text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    Sign up for our weekly tourism digest mapping city attractions, cultural events, and travel tips.
                </p>

                {{-- Email Newsletter Form --}}
                <form @submit.prevent="subscribed = true"
                      class="mb-3 flex items-center rounded-full border border-gray-300 bg-gray-100 p-1.5 transition-colors focus-within:border-primary-600 dark:border-gray-600 dark:bg-gray-800 dark:focus-within:border-blue-400">
                    <input type="email" required placeholder="Enter your email address"
                           class="w-full px-4 text-sm text-gray-900 bg-transparent outline-none placeholder:text-gray-400 dark:text-white dark:placeholder:text-gray-500">
                    <button type="submit"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-600 text-white transition-all duration-200 hover:bg-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 active:scale-95"
                            aria-label="Subscribe">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </button>
                </form>
                <div x-show="subscribed" x-cloak class="mb-4 text-xs text-green-600 dark:text-green-400">
                    Thank you for subscribing!
                </div>
                <div x-show="!subscribed" class="h-5"></div>

                {{-- Contact Info --}}
                <div class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">
                    <h4 class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-900 dark:text-white">
                        Victorias City Tourism Office
                    </h4>
                    <p class="text-xs">City Hall Complex, Victorias City, Negros Occidental, Philippines</p>
                    <p class="text-xs">
                        <a href="mailto:tourism@victoriascity.gov.ph"
                           class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                            tourism@victoriascity.gov.ph
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <hr class="mb-6 border-gray-200 dark:border-gray-700">

        {{-- Bottom Section: Legal & Socials --}}
        <div class="flex flex-col items-center justify-between gap-6 text-xs text-gray-500 dark:text-gray-400 md:flex-row">

            <div class="flex flex-col items-center gap-4 md:flex-row lg:gap-8">
                <span>© {{ date('Y') }} {{ $siteName }}. All rights reserved.</span>
                <div class="flex gap-6">
                    <a href="#" class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                        Privacy Policy
                    </a>
                    <a href="#" class="inline-block transition-all duration-200 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded active:scale-95 dark:hover:text-blue-400">
                        Terms of Service
                    </a>
                </div>
            </div>

            {{-- Social Icons --}}
            <div class="flex items-center gap-3">
                @foreach([
                    'instagram' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 7.5h.01M12 15a3 3 0 100-6 3 3 0 000 6zM5.25 9.5a4.25 4.25 0 014.25-4.25h5a4.25 4.25 0 014.25 4.25v5a4.25 4.25 0 01-4.25 4.25h-5a4.25 4.25 0 01-4.25-4.25v-5z"/></svg>',
                    'x' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
                    'facebook' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2C6.477 2 2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.129 22 16.99 22 12c0-5.523-4.477-10-10-10z"/></svg>',
                    'twitter' => '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M23 3a10.9 10.9 0 01-3.14 1.53 4.48 4.48 0 00-7.86 3v1A10.66 10.66 0 013 4s-4 9 5 13a11.64 11.64 0 01-7 2c9 5 20 0 20-11.5a4.5 4.5 0 00-.08-.83A7.72 7.72 0 0023 3z"/></svg>'
                ] as $name => $icon)
                    <a href="#" aria-label="{{ ucfirst($name) }}" title="Follow us on {{ ucfirst($name) }}"
                       class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-300 text-gray-500 transition-all duration-200 hover:border-primary-600 hover:text-primary-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/50 active:scale-95 dark:border-gray-600 dark:text-gray-400 dark:hover:border-blue-400 dark:hover:text-blue-400">
                        {!! $icon !!}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</footer>