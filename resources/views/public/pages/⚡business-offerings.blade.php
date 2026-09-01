<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;

new
#[Layout('layouts.app')]
#[Title('What We Offer')]
class extends Component
{
    public Tenant $tenant;
    public ?string $coverPhoto = null;

    public array  $galleryImages   = [];
    public string $galleryTitle    = '';
    public string $gallerySubtitle = '';

    public function mount($slug)
    {
        $this->tenant = Tenant::where('slug', $slug)->firstOrFail();

        $settings = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('key', ['spot_cover','business_gallery','gallery_title','gallery_subtitle'])
            ->get()->pluck('value','key');

        $this->coverPhoto      = $settings['spot_cover']       ?? null;
        $this->galleryImages   = $settings['business_gallery'] ?? [];
        if (!is_array($this->galleryImages)) {
            $this->galleryImages = [];
        }
        $this->galleryTitle    = $settings['gallery_title']    ?? '';
        $this->gallerySubtitle = $settings['gallery_subtitle'] ?? '';
    }

    #[Computed]
    public function properties()
    {
        return $this->tenant->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->select('id', 'tenant_id', 'property_type_id', 'name', 'description', 'price', 'capacity', 'quantity', 'is_active')
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
                'images'       => fn($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'property_id', 'image_path'),
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function services()
    {
        return $this->tenant->services()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->select('id', 'tenant_id', 'name', 'price', 'is_active')
            ->orderBy('name')
            ->get()
            ->each(function ($service) {
                $service->icon_path = $this->getServiceIconPath($service);
            });
    }

    /**
     * Determine the SVG path data for a service based on its name.
     */
    protected function getServiceIconPath($service): string
    {
        $name = strtolower($service->name);

        return match (true) {
            str_contains($name, 'pool')    => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z',
            str_contains($name, 'food')
                || str_contains($name, 'meal')
                || str_contains($name, 'dining') => 'M18 3a1 1 0 00-1 1v5h-2V4a1 1 0 00-2 0v5H9V4a1 1 0 00-2 0v6a4 4 0 003 3.87V20a1 1 0 002 0v-6.13A4 4 0 0016 10V4a1 1 0 00-2 0',
            str_contains($name, 'spa')
                || str_contains($name, 'massage') => 'M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zm0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7z',
            str_contains($name, 'tour')
                || str_contains($name, 'guide') => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
            str_contains($name, 'transfer')
                || str_contains($name, 'transport') => 'M8 17l4 4 4-4m-4-5v9M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 103 16.29',
            default => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
        };
    }
};
?>

@push('styles')
<style>
    .reveal {
        opacity: 0;
        transform: translateY(22px);
        transition: opacity .65s cubic-bezier(.16,1,.3,1), transform .65s cubic-bezier(.16,1,.3,1);
    }
    .reveal.in {
        opacity: 1;
        transform: translateY(0);
    }
    @media (prefers-reduced-motion: reduce) {
        .reveal { opacity: 1; transform: none; transition: none; }
    }
    .gal-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999;
        background: rgba(0,0,0,0.97);
        display: flex;
        flex-direction: column;
        padding-top: 64px;
        box-sizing: border-box;
    }
    .gal-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        grid-auto-rows: 180px;
        gap: 6px;
    }
    @media (min-width: 768px) {
        .gal-grid { grid-template-columns: repeat(4, 1fr); }
        .gal-item:nth-child(1) { grid-column: span 2; grid-row: span 2; }
        .gal-item:nth-child(5) { grid-column: span 2; }
        .gal-item:nth-child(9) { grid-column: span 2; grid-row: span 2; }
    }
    .lb-wrap {
        position: fixed;
        inset: 0;
        z-index: 999999;
        background: rgba(0,0,0,0.96);
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn .18s ease;
    }
    @keyframes fadeIn { from { opacity: 0 } to { opacity: 1 } }
    .lb-img {
        max-width: 90vw;
        max-height: 88vh;
        object-fit: contain;
        border-radius: 10px;
        box-shadow: 0 40px 80px rgba(0,0,0,.6);
    }
    .lb-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(255,255,255,.07);
        border: 1px solid rgba(255,255,255,.12);
        display: flex;
        align-items: center;
        justify-content: center;
        color: rgba(255,255,255,.6);
        cursor: pointer;
        transition: all .2s;
    }
    .lb-nav:hover {
        background: rgba(255,255,255,.15);
        color: #fff;
    }
    @keyframes floatPill { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-4px) } }
    .gallery-pill { animation: floatPill 3s ease-in-out infinite; }
    .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
</style>
@endpush

<div
    class="relative z-10 min-h-screen"
    x-data="{
        galleryOpen: false,
        lbSrc: null,
        lbIndex: 0,
        lbStartX: 0,
        galleryImages: {{ Js::from(collect($galleryImages)->map(fn($p) => asset('storage/'.$p))->values()) }},
        previousFocus: null,

        openGallery()  {
            this.previousFocus = document.activeElement;
            this.galleryOpen = true;
            document.body.style.overflow='hidden';
            this.$nextTick(() => this.$refs.galleryCloseBtn?.focus());
        },
        closeGallery() {
            this.galleryOpen = false;
            this.lbSrc=null;
            document.body.style.overflow='';
            this.previousFocus?.focus();
            this.previousFocus = null;
        },

        openLb(src, idx) { this.lbSrc = src; this.lbIndex = idx; },
        prevLb() { this.lbIndex = (this.lbIndex - 1 + this.galleryImages.length) % this.galleryImages.length; this.lbSrc = this.galleryImages[this.lbIndex]; },
        nextLb() { this.lbIndex = (this.lbIndex + 1) % this.galleryImages.length; this.lbSrc = this.galleryImages[this.lbIndex]; },

        touchStart(e) { this.lbStartX = e.changedTouches[0].clientX; },
        touchEnd(e) {
            const dx = e.changedTouches[0].clientX - this.lbStartX;
            if (Math.abs(dx) > 50) { dx < 0 ? this.nextLb() : this.prevLb(); }
        },

        setupReveal() {
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            const obs = new IntersectionObserver(entries => {
                entries.forEach(e => { if(e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
            }, { threshold: .08 });
            document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
        },

        stickyVisible: false,
        setupSticky() {
            const hero = document.getElementById('offerings-hero');
            if (!hero) return;
            const obs = new IntersectionObserver(([e]) => { this.stickyVisible = !e.isIntersecting; }, { threshold: .1 });
            obs.observe(hero);
        },
    }"
    x-init="setupReveal(); setupSticky();"
    @keydown.escape.window="lbSrc ? lbSrc=null : closeGallery()"
    @keydown.arrow-left.window="lbSrc && prevLb()"
    @keydown.arrow-right.window="lbSrc && nextLb()"
>

    {{-- Gallery Modal --}}
    <div x-show="galleryOpen" x-cloak class="gal-overlay"
         x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div class="flex-none flex items-center justify-between px-6 md:px-10 py-4 border-b border-white/[0.07]">
            <div>
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="w-3 h-px bg-primary-600"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-primary-400 font-bold">Photo Gallery</span>
                </div>
                <h2 class="font-display text-lg font-semibold text-white">
                    {{ $tenant->name }}
                    @if($galleryTitle)
                        <span class="text-white/30 mx-2 font-normal">·</span>
                        <em class="italic text-primary-400 text-base font-normal">{{ $galleryTitle }}</em>
                    @endif
                </h2>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-white/25 hidden sm:block">{{ count($galleryImages) }} photos</span>
                <button @click="closeGallery()"
                        x-ref="galleryCloseBtn"
                        class="w-9 h-9 rounded-full border border-white/12 flex items-center justify-center text-white/40 hover:text-white hover:border-white/35 hover:bg-white/[0.07] transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                        aria-label="Close gallery">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-4 md:p-6">
            @if(!empty($galleryImages))
                <div class="gal-grid max-w-7xl mx-auto">
                    @foreach($galleryImages as $idx => $imgPath)
                        <div class="gal-item relative overflow-hidden rounded-xl cursor-pointer group"
                             wire:key="gal-{{ $idx }}"
                             @click="openLb('{{ asset('storage/'.$imgPath) }}', {{ $idx }})">
                            <img src="{{ asset('storage/'.$imgPath) }}"
                                 class="w-full h-full object-cover"
                                 alt="{{ $tenant->name }} photo {{ $idx + 1 }}" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-3">
                                <div class="w-7 h-7 rounded-full bg-white/10 border border-white/20 flex items-center justify-center ml-auto">
                                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-white/25">
                    <svg class="w-10 h-10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm">No photos yet</p>
                </div>
            @endif
        </div>

        @if($gallerySubtitle)
            <div class="flex-none border-t border-white/[0.06] px-8 py-3 text-xs text-white/25 italic">{{ $gallerySubtitle }}</div>
        @endif
    </div>

    {{-- Lightbox --}}
    <div x-show="lbSrc" x-cloak class="lb-wrap"
         @click.self="lbSrc=null"
         @touchstart="touchStart($event)"
         @touchend="touchEnd($event)">
        <button @click="prevLb()" class="lb-nav" style="left:16px" aria-label="Previous">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="relative">
            <img :src="lbSrc" class="lb-img" alt="Gallery photo">
            <div class="absolute bottom-0 left-0 right-0 flex justify-between items-center px-4 py-3 bg-gradient-to-t from-black/80 to-transparent rounded-b-xl">
                <span class="text-xs text-white/40" x-text="(lbIndex+1)+' / '+galleryImages.length"></span>
                <button @click="lbSrc=null" class="text-[10px] text-white/35 hover:text-white uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50 inline-flex items-center gap-1">
                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Close
                </button>
            </div>
        </div>
        <button @click="nextLb()" class="lb-nav" style="right:16px" aria-label="Next">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- HERO --}}
    <section id="offerings-hero" class="relative min-h-[72vh] flex items-end overflow-hidden pb-16 md:pb-20">

        @if($coverPhoto)
            <img src="{{ asset('storage/'.$coverPhoto) }}"
                 class="absolute inset-0 w-full h-full object-cover scale-105"
                 style="filter:brightness(.35) saturate(1.2)" alt="" loading="eager">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-neutral-950 via-neutral-900 to-neutral-950"></div>
        @endif

        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/15 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/55 via-transparent to-transparent"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16 w-full">
            <div class="mb-7">
                <a href="{{ route('tenant.show', $tenant->slug) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 text-[10px] tracking-[0.22em] uppercase text-white/30 hover:text-primary-400 transition-colors group active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50 rounded">
                    <svg class="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                    Back to {{ $tenant->name }}
                </a>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">
                <div class="max-w-2xl">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-5 h-px bg-primary-400"></span>
                        <span class="text-[10px] tracking-[0.25em] uppercase text-primary-400 font-bold">Offerings</span>
                    </div>
                    <h1 class="font-display text-4xl sm:text-5xl md:text-7xl font-semibold text-white leading-[0.88] tracking-tight">
                        What<br>
                        <em class="italic bg-gradient-to-r from-blue-300 via-blue-400 to-cyan-400 bg-clip-text text-transparent">We Offer</em>
                    </h1>
                    <p class="mt-4 text-sm text-white/40 max-w-sm leading-relaxed">
                        Discover our activities and services — crafted for comfort, built for memory.
                    </p>

                    <div class="mt-8 flex items-center gap-7">
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-primary-400">{{ $this->properties->count() }}</div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/30 mt-0.5">Activities</div>
                        </div>
                        <div class="w-px h-10 bg-white/10"></div>
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-primary-400">{{ $this->services->count() }}</div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/30 mt-0.5">Services</div>
                        </div>
                        @if(!empty($galleryImages))
                            <div class="w-px h-10 bg-white/10"></div>
                            <div class="text-center">
                                <div class="font-display text-4xl font-medium text-primary-400">{{ count($galleryImages) }}</div>
                                <div class="text-[10px] tracking-[0.18em] uppercase text-white/30 mt-0.5">Photos</div>
                            </div>
                        @endif
                    </div>
                </div>

                @if(!empty($galleryImages))
                    <div class="flex flex-col items-start lg:items-end gap-4">
                        <div class="flex gap-1.5 cursor-pointer group" @click="openGallery()">
                            @foreach(array_slice($galleryImages, 0, 4) as $i => $img)
                                <div class="overflow-hidden rounded-xl w-16 h-20 md:w-20 md:h-24 {{ $i === 0 ? 'rounded-l-2xl' : '' }} {{ $i === 3 ? 'rounded-r-2xl' : '' }}">
                                    <img src="{{ asset('storage/'.$img) }}"
                                         class="w-full h-full object-cover brightness-75 group-hover:brightness-90 group-hover:scale-110 transition duration-700"
                                         alt="" loading="lazy">
                                </div>
                            @endforeach
                            @if(count($galleryImages) > 4)
                                <div class="overflow-hidden rounded-r-2xl w-16 h-20 md:w-20 md:h-24 bg-black/60 flex items-center justify-center border border-white/10 cursor-pointer hover:bg-black/40 transition">
                                    <span class="text-white/70 text-xs font-bold">+{{ count($galleryImages) - 4 }}</span>
                                </div>
                            @endif
                        </div>

                        <button @click="openGallery()"
                                class="gallery-pill inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full
                                       bg-white/[0.07] border border-white/18 text-white/65
                                       hover:bg-primary-500/18 hover:border-primary-400/45 hover:text-white
                                       text-xs font-semibold uppercase tracking-widest transition-all shadow-lg shadow-black/20 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                            <svg class="w-3.5 h-3.5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            View All Photos
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Main Content: Activities and Services --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-12 md:py-16 space-y-16">

        {{-- Activities Section --}}
        <div id="activities">
            <div class="reveal mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Explore & Book</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-gray-900 dark:text-white">
                    Available <em class="italic text-primary-600 dark:text-primary-400">Activities</em>
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-md">All activities are listed below. Select your dates to book.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($this->properties as $property)
                    @php $images = $property->images->pluck('image_path')->toArray(); @endphp

                    <article class="group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col reveal"
                             wire:key="prop-{{ $property->id }}"
                             x-data="{ imgIndex: 0, images: {{ Js::from($images) }} }">

                        <div class="relative overflow-hidden aspect-[16/10]">
                            <template x-if="images.length > 0">
                                <img :src="'/storage/' + images[imgIndex]"
                                     :alt="'{{ addslashes($property->name) }} photo ' + (imgIndex+1)"
                                     class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                     loading="lazy">
                            </template>
                            <template x-if="images.length === 0">
                                <div class="w-full h-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 dark:text-gray-500">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            </template>

                            <template x-if="images.length > 1">
                                <div>
                                    <button @click.prevent="imgIndex=(imgIndex-1+images.length)%images.length"
                                            class="absolute left-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-black/60 border border-white/15 flex items-center justify-center text-white/70 hover:bg-black/80 hover:text-white transition-all opacity-0 group-hover:opacity-100 focus:opacity-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <button @click.prevent="imgIndex=(imgIndex+1)%images.length"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-black/60 border border-white/15 flex items-center justify-center text-white/70 hover:bg-black/80 hover:text-white transition-all opacity-0 group-hover:opacity-100 focus:opacity-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                    <div class="absolute bottom-2.5 left-0 right-0 flex justify-center gap-1">
                                        <template x-for="(img,i) in images" :key="i">
                                            <div @click.prevent="imgIndex=i"
                                                 class="rounded-full transition-all cursor-pointer active:scale-90"
                                                 :class="i===imgIndex ? 'w-4 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/40'">
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <div class="absolute top-3 left-3 flex flex-col gap-1.5 pointer-events-none">
                                @if($property->propertyType)
                                    <span class="bg-black/65 backdrop-blur text-[10px] font-bold text-primary-300 px-2.5 py-1 rounded-full tracking-wider uppercase">
                                        {{ $property->propertyType->name }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-display text-xl font-semibold text-gray-900 dark:text-white mb-2 leading-snug group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">
                                {{ $property->name }}
                            </h3>
                            @if($property->description)
                                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2 mb-4 flex-1 leading-relaxed">{{ $property->description }}</p>
                            @else
                                <div class="flex-1"></div>
                            @endif

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between pt-4 mt-auto border-t border-gray-200 dark:border-gray-700 gap-3">
                                <div>
                                    <span class="font-display text-2xl font-semibold text-primary-600 dark:text-primary-400">₱{{ number_format($property->price, 2) }}</span>
                                    <span class="text-[10px] text-gray-500 dark:text-gray-400 ml-1 uppercase tracking-wider">/ unit</span>
                                </div>
                                @auth
                                    <a href="{{ route('booking.create', ['publicproperty' => $property->id]) }}" wire:navigate
                                       class="block w-full sm:w-auto text-center py-2 px-5 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-[10px] font-bold uppercase tracking-widest transition-all shadow-lg shadow-primary-500/20 hover:-translate-y-0.5 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Book Now
                                    </a>
                                @else
                                    <a href="{{ route('login', ['redirect' => url()->current()]) }}"
                                       class="block w-full sm:w-auto text-center py-2 px-5 rounded-full border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-[10px] font-bold uppercase tracking-widest transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                                        Login to Book
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full text-center py-20 rounded-3xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="font-display text-xl italic text-gray-500 dark:text-gray-400">No activities listed yet.</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Check back soon — new activities may be added.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Services Section --}}
        @if($this->services->isNotEmpty())
        <div id="services" class="pt-8 border-t border-gray-200 dark:border-gray-700">
            <div class="reveal mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Enhance Your Visit</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-gray-900 dark:text-white">
                    Add-on <em class="italic text-primary-600 dark:text-primary-400">Services</em>
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-md">Extras available to elevate your experience.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($this->services as $service)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col reveal"
                         wire:key="svc-{{ $service->id }}">

                        <div class="w-11 h-11 rounded-2xl bg-primary-50 dark:bg-primary-500/10 border border-primary-200 dark:border-primary-500/20 flex items-center justify-center text-primary-600 dark:text-primary-400 mb-4 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $service->icon_path }}"/>
                            </svg>
                        </div>

                        <h3 class="font-display text-lg font-semibold text-gray-900 dark:text-white mb-2 leading-snug">{{ $service->name }}</h3>

                        <div class="flex-1 mb-5"></div>

                        <div class="flex items-center justify-between pt-4 mt-auto border-t border-gray-200 dark:border-gray-700">
                            <span class="font-display text-2xl font-semibold text-gray-900 dark:text-white">₱{{ number_format($service->price, 2) }}</span>
                            @auth
                                <span class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-full px-3 py-1">
                                    Add at checkout
                                </span>
                            @else
                                <a href="{{ route('login', ['redirect' => url()->current()]) }}"
                                   class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-widest text-primary-600 hover:text-primary-700 transition-colors active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                                    Login to add
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            @endauth
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- Gallery Footer Teaser --}}
    @if(!empty($galleryImages))
        <div class="h-px bg-gradient-to-r from-transparent via-gray-200 dark:via-gray-700 to-transparent mx-6 md:mx-16"></div>
        <section class="max-w-7xl mx-auto px-6 md:px-16 py-14 reveal flex flex-col sm:flex-row items-center justify-between gap-5">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-4 h-px bg-primary-600"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Photo Gallery</span>
                </div>
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    Explore all <span class="text-gray-900 dark:text-white font-semibold">{{ count($galleryImages) }} photos</span> of {{ $tenant->name }}
                    @if($gallerySubtitle) — <em class="italic text-gray-500 dark:text-gray-400">{{ $gallerySubtitle }}</em> @endif
                </p>
            </div>
            <button @click="openGallery()"
                    class="shrink-0 inline-flex items-center gap-2.5 px-7 py-3 rounded-full
                           bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-widest
                           transition-all shadow-lg shadow-primary-500/20 hover:-translate-y-0.5 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Open Gallery
            </button>
        </section>
    @endif

    {{-- Sticky Book Bar --}}
    <div class="fixed bottom-0 left-0 right-0 z-[900] bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 shadow-lg lg:hidden transition-transform duration-300 pb-safe"
         :class="stickyVisible ? 'translate-y-0' : 'translate-y-full'">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between gap-4">
            <div class="flex-1 min-w-0">
                <p class="text-gray-900 dark:text-white font-semibold text-sm truncate">{{ $tenant->name }}</p>
                <p class="text-gray-500 dark:text-gray-400 text-xs">
                    @if($this->properties->count())
                        From ₱{{ number_format($this->properties->min('price'), 0) }} / unit
                    @else
                        View offerings above
                    @endif
                </p>
            </div>
            @if($this->properties->count() > 0)
                <button @click="document.getElementById('activities').scrollIntoView({behavior:'smooth'})"
                        class="shrink-0 px-6 py-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-widest transition shadow-lg shadow-primary-500/30 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    View Activities
                </button>
            @endif
        </div>
    </div>

    @script
    <script>
        document.addEventListener('livewire:navigated', () => {
            const obs = new IntersectionObserver(entries => {
                entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); obs.unobserve(e.target); } });
            }, { threshold:.08 });
            document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
        });
    </script>
    @endscript
</div>