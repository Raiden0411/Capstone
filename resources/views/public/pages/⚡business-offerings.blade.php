<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Storage;

new
#[Layout('layouts.app')]
#[Title('What We Offer')]
class extends Component
{
    public Tenant $tenant;
    public string $activeTab = 'accommodations';
    public ?string $coverPhoto = null;

    // ── Gallery fields ──
    public array  $galleryImages   = [];
    public string $galleryTitle    = '';
    public string $gallerySubtitle = '';

    public function mount($slug)
    {
        $this->tenant = Tenant::where('slug', $slug)->firstOrFail();

        $settings = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('key', ['spot_cover', 'business_gallery', 'gallery_title', 'gallery_subtitle'])
            ->get()
            ->pluck('value', 'key');

        $this->coverPhoto      = $settings['spot_cover']       ?? null;
        $this->galleryImages   = $settings['business_gallery'] ?? [];
        $this->galleryTitle    = $settings['gallery_title']    ?? '';
        $this->gallerySubtitle = $settings['gallery_subtitle'] ?? '';
    }

    #[Computed]
    public function properties()
    {
        return $this->tenant->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where('status', 'available')
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'images'       => fn($q) => $q->withoutGlobalScope(TenantScope::class),
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
            ->orderBy('name')
            ->get();
    }

    public function getGalleryTitleHtml(): string
    {
        if (empty($this->galleryTitle)) return '';
        return '<em>' . str_replace(' ', '</em> <em>', e($this->galleryTitle)) . '</em>';
    }
};
?>

@push('styles')
<style>
    /* ── Entrance animations ── */
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(24px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to   { opacity: 1; }
    }
    @keyframes scaleIn {
        from { opacity: 0; transform: scale(0.96); }
        to   { opacity: 1; transform: scale(1); }
    }
    @keyframes floatPulse {
        0%, 100% { transform: translateY(0px) scale(1); }
        50%       { transform: translateY(-4px) scale(1.02); }
    }

    .anim-hero-title  { animation: fadeUp  0.8s cubic-bezier(0.16,1,0.3,1) both; }
    .anim-hero-meta   { animation: fadeUp  0.8s cubic-bezier(0.16,1,0.3,1) 0.15s both; }
    .anim-hero-stats  { animation: fadeUp  0.8s cubic-bezier(0.16,1,0.3,1) 0.28s both; }
    .anim-hero-ctas   { animation: fadeUp  0.8s cubic-bezier(0.16,1,0.3,1) 0.38s both; }
    .anim-section     { animation: fadeUp  0.7s cubic-bezier(0.16,1,0.3,1) both; }
    .anim-fade        { animation: fadeIn  0.5s ease both; }
    .anim-scale-in    { animation: scaleIn 0.4s cubic-bezier(0.16,1,0.3,1) both; }

    /* ── Property cards ── */
    .prop-card {
        background: rgba(255,255,255,0.04);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 20px;
        overflow: hidden;
        transition: transform 0.4s cubic-bezier(0.16,1,0.3,1),
                    border-color 0.3s ease,
                    box-shadow 0.4s ease;
        will-change: transform;
    }
    .prop-card:hover {
        transform: translateY(-6px);
        border-color: rgba(var(--color-brand-400), 0.35);
        box-shadow: 0 24px 60px -12px rgba(0,0,0,0.55),
                    0 0 0 1px rgba(var(--color-brand-500), 0.15);
    }
    .prop-card-image { position: relative; overflow: hidden; aspect-ratio: 16/10; }
    .prop-card-image img {
        width: 100%; height: 100%; object-fit: cover;
        transition: transform 0.8s cubic-bezier(0.16,1,0.3,1),
                    filter 0.5s ease;
        filter: brightness(0.9) saturate(1.1);
    }
    .prop-card:hover .prop-card-image img {
        transform: scale(1.07);
        filter: brightness(1.05) saturate(1.2);
    }

    /* ── Service cards ── */
    .svc-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.07);
        border-radius: 16px;
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
        transition: transform 0.35s cubic-bezier(0.16,1,0.3,1),
                    border-color 0.3s ease,
                    background 0.3s ease;
    }
    .svc-card::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, rgba(var(--color-brand-500), 0.06) 0%, transparent 60%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .svc-card:hover { transform: translateY(-4px); border-color: rgba(var(--color-brand-400),0.25); }
    .svc-card:hover::before { opacity: 1; }

    /* ── Gallery modal ── */
    .gallery-modal-overlay {
        position: fixed;
        inset: 0;
        z-index: 99999 !important;
        background: rgba(0,0,0,0.97);
        backdrop-filter: blur(20px) saturate(0.5);
        display: flex;
        flex-direction: column;
        padding-top: 68px;
        box-sizing: border-box;
    }
    .gallery-masonry {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        grid-auto-rows: 160px;
        gap: 6px;
    }
    .gm-item:nth-child(1)   { grid-column: span 5; grid-row: span 3; }
    .gm-item:nth-child(2)   { grid-column: span 4; grid-row: span 2; }
    .gm-item:nth-child(3)   { grid-column: span 3; grid-row: span 2; }
    .gm-item:nth-child(4)   { grid-column: span 3; grid-row: span 2; }
    .gm-item:nth-child(5)   { grid-column: span 4; grid-row: span 3; }
    .gm-item:nth-child(6)   { grid-column: span 5; grid-row: span 2; }
    .gm-item:nth-child(n+7) { grid-column: span 3; grid-row: span 2; }

    /* ── Gallery hero strip (teaser) ── */
    .gallery-strip {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        grid-template-rows: 220px 180px;
        gap: 4px;
        border-radius: 16px;
        overflow: hidden;
    }
    .gallery-strip > *:first-child {
        grid-column: 1 / 2;
        grid-row: 1 / 3;
    }

    /* ── Floating gallery button ── */
    .gallery-pill {
        animation: floatPulse 3s ease-in-out infinite;
    }

    /* ── Tab pill nav ── */
    .tab-pill {
        padding: 0.5rem 1.25rem;
        border-radius: 9999px;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        transition: all 0.25s ease;
        cursor: pointer;
        border: 1px solid rgba(255,255,255,0.1);
        color: rgba(255,255,255,0.45);
        background: transparent;
    }
    .tab-pill.active,
    .tab-pill:hover {
        background: rgba(var(--color-brand-500), 0.18);
        border-color: rgba(var(--color-brand-400), 0.4);
        color: rgb(var(--color-brand-400));
    }
    .tab-pill.active {
        box-shadow: 0 0 16px rgba(var(--color-brand-500), 0.25);
    }

    /* ── Section divider ── */
    .section-rule {
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1) 30%, rgba(255,255,255,0.1) 70%, transparent);
    }

    /* ── Lightbox within gallery modal ── */
    .lb-inner {
        position: fixed;
        inset: 0;
        z-index: 999999 !important;
        background: rgba(0,0,0,0.98);
        display: flex;
        align-items: center;
        justify-content: center;
        animation: fadeIn 0.2s ease;
    }

    @media (max-width: 768px) {
        .gallery-masonry {
            grid-template-columns: repeat(2, 1fr);
            grid-auto-rows: 130px;
        }
        .gm-item { grid-column: span 1 !important; grid-row: span 1 !important; }
        .gallery-strip {
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 160px 120px;
        }
        .gallery-strip > *:first-child { grid-column: 1 / 3; grid-row: 1; }
    }
</style>
@endpush

<div
    class="relative z-10 min-h-screen"
    x-data="{
        galleryOpen: false,
        lightboxSrc: null,
        lightboxIndex: 0,
        galleryImages: {{ Js::from(collect($galleryImages)->map(fn($p) => Storage::url($p))->values()) }},

        openGallery() {
            this.galleryOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeGallery() {
            this.galleryOpen = false;
            this.lightboxSrc = null;
            document.body.style.overflow = '';
        },
        openLightbox(src, idx) {
            this.lightboxSrc = src;
            this.lightboxIndex = idx;
        },
        prevImage() {
            this.lightboxIndex = (this.lightboxIndex - 1 + this.galleryImages.length) % this.galleryImages.length;
            this.lightboxSrc = this.galleryImages[this.lightboxIndex];
        },
        nextImage() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.galleryImages.length;
            this.lightboxSrc = this.galleryImages[this.lightboxIndex];
        }
    }"
    @keydown.escape.window="lightboxSrc ? lightboxSrc = null : closeGallery()"
    @keydown.arrow-left.window="lightboxSrc && prevImage()"
    @keydown.arrow-right.window="lightboxSrc && nextImage()"
>

    {{-- ══════════════════════════════════════════════
         GALLERY MODAL (full-screen overlay)
    ══════════════════════════════════════════════ --}}
    <div x-show="galleryOpen" x-cloak
         class="gallery-modal-overlay"
         x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        {{-- Modal header --}}
        <div class="flex-none flex items-center justify-between px-8 py-5 border-b border-white/8">
            <div>
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="w-3 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Photo Gallery</span>
                </div>
                <h2 class="font-display text-xl font-semibold text-white">
                    {{ $tenant->name }}
                    @if($galleryTitle)
                        <span class="text-white/40 font-normal mx-2">·</span>
                        <em class="italic text-brand-400 text-lg">{{ $galleryTitle }}</em>
                    @endif
                </h2>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-white/30 tabular-nums hidden sm:block">
                    {{ count($galleryImages) }} photos
                </span>
                <button @click="closeGallery()"
                        class="w-10 h-10 rounded-full border border-white/15 flex items-center justify-center text-white/50 hover:text-white hover:border-white/40 hover:bg-white/8 transition-all">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Gallery grid --}}
        <div class="flex-1 overflow-y-auto p-6 md:p-8">
            @if(!empty($galleryImages))
                <div class="gallery-masonry max-w-7xl mx-auto">
                    @foreach($galleryImages as $index => $imagePath)
                        <div wire:key="gm-{{ $index }}"
                             class="gm-item relative overflow-hidden rounded-xl cursor-pointer group"
                             @click="openLightbox('{{ Storage::url($imagePath) }}', {{ $index }})"
                             style="animation: scaleIn 0.4s cubic-bezier(0.16,1,0.3,1) {{ $index * 40 }}ms both">
                            <img src="{{ Storage::url($imagePath) }}"
                                 class="w-full h-full object-cover brightness-90 group-hover:brightness-105 group-hover:scale-105 transition duration-700"
                                 alt="{{ $tenant->name }} photo {{ $index + 1 }}" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <div class="absolute bottom-3 right-3 w-8 h-8 rounded-full bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center text-white">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-white/30">
                    <svg class="w-12 h-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm">No photos yet</p>
                </div>
            @endif
        </div>

        {{-- Gallery subtitle strip --}}
        @if($gallerySubtitle)
            <div class="flex-none border-t border-white/8 px-8 py-3 text-xs text-white/30 italic">
                {{ $gallerySubtitle }}
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════
         LIGHTBOX (within gallery modal)
    ══════════════════════════════════════════════ --}}
    <div x-show="lightboxSrc" x-cloak class="lb-inner" @click.self="lightboxSrc = null">
        <button @click="prevImage()"
                class="absolute left-4 md:left-8 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/8 border border-white/15 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/15 transition-all z-10">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="relative max-w-[88vw] max-h-[88vh]">
            <img :src="lightboxSrc" class="max-w-full max-h-[88vh] object-contain rounded-lg shadow-2xl">
            <div class="absolute bottom-0 left-0 right-0 flex justify-between items-center px-4 py-3 bg-gradient-to-t from-black/80 to-transparent rounded-b-lg">
                <span class="text-xs text-white/50" x-text="(lightboxIndex + 1) + ' / ' + galleryImages.length"></span>
                <button @click="lightboxSrc = null" class="text-xs text-white/40 hover:text-white uppercase tracking-widest transition">✕ Close</button>
            </div>
        </div>
        <button @click="nextImage()"
                class="absolute right-4 md:right-8 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/8 border border-white/15 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/15 transition-all z-10">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- ══════════════════════════════════════════════
         HERO
    ══════════════════════════════════════════════ --}}
    <section class="relative min-h-[72vh] flex items-end overflow-hidden pb-16 md:pb-20">

        {{-- Background --}}
        @if($coverPhoto)
            <img src="{{ Storage::url($coverPhoto) }}"
                 class="absolute inset-0 w-full h-full object-cover scale-105"
                 style="filter: brightness(0.38) saturate(1.2);" alt="">
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-neutral-950 via-neutral-900 to-neutral-950"></div>
        @endif

        {{-- Layered gradients for depth --}}
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-transparent to-transparent"></div>

        {{-- Noise grain --}}
        <div class="absolute inset-0 opacity-[0.035]"
             style="background-image: url(\"data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E\"); background-size: 180px;"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16 w-full">

            {{-- Back link --}}
            <div class="anim-hero-meta mb-8">
                <a href="{{ route('tenant.show', $tenant->slug) }}" wire:navigate
                   class="inline-flex items-center gap-2 text-[10px] tracking-[0.2em] uppercase text-white/35 hover:text-brand-400 transition-colors group">
                    <svg class="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                    Back to {{ $tenant->name }}
                </a>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">

                {{-- Left: Headline --}}
                <div class="max-w-2xl">
                    <div class="anim-hero-meta flex items-center gap-2 mb-4">
                        <span class="w-6 h-px bg-brand-500"></span>
                        <span class="text-[10px] tracking-[0.25em] uppercase text-brand-400 font-bold">Offerings</span>
                    </div>
                    <h1 class="anim-hero-title font-display text-5xl md:text-7xl font-semibold text-white leading-[0.9] tracking-tight">
                        What<br>
                        <em class="italic bg-gradient-to-r from-brand-300 via-brand-400 to-cyan-400 bg-clip-text text-transparent">We Offer</em>
                    </h1>
                    <p class="anim-hero-meta mt-4 text-sm text-white/45 max-w-sm leading-relaxed">
                        Discover our spaces and services — crafted for comfort, built for memory.
                    </p>

                    {{-- Stats --}}
                    <div class="anim-hero-stats mt-8 flex items-center gap-6">
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-brand-400 tabular-nums">{{ $this->properties->count() }}</div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-0.5">Rooms</div>
                        </div>
                        <div class="w-px h-10 bg-white/10"></div>
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-brand-400 tabular-nums">{{ $this->services->count() }}</div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-0.5">Services</div>
                        </div>
                        @if(!empty($galleryImages))
                            <div class="w-px h-10 bg-white/10"></div>
                            <div class="text-center">
                                <div class="font-display text-4xl font-medium text-brand-400 tabular-nums">{{ count($galleryImages) }}</div>
                                <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-0.5">Photos</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Right: Gallery teaser + CTA --}}
                @if(!empty($galleryImages))
                    <div class="anim-hero-ctas flex flex-col items-start lg:items-end gap-5">

                        {{-- Mini gallery preview strip --}}
                        <div class="gallery-strip w-full lg:w-80 opacity-80 hover:opacity-100 transition-opacity cursor-pointer"
                             @click="openGallery()">
                            @foreach(array_slice($galleryImages, 0, 5) as $i => $img)
                                <div class="overflow-hidden {{ $i === 0 ? 'rounded-l-xl' : '' }} {{ $i === 4 ? 'rounded-r-xl' : '' }}">
                                    <img src="{{ Storage::url($img) }}"
                                         class="w-full h-full object-cover hover:scale-110 transition duration-700 brightness-75"
                                         alt="" loading="lazy">
                                </div>
                            @endforeach
                        </div>

                        {{-- Gallery open button --}}
                        <button @click="openGallery()"
                                class="gallery-pill inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full
                                       bg-white/8 border border-white/20 text-white/70
                                       hover:bg-brand-500/20 hover:border-brand-400/50 hover:text-white
                                       text-xs font-semibold uppercase tracking-widest transition-all duration-300
                                       shadow-lg shadow-black/20">
                            <svg class="w-3.5 h-3.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            View All Photos
                        </button>
                    </div>
                @endif

            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════════════════
         TAB NAV (pill style, floating)
    ══════════════════════════════════════════════ --}}
    <div class="sticky top-16 z-20 py-3 bg-black/60 backdrop-blur-xl border-b border-white/6">
        <div class="max-w-7xl mx-auto px-6 md:px-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <button wire:click="$set('activeTab','accommodations')"
                        class="tab-pill {{ $activeTab === 'accommodations' ? 'active' : '' }}">
                    Accommodations
                    <span class="ml-1.5 text-[10px] opacity-70">{{ $this->properties->count() }}</span>
                </button>
                <button wire:click="$set('activeTab','services')"
                        class="tab-pill {{ $activeTab === 'services' ? 'active' : '' }}">
                    Services
                    <span class="ml-1.5 text-[10px] opacity-70">{{ $this->services->count() }}</span>
                </button>
            </div>

            {{-- Quick gallery trigger in nav --}}
            @if(!empty($galleryImages))
                <button @click="openGallery()"
                        class="hidden sm:inline-flex items-center gap-1.5 text-[10px] tracking-widest uppercase text-white/35 hover:text-brand-400 transition-colors font-semibold">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Gallery
                </button>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════════════════ --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-12 md:py-16">

        {{-- ── ACCOMMODATIONS ── --}}
        @if($activeTab === 'accommodations')
            <div class="anim-section mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Stay & Explore</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-white">
                    Available <em class="italic text-brand-400">Accommodations</em>
                </h2>
                <p class="mt-2 text-sm text-white/40 max-w-md">All rooms shown are immediately bookable for your stay.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($this->properties as $index => $property)
                    <div class="prop-card flex flex-col" wire:key="prop-{{ $property->id }}"
                         style="animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) {{ $index * 80 }}ms both">

                        {{-- Image area --}}
                        <div class="prop-card-image">
                            @if($property->images->isNotEmpty())
                                <img src="{{ asset('storage/'.$property->images->first()->image_path) }}"
                                     alt="{{ $property->name }}">
                            @else
                                <div class="w-full h-full bg-white/4 flex items-center justify-center text-white/20">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif

                            {{-- Badges --}}
                            <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                                @if($property->propertyType)
                                    <span class="bg-black/65 backdrop-blur-sm text-[10px] font-bold text-brand-300 px-2.5 py-1 rounded-full tracking-wider uppercase">
                                        {{ $property->propertyType->name }}
                                    </span>
                                @endif
                            </div>
                            <span class="absolute top-3 right-3 bg-black/65 backdrop-blur-sm text-[10px] font-bold text-emerald-300 px-2.5 py-1 rounded-full flex items-center gap-1 uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 shadow-[0_0_6px_rgba(52,211,153,0.7)]"></span>
                                Available
                            </span>

                            {{-- Image count indicator if multiple --}}
                            @if($property->images->count() > 1)
                                <span class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-sm text-[10px] text-white/60 px-2 py-0.5 rounded-full flex items-center gap-1">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/></svg>
                                    {{ $property->images->count() }}
                                </span>
                            @endif
                        </div>

                        {{-- Card body --}}
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-display text-xl font-semibold text-white mb-2 leading-snug">
                                {{ $property->name }}
                            </h3>
                            @if($property->description)
                                <p class="text-sm text-white/50 line-clamp-2 mb-4 flex-1 leading-relaxed">
                                    {{ $property->description }}
                                </p>
                            @else
                                <div class="flex-1"></div>
                            @endif

                            {{-- Price + CTA (fixed – visible price and clearer night label) --}}
                            <div class="flex items-center justify-between pt-4 mt-auto"
                                 style="border-top: 1px solid rgba(255,255,255,0.08)">
                                <div>
                                    <span class="font-display text-2xl font-semibold text-brand-400">
                                        ₱{{ number_format($property->price, 2) }}
                                    </span>
                                    <span class="text-[10px] text-white/60 ml-1 uppercase tracking-wider font-medium">/ night</span>
                                </div>
                                @auth
                                    <a href="{{ route('booking.create', ['publicproperty' => $property->id]) }}"
                                       class="py-2 px-5 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-[10px] font-bold uppercase tracking-widest transition-all shadow-lg shadow-brand-600/25 hover:shadow-brand-500/40 hover:-translate-y-0.5">
                                        Reserve
                                    </a>
                                @else
                                    <a href="{{ route('login', ['redirect' => url()->current()]) }}"
                                       class="py-2 px-5 rounded-full border border-white/15 hover:bg-white/8 text-white/60 hover:text-white text-[10px] font-bold uppercase tracking-widest transition-all">
                                        Login to Book
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-20"
                         style="border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; background: rgba(255,255,255,0.02);">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="font-display text-xl italic text-white/35">No accommodations listed yet.</h3>
                        <p class="text-xs text-white/25 mt-2 tracking-wide">Check back soon — new rooms may be added.</p>
                    </div>
                @endforelse
            </div>
        @endif

        {{-- ── SERVICES ── --}}
        @if($activeTab === 'services')
            <div class="anim-section mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Enhance Your Stay</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-white">
                    Add‑on <em class="italic text-brand-400">Services</em>
                </h2>
                <p class="mt-2 text-sm text-white/40 max-w-md">Extras available to make your stay even more special.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse($this->services as $index => $service)
                    <div class="svc-card flex flex-col" wire:key="svc-{{ $service->id }}"
                         style="animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) {{ $index * 70 }}ms both">

                        {{-- Icon --}}
                        <div class="w-10 h-10 rounded-xl bg-brand-500/15 border border-brand-400/20 flex items-center justify-center text-brand-400 mb-4 flex-none">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </div>

                        <h3 class="font-display text-lg font-semibold text-white mb-2 leading-snug">{{ $service->name }}</h3>

                        @if($service->description)
                            <p class="text-sm text-white/50 flex-1 mb-5 leading-relaxed">{{ $service->description }}</p>
                        @else
                            <div class="flex-1 mb-5"></div>
                        @endif

                        <div class="flex items-center justify-between pt-4 mt-auto"
                             style="border-top: 1px solid rgba(255,255,255,0.07)">
                            <span class="font-display text-2xl font-semibold text-white">
                                ₱{{ number_format($service->price, 2) }}
                            </span>
                            @auth
                                <span class="text-[10px] font-bold uppercase tracking-widest text-white/30 bg-white/5 border border-white/8 rounded-full px-3 py-1">
                                    Add at checkout
                                </span>
                            @else
                                <a href="{{ route('login', ['redirect' => url()->current()]) }}"
                                   class="text-[10px] font-bold uppercase tracking-widest text-brand-400 hover:text-brand-300 transition-colors">
                                    Login to add →
                                </a>
                            @endauth
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-20"
                         style="border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; background: rgba(255,255,255,0.02);">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <h3 class="font-display text-xl italic text-white/35">No services available yet.</h3>
                        <p class="text-xs text-white/25 mt-2 tracking-wide">Check back soon — new add‑ons may appear.</p>
                    </div>
                @endforelse
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════════════════
         GALLERY SECTION FOOTER TEASER
    ══════════════════════════════════════════════ --}}
    @if(!empty($galleryImages))
        <div class="section-rule mx-6 md:mx-16 mb-0"></div>
        <section class="max-w-7xl mx-auto px-6 md:px-16 py-14 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-4 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Photo Gallery</span>
                </div>
                <p class="text-white/50 text-sm">
                    Explore all <span class="text-white font-semibold">{{ count($galleryImages) }} photos</span> of {{ $tenant->name }}
                    @if($gallerySubtitle) — <em class="italic text-white/40">{{ $gallerySubtitle }}</em> @endif
                </p>
            </div>
            <button @click="openGallery()"
                    class="flex-none inline-flex items-center gap-2.5 px-7 py-3 rounded-full
                           bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold uppercase tracking-widest
                           transition-all shadow-xl shadow-brand-600/25 hover:shadow-brand-500/35 hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Open Gallery
            </button>
        </section>
    @endif

</div>