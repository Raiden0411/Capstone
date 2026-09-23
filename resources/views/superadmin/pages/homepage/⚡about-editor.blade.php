{{-- resources/views/superadmin/pages/homepage/⚡about-editor.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use App\Traits\HandlesImageUploads;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

new
#[Layout('superadmin.layouts.app')]
#[Title('About Page Editor')]
class extends Component
{
    use WithFileUploads;
    use HandlesImageUploads;

    /** Image contexts that this editor manages. */
    private const IMAGE_KEYS = [
        'about_hero_image',
        'about_story_image1',
        'about_story_image2',
        'about_story_image3',
        'about_highlight1_image',
        'about_highlight2_image',
        'about_highlight3_image',
        'about_cta_background_image',
    ];

    // Hero
    public $heroImage;
    public $heroSubheading;
    public $heroHeading;
    public $heroDescription;

    // Story
    public $storyHeading;
    public $storyText1;
    public $storyText2;
    public $storyImage1;
    public $storyImage2;
    public $storyImage3;

    // Highlights
    public $highlight1TenantId;
    public $highlight1Title;
    public $highlight1Text;
    public $highlight1Image;

    public $highlight2TenantId;
    public $highlight2Title;
    public $highlight2Text;
    public $highlight2Image;

    public $highlight3TenantId;
    public $highlight3Title;
    public $highlight3Text;
    public $highlight3Image;

    // CTA
    public $ctaHeading;
    public $ctaText;
    public $ctaBackgroundImage;

    public function mount()
    {
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->heroSubheading  = SiteSetting::getValue('about_hero_subheading', 'Welcome to Victorias City');
        $this->heroHeading     = SiteSetting::getValue('about_hero_heading', 'KADALAG-AN');
        $this->heroDescription = SiteSetting::getValue('about_hero_description', '');

        $this->storyHeading = SiteSetting::getValue('about_story_heading', 'The Story of the City');
        $this->storyText1   = SiteSetting::getValue('about_story_text1', '');
        $this->storyText2   = SiteSetting::getValue('about_story_text2', '');

        $this->highlight1TenantId = SiteSetting::getValue('about_highlight1_tenant_id');
        $this->highlight1Title    = SiteSetting::getValue('about_highlight1_title', 'Gawahon Eco-Park');
        $this->highlight1Text     = SiteSetting::getValue('about_highlight1_text', '');

        $this->highlight2TenantId = SiteSetting::getValue('about_highlight2_tenant_id');
        $this->highlight2Title    = SiteSetting::getValue('about_highlight2_title', 'The Angry Christ Mural');
        $this->highlight2Text     = SiteSetting::getValue('about_highlight2_text', '');

        $this->highlight3TenantId = SiteSetting::getValue('about_highlight3_tenant_id');
        $this->highlight3Title    = SiteSetting::getValue('about_highlight3_title', 'The VMC Kingdom');
        $this->highlight3Text     = SiteSetting::getValue('about_highlight3_text', '');

        $this->ctaHeading = SiteSetting::getValue('about_cta_heading', 'Plan Your Visit');
        $this->ctaText    = SiteSetting::getValue('about_cta_text', '');
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::orderBy('name')->get();
    }

    /**
     * Batched, cached read of every image URL this page manages. One
     * cache entry instead of ~30 individual reads spread across the
     * view.
     *
     * @return array<string, string|null>
     */
    #[Computed]
    public function imageUrls(): array
    {
        $stored = SiteSetting::getBatch(self::IMAGE_KEYS, 'about_editor_images');

        $urls = [];
        foreach (self::IMAGE_KEYS as $key) {
            $path = $stored[$key] ?? null;
            $urls[$key] = (is_string($path) && $path !== '') ? asset('storage/' . $path) : null;
        }

        return $urls;
    }

    /**
     * Persist every field on this screen.
     *
     * On success, redirect back to this same route. Rationale is
     * unchanged from the previous version — the short version is: a
     * redirect re-mounts the component, recomputes imageUrls(), and
     * renders fresh src attributes on first paint. Sidesteps the
     * stale-preview bug entirely (Rule 133).
     */
    public function save()
    {
        // Livewire update requests bypass route middleware — re-check
        // per action. Rule 16 (four-layer authorization).
        abort_unless(Auth::user()?->hasRole('super-admin'), 403, 'Super-admin access only.');

        $this->validate([
            // 5 MB ceiling, raster only. Output compressed to ≤2 MB via
            // the 'site' context.
            'heroImage'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'storyImage1'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'storyImage2'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'storyImage3'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'highlight1Image'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'highlight2Image'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'highlight3Image'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'ctaBackgroundImage' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        try {
            $this->updateImage('about_hero_image', $this->heroImage);
            $this->updateImage('about_story_image1', $this->storyImage1);
            $this->updateImage('about_story_image2', $this->storyImage2);
            $this->updateImage('about_story_image3', $this->storyImage3);
            $this->updateImage('about_highlight1_image', $this->highlight1Image);
            $this->updateImage('about_highlight2_image', $this->highlight2Image);
            $this->updateImage('about_highlight3_image', $this->highlight3Image);
            $this->updateImage('about_cta_background_image', $this->ctaBackgroundImage);
        } catch (\Throwable $e) {
            Log::error('About page image save failed', [
                'actor_id' => Auth::id(),
                'error'    => $e->getMessage(),
            ]);

            session()->flash('error', 'Failed to upload one or more images. Please try again.');
            return null;
        }

        $textFields = [
            'about_hero_subheading'      => 'heroSubheading',
            'about_hero_heading'         => 'heroHeading',
            'about_hero_description'     => 'heroDescription',
            'about_story_heading'        => 'storyHeading',
            'about_story_text1'          => 'storyText1',
            'about_story_text2'          => 'storyText2',
            'about_highlight1_tenant_id' => 'highlight1TenantId',
            'about_highlight1_title'     => 'highlight1Title',
            'about_highlight1_text'      => 'highlight1Text',
            'about_highlight2_tenant_id' => 'highlight2TenantId',
            'about_highlight2_title'     => 'highlight2Title',
            'about_highlight2_text'      => 'highlight2Text',
            'about_highlight3_tenant_id' => 'highlight3TenantId',
            'about_highlight3_title'     => 'highlight3Title',
            'about_highlight3_text'      => 'highlight3Text',
            'about_cta_heading'          => 'ctaHeading',
            'about_cta_text'             => 'ctaText',
        ];

        foreach ($textFields as $key => $property) {
            SiteSetting::setValue($key, $this->{$property} ?? '');
        }

        // Clear the uploaded-file properties so the redirected re-mount
        // starts from a clean slate with no stale upload handles.
        $this->reset([
            'heroImage', 'storyImage1', 'storyImage2', 'storyImage3',
            'highlight1Image', 'highlight2Image', 'highlight3Image', 'ctaBackgroundImage',
        ]);

        session()->flash('message', 'About page updated successfully.');

        return $this->redirect(route('superadmin.about.editor'), navigate: true);
    }

    /**
     * Golden sequence: store + compress the new file FIRST, then write
     * the DB row, then delete the old file AFTER success. If anything
     * fails, the new file is cleaned up and the old file is preserved.
     */
    private function updateImage(string $key, $file): void
    {
        if (! $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            return;
        }

        $oldPath = SiteSetting::getValue($key);
        $newPath = null;

        try {
            // Compresses against the 'site' context (≤2 MB / 2560×1440).
            $newPath = $this->storeImage($file, 'about', 'public', 'site');

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

        if ($oldPath
            && $oldPath !== $newPath
            && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    public function getStoredImageUrl(string $key, ?string $default = null): ?string
    {
        return $this->imageUrls[$key] ?? $default;
    }
};
?>

{{--
    Root element carries the initial live-preview state via a data-* attribute
    encoded with JSON_HEX_* flags, so quotes inside the values can never break
    the HTML attribute.

    The Alpine factory `aboutEditor()` is defined in resources/js/app.js
    (Rule 119). It is NOT in an @script/@endscript block here — that wrapper
    is dead syntax in Livewire v4 (Rule 120).
--}}
<div
    x-data="aboutEditor()"
    data-preview="{{ json_encode([
        'heroSubheading'   => $heroSubheading,
        'heroHeading'      => $heroHeading,
        'heroDescription'  => $heroDescription,
        'storyHeading'     => $storyHeading,
        'storyText1'       => $storyText1,
        'storyText2'       => $storyText2,
        'highlight1Title'  => $highlight1Title,
        'highlight1Text'   => $highlight1Text,
        'highlight2Title'  => $highlight2Title,
        'highlight2Text'   => $highlight2Text,
        'highlight3Title'  => $highlight3Title,
        'highlight3Text'   => $highlight3Text,
        'ctaHeading'       => $ctaHeading,
        'ctaText'          => $ctaText,
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
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">About Page Editor</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Edit each section below. Previews mirror the live About page layout.
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
             SECTION 1 — HERO  (mirrors the About hero banner)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold">1</span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">Hero Section</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">The banner at the top of the About page.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live preview
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- PREVIEW --}}
                <div class="relative rounded-2xl overflow-hidden bg-gray-900 min-h-[320px] flex items-center justify-center">
                    <template x-if="filePreviews.heroImage">
                        <img :src="filePreviews.heroImage" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!filePreviews.heroImage && @js((bool) $this->getStoredImageUrl('about_hero_image'))">
                        <img src="{{ $this->getStoredImageUrl('about_hero_image') }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!filePreviews.heroImage && !@js((bool) $this->getStoredImageUrl('about_hero_image'))">
                        <img src="https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <div class="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70"></div>

                    <div class="relative z-10 text-center px-6 py-8">
                        <h1 class="text-lg sm:text-xl font-bold tracking-widest text-white uppercase break-words"
                            x-text="preview.heroSubheading || 'Hero Subheading'"></h1>
                        <h2 class="mt-3 text-3xl sm:text-4xl md:text-5xl font-display font-bold tracking-wide text-amber-300 break-words"
                            x-text="preview.heroHeading || 'HEADING'"></h2>
                        <p class="mt-4 max-w-sm mx-auto text-xs sm:text-sm text-white/90 leading-relaxed line-clamp-3"
                           x-text="preview.heroDescription || 'Hero description appears here.'"></p>
                        <span class="mt-6 inline-flex items-center gap-2 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white bg-primary-600 rounded-full shadow-lg">
                            Plan your Visit Now
                        </span>
                    </div>

                    <div class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/80">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        About page preview
                    </div>
                </div>

                {{-- FIELDS --}}
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Subheading</label>
                        <input type="text" wire:model="heroSubheading"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'heroSubheading')">
                        @error('heroSubheading') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Heading</label>
                        <input type="text" wire:model="heroHeading"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'heroHeading')">
                        @error('heroHeading') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Description</label>
                        <textarea wire:model="heroDescription" rows="3"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                  x-on:input="bindField($event, 'heroDescription')"></textarea>
                        @error('heroDescription') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Background Image
                            <span class="text-xs text-gray-400 font-normal ml-1">— 5 MB max, auto-compressed</span>
                        </label>

                        @include('superadmin.pages.homepage.partials.image-uploader', [
                            'key'      => 'heroImage',
                            'existing' => $this->getStoredImageUrl('about_hero_image'),
                        ])
                        @error('heroImage') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 2 — STORY  (mirrors the "Story of the City" block)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold">2</span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">Story Section</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">The "Story of the City" block with a lead image.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live preview
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- PREVIEW --}}
                <div class="rounded-2xl overflow-hidden bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-5 sm:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                        <div>
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-4 h-4 bg-amber-400 rounded-full shadow-sm shrink-0"></span>
                                <h3 class="text-base font-bold tracking-wider text-gray-900 dark:text-white uppercase break-words"
                                    x-text="preview.storyHeading || 'Story Heading'"></h3>
                            </div>
                            <div class="space-y-2 text-xs text-gray-600 dark:text-gray-300 leading-relaxed">
                                <p x-text="preview.storyText1 || 'First paragraph appears here.'"></p>
                                <p x-show="preview.storyText2" x-text="preview.storyText2"></p>
                            </div>
                        </div>

                        <div class="aspect-[4/3] rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-800">
                            <template x-if="filePreviews.storyImage1">
                                <img :src="filePreviews.storyImage1" alt="" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!filePreviews.storyImage1 && @js((bool) $this->getStoredImageUrl('about_story_image1'))">
                                <img src="{{ $this->getStoredImageUrl('about_story_image1') }}" alt="" class="w-full h-full object-cover">
                            </template>
                            <template x-if="!filePreviews.storyImage1 && !@js((bool) $this->getStoredImageUrl('about_story_image1'))">
                                <div class="w-full h-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- FIELDS --}}
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Heading</label>
                        <input type="text" wire:model="storyHeading"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'storyHeading')">
                        @error('storyHeading') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paragraph 1</label>
                        <textarea wire:model="storyText1" rows="3"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                  x-on:input="bindField($event, 'storyText1')"></textarea>
                        @error('storyText1') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paragraph 2 <span class="text-xs text-gray-400 font-normal">(optional)</span></label>
                        <textarea wire:model="storyText2" rows="3"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                  x-on:input="bindField($event, 'storyText2')"></textarea>
                        @error('storyText2') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Story Images
                            <span class="text-xs text-gray-400 font-normal ml-1">— the first is the lead; all three appear in the gallery</span>
                        </label>

                        <div class="grid grid-cols-3 gap-3">
                            @foreach([
                                ['prop' => 'storyImage1', 'key' => 'about_story_image1', 'label' => 'Lead'],
                                ['prop' => 'storyImage2', 'key' => 'about_story_image2', 'label' => 'Gallery'],
                                ['prop' => 'storyImage3', 'key' => 'about_story_image3', 'label' => 'Gallery'],
                            ] as $i)
                                <div wire:key="story-image-{{ $i['prop'] }}">
                                    @include('superadmin.pages.homepage.partials.image-uploader', [
                                        'key'      => $i['prop'],
                                        'existing' => $this->getStoredImageUrl($i['key']),
                                        'label'    => $i['label'],
                                        'compact'  => true,
                                    ])
                                    @error($i['prop']) <span class="text-rose-500 dark:text-rose-400 text-[11px] mt-1 block">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 3 — GALLERY CAROUSEL (auto-derived — informational only)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 bg-gray-50/60 dark:bg-gray-800/40 p-5">
            <div class="flex items-start gap-3">
                <div class="shrink-0 rounded-xl bg-gray-200 dark:bg-gray-700 p-2 text-gray-500 dark:text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-200">Gallery carousel is auto-generated</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 leading-relaxed">
                        The image carousel section shows every uploaded image from this page in this order:
                        <span class="font-semibold">Story Lead</span>,
                        <span class="font-semibold">Story Gallery 1</span>,
                        <span class="font-semibold">Story Gallery 2</span>,
                        <span class="font-semibold">Highlight 1</span>,
                        <span class="font-semibold">Highlight 2</span>,
                        <span class="font-semibold">Highlight 3</span>.
                        Upload images in any of the sections below to populate it.
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 4 — HIGHLIGHTS (3 cards, alternating layout)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold">4</span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">Highlights</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Three alternating feature cards. Link each to a tenant to pull in a "Visit" button.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live preview
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- PREVIEW — stacked alternating cards --}}
                <div class="rounded-2xl overflow-hidden bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-5 space-y-5">
                    @foreach([1, 2, 3] as $n)
                        @php $reverse = ($n + 1) % 2 === 0; @endphp
                        <div wire:key="highlight-preview-{{ $n }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                            <div class="aspect-[4/3] rounded-xl overflow-hidden bg-gray-200 dark:bg-gray-800 {{ $reverse ? 'sm:order-2' : '' }}">
                                <template x-if="filePreviews.highlight{{ $n }}Image">
                                    <img :src="filePreviews.highlight{{ $n }}Image" alt="" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!filePreviews.highlight{{ $n }}Image && @js((bool) $this->getStoredImageUrl('about_highlight' . $n . '_image'))">
                                    <img src="{{ $this->getStoredImageUrl('about_highlight' . $n . '_image') }}" alt="" class="w-full h-full object-cover">
                                </template>
                                <template x-if="!filePreviews.highlight{{ $n }}Image && !@js((bool) $this->getStoredImageUrl('about_highlight' . $n . '_image'))">
                                    <div class="w-full h-full flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                    </div>
                                </template>
                            </div>
                            <div class="{{ $reverse ? 'sm:order-1' : '' }}">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-[10px] font-bold mb-2">{{ $n }}</span>
                                <h3 class="text-base font-bold text-primary-600 dark:text-blue-400 mb-2 break-words"
                                    x-text="preview.highlight{{ $n }}Title || 'Highlight title'"></h3>
                                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed line-clamp-3"
                                   x-text="preview.highlight{{ $n }}Text || 'Highlight description appears here.'"></p>
                            </div>
                        </div>
                        @if($n < 3)
                            <div class="h-px bg-gray-200 dark:bg-gray-700"></div>
                        @endif
                    @endforeach
                </div>

                {{-- FIELDS — 3 sub-groups --}}
                <div class="space-y-6">
                    @foreach([1, 2, 3] as $n)
                        <div wire:key="highlight-fields-{{ $n }}" class="rounded-xl border border-gray-200 dark:border-gray-700 p-4 space-y-4 bg-gray-50/50 dark:bg-gray-900/40">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-primary-600 text-white text-[11px] font-bold">{{ $n }}</span>
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Highlight {{ $n }}</h3>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Link Tenant (optional)</label>
                                <select wire:model="highlight{{ $n }}TenantId"
                                        class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                                    <option value="">None — use the title and text below as-is</option>
                                    @foreach($this->tenants as $tenant)
                                        <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Title</label>
                                <input type="text" wire:model="highlight{{ $n }}Title"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                       x-on:input="bindField($event, 'highlight{{ $n }}Title')">
                                @error("highlight{$n}Title") <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Text</label>
                                <textarea wire:model="highlight{{ $n }}Text" rows="3"
                                          class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg py-2 px-3 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                          x-on:input="bindField($event, 'highlight{{ $n }}Text')"></textarea>
                                @error("highlight{$n}Text") <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Image</label>
                                @include('superadmin.pages.homepage.partials.image-uploader', [
                                    'key'      => 'highlight' . $n . 'Image',
                                    'existing' => $this->getStoredImageUrl('about_highlight' . $n . '_image'),
                                ])
                                @error("highlight{$n}Image") <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 5 — CTA (Plan Your Visit)
             ═══════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-5 sm:p-6 shadow-sm space-y-5">

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-300 text-xs font-bold">5</span>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white leading-tight">Plan Your Visit CTA</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400">The closing call-to-action at the bottom of the page.</p>
                    </div>
                </div>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse motion-reduce:animate-none"></span>
                    Live preview
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- PREVIEW --}}
                <div class="relative rounded-2xl overflow-hidden min-h-[280px] flex items-center justify-center bg-gray-900">
                    <template x-if="filePreviews.ctaBackgroundImage">
                        <img :src="filePreviews.ctaBackgroundImage" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!filePreviews.ctaBackgroundImage && @js((bool) $this->getStoredImageUrl('about_cta_background_image'))">
                        <img src="{{ $this->getStoredImageUrl('about_cta_background_image') }}" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <template x-if="!filePreviews.ctaBackgroundImage && !@js((bool) $this->getStoredImageUrl('about_cta_background_image'))">
                        <img src="https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80" alt="" class="absolute inset-0 w-full h-full object-cover">
                    </template>
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-black/50"></div>

                    <div class="relative z-10 text-center px-6 py-8 max-w-sm">
                        <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3 break-words"
                            x-text="preview.ctaHeading || 'CTA Heading'"></h2>
                        <p class="text-xs sm:text-sm text-gray-200 mb-5 line-clamp-3"
                           x-text="preview.ctaText || 'CTA description appears here.'"></p>
                        <span class="inline-flex items-center gap-2 px-6 py-3 text-xs sm:text-sm font-semibold text-white bg-primary-600 rounded-full shadow-lg">
                            Plan your Visit Now
                        </span>
                    </div>

                    <div class="absolute bottom-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur-md px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-white/80">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        About page preview
                    </div>
                </div>

                {{-- FIELDS --}}
                <div class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Heading</label>
                        <input type="text" wire:model="ctaHeading"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                               x-on:input="bindField($event, 'ctaHeading')">
                        @error('ctaHeading') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Text</label>
                        <textarea wire:model="ctaText" rows="3"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"
                                  x-on:input="bindField($event, 'ctaText')"></textarea>
                        @error('ctaText') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Background Image
                            <span class="text-xs text-gray-400 font-normal ml-1">— 5 MB max, auto-compressed</span>
                        </label>

                        @include('superadmin.pages.homepage.partials.image-uploader', [
                            'key'      => 'ctaBackgroundImage',
                            'existing' => $this->getStoredImageUrl('about_cta_background_image'),
                        ])
                        @error('ctaBackgroundImage') <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Sticky save bar --}}
        <div class="sticky bottom-4 z-10 flex justify-end">
            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-900/95 backdrop-blur-md shadow-lg p-2 flex items-center gap-3">
                <div class="hidden sm:flex items-center gap-2 px-3 text-xs text-gray-500 dark:text-gray-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Changes are applied to the About page after saving.
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