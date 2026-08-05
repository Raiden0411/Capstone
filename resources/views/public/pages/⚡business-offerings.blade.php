{{-- resources/views/public/pages/business/offerings.blade.php --}}
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
    public string $activeTab  = 'accommodations';
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
        $this->galleryTitle    = $settings['gallery_title']    ?? '';
        $this->gallerySubtitle = $settings['gallery_subtitle'] ?? '';
    }

    #[Computed]
    public function properties()
    {
        return $this->tenant->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)->where('status', 'available')
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'images'       => fn($q) => $q->withoutGlobalScope(TenantScope::class),
            ])
            ->orderBy('name')->get();
    }

    #[Computed]
    public function services()
    {
        return $this->tenant->services()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->orderBy('name')->get();
    }
};
?>

@push('styles')
<style>
/* ── Animations ── */
@keyframes fadeUp   { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
@keyframes scaleIn  { from{opacity:0;transform:scale(0.95)}      to{opacity:1;transform:scale(1)} }
@keyframes fadeIn   { from{opacity:0}                             to{opacity:1} }
@keyframes shimmer  { 0%{background-position:-200% 0} 100%{background-position:200% 0} }

/* ── Scroll reveal ── */
.reveal { opacity:0; transform:translateY(22px);
          transition: opacity .65s cubic-bezier(.16,1,.3,1), transform .65s cubic-bezier(.16,1,.3,1); }
.reveal.in { opacity:1; transform:translateY(0); }

/* ── Property cards ── */
.prop-card {
    background: rgba(255,255,255,0.035);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 22px; overflow:hidden;
    transition: transform .4s cubic-bezier(.16,1,.3,1),
                border-color .3s ease, box-shadow .4s ease;
}
.prop-card:hover {
    transform: translateY(-7px);
    border-color: rgba(34,197,94,.3);
    box-shadow: 0 28px 60px -16px rgba(0,0,0,.55), 0 0 0 1px rgba(34,197,94,.12);
}
.prop-card-img { position:relative; overflow:hidden; aspect-ratio:16/10; }
.prop-card-img img { width:100%;height:100%;object-fit:cover; transition:transform .8s cubic-bezier(.16,1,.3,1); }
.prop-card:hover .prop-card-img img { transform:scale(1.06); }

/* ── Service cards ── */
.svc-card {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.07);
    border-radius: 18px; padding:1.5rem;
    transition: transform .35s cubic-bezier(.16,1,.3,1), border-color .3s, background .3s;
    position:relative; overflow:hidden;
}
.svc-card::after {
    content:''; position:absolute; inset:0;
    background:linear-gradient(135deg,rgba(34,197,94,.07) 0%,transparent 65%);
    opacity:0; transition:opacity .3s;
}
.svc-card:hover { transform:translateY(-4px); border-color:rgba(34,197,94,.22); }
.svc-card:hover::after { opacity:1; }

/* ── Tab indicator ── */
.tab-track { position:relative; }
.tab-indicator {
    position:absolute; bottom:0; height:2px; border-radius:2px;
    background: linear-gradient(90deg, #22c55e, #86efac);
    transition: left .35s cubic-bezier(.68,-.55,.27,1.55), width .35s cubic-bezier(.68,-.55,.27,1.55);
}

/* ── Gallery modal ── */
.gal-overlay {
    position:fixed; inset:0; z-index:99999;
    background:rgba(0,0,0,0.97); backdrop-filter:blur(20px) saturate(.5);
    display:flex; flex-direction:column; padding-top:64px; box-sizing:border-box;
}
.gal-grid {
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    grid-auto-rows: 180px;
    gap:6px;
}
/* First item always spans 2 cols × 2 rows on larger screens */
@media(min-width:768px){
    .gal-grid { grid-template-columns: repeat(4, 1fr); }
    .gal-item:nth-child(1)  { grid-column:span 2; grid-row:span 2; }
    .gal-item:nth-child(5)  { grid-column:span 2; }
    .gal-item:nth-child(9)  { grid-column:span 2; grid-row:span 2; }
}
.gal-item img { width:100%;height:100%;object-fit:cover;transition:transform .6s ease,filter .4s ease; filter:brightness(.88); }
.gal-item:hover img { transform:scale(1.06); filter:brightness(1); }

/* ── Lightbox ── */
.lb-wrap {
    position:fixed; inset:0; z-index:999999;
    background:rgba(0,0,0,.96);
    display:flex; align-items:center; justify-content:center;
    animation:fadeIn .18s ease;
}
.lb-img { max-width:90vw; max-height:88vh; object-fit:contain; border-radius:10px; box-shadow:0 40px 80px rgba(0,0,0,.6); }
.lb-nav {
    position:absolute; top:50%; transform:translateY(-50%);
    width:44px;height:44px; border-radius:50%;
    background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12);
    display:flex;align-items:center;justify-content:center;
    color:rgba(255,255,255,.6); cursor:pointer;
    transition:all .2s;
}
.lb-nav:hover { background:rgba(255,255,255,.15); color:#fff; }

/* ── Sticky bar ── */
.sticky-book {
    position:fixed; bottom:0; left:0; right:0; z-index:900;
    background:rgba(7,20,18,0.92); backdrop-filter:blur(20px);
    border-top:1px solid rgba(255,255,255,.08);
    transform:translateY(100%); transition:transform .4s cubic-bezier(.16,1,.3,1);
    padding:12px 20px; display:flex; align-items:center; justify-content:space-between; gap:12px;
}
.sticky-book.visible { transform:translateY(0); }

/* ── Floating gallery pill ── */
@keyframes floatPill { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-4px)} }
.gallery-pill { animation: floatPill 3s ease-in-out infinite; }

/* ── Section rule ── */
.section-rule { height:1px; background:linear-gradient(90deg,transparent,rgba(255,255,255,.08) 30%,rgba(255,255,255,.08) 70%,transparent); }
</style>
@endpush

<div
    class="relative z-10 min-h-screen"
    x-data="{
        galleryOpen:    false,
        lbSrc:          null,
        lbIndex:        0,
        lbStartX:       0,
        galleryImages:  {{ Js::from(collect($galleryImages)->map(fn($p) => asset('storage/'.$p))->values()) }},
        activeTab:      '{{ $activeTab }}',
        tabIndicator:   { left: 0, width: 0 },

        openGallery()  { this.galleryOpen = true;  document.body.style.overflow='hidden'; },
        closeGallery() { this.galleryOpen = false; this.lbSrc=null; document.body.style.overflow=''; },

        openLb(src,idx) { this.lbSrc=src; this.lbIndex=idx; },
        prevLb() { this.lbIndex=(this.lbIndex-1+this.galleryImages.length)%this.galleryImages.length; this.lbSrc=this.galleryImages[this.lbIndex]; },
        nextLb() { this.lbIndex=(this.lbIndex+1)%this.galleryImages.length; this.lbSrc=this.galleryImages[this.lbIndex]; },

        /* swipe support */
        touchStart(e) { this.lbStartX = e.changedTouches[0].clientX; },
        touchEnd(e)   {
            const dx = e.changedTouches[0].clientX - this.lbStartX;
            if (Math.abs(dx) > 50) { dx < 0 ? this.nextLb() : this.prevLb(); }
        },

        updateTabIndicator() {
            const active = this.\$refs.tabNav?.querySelector('[data-active=\"true\"]');
            if (!active) return;
            this.tabIndicator = { left: active.offsetLeft+'px', width: active.offsetWidth+'px' };
        },

        init() {
            this.\$nextTick(() => this.updateTabIndicator());
        },

        /* scroll reveal */
        setupReveal() {
            const obs = new IntersectionObserver(entries => {
                entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); obs.unobserve(e.target); } });
            }, { threshold:.08 });
            document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
        },

        /* sticky book bar */
        stickyVisible: false,
        setupSticky() {
            const hero = document.getElementById('offerings-hero');
            if (!hero) return;
            const obs = new IntersectionObserver(([e]) => { this.stickyVisible = !e.isIntersecting; }, { threshold:.1 });
            obs.observe(hero);
        },
    }"
    x-init="setupReveal(); setupSticky();"
    @keydown.escape.window="lbSrc ? lbSrc=null : closeGallery()"
    @keydown.arrow-left.window="lbSrc && prevLb()"
    @keydown.arrow-right.window="lbSrc && nextLb()"
>

    {{-- ══════════════════════════════════
         GALLERY MODAL
    ══════════════════════════════════ --}}
    <div x-show="galleryOpen" x-cloak class="gal-overlay"
         x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        {{-- Header --}}
        <div class="flex-none flex items-center justify-between px-6 md:px-10 py-4 border-b border-white/[0.07]">
            <div>
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="w-3 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Photo Gallery</span>
                </div>
                <h2 class="font-display text-lg font-semibold text-white">
                    {{ $tenant->name }}
                    @if($galleryTitle)
                        <span class="text-white/30 mx-2 font-normal">·</span>
                        <em class="italic text-brand-400 text-base font-normal">{{ $galleryTitle }}</em>
                    @endif
                </h2>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-white/25 hidden sm:block">{{ count($galleryImages) }} photos</span>
                <button @click="closeGallery()"
                        class="w-9 h-9 rounded-full border border-white/12 flex items-center justify-center text-white/40 hover:text-white hover:border-white/35 hover:bg-white/[0.07] transition-all"
                        aria-label="Close gallery">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Grid --}}
        <div class="flex-1 overflow-y-auto p-4 md:p-6">
            @if(!empty($galleryImages))
                <div class="gal-grid max-w-7xl mx-auto">
                    @foreach($galleryImages as $idx => $imgPath)
                        <div class="gal-item relative overflow-hidden rounded-xl cursor-pointer group"
                             wire:key="gal-{{ $idx }}"
                             @click="openLb('{{ asset('storage/'.$imgPath) }}', {{ $idx }})"
                             style="animation: scaleIn .4s cubic-bezier(.16,1,.3,1) {{ $idx * 35 }}ms both">
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

    {{-- ══════════════════════════════════
         LIGHTBOX
    ══════════════════════════════════ --}}
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
                <button @click="lbSrc=null" class="text-[10px] text-white/35 hover:text-white uppercase tracking-widest transition">✕ Close</button>
            </div>
        </div>
        <button @click="nextLb()" class="lb-nav" style="right:16px" aria-label="Next">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- ══════════════════════════════════
         HERO
    ══════════════════════════════════ --}}
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
        {{-- Grain --}}
        <div class="absolute inset-0 opacity-[0.035]"
             style="background:url(\"data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E\") center/180px" aria-hidden="true"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16 w-full">
            <div class="mb-7" style="animation:fadeUp .6s .1s both">
                <a href="{{ route('tenant.show', $tenant->slug) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 text-[10px] tracking-[0.22em] uppercase text-white/30 hover:text-brand-400 transition-colors group">
                    <svg class="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                    Back to {{ $tenant->name }}
                </a>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">

                {{-- Left --}}
                <div class="max-w-2xl" style="animation:fadeUp .7s .2s both">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-5 h-px bg-brand-500"></span>
                        <span class="text-[10px] tracking-[0.25em] uppercase text-brand-400 font-bold">Offerings</span>
                    </div>
                    <h1 class="font-display text-5xl md:text-7xl font-semibold text-white leading-[0.88] tracking-tight">
                        What<br>
                        <em class="italic bg-gradient-to-r from-brand-300 via-brand-400 to-cyan-400 bg-clip-text text-transparent">We Offer</em>
                    </h1>
                    <p class="mt-4 text-sm text-white/40 max-w-sm leading-relaxed">
                        Discover our spaces and services — crafted for comfort, built for memory.
                    </p>

                    {{-- Stats --}}
                    <div class="mt-8 flex items-center gap-7" style="animation:fadeUp .7s .38s both">
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-brand-400">{{ $this->properties->count() }}</div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/30 mt-0.5">Rooms</div>
                        </div>
                        <div class="w-px h-10 bg-white/10"></div>
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-brand-400">{{ $this->services->count() }}</div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/30 mt-0.5">Services</div>
                        </div>
                        @if(!empty($galleryImages))
                            <div class="w-px h-10 bg-white/10"></div>
                            <div class="text-center">
                                <div class="font-display text-4xl font-medium text-brand-400">{{ count($galleryImages) }}</div>
                                <div class="text-[10px] tracking-[0.18em] uppercase text-white/30 mt-0.5">Photos</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Right: gallery strip --}}
                @if(!empty($galleryImages))
                    <div class="flex flex-col items-start lg:items-end gap-4" style="animation:fadeUp .7s .45s both">
                        {{-- Strip preview --}}
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
                                       hover:bg-brand-500/18 hover:border-brand-400/45 hover:text-white
                                       text-xs font-semibold uppercase tracking-widest transition-all shadow-lg shadow-black/20">
                            <svg class="w-3.5 h-3.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            View All Photos
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ══════════════════════════════════
         TAB NAV — sliding indicator
    ══════════════════════════════════ --}}
    <div class="sticky top-16 z-20 py-0 bg-black/65 backdrop-blur-2xl border-b border-white/[0.06]">
        <div class="max-w-7xl mx-auto px-6 md:px-16 flex items-center justify-between">

            <div class="tab-track flex items-stretch" x-ref="tabNav">
                @foreach([['accommodations','Accommodations',$this->properties->count()],['services','Services',$this->services->count()]] as [$key,$label,$count])
                    <button wire:click="$set('activeTab','{{ $key }}')"
                            @click="activeTab='{{ $key }}'; $nextTick(()=>updateTabIndicator())"
                            data-active="{{ $activeTab === $key ? 'true' : 'false' }}"
                            class="relative px-5 py-4 text-xs font-bold uppercase tracking-wider transition-colors {{ $activeTab === $key ? 'text-white' : 'text-white/35 hover:text-white/65' }}">
                        {{ $label }}
                        <span class="ml-1.5 text-[10px] {{ $activeTab === $key ? 'text-brand-400' : 'text-white/20' }}">{{ $count }}</span>
                    </button>
                @endforeach
                <div class="tab-indicator" :style="{ left: tabIndicator.left, width: tabIndicator.width }"></div>
            </div>

            @if(!empty($galleryImages))
                <button @click="openGallery()"
                        class="hidden sm:inline-flex items-center gap-1.5 text-[10px] tracking-widest uppercase text-white/30 hover:text-brand-400 transition-colors font-bold py-4">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Gallery
                </button>
            @endif
        </div>
    </div>

    {{-- ══════════════════════════════════
         MAIN CONTENT
    ══════════════════════════════════ --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-12 md:py-16">

        {{-- ── ACCOMMODATIONS ── --}}
        @if($activeTab === 'accommodations')

            <div class="reveal mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Stay & Explore</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-white">
                    Available <em class="italic text-brand-400">Accommodations</em>
                </h2>
                <p class="mt-2 text-sm text-white/35 max-w-md">All rooms are immediately bookable for your stay.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($this->properties as $index => $property)
                    @php $images = $property->images->pluck('image_path')->toArray(); @endphp

                    <article class="prop-card flex flex-col reveal" wire:key="prop-{{ $property->id }}"
                             style="transition-delay:{{ $index * 70 }}ms"
                             x-data="{ imgIndex: 0, images: {{ Js::from($images) }} }">

                        {{-- Image slider --}}
                        <div class="prop-card-img">
                            <template x-if="images.length > 0">
                                <img :src="'/storage/' + images[imgIndex]"
                                     :alt="'{{ addslashes($property->name) }} photo ' + (imgIndex+1)"
                                     class="w-full h-full object-cover"
                                     loading="lazy">
                            </template>
                            <template x-if="images.length === 0">
                                <div class="w-full h-full bg-white/[0.04] flex items-center justify-center text-white/15">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            </template>

                            {{-- Arrow nav for multiple images --}}
                            <template x-if="images.length > 1">
                                <div>
                                    <button @click.prevent="imgIndex=(imgIndex-1+images.length)%images.length"
                                            class="absolute left-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-black/60 border border-white/15 flex items-center justify-center text-white/70 hover:bg-black/80 hover:text-white transition-all opacity-0 group-hover:opacity-100 focus:opacity-100">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                    </button>
                                    <button @click.prevent="imgIndex=(imgIndex+1)%images.length"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-black/60 border border-white/15 flex items-center justify-center text-white/70 hover:bg-black/80 hover:text-white transition-all opacity-0 group-hover:opacity-100 focus:opacity-100">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                    </button>
                                    {{-- Dot indicators --}}
                                    <div class="absolute bottom-2.5 left-0 right-0 flex justify-center gap-1">
                                        <template x-for="(img,i) in images" :key="i">
                                            <div @click.prevent="imgIndex=i"
                                                 class="rounded-full transition-all cursor-pointer"
                                                 :class="i===imgIndex ? 'w-4 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/40'">
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>

                            {{-- Badges --}}
                            <div class="absolute top-3 left-3 flex flex-col gap-1.5 pointer-events-none">
                                @if($property->propertyType)
                                    <span class="bg-black/65 backdrop-blur text-[10px] font-bold text-brand-300 px-2.5 py-1 rounded-full tracking-wider uppercase">
                                        {{ $property->propertyType->name }}
                                    </span>
                                @endif
                            </div>
                            <span class="absolute top-3 right-3 bg-black/65 backdrop-blur text-[10px] font-bold text-emerald-300 px-2.5 py-1 rounded-full flex items-center gap-1 uppercase tracking-wider pointer-events-none">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_rgba(52,211,153,.7)] animate-pulse"></span>
                                Available
                            </span>
                        </div>

                        {{-- Body --}}
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-display text-xl font-semibold text-white mb-2 leading-snug group-hover:text-brand-400 transition-colors">
                                {{ $property->name }}
                            </h3>
                            @if($property->description)
                                <p class="text-sm text-white/45 line-clamp-2 mb-4 flex-1 leading-relaxed">{{ $property->description }}</p>
                            @else
                                <div class="flex-1"></div>
                            @endif

                            <div class="flex items-center justify-between pt-4 mt-auto border-t border-white/[0.07]">
                                <div>
                                    <span class="font-display text-2xl font-semibold text-brand-400">₱{{ number_format($property->price, 2) }}</span>
                                    <span class="text-[10px] text-white/40 ml-1 uppercase tracking-wider">/ night</span>
                                </div>
                                @auth
                                    <a href="{{ route('booking.create', ['publicproperty' => $property->id]) }}" wire:navigate
                                       class="py-2 px-5 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-[10px] font-bold uppercase tracking-widest transition-all shadow-lg shadow-brand-600/25 hover:-translate-y-0.5">
                                        Reserve
                                    </a>
                                @else
                                    <a href="{{ route('login', ['redirect' => url()->current()]) }}"
                                       class="py-2 px-5 rounded-full border border-white/12 hover:bg-white/[0.07] text-white/50 hover:text-white text-[10px] font-bold uppercase tracking-widest transition-all">
                                        Login to Book
                                    </a>
                                @endauth
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="col-span-full text-center py-20 rounded-3xl border border-white/[0.06] bg-white/[0.02]">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="font-display text-xl italic text-white/30">No accommodations listed yet.</h3>
                        <p class="text-xs text-white/20 mt-2">Check back soon — new rooms may be added.</p>
                    </div>
                @endforelse
            </div>
        @endif

        {{-- ── SERVICES ── --}}
        @if($activeTab === 'services')

            <div class="reveal mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Enhance Your Stay</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-white">
                    Add-on <em class="italic text-brand-400">Services</em>
                </h2>
                <p class="mt-2 text-sm text-white/35 max-w-md">Extras available to elevate your experience.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @forelse($this->services as $index => $service)
                    @php
                        $name = strtolower($service->name);
                        $iconPath = match(true) {
                            str_contains($name,'pool')    => 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z',
                            str_contains($name,'food')
                                || str_contains($name,'meal')
                                || str_contains($name,'dining') => 'M18 3a1 1 0 00-1 1v5h-2V4a1 1 0 00-2 0v5H9V4a1 1 0 00-2 0v6a4 4 0 003 3.87V20a1 1 0 002 0v-6.13A4 4 0 0016 10V4a1 1 0 00-2 0',
                            str_contains($name,'spa')
                                || str_contains($name,'massage') => 'M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9zm0 16c-3.86 0-7-3.14-7-7s3.14-7 7-7 7 3.14 7 7-3.14 7-7 7z',
                            str_contains($name,'tour')
                                || str_contains($name,'guide') => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
                            str_contains($name,'transfer')
                                || str_contains($name,'transport') => 'M8 17l4 4 4-4m-4-5v9M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 103 16.29',
                            default => 'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z',
                        };
                    @endphp

                    <div class="svc-card flex flex-col reveal" wire:key="svc-{{ $service->id }}"
                         style="transition-delay:{{ $index * 60 }}ms">

                        <div class="w-11 h-11 rounded-2xl bg-brand-500/12 border border-brand-400/18 flex items-center justify-center text-brand-400 mb-4 shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $iconPath }}"/>
                            </svg>
                        </div>

                        <h3 class="font-display text-lg font-semibold text-white mb-2 leading-snug">{{ $service->name }}</h3>

                        @if($service->description)
                            <p class="text-sm text-white/45 flex-1 mb-5 leading-relaxed">{{ $service->description }}</p>
                        @else
                            <div class="flex-1 mb-5"></div>
                        @endif

                        <div class="flex items-center justify-between pt-4 mt-auto border-t border-white/[0.06]">
                            <span class="font-display text-2xl font-semibold text-white">₱{{ number_format($service->price, 2) }}</span>
                            @auth
                                <span class="text-[10px] font-bold uppercase tracking-widest text-white/25 bg-white/[0.04] border border-white/[0.07] rounded-full px-3 py-1">
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
                    <div class="col-span-full text-center py-20 rounded-3xl border border-white/[0.06] bg-white/[0.02]">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <h3 class="font-display text-xl italic text-white/30">No services available yet.</h3>
                        <p class="text-xs text-white/20 mt-2">Check back soon.</p>
                    </div>
                @endforelse
            </div>
        @endif

    </div>

    {{-- ══════════════════════════════════
         GALLERY FOOTER TEASER
    ══════════════════════════════════ --}}
    @if(!empty($galleryImages))
        <div class="section-rule mx-6 md:mx-16"></div>
        <section class="max-w-7xl mx-auto px-6 md:px-16 py-14 reveal flex flex-col sm:flex-row items-center justify-between gap-5">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-4 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Photo Gallery</span>
                </div>
                <p class="text-white/45 text-sm">
                    Explore all <span class="text-white font-semibold">{{ count($galleryImages) }} photos</span> of {{ $tenant->name }}
                    @if($gallerySubtitle) — <em class="italic text-white/35">{{ $gallerySubtitle }}</em> @endif
                </p>
            </div>
            <button @click="openGallery()"
                    class="shrink-0 inline-flex items-center gap-2.5 px-7 py-3 rounded-full
                           bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold uppercase tracking-widest
                           transition-all shadow-xl shadow-brand-600/20 hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Open Gallery
            </button>
        </section>
    @endif

    {{-- ══════════════════════════════════
         STICKY BOOK BAR (mobile)
    ══════════════════════════════════ --}}
    <div class="sticky-book lg:hidden" :class="stickyVisible ? 'visible' : ''">
        <div class="flex-1 min-w-0">
            <p class="text-white font-semibold text-sm truncate">{{ $tenant->name }}</p>
            <p class="text-white/40 text-xs">
                @if($this->properties->count())
                    From ₱{{ number_format($this->properties->min('price'), 0) }} / night
                @else
                    View offerings above
                @endif
            </p>
        </div>
        @if($this->properties->count() > 0)
            <button wire:click="$set('activeTab','accommodations')" @click="stickyVisible=false; window.scrollTo({top:0,behavior:'smooth'})"
                    class="shrink-0 px-6 py-3 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold uppercase tracking-widest transition shadow-lg shadow-brand-600/30">
                Book Now
            </button>
        @endif
    </div>

    @push('scripts')
    <script>
    document.addEventListener('livewire:navigated', () => {
        const obs = new IntersectionObserver(entries => {
            entries.forEach(e => { if(e.isIntersecting){ e.target.classList.add('in'); obs.unobserve(e.target); } });
        }, { threshold:.08 });
        document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
    });
    </script>
    @endpush
</div>