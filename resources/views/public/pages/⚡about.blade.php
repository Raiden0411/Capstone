{{-- resources/views/public/pages/⚡about.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Scopes\TenantScope;

new
#[Layout('layouts.app')]
#[Title('About')]
class extends Component
{
    /**
     * Keys this page reads. Central list so the batch cache, the editor,
     * and any future additions stay in sync.
     */
    private const ABOUT_KEYS = [
        'about_hero_image',
        'about_hero_subheading',
        'about_hero_heading',
        'about_hero_description',
        'about_story_heading',
        'about_story_text1',
        'about_story_text2',
        'about_story_image1',
        'about_story_image2',
        'about_story_image3',
        'about_highlight1_tenant_id',
        'about_highlight1_title',
        'about_highlight1_text',
        'about_highlight1_image',
        'about_highlight2_tenant_id',
        'about_highlight2_title',
        'about_highlight2_text',
        'about_highlight2_image',
        'about_highlight3_tenant_id',
        'about_highlight3_title',
        'about_highlight3_text',
        'about_highlight3_image',
        'about_cta_background_image',
        'about_cta_heading',
        'about_cta_text',
    ];

    /**
     * Every setting on this page, sourced from a single versioned
     * cache entry. Any `SiteSetting::setValue()` call by the admin
     * editor bumps the version token → this batch key invalidates
     * instantly → the About page shows the fresh content on next
     * request without waiting for any TTL.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function aboutSettings(): array
    {
        return SiteSetting::getBatch(self::ABOUT_KEYS, 'about_page');
    }

    protected function setting(string $key, string $default = ''): string
    {
        $value = $this->aboutSettings[$key] ?? $default;

        return is_string($value) ? $value : (string) $value;
    }

    protected function imageUrl(?string $path, string $defaultUrl): string
    {
        return $path ? asset('storage/' . $path) : $defaultUrl;
    }

    #[Computed]
    public function heroImageUrl(): string
    {
        return $this->imageUrl(
            $this->setting('about_hero_image'),
            'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80'
        );
    }

    #[Computed]
    public function heroSubheading(): string
    {
        return $this->setting('about_hero_subheading', 'Welcome to Victorias City');
    }

    #[Computed]
    public function heroHeading(): string
    {
        return $this->setting('about_hero_heading', 'KADALAG-AN');
    }

    #[Computed]
    public function heroDescription(): string
    {
        return $this->setting('about_hero_description');
    }

    #[Computed]
    public function storyHeading(): string
    {
        return $this->setting('about_story_heading', 'The Story of the City');
    }

    #[Computed]
    public function storyText1(): string
    {
        return $this->setting('about_story_text1');
    }

    #[Computed]
    public function storyText2(): string
    {
        return $this->setting('about_story_text2');
    }

    #[Computed]
    public function storyImage1Url(): string
    {
        return $this->imageUrl(
            $this->setting('about_story_image1'),
            'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?q=80&w=600'
        );
    }

    #[Computed]
    public function galleryImages(): array
    {
        $images = [
            $this->setting('about_story_image1'),
            $this->setting('about_story_image2'),
            $this->setting('about_story_image3'),
            $this->setting('about_highlight1_image'),
            $this->setting('about_highlight2_image'),
            $this->setting('about_highlight3_image'),
        ];

        $images = array_filter($images, fn ($img) => $img !== '' && $img !== null);

        if (empty($images)) {
            return [
                'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?q=80&w=600',
                'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=600',
                'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600',
                'https://images.unsplash.com/photo-1433086966358-54859d0ed716?q=80&w=800',
                'https://images.unsplash.com/photo-1501785888041-af3ef285b470?auto=format&fit=crop&w=800&q=80',
            ];
        }

        return array_values(array_map(fn ($img) => asset('storage/' . $img), $images));
    }

    /**
     * JSON-encoded gallery URLs for the Alpine carousel.
     *
     * Encoded with JSON_HEX_* flags so the payload is safe to embed in
     * an HTML data-* attribute. This is the pattern established across
     * every carousel in the codebase — see the header comment on the
     * tourist-spots SFC for why `@js()` inside `x-data` is a trap.
     */
    #[Computed]
    public function galleryImagesJson(): string
    {
        return (string) json_encode(
            $this->galleryImages,
            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG,
        );
    }

    #[Computed]
    public function highlights(): array
    {
        $tenantIds = collect([1, 2, 3])
            ->map(fn ($n) => $this->setting("about_highlight{$n}_tenant_id"))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        // `Tenant` itself is unscoped, but `settings` is a
        // BelongsToTenant relationship — the eager load MUST bypass
        // TenantScope. Narrowed to the two keys actually read below.
        $tenants = $tenantIds->isNotEmpty()
            ? Tenant::query()
                ->with([
                    'settings' => fn ($q) => $q
                        ->withoutGlobalScope(TenantScope::class)
                        ->whereIn('key', ['spot_description', 'spot_cover'])
                        ->select('id', 'tenant_id', 'key', 'value'),
                ])
                ->whereIn('id', $tenantIds)
                ->get()
                ->keyBy('id')
            : collect();

        return collect([1, 2, 3])->map(function ($n) use ($tenants) {
            $tenantId      = $this->setting("about_highlight{$n}_tenant_id");
            $overrideTitle = $this->setting("about_highlight{$n}_title");
            $overrideText  = $this->setting("about_highlight{$n}_text");
            $overrideImage = $this->setting("about_highlight{$n}_image");

            $defaultTitle = match ($n) {
                1 => 'Gawahon Eco-Park',
                2 => 'The Angry Christ Mural',
                default => 'The VMC Kingdom',
            };

            $defaultImage = match ($n) {
                1 => 'https://images.unsplash.com/photo-1433086966358-54859d0ed716?q=80&w=800',
                2 => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?q=80&w=800',
                default => 'https://images.unsplash.com/photo-1592388792816-621e508de543?q=80&w=800',
            };

            $title = $overrideTitle ?: $defaultTitle;
            $text  = $overrideText;
            $image = $overrideImage;
            $slug  = null;

            if ($tenantId && ($tenant = $tenants->get((int) $tenantId))) {
                $title = $overrideTitle ?: $tenant->name;
                $text  = $overrideText ?: ($tenant->settings->firstWhere('key', 'spot_description')?->value ?? '');
                $image = $overrideImage ?: ($tenant->settings->firstWhere('key', 'spot_cover')?->value ?? null);
                $slug  = $tenant->slug;
            }

            $imageUrl = $image
                ? asset('storage/' . $image)
                : $defaultImage;

            return [
                'title'    => $title,
                'text'     => $text,
                'imageUrl' => $imageUrl,
                'slug'     => $slug,
            ];
        })->toArray();
    }

    #[Computed]
    public function ctaBackgroundUrl(): string
    {
        return $this->imageUrl(
            $this->setting('about_cta_background_image'),
            'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80'
        );
    }

    #[Computed]
    public function ctaHeading(): string
    {
        return $this->setting('about_cta_heading', 'Plan Your Visit');
    }

    #[Computed]
    public function ctaText(): string
    {
        return $this->setting('about_cta_text', 'Start your journey and discover the best places, experiences, and attractions Victorias City has to offer.');
    }
};
?>

<div class="relative z-10">
    {{-- 1. Hero Section --}}
    <section class="relative flex items-center justify-center w-full min-h-[420px] md:min-h-[520px] overflow-hidden">
        <img src="{{ $this->heroImageUrl }}"
             alt=""
             aria-hidden="true"
             class="absolute inset-0 object-cover w-full h-full"
             loading="eager"
             fetchpriority="high"
             decoding="async"
             width="1920"
             height="520">

        <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>

        <div class="relative z-10 flex flex-col items-center text-center px-4 py-16">
            {{--
                Eyebrow — rendered as <p> not <h1>.

                WHY: The page's real <h1> is the main title below
                ("KADALAG-AN"). Screen readers walking the heading tree
                should land on the page's actual subject, not the
                decorative kicker above it.
            --}}
            <p class="text-xl md:text-3xl font-bold tracking-widest text-white uppercase">
                {{ $this->heroSubheading }}
            </p>

            <h1 class="mt-3 text-4xl md:text-6xl font-display font-bold tracking-wide text-amber-300">
                {{ $this->heroHeading }}
            </h1>

            @if($this->heroDescription)
                <p class="mt-5 max-w-2xl text-sm md:text-base text-white/90 leading-relaxed">
                    {{ $this->heroDescription }}
                </p>
            @endif

            <a href="{{ route('tourist-spots.index') }}" wire:navigate
               class="mt-8 px-8 py-3.5 text-sm md:text-base font-semibold text-white transition-all bg-primary-600 rounded-full hover:bg-primary-700 shadow-lg shadow-primary-600/20 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Plan your Visit Now
            </a>
        </div>
    </section>

    {{-- 2. The Story of the City --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 py-12 md:py-20">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center">
            <div>
                <div class="flex items-center gap-3 mb-5">
                    <span class="w-5 h-5 bg-amber-400 rounded-full shadow-sm" aria-hidden="true"></span>
                    <h2 class="text-xl md:text-2xl font-bold tracking-wider text-gray-900 dark:text-white uppercase">
                        {{ $this->storyHeading }}
                    </h2>
                </div>

                <div class="space-y-4 text-gray-600 dark:text-gray-300 leading-relaxed text-sm md:text-base">
                    @if($this->storyText1)
                        <p>{{ $this->storyText1 }}</p>
                    @endif

                    @if($this->storyText2)
                        <p>{{ $this->storyText2 }}</p>
                    @endif
                </div>
            </div>

            <div class="w-full aspect-[4/3] rounded-3xl overflow-hidden shadow-lg bg-gray-200 dark:bg-gray-800 group">
                <img src="{{ $this->storyImage1Url }}"
                     alt="{{ $this->storyHeading }}"
                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                     loading="lazy"
                     decoding="async">
            </div>
        </div>
    </section>

    {{-- 3. Gallery / Carousel Section --}}
    {{--
        `wire:ignore` isolates the Alpine carousel state from Livewire
        morphs. No Livewire actions exist on this page today, but the
        pattern matches the homepage carousel and keeps the section
        safe if an action is added later.

        `overflow-hidden` clips the outer carousel slides whose
        `translate(±20%)` would otherwise extend past the section
        edge and create horizontal document overflow.

        The `items` payload is read from `$el.dataset.galleryImages`
        rather than embedded as a JS literal inside `x-data`. See
        `galleryImagesJson()` above for the why.
    --}}
    <section wire:ignore
             class="w-full py-12 md:py-16 overflow-hidden bg-white dark:bg-gray-900"
             data-gallery-images="{{ $this->galleryImagesJson }}"
             x-data="{
                 items: JSON.parse($el.dataset.galleryImages || '[]'),
                 active: 0,
                 interval: null,
                 observer: null,
                 touchStartX: 0,
                 touchEndX: 0,
                 prefersReduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                 init() {
                     if (this.prefersReduced) {
                         return;
                     }

                     if (!('IntersectionObserver' in window)) {
                         this.startAutoPlay();
                         return;
                     }

                     this.observer = new IntersectionObserver((entries) => {
                         entries.forEach(entry => {
                             if (entry.isIntersecting && !document.hidden) {
                                 this.startAutoPlay();
                             } else {
                                 this.stopAutoPlay();
                             }
                         });
                     }, { threshold: 0.15 });
                     this.observer.observe(this.$el);

                     document.addEventListener('visibilitychange', () => {
                         if (document.hidden) {
                             this.stopAutoPlay();
                         }
                     }, { passive: true });
                 },
                 destroy() {
                     this.stopAutoPlay();
                     if (this.observer) this.observer.disconnect();
                 },
                 startAutoPlay() {
                     if (this.prefersReduced) return;
                     if (this.interval) clearInterval(this.interval);
                     this.interval = setInterval(() => this.goTo(this.active + 1), 4000);
                 },
                 stopAutoPlay() {
                     if (this.interval) clearInterval(this.interval);
                     this.interval = null;
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

                 // ─────────────────────────────────────────────────────────
                 //  Adaptive slot layout — matches the homepage carousel.
                 //
                 //  Instead of a fixed 0..4 index table (which broke when the
                 //  item count wasn't exactly 5), we compute each card's
                 //  *signed shortest offset* from the active card and place
                 //  it into a left/right slot. This guarantees the same
                 //  number of cards appear on each side regardless of how
                 //  many items exist — 3, 4, 5, or 20 all look balanced.
                 // ─────────────────────────────────────────────────────────
                 getPositionStyle(index) {
                     const length = this.items.length;
                     if (length === 0) return { display: 'none' };

                     let offset = (index - this.active + length) % length;
                     if (offset > length / 2) offset -= length;

                     const absOffset = Math.abs(offset);
                     const maxSlots  = Math.min(Math.floor((length - 1) / 2), 2);

                     const base = {
                         position: 'absolute',
                         top: '50%',
                         transition: 'width 0.55s cubic-bezier(0.4,0,0.2,1), height 0.55s cubic-bezier(0.4,0,0.2,1), transform 0.55s cubic-bezier(0.4,0,0.2,1), opacity 0.55s cubic-bezier(0.4,0,0.2,1), left 0.55s cubic-bezier(0.4,0,0.2,1), right 0.55s cubic-bezier(0.4,0,0.2,1)',
                         overflow: 'hidden',
                         willChange: 'width, height, transform, opacity',
                     };

                     // ── CENTER ───────────────────────────────────────
                     if (offset === 0) {
                         return {
                             ...base,
                             left: '50%',
                             width: '55%',
                             height: '100%',
                             transform: 'translate(-50%, -50%)',
                             zIndex: 30,
                             opacity: 1,
                         };
                     }

                     // ── HIDDEN ───────────────────────────────────────
                     if (absOffset > maxSlots) {
                         return {
                             ...base,
                             left: '50%',
                             width: '0%',
                             height: '0%',
                             transform: 'translate(-50%, -50%)',
                             zIndex: 0,
                             opacity: 0,
                         };
                     }

                     // ── SIDE CARD ────────────────────────────────────
                     const isRight = offset > 0;
                     const isInner = absOffset === 1;

                     const innerWidth  = maxSlots >= 2 ? 32 : 38;
                     const outerWidth  = 22;
                     const innerHeight = maxSlots >= 2 ? 80 : 86;
                     const outerHeight = 60;

                     const width   = isInner ? innerWidth  : outerWidth;
                     const height  = isInner ? innerHeight : outerHeight;
                     const edgePct = isInner ? (maxSlots >= 2 ? 12 : 6) : 0;
                     const pushX   = isInner ? '0%' : (isRight ? '20%' : '-20%');
                     const opacity = isInner ? 0.85 : 0.5;
                     const z       = isInner ? 20 : 10;

                     return {
                         ...base,
                         ...(isRight ? { right: edgePct + '%' } : { left: edgePct + '%' }),
                         width: width + '%',
                         height: height + '%',
                         transform: `translate(${pushX}, -50%)`,
                         zIndex: z,
                         opacity: opacity,
                     };
                 }
             }"
             @mouseenter="stopAutoPlay()"
             @mouseleave="startAutoPlay()"
             @touchstart.passive="handleTouchStart($event)"
             @touchend="handleTouchEnd($event)">

        <div class="relative flex items-center justify-center max-w-6xl mx-auto h-[280px] sm:h-[350px] md:h-[450px] px-4 sm:px-6 lg:px-8">
            <template x-for="(item, index) in items" :key="index">
                <div class="absolute rounded-3xl overflow-hidden shadow-xl"
                     :style="getPositionStyle(index)">
                    <img :src="item"
                         alt=""
                         aria-hidden="true"
                         class="object-cover w-full h-full"
                         loading="lazy"
                         decoding="async">
                </div>
            </template>
        </div>

        <div class="flex justify-center items-center gap-3 mt-8">
            <button type="button" @click="goTo(active - 1)"
                    class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                    aria-label="Previous slide">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </button>

            <div class="flex items-center gap-2">
                <template x-for="(item, index) in items" :key="`dot-${index}`">
                    <button type="button" @click="goTo(index)"
                            :aria-label="'Go to slide ' + (index + 1)"
                            :aria-current="index === active ? 'true' : 'false'"
                            :class="index === active ? 'w-3 h-3 bg-primary-600' : 'w-2 h-2 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400'"
                            class="rounded-full transition-all duration-300 active:scale-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"></button>
                </template>
            </div>

            <button type="button" @click="goTo(active + 1)"
                    class="w-10 h-10 flex items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 hover:text-primary-600 dark:hover:text-primary-400 transition-colors active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                    aria-label="Next slide">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
    </section>

    {{-- 4. Alternating Features / Highlights --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 md:px-12 py-12 md:py-20 space-y-16 md:space-y-24 [content-visibility:auto] [contain-intrinsic-size:auto_1200px]">
        @foreach($this->highlights as $n => $data)
            @php $reverse = ($n + 1) % 2 === 0; @endphp

            <div wire:key="highlight-{{ $n }}" class="grid grid-cols-1 md:grid-cols-2 gap-10 lg:gap-16 items-center">
                <div class="order-1 {{ $reverse ? 'md:order-2' : 'md:order-1' }} w-full aspect-[4/3] rounded-3xl overflow-hidden shadow-lg bg-gray-200 dark:bg-gray-800 group">
                    <img src="{{ $data['imageUrl'] }}"
                         alt="{{ $data['title'] }}"
                         class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                         loading="lazy"
                         decoding="async">
                </div>

                <div class="order-2 {{ $reverse ? 'md:order-1 md:text-left' : 'md:order-2' }}">
                    <h3 class="text-2xl md:text-3xl font-bold text-primary-600 dark:text-primary-400 mb-4">
                        {{ $data['title'] }}
                    </h3>

                    @if($data['text'])
                        <p class="text-gray-600 dark:text-gray-300 leading-relaxed text-sm md:text-base">
                            {{ $data['text'] }}
                        </p>
                    @endif

                    @if($data['slug'])
                        <a href="{{ route('tenant.show', $data['slug']) }}" wire:navigate
                           class="inline-flex items-center gap-2 mt-6 text-primary-600 dark:text-primary-400 font-semibold hover:underline active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                            Visit this place
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    {{-- 5. Plan Your Visit CTA --}}
    <section class="relative flex items-center justify-center w-full py-16 md:py-24 overflow-hidden [content-visibility:auto] [contain-intrinsic-size:auto_420px]">
        <img src="{{ $this->ctaBackgroundUrl }}"
             alt=""
             aria-hidden="true"
             class="absolute inset-0 object-cover w-full h-full"
             loading="lazy"
             decoding="async">
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-black/50"></div>

        <div class="relative z-10 flex flex-col items-center text-center px-4 max-w-2xl mx-auto">
            <h2 class="text-3xl md:text-5xl font-bold text-white mb-4">
                {{ $this->ctaHeading }}
            </h2>

            <p class="text-gray-200 text-sm md:text-base mb-8">
                {{ $this->ctaText }}
            </p>

            <a href="{{ route('tourist-spots.index') }}" wire:navigate
               class="px-10 py-4 text-sm md:text-base font-semibold text-white transition-all bg-primary-600 rounded-full hover:bg-primary-700 w-full sm:w-auto text-center shadow-lg shadow-primary-600/20 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Plan your Visit Now
            </a>
        </div>
    </section>
</div>