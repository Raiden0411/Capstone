{{-- resources/views/public/pages/⚡business-offerings.blade.php --}}
<?php

use App\Models\BusinessApplication;
use App\Models\Service;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Layout('layouts.app')]
#[Title('What We Offer')]
class extends Component
{
    public Tenant $tenant;

    public ?string $coverPhoto      = null;
    public array   $galleryImages   = [];
    public string  $galleryTitle    = '';
    public string  $gallerySubtitle = '';

    /** @var array<string, mixed> */
    public array $businessInfo = [];

    /** @var array{name:string, avatar_path:?string, id_type:?string}|null */
    public ?array $owner = null;

    public bool    $hasKyb          = false;
    public ?string $kybSource       = null;
    public ?string $memberSince     = null;
    public ?string $permitExpiresAt = null;

    public function mount($slug): void
    {
        // NOTE: Tenant does NOT use BelongsToTenant (it is the root of the
        // tenant system), so withoutGlobalScope() here is a no-op kept for
        // forward-compat if TenantScope is ever applied to Tenant.
        $this->tenant = Tenant::withoutGlobalScope(TenantScope::class)
            ->with([
                'typeOfTenant:id,type,description',
                'businessApplication' => fn ($q) => $q->select(
                    'id',
                    'approved_tenant_id',
                    'business_name',
                    'business_type',
                    'owner_full_name',
                    'owner_avatar_path',
                    'owner_id_type',
                    'status',
                    'source',
                    'reviewed_at',
                ),
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $settings = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('key', ['spot_cover', 'business_gallery', 'gallery_title', 'gallery_subtitle'])
            ->get()
            ->pluck('value', 'key');

        $this->coverPhoto      = $settings['spot_cover']       ?? null;
        $this->galleryImages   = $settings['business_gallery'] ?? [];

        if (! is_array($this->galleryImages)) {
            $this->galleryImages = [];
        }

        $this->galleryTitle    = $settings['gallery_title']    ?? '';
        $this->gallerySubtitle = $settings['gallery_subtitle'] ?? '';

        $info = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'business_info')
            ->first();

        $this->businessInfo = ($info && is_array($info->value)) ? $info->value : [];

        $app = $this->tenant->businessApplication;

        if ($app) {
            $this->hasKyb    = $app->status === BusinessApplication::STATUS_APPROVED;
            $this->kybSource = $app->source;

            if ($app->owner_full_name) {
                $this->owner = [
                    'name'        => $app->owner_full_name,
                    'avatar_path' => $app->owner_avatar_path,
                    'id_type'     => $app->owner_id_type,
                ];
            }

            if ($app->reviewed_at) {
                $this->memberSince = $app->reviewed_at->format('F Y');
            }
        }

        if (! $this->owner) {
            $admin = $this->tenant->users()
                ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                ->select('id', 'name', 'avatar', 'tenant_id')
                ->first();

            if ($admin) {
                $this->owner = [
                    'name'        => $admin->name,
                    'avatar_path' => $admin->avatar,
                    'id_type'     => null,
                ];
            }

            if (! $this->memberSince && $this->tenant->verified_at) {
                $this->memberSince = $this->tenant->verified_at->format('F Y');
            }
        }

        if ($this->tenant->permit_expires_at) {
            $this->permitExpiresAt = $this->tenant->permit_expires_at->format('F j, Y');
        }
    }

    /**
     * Rule 17 — Typed Eloquent property binding re-runs on hydrate
     * WITHOUT the TenantScope bypass. Re-apply the bypass here.
     */
    public function hydrate(): void
    {
        $this->tenant = Tenant::withoutGlobalScope(TenantScope::class)
            ->with([
                'typeOfTenant:id,type,description',
            ])
            ->where('is_active', true)
            ->whereKey($this->tenant->getKey())
            ->firstOrFail();
    }

    // ─────────────────────────────────────────────────────
    //  Media
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function galleryImageUrls(): array
    {
        return array_values(array_map(
            fn ($path) => asset('storage/' . $path),
            $this->galleryImages,
        ));
    }

    /** Pre-encoded JSON for the data-attribute (HTML-entity safe). */
    #[Computed]
    public function galleryImageUrlsJson(): string
    {
        return (string) json_encode(
            $this->galleryImageUrls,
            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG,
        );
    }

    /** First 4 gallery thumbs shown in the hero mini-strip. */
    #[Computed]
    public function heroThumbs(): array
    {
        return array_slice($this->galleryImages, 0, 4);
    }

    #[Computed]
    public function heroThumbCount(): int
    {
        return count($this->galleryImages);
    }

    #[Computed]
    public function coverPhotoUrl(): ?string
    {
        return $this->coverPhoto ? asset('storage/' . $this->coverPhoto) : null;
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        return $this->tenant->logo ? asset('storage/' . $this->tenant->logo) : null;
    }

    // ─────────────────────────────────────────────────────
    //  Business info computeds
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function description(): ?string
    {
        $desc = trim((string) ($this->businessInfo['description'] ?? ''));

        return $desc !== '' ? $desc : null;
    }

    #[Computed]
    public function fullAddress(): ?string
    {
        $parts = array_filter([
            $this->tenant->address,
            $this->businessInfo['barangay'] ?? null,
            $this->businessInfo['city']     ?? null,
            $this->businessInfo['province'] ?? null,
        ]);

        return $parts ? implode(', ', $parts) : null;
    }

    #[Computed]
    public function hoursLabel(): ?string
    {
        $hours = $this->businessInfo['opening_hours'] ?? null;

        if (! is_array($hours)) {
            return null;
        }

        if (! empty($hours['is_24hr'])) {
            return 'Open 24 hours';
        }

        $open  = $hours['opening'] ?? null;
        $close = $hours['closing'] ?? null;

        if (! $open || ! $close) {
            return null;
        }

        $fmt = function (?string $time): ?string {
            if (! $time) return null;
            try {
                return Carbon::createFromFormat('H:i', $time)->format('g:i A');
            } catch (\Throwable) {
                return $time;
            }
        };

        $openLabel  = $fmt($open);
        $closeLabel = $fmt($close);

        return ($openLabel && $closeLabel) ? "{$openLabel} – {$closeLabel}" : null;
    }

    #[Computed]
    public function socialLinks(): array
    {
        $socials = $this->businessInfo['social_links'] ?? [];

        return array_filter([
            'facebook'  => $socials['facebook']  ?? null,
            'instagram' => $socials['instagram'] ?? null,
            'website'   => $this->businessInfo['website'] ?? null,
        ]);
    }

    #[Computed]
    public function hasCoordinates(): bool
    {
        $coords = $this->tenant->coordinates ?? [];

        return is_array($coords)
            && isset($coords[0]['lat'], $coords[0]['lng']);
    }

    #[Computed]
    public function coordinates(): ?array
    {
        $coords = $this->tenant->coordinates ?? [];

        if (! is_array($coords) || ! isset($coords[0]['lat'], $coords[0]['lng'])) {
            return null;
        }

        return [
            'lat' => (float) $coords[0]['lat'],
            'lng' => (float) $coords[0]['lng'],
        ];
    }

    /**
     * Deep-link into OUR map with the tenant pre-focused and the routing
     * flow pre-armed (`?marker=X&directions=1`). The explore-map SFC
     * hydrates those params on mount, calls OsrmDistanceService server-
     * side, and animates the user in. Previously this pointed at Google
     * Maps — which broke the immersive map experience for users who
     * were already inside our platform.
     */
    #[Computed]
    public function directionsUrl(): ?string
    {
        if (! $this->coordinates) {
            return null;
        }

        return route('explore.map', [
            'marker'     => $this->tenant->id,
            'directions' => 1,
        ]);
    }

    #[Computed]
    public function viewOnMapUrl(): ?string
    {
        if (! $this->coordinates) {
            return null;
        }

        return route('explore.map', ['marker' => $this->tenant->id]);
    }

    // ─────────────────────────────────────────────────────
    //  Owner / trust
    // ─────────────────────────────────────────────────────

    #[Computed]
    public function ownerAvatarUrl(): ?string
    {
        $path = $this->owner['avatar_path'] ?? null;

        return $path ? asset('storage/' . $path) : null;
    }

    #[Computed]
    public function ownerIdTypeLabel(): ?string
    {
        $type = $this->owner['id_type'] ?? null;

        if (! $type) {
            return null;
        }

        return BusinessApplication::OWNER_ID_TYPES[$type] ?? $type;
    }

    #[Computed]
    public function businessTypeLabel(): ?string
    {
        return $this->tenant->typeOfTenant?->type;
    }

    #[Computed]
    public function businessTypeDescription(): ?string
    {
        return $this->tenant->typeOfTenant?->description;
    }

    #[Computed]
    public function isVerified(): bool
    {
        return $this->tenant->verified_at !== null;
    }

    #[Computed]
    public function isRecommended(): bool
    {
        return (bool) $this->tenant->is_recommended;
    }

    #[Computed]
    public function isPermitValid(): bool
    {
        return $this->tenant->permit_expires_at !== null
            && ! $this->tenant->permit_expires_at->isPast();
    }

    // ─────────────────────────────────────────────────────
    //  Properties / Services
    // ─────────────────────────────────────────────────────

    /**
     * Step 2 of the optimization protocol — only columns actually used
     * in the template are selected. `capacity`, `quantity`, and the
     * filter-only `is_active` are dropped from the SELECT. `tenant_id`
     * is kept for model integrity on downstream consumers.
     */
    #[Computed]
    public function properties()
    {
        return $this->tenant->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->select('id', 'tenant_id', 'property_type_id', 'name', 'description', 'price')
            ->with([
                'propertyType' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name'),
                'images'       => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'property_id', 'image_path'),
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
            ->select('id', 'tenant_id', 'name', 'price')
            ->orderBy('name')
            ->get()
            ->each(function (Service $service): void {
                $service->icon_path = $this->getServiceIconPath($service);
            });
    }

    protected function getServiceIconPath(Service $service): string
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
        animation: galFadeIn .25s ease;
    }
    @keyframes galFadeIn {
        from { opacity: 0 }
        to   { opacity: 1 }
    }
    @media (prefers-reduced-motion: reduce) {
        .gal-overlay { animation: none; }
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
    @media (prefers-reduced-motion: reduce) {
        .lb-wrap { animation: none; }
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
    @media (prefers-reduced-motion: reduce) {
        .gallery-pill { animation: none; }
    }
    .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
    .hero-radial {
        background:
            radial-gradient(ellipse 80% 60% at 15% 20%, rgba(59,130,246,.22) 0%, transparent 55%),
            radial-gradient(ellipse 70% 50% at 85% 80%, rgba(16,185,129,.15) 0%, transparent 55%);
    }
</style>
@endpush

<div
    class="relative z-10 min-h-screen"
    x-data="{
        galleryOpen: false,
        lbSrc: null,
        lbIndex: 0,
        lbStartX: 0,
        galleryImages: JSON.parse($el.dataset.galleryImages || '[]'),
        previousFocus: null,

        openGallery() {
            this.previousFocus = document.activeElement;
            this.galleryOpen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => this.$refs.galleryCloseBtn?.focus());
        },
        closeGallery() {
            this.galleryOpen = false;
            this.lbSrc = null;
            document.body.style.overflow = '';
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

            const els = document.querySelectorAll('.reveal');

            // No IntersectionObserver — reveal everything immediately so
            // content is never permanently hidden.
            if (!('IntersectionObserver' in window)) {
                els.forEach(el => el.classList.add('in'));
                return;
            }

            const obs = new IntersectionObserver(entries => {
                entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); obs.unobserve(e.target); } });
            }, { threshold: .08 });

            els.forEach(el => obs.observe(el));
        },

        stickyVisible: false,
        setupSticky() {
            const hero = document.getElementById('offerings-hero');
            if (!hero) return;

            // No IntersectionObserver — leave the bar hidden (matches the
            // pre-existing behaviour, but without throwing).
            if (!('IntersectionObserver' in window)) return;

            const obs = new IntersectionObserver(([e]) => { this.stickyVisible = !e.isIntersecting; }, { threshold: .1 });
            obs.observe(hero);
        },
    }"
    data-gallery-images="{{ $this->galleryImageUrlsJson }}"
    x-init="setupReveal(); setupSticky();"
    @keydown.escape.window="lbSrc ? lbSrc=null : closeGallery()"
    @keydown.arrow-left.window="lbSrc && prevLb()"
    @keydown.arrow-right.window="lbSrc && nextLb()"
>

    {{-- ═══════════════ GALLERY MODAL ═══════════════ --}}
    {{-- Rule 69: no `x-show`, no `x-transition`. Visibility is driven by
         :class toggling between the `.gal-overlay` class (with its own
         CSS fade-in animation) and Tailwind's `.hidden`. --}}
    <div x-cloak
         :class="galleryOpen ? 'gal-overlay' : 'hidden'"
         role="dialog"
         aria-modal="true">

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
                <button type="button" @click="closeGallery()"
                        x-ref="galleryCloseBtn"
                        class="w-9 h-9 rounded-full border border-white/12 flex items-center justify-center text-white/40 hover:text-white hover:border-white/35 hover:bg-white/[0.07] transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                        aria-label="Close gallery">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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
                                 alt="{{ $tenant->name }} photo {{ $idx + 1 }}"
                                 loading="lazy" decoding="async">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-3">
                                <div class="w-7 h-7 rounded-full bg-white/10 border border-white/20 flex items-center justify-center ml-auto">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center h-64 text-white/25">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm">No photos yet</p>
                </div>
            @endif
        </div>

        @if($gallerySubtitle)
            <div class="flex-none border-t border-white/[0.06] px-8 py-3 text-xs text-white/25 italic">{{ $gallerySubtitle }}</div>
        @endif
    </div>

    {{-- ═══════════════ LIGHTBOX ═══════════════ --}}
    {{-- Rule 69: `:class` toggle. The `.lb-wrap` class carries the
         fade-in animation via CSS. --}}
    <div x-cloak
         :class="lbSrc ? 'lb-wrap' : 'hidden'"
         @click.self="lbSrc=null"
         @touchstart="touchStart($event)"
         @touchend="touchEnd($event)"
         role="dialog"
         aria-modal="true">
        <button type="button" @click="prevLb()" class="lb-nav" style="left:16px" aria-label="Previous">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="relative">
            <img :src="lbSrc || ''" class="lb-img" alt="Gallery photo" decoding="async">
            <div class="absolute bottom-0 left-0 right-0 flex justify-between items-center px-4 py-3 bg-gradient-to-t from-black/80 to-transparent rounded-b-xl">
                <span class="text-xs text-white/40" x-text="(lbIndex+1)+' / '+galleryImages.length"></span>
                <button type="button" @click="lbSrc=null" class="text-[10px] text-white/35 hover:text-white uppercase tracking-widest transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50 inline-flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Close
                </button>
            </div>
        </div>
        <button type="button" @click="nextLb()" class="lb-nav" style="right:16px" aria-label="Next">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    {{-- ═══════════════ HERO ═══════════════ --}}
    <section id="offerings-hero" class="relative overflow-hidden bg-neutral-950">

        {{-- Background priority: tenant logo → cover photo → gradient fallback.
             The logo is usually a small square image; scaling it to full-bleed
             requires a heavy blur to hide pixelation, which reads as an
             "ambient brand color" hero. The cover photo (if present) gets a
             lighter treatment since it's already designed for wide display. --}}
        @if($this->logoUrl)
            <img src="{{ $this->logoUrl }}"
                 class="absolute inset-0 w-full h-full object-cover scale-125"
                 style="filter:brightness(.28) saturate(1.25) blur(14px)" alt="" loading="eager" decoding="async">

            <div class="absolute inset-0 bg-gradient-to-tr from-primary-950/85 via-neutral-950/70 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/40 to-transparent"></div>
        @elseif($this->coverPhotoUrl)
            <img src="{{ $this->coverPhotoUrl }}"
                 class="absolute inset-0 w-full h-full object-cover scale-105"
                 style="filter:brightness(.3) saturate(1.15)" alt="" loading="eager" decoding="async">

            <div class="absolute inset-0 bg-gradient-to-tr from-primary-950/85 via-neutral-950/70 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-neutral-950 via-neutral-950/40 to-transparent"></div>
        @else
            <div class="absolute inset-0 bg-gradient-to-br from-primary-900 via-neutral-950 to-neutral-950"></div>
            <div class="absolute inset-0 hero-radial"></div>
            <div class="absolute inset-0 opacity-[0.04]"
                 style="background-image: linear-gradient(rgba(255,255,255,.6) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.6) 1px, transparent 1px); background-size: 48px 48px;"></div>
        @endif

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16 pt-10 md:pt-16 pb-16 md:pb-20">

            <div class="mb-10 md:mb-14">
                <a href="{{ route('tenant.show', $tenant->slug) }}" wire:navigate
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/[0.05] border border-white/10 hover:bg-white/10 hover:border-white/20 text-[10px] tracking-[0.22em] uppercase text-white/70 hover:text-white transition-all group active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                    Back to {{ $tenant->name }}
                </a>
            </div>

            {{--
                On mobile:  content column first (heading, description, stats,
                            quick-action pills), brand card second.
                On desktop: brand card on the right via `lg:order-2` on the
                            brand card and default source order for the content.
                Result — mobile users reach "What We Offer" and the offers
                teaser immediately instead of scrolling past a tall brand
                card first.
            --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-start">

                {{-- ─────────── CONTENT (mobile-first) ─────────── --}}
                <div class="lg:col-span-7 lg:order-1">

                    <div class="flex flex-wrap items-center gap-2 mb-5">
                        @if($this->businessTypeLabel)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-primary-500/15 border border-primary-400/30 text-[10px] tracking-[0.18em] uppercase text-primary-200 font-bold backdrop-blur-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                {{ $this->businessTypeLabel }}
                            </span>
                        @endif

                        @if($this->isVerified)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-400/30 text-[10px] tracking-[0.18em] uppercase text-emerald-200 font-bold backdrop-blur-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                                Verified
                            </span>
                        @endif

                        @if($this->isRecommended)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-400/30 text-[10px] tracking-[0.18em] uppercase text-amber-200 font-bold backdrop-blur-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                Recommended
                            </span>
                        @endif
                    </div>

                    <h1 class="font-display text-5xl sm:text-6xl md:text-7xl lg:text-[5.5rem] font-semibold text-white leading-[0.92] tracking-tight">
                        What<br>
                        <em class="italic bg-gradient-to-r from-blue-300 via-cyan-300 to-emerald-300 bg-clip-text text-transparent">We Offer</em>
                    </h1>

                    @if($this->description)
                        <p class="mt-6 max-w-xl text-sm md:text-base text-white/65 leading-relaxed">
                            {{ $this->description }}
                        </p>
                    @else
                        <p class="mt-6 max-w-xl text-sm md:text-base text-white/55 leading-relaxed">
                            Discover everything {{ $tenant->name }} has to offer — from bookable activities to add-on services that make your visit unforgettable.
                        </p>
                    @endif

                    <div class="mt-8 md:mt-10 pt-6 border-t border-white/10">
                        <div class="flex flex-wrap items-center gap-5 sm:gap-7 md:gap-10">
                            <div>
                                <div class="font-display text-3xl sm:text-4xl font-medium text-primary-300">{{ $this->properties->count() }}</div>
                                <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-1">Activities</div>
                            </div>
                            <div class="w-px h-12 bg-white/10"></div>
                            <div>
                                <div class="font-display text-3xl sm:text-4xl font-medium text-primary-300">{{ $this->services->count() }}</div>
                                <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-1">Services</div>
                            </div>
                            @if($this->heroThumbCount)
                                <div class="w-px h-12 bg-white/10"></div>
                                <div>
                                    <div class="font-display text-3xl sm:text-4xl font-medium text-primary-300">{{ $this->heroThumbCount }}</div>
                                    <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-1">Photos</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @php
                        $hasAnyPill = $this->directionsUrl
                            || $tenant->email
                            || !empty($this->socialLinks['website']);
                    @endphp

                    @if($hasAnyPill)
                        <div class="mt-8 flex flex-wrap items-center gap-2.5">
                            @if($this->directionsUrl)
                                <a href="{{ $this->directionsUrl }}" wire:navigate
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-primary-600 hover:bg-primary-700 border border-primary-500/40 hover:border-primary-400 text-xs font-bold text-white transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Get Directions
                                </a>
                            @endif

                            @if($tenant->email)
                                <a href="mailto:{{ $tenant->email }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/[0.06] border border-white/15 hover:bg-white/[0.12] hover:border-white/25 text-xs font-semibold text-white/80 hover:text-white transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    Email
                                </a>
                            @endif

                            @if(!empty($this->socialLinks['website']))
                                <a href="{{ $this->socialLinks['website'] }}" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/[0.06] border border-white/15 hover:bg-white/[0.12] hover:border-white/25 text-xs font-semibold text-white/80 hover:text-white transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"/>
                                    </svg>
                                    Website
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- ─────────── BRAND CARD (mobile-second) ─────────── --}}
                <div class="lg:col-span-5 lg:order-2">
                    <div class="relative rounded-3xl bg-white/[0.05] backdrop-blur-xl border border-white/10 shadow-2xl shadow-black/40 overflow-hidden">

                        <div class="h-1 bg-gradient-to-r from-primary-500 via-cyan-400 to-emerald-400"></div>

                        <div class="p-6 md:p-7 flex items-center gap-4">
                            <div class="shrink-0 w-20 h-20 md:w-24 md:h-24 rounded-2xl bg-white shadow-xl ring-1 ring-black/5 overflow-hidden flex items-center justify-center">
                                @if($this->logoUrl)
                                    <img src="{{ $this->logoUrl }}"
                                         alt="{{ $tenant->name }}"
                                         class="w-full h-full object-contain p-2" decoding="async">
                                @else
                                    <span class="font-display text-3xl font-bold text-primary-600">
                                        {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                    </span>
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] tracking-[0.22em] uppercase text-white/40 font-bold mb-1">
                                    @if($this->isVerified) Verified Business @else Business @endif
                                </p>
                                <h2 class="font-display text-xl md:text-2xl font-semibold text-white leading-tight truncate">
                                    {{ $tenant->name }}
                                </h2>
                                @if($this->businessTypeLabel)
                                    <p class="text-xs text-white/50 mt-1 truncate">{{ $this->businessTypeLabel }}</p>
                                @endif
                            </div>
                        </div>

                        <dl class="divide-y divide-white/[0.06] border-t border-white/[0.06]">
                            @if($this->fullAddress)
                                <div class="flex items-start gap-3 px-6 md:px-7 py-3.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-300 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="text-xs text-white/70 leading-snug">{{ $this->fullAddress }}</span>
                                </div>
                            @endif

                            @if($this->hoursLabel)
                                <div class="flex items-center gap-3 px-6 md:px-7 py-3.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-xs text-white/70">{{ $this->hoursLabel }}</span>
                                </div>
                            @endif

                            @if($this->owner)
                                <div class="flex items-center gap-3 px-6 md:px-7 py-3.5">
                                    <div class="shrink-0 w-6 h-6 rounded-full overflow-hidden bg-white/10 flex items-center justify-center ring-1 ring-white/15">
                                        @if($this->ownerAvatarUrl)
                                            <img src="{{ $this->ownerAvatarUrl }}" alt="{{ $this->owner['name'] }}" class="w-full h-full object-cover" decoding="async">
                                        @else
                                            <span class="text-[9px] font-bold text-white/60">
                                                {{ strtoupper(substr($this->owner['name'], 0, 1)) }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="text-xs text-white/70">Operated by <span class="font-semibold text-white">{{ $this->owner['name'] }}</span></span>
                                </div>
                            @endif
                        </dl>

                        {{-- Trust strip — hidden on mobile to keep the card compact.
                             Still visible on lg: where the two-column layout gives
                             it room. --}}
                        @if($this->hasKyb || $this->isPermitValid || $memberSince)
                            <div class="hidden lg:block border-t border-white/[0.06] px-6 md:px-7 py-4 space-y-2">
                                @if($this->hasKyb)
                                    <div class="flex items-center gap-2 text-[11px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                        </svg>
                                        <span class="text-white/50">KYB</span>
                                        <span class="text-emerald-300 font-semibold">Approved</span>
                                    </div>
                                @endif

                                @if($this->isPermitValid)
                                    <div class="flex items-center gap-2 text-[11px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                                        </svg>
                                        <span class="text-white/50">Mayor's Permit</span>
                                        <span class="text-white/85 font-semibold">valid until {{ $permitExpiresAt }}</span>
                                    </div>
                                @endif

                                @if($memberSince)
                                    <div class="flex items-center gap-2 text-[11px]">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-primary-300 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="text-white/50">On platform since</span>
                                        <span class="text-white/85 font-semibold">{{ $memberSince }}</span>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @php
                            $socialIcons = array_filter([
                                'facebook'  => $this->socialLinks['facebook']  ?? null,
                                'instagram' => $this->socialLinks['instagram'] ?? null,
                            ]);
                        @endphp

                        @if(!empty($socialIcons))
                            <div class="border-t border-white/[0.06] px-6 md:px-7 py-3.5 flex items-center gap-3">
                                <span class="text-[10px] tracking-[0.22em] uppercase text-white/40 font-bold">Follow</span>
                                <div class="flex items-center gap-2">
                                    @if(!empty($socialIcons['facebook']))
                                        <a href="{{ $socialIcons['facebook'] }}" target="_blank" rel="noopener noreferrer"
                                           aria-label="Facebook"
                                           class="w-8 h-8 rounded-full bg-white/[0.06] border border-white/12 hover:bg-blue-500/20 hover:border-blue-400/40 text-white/70 hover:text-white flex items-center justify-center transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"/>
                                            </svg>
                                        </a>
                                    @endif

                                    @if(!empty($socialIcons['instagram']))
                                        <a href="{{ $socialIcons['instagram'] }}" target="_blank" rel="noopener noreferrer"
                                           aria-label="Instagram"
                                           class="w-8 h-8 rounded-full bg-white/[0.06] border border-white/12 hover:bg-pink-500/20 hover:border-pink-400/40 text-white/70 hover:text-white flex items-center justify-center transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                                <rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        {{-- Gallery strip + View All Photos — hidden on mobile
                             to keep the initial view compact. Mobile users
                             still reach the gallery via the footer teaser
                             at the bottom of the page. --}}
                        @if($this->heroThumbCount)
                            <div class="hidden lg:block border-t border-white/[0.06] px-6 md:px-7 py-4">
                                <div class="flex items-center justify-between mb-3">
                                    <p class="text-[10px] tracking-[0.22em] uppercase text-white/40 font-bold">Gallery</p>
                                    <span class="text-[10px] text-white/30">{{ $this->heroThumbCount }} photos</span>
                                </div>
                                <div class="flex gap-2 cursor-pointer group" @click="openGallery()">
                                    @foreach($this->heroThumbs as $i => $img)
                                        <div class="flex-1 aspect-square rounded-lg overflow-hidden ring-1 ring-white/10 group-hover:ring-primary-400/40 transition">
                                            <img src="{{ asset('storage/'.$img) }}"
                                                 class="w-full h-full object-cover brightness-90 group-hover:brightness-100 group-hover:scale-110 transition duration-500"
                                                 alt="" loading="lazy" decoding="async">
                                        </div>
                                    @endforeach
                                    @if($this->heroThumbCount > 4)
                                        <div class="flex-1 aspect-square rounded-lg bg-white/[0.06] border border-white/10 flex items-center justify-center">
                                            <span class="text-white/70 text-xs font-bold">+{{ $this->heroThumbCount - 4 }}</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="hidden lg:block border-t border-white/[0.06] px-6 md:px-7 py-4">
                                <button type="button" @click="openGallery()"
                                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full
                                               bg-white/[0.08] border border-white/15 hover:bg-primary-500/20 hover:border-primary-400/50 hover:text-white
                                               text-xs font-bold uppercase tracking-widest text-white/80
                                               transition-all active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-400/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    View All Photos
                                </button>
                            </div>
                        @endif
                    </div>

                    @if($this->coordinates)
                        <p class="mt-3 text-center text-[10px] font-mono text-white/25 tracking-tight">
                            {{ number_format($this->coordinates['lat'], 6) }}, {{ number_format($this->coordinates['lng'], 6) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════ MAIN CONTENT ═══════════════ --}}
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-12 md:py-16 space-y-16">

        {{-- ═══ ACTIVITIES ═══ --}}
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
                    @php
                        // Precompute full URLs server-side — matching every
                        // other image on the page. The Alpine expression then
                        // just reads from the array without string concat.
                        $imageUrls = array_values(array_map(
                            fn ($p) => asset('storage/' . $p),
                            $property->images->pluck('image_path')->all(),
                        ));

                        $imageUrlsJson = json_encode(
                            $imageUrls,
                            JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG,
                        );
                    @endphp

                    <article class="group bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col reveal"
                             wire:key="prop-{{ $property->id }}"
                             data-images="{{ $imageUrlsJson }}"
                             data-prop-name="{{ $property->name }}"
                             x-data="{ imgIndex: 0, images: JSON.parse($el.dataset.images || '[]') }">

                        <div class="relative overflow-hidden aspect-[16/10]">
                            {{-- Rule 69: the img element is ALWAYS in the DOM.
                                 Visibility is toggled with :class. --}}
                            <img :src="images.length > 0 ? images[imgIndex] : 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'"
                                 :alt="images.length > 0 ? ($el.dataset.propName + ' — photo ' + (imgIndex + 1)) : ''"
                                 :class="images.length > 0 ? 'block' : 'hidden'"
                                 class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                 loading="lazy" decoding="async">

                            {{-- Empty state — same element tree, no <template x-if>. --}}
                            <div :class="images.length === 0 ? 'flex' : 'hidden'"
                                 class="w-full h-full bg-gray-100 dark:bg-gray-700 items-center justify-center text-gray-400 dark:text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>

                            {{-- Carousel controls — same pattern. --}}
                            <div :class="images.length > 1 ? 'block' : 'hidden'">
                                <button type="button" @click.prevent="imgIndex=(imgIndex-1+images.length)%images.length"
                                        class="absolute left-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-black/60 border border-white/15 flex items-center justify-center text-white/70 hover:bg-black/80 hover:text-white transition-all opacity-0 group-hover:opacity-100 focus-visible:opacity-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                                        aria-label="Previous photo">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
                                </button>
                                <button type="button" @click.prevent="imgIndex=(imgIndex+1)%images.length"
                                        class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-black/60 border border-white/15 flex items-center justify-center text-white/70 hover:bg-black/80 hover:text-white transition-all opacity-0 group-hover:opacity-100 focus-visible:opacity-100 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/50"
                                        aria-label="Next photo">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                                </button>
                                <div class="absolute bottom-2.5 left-0 right-0 flex justify-center gap-1">
                                    <template x-for="(img, i) in images" :key="i">
                                        <div @click.prevent="imgIndex=i"
                                             class="rounded-full transition-all cursor-pointer active:scale-90"
                                             :class="i===imgIndex ? 'w-4 h-1.5 bg-white' : 'w-1.5 h-1.5 bg-white/40'">
                                        </div>
                                    </template>
                                </div>
                            </div>

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
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto mb-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="font-display text-xl italic text-gray-500 dark:text-gray-400">No activities listed yet.</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">Check back soon — new activities may be added.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- ═══ SERVICES ═══ --}}
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
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            @endauth
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>

    {{-- ═══ GALLERY FOOTER TEASER ═══ --}}
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
            <button type="button" @click="openGallery()"
                    class="shrink-0 inline-flex items-center gap-2.5 px-7 py-3 rounded-full
                           bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-widest
                           transition-all shadow-lg shadow-primary-500/20 hover:-translate-y-0.5 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Open Gallery
            </button>
        </section>
    @endif

    {{-- ═══ STICKY MOBILE BOOK BAR ═══ --}}
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
                <button type="button" @click="document.getElementById('activities').scrollIntoView({behavior:'smooth'})"
                        class="shrink-0 px-6 py-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-xs font-bold uppercase tracking-widest transition shadow-lg shadow-primary-500/30 active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    View Activities
                </button>
            @endif
        </div>
    </div>
</div>