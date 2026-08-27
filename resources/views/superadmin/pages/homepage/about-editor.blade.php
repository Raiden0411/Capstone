{{-- resources/views/superadmin/pages/homepage/⚡about-editor.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use App\Models\SiteSetting;
use App\Models\Tenant;

new
#[Layout('superadmin.layouts.app')]
#[Title('About Page Editor')]
class extends Component
{
    use WithFileUploads;

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
        $this->heroSubheading = SiteSetting::getValue('about_hero_subheading', 'Welcome to Victorias City');
        $this->heroHeading    = SiteSetting::getValue('about_hero_heading', 'KADALAG-AN');
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

    public function save()
    {
        $this->validate([
            'heroImage'       => 'nullable|image|max:10240',
            'storyImage1'     => 'nullable|image|max:10240',
            'storyImage2'     => 'nullable|image|max:10240',
            'storyImage3'     => 'nullable|image|max:10240',
            'highlight1Image' => 'nullable|image|max:10240',
            'highlight2Image' => 'nullable|image|max:10240',
            'highlight3Image' => 'nullable|image|max:10240',
            'ctaBackgroundImage' => 'nullable|image|max:10240',
        ]);

        $this->updateImage('about_hero_image', $this->heroImage);
        $this->updateImage('about_story_image1', $this->storyImage1);
        $this->updateImage('about_story_image2', $this->storyImage2);
        $this->updateImage('about_story_image3', $this->storyImage3);
        $this->updateImage('about_highlight1_image', $this->highlight1Image);
        $this->updateImage('about_highlight2_image', $this->highlight2Image);
        $this->updateImage('about_highlight3_image', $this->highlight3Image);
        $this->updateImage('about_cta_background_image', $this->ctaBackgroundImage);

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

        $this->reset([
            'heroImage', 'storyImage1', 'storyImage2', 'storyImage3',
            'highlight1Image', 'highlight2Image', 'highlight3Image', 'ctaBackgroundImage',
        ]);

        session()->flash('message', 'About page updated successfully.');
    }

    private function updateImage(string $key, $file): void
    {
        if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $file->store('about', 'public');
            SiteSetting::setValue($key, $path);
        }
    }

    public function getPreviewUrl($property, $key, $default = null): ?string
    {
        if ($this->{$property}) {
            try {
                return $this->{$property}->temporaryUrl();
            } catch (\Exception $e) {
                // ignore
            }
        }

        $stored = SiteSetting::getValue($key);

        if ($stored) {
            return asset('storage/' . $stored);
        }

        return $default;
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">About Page Editor</h1>
        </div>
        <a href="{{ route('superadmin.dashboard') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
            &larr; Back to Dashboard
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Hero --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Hero Section</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Subheading</label>
                    <input type="text" wire:model="heroSubheading"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Heading</label>
                    <input type="text" wire:model="heroHeading"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Description</label>
                    <textarea wire:model="heroDescription" rows="3"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Image</label>
                    @if($preview = $this->getPreviewUrl('heroImage', 'about_hero_image', 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1920&q=80'))
                        <img src="{{ $preview }}" class="w-full h-40 object-cover rounded-lg mb-2 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="heroImage" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                </div>
            </div>
        </div>

        {{-- Story --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Story Section</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Heading</label>
                    <input type="text" wire:model="storyHeading"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image 1</label>
                    @if($preview = $this->getPreviewUrl('storyImage1', 'about_story_image1'))
                        <img src="{{ $preview }}" class="w-full h-32 object-cover rounded-lg mb-2 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="storyImage1" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paragraph 1</label>
                    <textarea wire:model="storyText1" rows="3"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Paragraph 2</label>
                    <textarea wire:model="storyText2" rows="3"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image 2</label>
                    @if($preview = $this->getPreviewUrl('storyImage2', 'about_story_image2'))
                        <img src="{{ $preview }}" class="w-full h-32 object-cover rounded-lg mb-2 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="storyImage2" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image 3</label>
                    @if($preview = $this->getPreviewUrl('storyImage3', 'about_story_image3'))
                        <img src="{{ $preview }}" class="w-full h-32 object-cover rounded-lg mb-2 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="storyImage3" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                </div>
            </div>
        </div>

        {{-- Highlights --}}
        @foreach([1, 2, 3] as $n)
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Highlight {{ $n }}</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Link Tenant (optional)</label>
                        <select wire:model="highlight{{ $n }}TenantId"
                                class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                            <option value="">None</option>
                            @foreach($this->tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title</label>
                        <input type="text" wire:model="highlight{{ $n }}Title"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Text</label>
                        <textarea wire:model="highlight{{ $n }}Text" rows="3"
                                  class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Image</label>
                        @if($preview = $this->getPreviewUrl("highlight{$n}Image", "about_highlight{$n}_image"))
                            <img src="{{ $preview }}" class="w-full h-32 object-cover rounded-lg mb-2 border border-gray-200 dark:border-gray-700">
                        @endif
                        <input type="file" wire:model="highlight{{ $n }}Image" accept="image/*"
                               class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    </div>
                </div>
            </div>
        @endforeach

        {{-- CTA --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-4">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Call to Action</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Heading</label>
                    <input type="text" wire:model="ctaHeading"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Background Image</label>
                    @if($preview = $this->getPreviewUrl('ctaBackgroundImage', 'about_cta_background_image'))
                        <img src="{{ $preview }}" class="w-full h-32 object-cover rounded-lg mb-2 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="ctaBackgroundImage" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Text</label>
                    <textarea wire:model="ctaText" rows="3"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 bg-primary-600 hover:bg-primary-700 text-white font-medium py-2.5 px-6 rounded-full shadow-lg shadow-primary-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>
</div>