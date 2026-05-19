<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Homepage Editor')]
class extends Component
{
    use WithFileUploads;

    public $heroBackgroundImage;
    public $heroSideImage1;
    public $heroSideImage2;
    public $heroSideImage3;

    public $heroTitle;
    public $heroSubtitle;
    public $heroDescription;
    public $discoverTitle;
    public $discoverDescription;

    public $existingHeroBg;
    public $existingSide1;
    public $existingSide2;
    public $existingSide3;

    public function mount()
    {
        $this->existingHeroBg = SiteSetting::getValue('hero_background_image');
        $this->existingSide1  = SiteSetting::getValue('hero_side_image_1');
        $this->existingSide2  = SiteSetting::getValue('hero_side_image_2');
        $this->existingSide3  = SiteSetting::getValue('hero_side_image_3');

        $this->heroTitle       = SiteSetting::getValue('hero_title', 'Welcome to the North');
        $this->heroSubtitle    = SiteSetting::getValue('hero_subtitle', 'Victorias City');
        $this->heroDescription = SiteSetting::getValue('hero_description', 'Escape into a world...');
        $this->discoverTitle   = SiteSetting::getValue('discover_title', 'The City of Smiles & Heritage');
        $this->discoverDescription = SiteSetting::getValue('discover_description', 'Victorias is more than just an industrial hub...');
    }

    public function save()
    {
        $this->validate([
            'heroBackgroundImage' => 'nullable|image|max:10240',
            'heroSideImage1'      => 'nullable|image|max:10240',
            'heroSideImage2'      => 'nullable|image|max:10240',
            'heroSideImage3'      => 'nullable|image|max:10240',
        ]);

        $this->updateImage('hero_background_image', $this->heroBackgroundImage);
        $this->updateImage('hero_side_image_1', $this->heroSideImage1);
        $this->updateImage('hero_side_image_2', $this->heroSideImage2);
        $this->updateImage('hero_side_image_3', $this->heroSideImage3);

        $textFields = [
            'hero_title'             => 'heroTitle',
            'hero_subtitle'          => 'heroSubtitle',
            'hero_description'       => 'heroDescription',
            'discover_title'         => 'discoverTitle',
            'discover_description'   => 'discoverDescription',
        ];

        foreach ($textFields as $key => $property) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $this->{$property}]);
        }

        $this->existingHeroBg = SiteSetting::getValue('hero_background_image');
        $this->existingSide1  = SiteSetting::getValue('hero_side_image_1');
        $this->existingSide2  = SiteSetting::getValue('hero_side_image_2');
        $this->existingSide3  = SiteSetting::getValue('hero_side_image_3');

        $this->reset('heroBackgroundImage', 'heroSideImage1', 'heroSideImage2', 'heroSideImage3');

        session()->flash('message', 'Homepage updated successfully.');
    }

    private function updateImage($key, $uploadedFile)
    {
        if ($uploadedFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $uploadedFile->store('homepage', 'public');
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $path]);
        }
    }

    /**
     * Get the correct image URL for preview.
     */
    public function getImagePreviewUrl($propertyName, $existingValue)
    {
        // If a new file has been selected (temporary), use its temporary URL
        if ($this->{$propertyName}) {
            try {
                return $this->{$propertyName}->temporaryUrl();
            } catch (\Exception $e) {
                // fallback
            }
        }
        // Otherwise, return the stored image using the simple asset() helper
        if ($existingValue) {
            return asset('storage/' . $existingValue);
        }
        return null;
    }
};
?>

<div class="p-6 max-w-5xl mx-auto space-y-8 text-white">
    <h1 class="text-3xl font-bold">Homepage Editor</h1>

    @if (session()->has('message'))
        <div class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-green-300 text-sm">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        {{-- Hero Images --}}
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Hero Section Images</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Background image --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Hero Background</label>
                    @php $bgPreview = $this->getImagePreviewUrl('heroBackgroundImage', $existingHeroBg); @endphp
                    @if($bgPreview)
                        <img src="{{ $bgPreview }}" class="w-full h-40 object-cover rounded-lg mb-3">
                    @endif
                    <input type="file" wire:model="heroBackgroundImage" class="input-glass text-sm">
                    @error('heroBackgroundImage') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Side image 1 --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Side Image 1 (Nature)</label>
                    @php $side1Preview = $this->getImagePreviewUrl('heroSideImage1', $existingSide1); @endphp
                    @if($side1Preview)
                        <img src="{{ $side1Preview }}" class="w-full h-40 object-cover rounded-lg mb-3">
                    @endif
                    <input type="file" wire:model="heroSideImage1" class="input-glass text-sm">
                </div>

                {{-- Side image 2 --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Side Image 2 (The Sanctuary)</label>
                    @php $side2Preview = $this->getImagePreviewUrl('heroSideImage2', $existingSide2); @endphp
                    @if($side2Preview)
                        <img src="{{ $side2Preview }}" class="w-full h-40 object-cover rounded-lg mb-3">
                    @endif
                    <input type="file" wire:model="heroSideImage2" class="input-glass text-sm">
                </div>

                {{-- Side image 3 --}}
                <div>
                    <label class="block text-sm font-medium mb-2">Side Image 3 (Culture)</label>
                    @php $side3Preview = $this->getImagePreviewUrl('heroSideImage3', $existingSide3); @endphp
                    @if($side3Preview)
                        <img src="{{ $side3Preview }}" class="w-full h-40 object-cover rounded-lg mb-3">
                    @endif
                    <input type="file" wire:model="heroSideImage3" class="input-glass text-sm">
                </div>
            </div>
        </div>

        {{-- Text Content --}}
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Text Content</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Title</label>
                    <input type="text" wire:model="heroTitle" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Subtitle</label>
                    <input type="text" wire:model="heroSubtitle" class="input-glass text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Hero Description</label>
                    <textarea wire:model="heroDescription" rows="3" class="input-glass text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Discover Title</label>
                    <input type="text" wire:model="discoverTitle" class="input-glass text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Discover Description</label>
                    <textarea wire:model="discoverDescription" rows="4" class="input-glass text-sm"></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="bg-brand-600 hover:bg-brand-500 text-white py-3 px-8 rounded-xl font-semibold transition">
            Save Changes
        </button>
    </form>
</div>
