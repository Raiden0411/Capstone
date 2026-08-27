{{-- resources/views/public/pages/about.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\SiteSetting;
use App\Models\Tenant;

new
#[Layout('layouts.app')]
#[Title('About')]
class extends Component
{
    public function getSetting(string $key, string $default = ''): string
    {
        return SiteSetting::getValue($key, $default);
    }

    public function getImageUrl(?string $path, string $defaultUrl): string
    {
        return $path ? asset('storage/' . $path) : $defaultUrl;
    }

    public function defaultHeroImage(): string
    {
        return 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80';
    }

    public function defaultStoryImage(int $n): string
    {
        $images = [
            1 => 'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?q=80&w=600',
            2 => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=600',
            3 => 'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600',
        ];

        return $images[$n] ?? $images[1];
    }

    public function defaultHighlightImage(int $n): string
    {
        $images = [
            1 => 'https://images.unsplash.com/photo-1433086966358-54859d0ed716?q=80&w=800',
            2 => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?q=80&w=800',
            3 => 'https://images.unsplash.com/photo-1592388792816-621e508de543?q=80&w=800',
        ];

        return $images[$n] ?? $images[1];
    }

    public function getHighlightData(int $n): array
    {
        $tenantId      = SiteSetting::getValue("about_highlight{$n}_tenant_id");
        $overrideTitle = SiteSetting::getValue("about_highlight{$n}_title");
        $overrideText  = SiteSetting::getValue("about_highlight{$n}_text");
        $overrideImage = SiteSetting::getValue("about_highlight{$n}_image");

        $defaultTitle = match ($n) {
            1 => 'Gawahon Eco-Park',
            2 => 'The Angry Christ Mural',
            default => 'The VMC Kingdom',
        };

        $title = $overrideTitle ?: $defaultTitle;
        $text  = $overrideText ?: '';
        $image = $overrideImage;
        $slug  = null;

        if ($tenantId) {
            $tenant = Tenant::find($tenantId);

            if ($tenant) {
                $title = $overrideTitle ?: $tenant->name;
                $text  = $overrideText ?: ($tenant->settings?->where('key', 'spot_description')->first()?->value ?? '');
                $image = $overrideImage ?: ($tenant->settings?->where('key', 'spot_cover')->first()?->value ?? null);
                $slug  = $tenant->slug;
            }
        }

        $imageUrl = $image
            ? asset('storage/' . $image)
            : $this->defaultHighlightImage($n);

        return [
            'title'    => $title,
            'text'     => $text,
            'imageUrl' => $imageUrl,
            'slug'     => $slug,
        ];
    }
};
?>

<div class="relative z-10">
    {{-- 1. Hero Section --}}
    <section class="relative flex items-center justify-center w-full h-[420px] md:h-[520px] overflow-hidden">
        <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_hero_image'), $this->defaultHeroImage()) }}"
             alt="Victorias City"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>

        <div class="relative z-10 flex flex-col items-center text-center px-4">
            <h1 class="text-xl md:text-3xl font-bold tracking-widest text-white uppercase">
                {{ $this->getSetting('about_hero_subheading', 'Welcome to Victorias City') }}
            </h1>

            <h2 class="mt-3 text-4xl md:text-6xl font-display font-bold tracking-wide text-amber-300">
                {{ $this->getSetting('about_hero_heading', 'KADALAG-AN') }}
            </h2>

            @if($this->getSetting('about_hero_description'))
                <p class="mt-5 max-w-2xl text-sm md:text-base text-white/90 leading-relaxed">
                    {{ $this->getSetting('about_hero_description') }}
                </p>
            @endif

            <a href="{{ route('explore.map') }}" wire:navigate
               class="mt-8 px-8 py-3.5 text-sm md:text-base font-semibold text-white transition-all bg-primary-600 rounded-full hover:bg-primary-700 shadow-lg shadow-primary-600/20 focus-visible:ring-2 focus-visible:ring-primary-600/50">
                Plan your Visit Now
            </a>
        </div>
    </section>

    {{-- 2. The Story of the City --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 py-12 md:py-20">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div>
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-5 bg-amber-400 rounded-full shadow-sm"></span>
                    <h3 class="text-xl md:text-2xl font-bold tracking-wider text-gray-900 dark:text-white uppercase">
                        {{ $this->getSetting('about_story_heading', 'The Story of the City') }}
                    </h3>
                </div>

                <div class="space-y-4 text-gray-600 dark:text-gray-300 leading-relaxed text-sm md:text-base">
                    @if($this->getSetting('about_story_text1'))
                        <p>{{ $this->getSetting('about_story_text1') }}</p>
                    @endif

                    @if($this->getSetting('about_story_text2'))
                        <p>{{ $this->getSetting('about_story_text2') }}</p>
                    @endif
                </div>
            </div>

            <div class="w-full aspect-[4/3] rounded-3xl overflow-hidden shadow-lg bg-gray-200 dark:bg-gray-800 group">
                <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_story_image1'), $this->defaultStoryImage(1)) }}"
                     alt="Story of Victorias"
                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
            </div>
        </div>
    </section>

    {{-- 3. Gallery / Carousel Section --}}
    @php
        $galleryImages = [
            $this->getImageUrl(SiteSetting::getValue('about_story_image1'), $this->defaultStoryImage(1)),
            $this->getImageUrl(SiteSetting::getValue('about_story_image2'), $this->defaultStoryImage(2)),
            $this->getImageUrl(SiteSetting::getValue('about_story_image3'), $this->defaultStoryImage(3)),
            $this->getImageUrl(SiteSetting::getValue('about_highlight1_image'), $this->defaultHighlightImage(1)),
            'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=800&q=80',
        ];
    @endphp

    <section class="w-full py-12 md:py-16 overflow-hidden bg-white dark:bg-gray-900"
             x-data="{
                 items: {{ json_encode($galleryImages) }},
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
                         0: { left: '50%', width: '55%', height: '100%', transform: 'translate(-50%, -50%)', zIndex: 30, opacity: 1 },
                         1: { right: '12%', width: '32%', height: '80%', transform: 'translateY(-50%)', zIndex: 20, opacity: 0.85 },
                         2: { right: '0%', width: '22%', height: '60%', transform: 'translate(20%, -50%)', zIndex: 10, opacity: 0.5 },
                         3: { left: '12%', width: '32%', height: '80%', transform: 'translateY(-50%)', zIndex: 20, opacity: 0.85 },
                         4: { left: '0%', width: '22%', height: '60%', transform: 'translate(-20%, -50%)', zIndex: 10, opacity: 0.5 },
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

        <div class="relative flex items-center justify-center max-w-6xl mx-auto h-[280px] sm:h-[350px] md:h-[450px] px-4 sm:px-6 lg:px-8">
            <template x-for="(item, index) in items" :key="index">
                <div class="absolute rounded-3xl overflow-hidden shadow-xl transition-all duration-500"
                     :style="getPositionStyle(index)">
                    <img :src="item" alt="Gallery" class="object-cover w-full h-full">
                </div>
            </template>
        </div>

        <div class="flex justify-center items-center gap-3 mt-8">
            <button type="button" @click="goTo(active - 1)"
                    class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-blue-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-600/50"
                    aria-label="Previous">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <div class="flex items-center gap-2">
                <template x-for="(item, index) in items" :key="`dot-${index}`">
                    <button type="button" @click="goTo(index)"
                            :class="index === active ? 'w-3 h-3 bg-primary-600' : 'w-2 h-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400'"
                            class="rounded-full transition-all duration-300 focus-visible:ring-2 focus-visible:ring-primary-600/50"></button>
                </template>
            </div>

            <button type="button" @click="goTo(active + 1)"
                    class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-blue-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-600/50"
                    aria-label="Next">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </section>

    {{-- 4. Alternating Features / Highlights --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 py-12 md:py-20 space-y-16 md:space-y-24">
        @foreach([1, 2, 3] as $n)
            @php
                $data    = $this->getHighlightData($n);
                $reverse = $n % 2 === 0;
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="order-1 {{ $reverse ? 'md:order-2' : 'md:order-1' }} w-full aspect-[4/3] rounded-3xl overflow-hidden shadow-lg bg-gray-200 dark:bg-gray-800 group">
                    <img src="{{ $data['imageUrl'] }}"
                         alt="{{ $data['title'] }}"
                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                </div>

                <div class="order-2 {{ $reverse ? 'md:order-1 md:text-left' : 'md:order-2' }}">
                    <h3 class="text-2xl md:text-3xl font-bold text-primary-600 dark:text-blue-400 mb-4">
                        {{ $data['title'] }}
                    </h3>

                    @if($data['text'])
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed text-sm md:text-base">
                            {{ $data['text'] }}
                        </p>
                    @endif

                    @if($data['slug'])
                        <a href="{{ route('tenant.show', $data['slug']) }}" wire:navigate
                           class="inline-flex items-center gap-2 mt-6 text-primary-600 dark:text-blue-400 font-semibold hover:underline focus-visible:ring-2 focus-visible:ring-primary-600/50 rounded">
                            Visit this place →
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    {{-- 5. Plan Your Visit CTA --}}
    <section class="relative flex items-center justify-center w-full py-16 md:py-24 overflow-hidden">
        <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_cta_background_image'), 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80') }}"
             alt="Plan Your Visit"
             class="absolute inset-0 object-cover w-full h-full">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-black/50"></div>

        <div class="relative z-10 flex flex-col items-center text-center px-4 max-w-2xl mx-auto">
            <h2 class="text-3xl md:text-5xl font-bold text-white mb-4">
                {{ $this->getSetting('about_cta_heading', 'Plan Your Visit') }}
            </h2>

            @if($this->getSetting('about_cta_text'))
                <p class="text-gray-200 text-sm md:text-base mb-8">
                    {{ $this->getSetting('about_cta_text') }}
                </p>
            @else
                <p class="text-gray-200 text-sm md:text-base mb-8">
                    Start your journey and discover the best places, experiences, and attractions Victorias City has to offer.
                </p>
            @endif

            <a href="{{ route('explore.map') }}" wire:navigate
               class="px-10 py-4 text-sm md:text-base font-semibold text-white transition-all bg-primary-600 rounded-full hover:bg-primary-700 w-full sm:w-auto text-center shadow-lg shadow-primary-600/20 focus-visible:ring-2 focus-visible:ring-primary-600/50">
                Plan your Visit Now
            </a>
        </div>
    </section>
</div>