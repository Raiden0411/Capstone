<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

new 
#[Layout('superadmin.layouts.app')]
#[Title('About Page Editor')]
class extends Component
{
    use WithFileUploads;

    // ── Text fields ──
    public $heroHeading;
    public $heroSubheading;
    public $heroDescription;
    public $storyHeading;
    public $storyText1;
    public $storyText2;
    public $ctaHeading;
    public $ctaText;

    // ── Highlight tenant IDs ──
    public $highlight1TenantId;
    public $highlight2TenantId;
    public $highlight3TenantId;

    // ── Override texts ──
    public $highlight1Title;
    public $highlight1Text;
    public $highlight2Title;
    public $highlight2Text;
    public $highlight3Title;
    public $highlight3Text;

    // ── Images (upload fields) ──
    public $heroImage;
    public $storyImage1;
    public $storyImage2;
    public $storyImage3;
    public $highlight1Image;
    public $highlight2Image;
    public $highlight3Image;

    // ── Existing previews ──
    public $existingHeroImage;
    public $existingStoryImage1;
    public $existingStoryImage2;
    public $existingStoryImage3;
    public $existingHighlight1Image;
    public $existingHighlight2Image;
    public $existingHighlight3Image;

    public function mount()
    {
        $this->heroHeading         = SiteSetting::getValue('about_hero_heading', 'VICTORIAS');
        $this->heroSubheading      = SiteSetting::getValue('about_hero_subheading', 'Region VI | Negros Occidental');
        $this->heroDescription     = SiteSetting::getValue('about_hero_description', 'Step into the "Sweet City of the North"...');
        $this->storyHeading        = SiteSetting::getValue('about_story_heading', 'Where Industry Meets the Wilderness');
        $this->storyText1          = SiteSetting::getValue('about_story_text1', 'Victorias City is widely recognized...');
        $this->storyText2          = SiteSetting::getValue('about_story_text2', 'To the east, the city rises...');
        $this->ctaHeading          = SiteSetting::getValue('about_cta_heading', 'Come and enjoy the wonderful city of Victorias');
        $this->ctaText             = SiteSetting::getValue('about_cta_text', 'Experience the natural beauty...');

        // Tenant IDs for highlights
        $this->highlight1TenantId = SiteSetting::getValue('about_highlight1_tenant_id');
        $this->highlight2TenantId = SiteSetting::getValue('about_highlight2_tenant_id');
        $this->highlight3TenantId = SiteSetting::getValue('about_highlight3_tenant_id');

        // Override texts
        $this->highlight1Title = SiteSetting::getValue('about_highlight1_title');
        $this->highlight1Text  = SiteSetting::getValue('about_highlight1_text');
        $this->highlight2Title = SiteSetting::getValue('about_highlight2_title');
        $this->highlight2Text  = SiteSetting::getValue('about_highlight2_text');
        $this->highlight3Title = SiteSetting::getValue('about_highlight3_title');
        $this->highlight3Text  = SiteSetting::getValue('about_highlight3_text');

        // Image previews
        $this->existingHeroImage       = SiteSetting::getValue('about_hero_image');
        $this->existingStoryImage1     = SiteSetting::getValue('about_story_image1');
        $this->existingStoryImage2     = SiteSetting::getValue('about_story_image2');
        $this->existingStoryImage3     = SiteSetting::getValue('about_story_image3');
        $this->existingHighlight1Image = SiteSetting::getValue('about_highlight1_image');
        $this->existingHighlight2Image = SiteSetting::getValue('about_highlight2_image');
        $this->existingHighlight3Image = SiteSetting::getValue('about_highlight3_image');
    }

    public function getTenantsProperty()
    {
        return Tenant::where('is_active', true)->orderBy('name')->get();
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
        ]);

        // Save images
        $this->updateImage('about_hero_image', $this->heroImage);
        $this->updateImage('about_story_image1', $this->storyImage1);
        $this->updateImage('about_story_image2', $this->storyImage2);
        $this->updateImage('about_story_image3', $this->storyImage3);
        $this->updateImage('about_highlight1_image', $this->highlight1Image);
        $this->updateImage('about_highlight2_image', $this->highlight2Image);
        $this->updateImage('about_highlight3_image', $this->highlight3Image);

        // Save text fields
        $textFields = [
            'about_hero_heading'     => 'heroHeading',
            'about_hero_subheading'  => 'heroSubheading',
            'about_hero_description' => 'heroDescription',
            'about_story_heading'    => 'storyHeading',
            'about_story_text1'      => 'storyText1',
            'about_story_text2'      => 'storyText2',
            'about_cta_heading'      => 'ctaHeading',
            'about_cta_text'         => 'ctaText',
        ];
        foreach ($textFields as $key => $prop) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $this->{$prop}]);
        }

        // Save tenant IDs
        $tenantFields = [
            'about_highlight1_tenant_id' => 'highlight1TenantId',
            'about_highlight2_tenant_id' => 'highlight2TenantId',
            'about_highlight3_tenant_id' => 'highlight3TenantId',
        ];
        foreach ($tenantFields as $key => $prop) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $this->{$prop}]);
        }

        // Save override texts (empty means use tenant's own data)
        $overrideFields = [
            'about_highlight1_title' => 'highlight1Title',
            'about_highlight1_text'  => 'highlight1Text',
            'about_highlight2_title' => 'highlight2Title',
            'about_highlight2_text'  => 'highlight2Text',
            'about_highlight3_title' => 'highlight3Title',
            'about_highlight3_text'  => 'highlight3Text',
        ];
        foreach ($overrideFields as $key => $prop) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $this->{$prop} ?? '']);
        }

        // Refresh previews
        $this->mount();

        session()->flash('message', 'About page updated successfully.');
    }

    private function updateImage(string $key, $file)
    {
        if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $file->store('about', 'public');
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $path]);
        }
    }

    private function previewUrl($propertyName, $existing)
    {
        if ($this->{$propertyName}) {
            try { return $this->{$propertyName}->temporaryUrl(); } catch (\Exception $e) {}
        }
        return $existing ? asset('storage/' . $existing) : null;
    }
};
?>

<div class="p-6 max-w-5xl mx-auto space-y-8 text-white">
    <h1 class="text-3xl font-bold">About Page Editor</h1>

    @if (session()->has('message'))
        <div class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-green-300 text-sm">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        {{-- Hero Section --}}
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Hero Section</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Heading</label>
                    <input type="text" wire:model="heroHeading" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Subheading</label>
                    <input type="text" wire:model="heroSubheading" class="input-glass text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea wire:model="heroDescription" rows="2" class="input-glass text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Background</label>
                    @php $url = $this->previewUrl('heroImage', $existingHeroImage); @endphp
                    @if($url) <img src="{{ $url }}" class="w-full h-32 object-cover rounded-lg mb-2"> @endif
                    <input type="file" wire:model="heroImage" class="input-glass text-sm">
                </div>
            </div>
        </div>

        {{-- Story Section --}}
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Story Section</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Story Heading</label>
                    <input type="text" wire:model="storyHeading" class="input-glass text-sm">
                </div>
                <div></div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Story Text 1</label>
                    <textarea wire:model="storyText1" rows="4" class="input-glass text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Story Text 2</label>
                    <textarea wire:model="storyText2" rows="4" class="input-glass text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Story Image 1</label>
                    @php $url = $this->previewUrl('storyImage1', $existingStoryImage1); @endphp
                    @if($url) <img src="{{ $url }}" class="w-full h-32 object-cover rounded-lg mb-2"> @endif
                    <input type="file" wire:model="storyImage1" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Story Image 2</label>
                    @php $url = $this->previewUrl('storyImage2', $existingStoryImage2); @endphp
                    @if($url) <img src="{{ $url }}" class="w-full h-32 object-cover rounded-lg mb-2"> @endif
                    <input type="file" wire:model="storyImage2" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Story Image 3</label>
                    @php $url = $this->previewUrl('storyImage3', $existingStoryImage3); @endphp
                    @if($url) <img src="{{ $url }}" class="w-full h-32 object-cover rounded-lg mb-2"> @endif
                    <input type="file" wire:model="storyImage3" class="input-glass text-sm">
                </div>
            </div>
        </div>

        {{-- Highlights (tenant-linked) --}}
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Highlights (linked to Businesses)</h2>
            @foreach([1,2,3] as $n)
                @php
                    $tenantIdProp = "highlight{$n}TenantId";
                    $titleProp    = "highlight{$n}Title";
                    $textProp     = "highlight{$n}Text";
                    $imgProp      = "highlight{$n}Image";
                    $existingProp = "existingHighlight{$n}Image";
                @endphp
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-white/10 pt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Highlight {{ $n }} – Tenant</label>
                        <select wire:model="{{ $tenantIdProp }}" class="input-glass text-sm">
                            <option value="">-- Select a business --</option>
                            @foreach($this->tenants as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Override Title (optional)</label>
                        <input type="text" wire:model="{{ $titleProp }}" class="input-glass text-sm" placeholder="Leave blank to use business name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Override Text (optional)</label>
                        <textarea wire:model="{{ $textProp }}" rows="3" class="input-glass text-sm" placeholder="Leave blank to use business description"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Override Image (optional)</label>
                        @php $url = $this->previewUrl($imgProp, $this->{$existingProp}); @endphp
                        @if($url) <img src="{{ $url }}" class="w-full h-32 object-cover rounded-lg mb-2"> @endif
                        <input type="file" wire:model="{{ $imgProp }}" class="input-glass text-sm">
                    </div>
                </div>
            @endforeach
        </div>

        {{-- CTA Section --}}
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Call to Action</h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">CTA Heading</label>
                    <input type="text" wire:model="ctaHeading" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">CTA Text</label>
                    <textarea wire:model="ctaText" rows="2" class="input-glass text-sm"></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="bg-brand-600 hover:bg-brand-500 text-white py-3 px-8 rounded-xl font-semibold transition">
            Save All Changes
        </button>
    </form>
</div>