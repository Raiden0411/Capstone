{{-- resources/views/superadmin/pages/homepage/⚡homepage-editor.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use App\Traits\HandlesImageUploads;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new
#[Layout('superadmin.layouts.app')]
#[Title('Homepage Editor')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    /** Image contexts that this editor manages. */
    private const IMAGE_KEYS = [
        'hero_background_image',
        'hero_side_image_1',
        'hero_side_image_2',
        'hero_side_image_3',
        'hero_side_image_4',
    ];

    public $heroBackgroundImage;
    public $heroSideImage1;
    public $heroSideImage2;
    public $heroSideImage3;
    public $heroSideImage4;

    public $heroTitle;
    public $heroSubtitle;
    public $heroDescription;
    public $discoverTitle;
    public $discoverDescription;

    public $existingHeroBg;
    public $existingSide1;
    public $existingSide2;
    public $existingSide3;
    public $existingSide4;

    public function mount()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->loadExistingImages();

        $this->heroTitle       = SiteSetting::getValue('hero_title', 'Welcome to the North');
        $this->heroSubtitle    = SiteSetting::getValue('hero_subtitle', 'Victorias City');
        $this->heroDescription = SiteSetting::getValue('hero_description', 'Escape into a world where the air is scented with sugar cane and the mountains hum with hidden waterfalls. A breathtaking sanctuary in Negros Occidental.');
        $this->discoverTitle   = SiteSetting::getValue('discover_title', 'The City of Smiles & Heritage');
        $this->discoverDescription = SiteSetting::getValue('discover_description', 'Victorias is more than just an industrial hub; it is a blend of natural sanctuary, deep-rooted history, and warm hospitality. Experience the unique charm that makes this city a hidden gem in Western Visayas.');
    }

    /**
     * Batched, cached read of every image path this page manages.
     * Replaces the previous pattern of 5 individual `getValue()` calls
     * in mount + 5 more in save() for a post-write refresh.
     */
    private function loadExistingImages(): void
    {
        $stored = SiteSetting::getBatch(self::IMAGE_KEYS, 'homepage_editor_images');

        $this->existingHeroBg = $stored['hero_background_image'] ?? null;
        $this->existingSide1  = $stored['hero_side_image_1'] ?? null;
        $this->existingSide2  = $stored['hero_side_image_2'] ?? null;
        $this->existingSide3  = $stored['hero_side_image_3'] ?? null;
        $this->existingSide4  = $stored['hero_side_image_4'] ?? null;
    }

    public function save()
    {
        // Livewire update requests bypass route middleware — re-check
        // per action. Rule 16.
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->validate([
            // 5 MB ceiling, raster only. Output compressed to ≤2 MB via 'site'.
            'heroBackgroundImage' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'heroSideImage1'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'heroSideImage2'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'heroSideImage3'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'heroSideImage4'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'heroTitle'           => 'required|string|max:255',
            'heroSubtitle'        => 'required|string|max:255',
            'heroDescription'     => 'nullable|string|max:1000',
            'discoverTitle'       => 'required|string|max:255',
            'discoverDescription' => 'nullable|string|max:1000',
        ]);

        try {
            $this->updateImage('hero_background_image', $this->heroBackgroundImage);
            $this->updateImage('hero_side_image_1', $this->heroSideImage1);
            $this->updateImage('hero_side_image_2', $this->heroSideImage2);
            $this->updateImage('hero_side_image_3', $this->heroSideImage3);
            $this->updateImage('hero_side_image_4', $this->heroSideImage4);
        } catch (\Throwable $e) {
            Log::error('Homepage image save failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to upload one or more images. Please try again.');
            return null;
        }

        $textFields = [
            'hero_title'           => 'heroTitle',
            'hero_subtitle'        => 'heroSubtitle',
            'hero_description'     => 'heroDescription',
            'discover_title'       => 'discoverTitle',
            'discover_description' => 'discoverDescription',
        ];

        foreach ($textFields as $key => $property) {
            SiteSetting::setValue($key, $this->{$property});
        }

        // Refresh the "existing" mirrors so the UI falls back to the
        // freshly-saved thumbnails. Uses one batched read.
        $this->loadExistingImages();

        $this->reset(
            'heroBackgroundImage',
            'heroSideImage1',
            'heroSideImage2',
            'heroSideImage3',
            'heroSideImage4'
        );

        // Clear client-side file previews so the DOM falls back to the
        // freshly-saved server-rendered thumbnails.
        $this->dispatch('preview-reset');

        session()->flash('message', 'Homepage updated successfully.');
    }

    /**
     * Golden sequence: store + compress the new file FIRST, then write
     * the DB row, then delete the old file AFTER success. On failure,
     * the new file is cleaned up and the old one is preserved.
     */
    private function updateImage(string $key, $uploadedFile): void
    {
        if (! $uploadedFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            return;
        }

        $oldPath = SiteSetting::getValue($key);
        $newPath = null;

        try {
            // Compresses against the 'site' context (≤2 MB / 2560×1440).
            $newPath = $this->storeImage($uploadedFile, 'homepage', 'public', 'site');

            if (! $newPath) {
                throw new \RuntimeException('Failed to store the uploaded image.');
            }

            SiteSetting::setValue($key, $newPath);
        } catch (\Throwable $e) {
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                Storage::disk('public')->delete($newPath);
            }

            throw $e;
        }

        // Delete the old file AFTER the DB write succeeded. Closes the
        // disk leak that existed before. Guard against the (theoretical)
        // case where the new path equals the old path.
        if ($oldPath
            && $oldPath !== $newPath
            && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
    }
};
?>

{{--
    Root element carries the initial preview state via a data-* attribute
    encoded with JSON_HEX_* flags, so any quotes/apostrophes inside the
    values can't break the HTML attribute.
--}}
<div
    x-data="homepageEditor()"
    data-preview="{{ json_encode([
        'heroTitle'           => $heroTitle,
        'heroSubtitle'        => $heroSubtitle,
        'heroDescription'     => $heroDescription,
        'discoverTitle'       => $discoverTitle,
        'discoverDescription' => $discoverDescription,
    ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
    class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    @if (session()->has('message'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md text-sm text-emerald-700 dark:text-emerald-300 font-medium">
            {{ session('message') }}
        </div>
    @endif
    @if (session()->has('error'))
        <div class="bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 border-l-4 border-l-rose-500 p-4 rounded-md text-sm text-rose-700 dark:text-rose-300 font-medium">
            {{ session('error') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Homepage Editor</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Edit each section below. Previews mirror the live homepage layout.
            </p>
        </div>
        <a href="{{ route('superadmin.dashboard') }}" wire:navigate
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Dashboard
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 1 — HERO  (mirrors the homepage hero)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold">1</span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">Hero Section</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">The full-screen banner at the top of the homepage.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live preview
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- ── PREVIEW ── --}}
                <div class="relative rounded-2xl overflow-hidden bg-gray-900 min-h-[360px] sm:min-h-[420px] flex items-center justify-center">
                    <template x-if="filePreviews.heroBackgroundImage">
                        <img :src="filePreviews.heroBackgroundImage" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!filePreviews.heroBackgroundImage && @js((bool) $existingHeroBg)">
                        <img src="{{ asset('storage/' . $existingHeroBg) }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-black/80"></div>

                    <div class="relative z-10 text-center px-6 py-10">
                        <p class="text-yellow-400 font-semibold tracking-[0.35em] uppercase text-xs sm:text-sm mb-3"
                           x-text="preview.heroTitle || 'Hero Title'"></p>
                        <h1 class="text-white font-display text-3xl sm:text-4xl md:text-5xl font-bold leading-tight max-w-md mx-auto break-words"
                            x-text="preview.heroSubtitle || 'Hero Subtitle'"></h1>
                        <p class="mt-4 text-white/60 text-xs sm:text-sm leading-relaxed max-w-sm mx-auto line-clamp-3"
                           x-text="preview.heroDescription || 'Hero description appears here.'"></p>
                    </div>

                    <div class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/80">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Homepage preview
                    </div>
                </div>

                {{-- ── FIELDS ── --}}
                <div class="space-y-5">

                    {{-- Background image uploader --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Background Image
                            <span class="text-xs text-gray-400 font-normal ml-1">— 5 MB max, auto-compressed</span>
                        </label>

                        @include('superadmin.pages.homepage.partials.image-uploader', [
                            'key'      => 'heroBackgroundImage',
                            'existing' => $existingHeroBg ? asset('storage/' . $existingHeroBg) : null,
                        ])
                        @error('heroBackgroundImage') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Title</label>
                        <input type="text" wire:model="heroTitle"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'heroTitle')">
                        @error('heroTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Subtitle</label>
                        <input type="text" wire:model="heroSubtitle"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'heroSubtitle')">
                        @error('heroSubtitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Description</label>
                        <textarea wire:model="heroDescription" rows="3"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                  x-on:input="bindField($event, 'heroDescription')"></textarea>
                        @error('heroDescription') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 2 — DISCOVER VICTORIAS CITY
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold">2</span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">Discover Section</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">The blue "About Victorias City" block with a 4-image gallery.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live preview
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- ── PREVIEW ── --}}
                <div class="relative rounded-2xl overflow-hidden min-h-[360px] sm:min-h-[420px] p-6 flex items-center justify-center">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-800 to-blue-950"></div>
                    <div class="absolute inset-0 opacity-20" style="background-image:url('https://images.unsplash.com/photo-1506744038136-46273834b3fb?auto=format&fit=crop&w=1600&q=80');background-size:cover;background-position:center;"></div>

                    <div class="relative z-10 grid grid-cols-1 sm:grid-cols-2 gap-4 w-full max-w-lg">
                        <div class="flex flex-col justify-center">
                            <h2 class="mb-2 text-xl sm:text-2xl font-display font-bold leading-snug text-white break-words"
                                x-text="preview.discoverTitle || 'Discover Title'"></h2>
                            <p class="text-xs sm:text-sm leading-relaxed text-blue-100 line-clamp-5"
                               x-text="preview.discoverDescription || 'Discover description appears here.'"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            @foreach([
                                ['key' => 'heroSideImage1', 'existing' => $existingSide1],
                                ['key' => 'heroSideImage2', 'existing' => $existingSide2],
                                ['key' => 'heroSideImage3', 'existing' => $existingSide3],
                                ['key' => 'heroSideImage4', 'existing' => $existingSide4],
                            ] as $img)
                                <div wire:key="preview-{{ $img['key'] }}" class="aspect-square rounded-xl overflow-hidden bg-white/10 backdrop-blur-sm border border-white/20">
                                    <template x-if="filePreviews['{{ $img['key'] }}']">
                                        <img :src="filePreviews['{{ $img['key'] }}']" alt="" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!filePreviews['{{ $img['key'] }}'] && @js((bool) $img['existing'])">
                                        <img src="{{ asset('storage/' . $img['existing']) }}" alt="" class="w-full h-full object-cover">
                                    </template>
                                    <template x-if="!filePreviews['{{ $img['key'] }}'] && !@js((bool) $img['existing'])">
                                        <div class="w-full h-full flex items-center justify-center">
                                            <svg class="w-6 h-6 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                        </div>
                                    </template>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/80">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Homepage preview
                    </div>
                </div>

                {{-- ── FIELDS ── --}}
                <div class="space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Gallery Images
                            <span class="text-xs text-gray-400 font-normal ml-1">— 4 tiles, 5 MB max each</span>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            @foreach([
                                ['key' => 'heroSideImage1', 'existing' => $existingSide1, 'label' => 'Nature'],
                                ['key' => 'heroSideImage2', 'existing' => $existingSide2, 'label' => 'Sanctuary'],
                                ['key' => 'heroSideImage3', 'existing' => $existingSide3, 'label' => 'Culture'],
                                ['key' => 'heroSideImage4', 'existing' => $existingSide4, 'label' => 'Discover'],
                            ] as $sideImage)
                                <div wire:key="side-{{ $sideImage['key'] }}">
                                    @include('superadmin.pages.homepage.partials.image-uploader', [
                                        'key'      => $sideImage['key'],
                                        'existing' => $sideImage['existing'] ? asset('storage/' . $sideImage['existing']) : null,
                                        'label'    => $sideImage['label'],
                                        'compact'  => true,
                                    ])
                                    @error($sideImage['key']) <span class="text-rose-500 dark:text-rose-400 text-[11px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discover Title</label>
                        <input type="text" wire:model="discoverTitle"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'discoverTitle')">
                        @error('discoverTitle') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discover Description</label>
                        <textarea wire:model="discoverDescription" rows="4"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                  x-on:input="bindField($event, 'discoverDescription')"></textarea>
                        @error('discoverDescription') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 3 — Non-editable sections (context)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 rounded-xl bg-gray-200 dark:bg-gray-700 p-2 text-gray-500 dark:text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Other homepage sections are data-driven</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                        The <span class="font-semibold">Popular Picks</span>, <span class="font-semibold">Most Visited Places</span>,
                        <span class="font-semibold">Explore Map</span>, <span class="font-semibold">Featured Events</span>, and
                        <span class="font-semibold">Plan Your Visit</span> sections are populated automatically from tenants,
                        events, and site settings — they don't have editable copy on this screen.
                    </p>
                </div>
            </div>
        </div>

        {{-- Sticky save bar --}}
        <div class="sticky bottom-4 z-10 flex justify-end">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md shadow-lg p-2 flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 px-3 text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Changes are applied to the homepage after saving.
                </div>
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="btn-primary active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed">
                    <span wire:loading.remove>Save Changes</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        Saving…
                    </span>
                </button>
            </div>
        </div>
    </form>
</div>

{{--
    Alpine component factory.
    Runs BEFORE Alpine boots (Livewire v4 `@script` semantics), so
    `x-data="homepageEditor()"` resolves at init time.
--}}
@script
<script>
    window.homepageEditor = function () {
        return {
            preview:      {},
            filePreviews: {
                heroBackgroundImage: null,
                heroSideImage1:      null,
                heroSideImage2:      null,
                heroSideImage3:      null,
                heroSideImage4:      null,
            },
            draggingKey:  null,

            init() {
                try {
                    this.preview = JSON.parse(this.$el.dataset.preview || '{}');
                } catch (e) {
                    this.preview = {};
                }

                // After a successful save, clear local file previews so the
                // DOM falls back to the freshly-saved server thumbnails.
                window.addEventListener('preview-reset', () => {
                    this.filePreviews = {
                        heroBackgroundImage: null,
                        heroSideImage1:      null,
                        heroSideImage2:      null,
                        heroSideImage3:      null,
                        heroSideImage4:      null,
                    };
                });
            },

            handleDrop(event, key) {
                this.draggingKey = null;
                const input = this.$refs['file-' + key];
                if (!input || !event.dataTransfer.files.length) return;
                input.files = event.dataTransfer.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            },

            previewFile(event, key) {
                const file = event.target.files?.[0];
                this.filePreviews[key] = file ? URL.createObjectURL(file) : null;
            },

            bindField(event, field) {
                this.preview[field] = event.target.value;
            },
        };
    };
</script>
@endscript