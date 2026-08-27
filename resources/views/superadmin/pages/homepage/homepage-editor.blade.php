{{-- resources/views/superadmin/pages/homepage/⚡homepage-editor.blade.php --}}
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
        $this->existingHeroBg = SiteSetting::getValue('hero_background_image');
        $this->existingSide1  = SiteSetting::getValue('hero_side_image_1');
        $this->existingSide2  = SiteSetting::getValue('hero_side_image_2');
        $this->existingSide3  = SiteSetting::getValue('hero_side_image_3');
        $this->existingSide4  = SiteSetting::getValue('hero_side_image_4');

        $this->heroTitle       = SiteSetting::getValue('hero_title', 'Welcome to the North');
        $this->heroSubtitle    = SiteSetting::getValue('hero_subtitle', 'Victorias City');
        $this->heroDescription = SiteSetting::getValue('hero_description', 'Escape into a world where the air is scented with sugar cane and the mountains hum with hidden waterfalls. A breathtaking sanctuary in Negros Occidental.');
        $this->discoverTitle   = SiteSetting::getValue('discover_title', 'The City of Smiles & Heritage');
        $this->discoverDescription = SiteSetting::getValue('discover_description', 'Victorias is more than just an industrial hub; it is a blend of natural sanctuary, deep-rooted history, and warm hospitality. Experience the unique charm that makes this city a hidden gem in Western Visayas.');
    }

    public function save()
    {
        $this->validate([
            'heroBackgroundImage' => 'nullable|image|max:10240',
            'heroSideImage1'      => 'nullable|image|max:10240',
            'heroSideImage2'      => 'nullable|image|max:10240',
            'heroSideImage3'      => 'nullable|image|max:10240',
            'heroSideImage4'      => 'nullable|image|max:10240',
            'heroTitle'           => 'required|string|max:255',
            'heroSubtitle'        => 'required|string|max:255',
            'heroDescription'     => 'nullable|string|max:1000',
            'discoverTitle'       => 'required|string|max:255',
            'discoverDescription' => 'nullable|string|max:1000',
        ]);

        $this->updateImage('hero_background_image', $this->heroBackgroundImage);
        $this->updateImage('hero_side_image_1', $this->heroSideImage1);
        $this->updateImage('hero_side_image_2', $this->heroSideImage2);
        $this->updateImage('hero_side_image_3', $this->heroSideImage3);
        $this->updateImage('hero_side_image_4', $this->heroSideImage4);

        $textFields = [
            'hero_title'             => 'heroTitle',
            'hero_subtitle'          => 'heroSubtitle',
            'hero_description'       => 'heroDescription',
            'discover_title'         => 'discoverTitle',
            'discover_description'   => 'discoverDescription',
        ];

        foreach ($textFields as $key => $property) {
            SiteSetting::setValue($key, $this->{$property});
        }

        $this->existingHeroBg = SiteSetting::getValue('hero_background_image');
        $this->existingSide1  = SiteSetting::getValue('hero_side_image_1');
        $this->existingSide2  = SiteSetting::getValue('hero_side_image_2');
        $this->existingSide3  = SiteSetting::getValue('hero_side_image_3');
        $this->existingSide4  = SiteSetting::getValue('hero_side_image_4');

        $this->reset(
            'heroBackgroundImage',
            'heroSideImage1',
            'heroSideImage2',
            'heroSideImage3',
            'heroSideImage4'
        );

        session()->flash('message', 'Homepage updated successfully.');
    }

    private function updateImage($key, $uploadedFile)
    {
        if ($uploadedFile instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            $path = $uploadedFile->store('homepage', 'public');
            SiteSetting::setValue($key, $path);
        }
    }

    public function getImagePreviewUrl($propertyName, $existingValue)
    {
        if ($this->{$propertyName}) {
            try {
                return $this->{$propertyName}->temporaryUrl();
            } catch (\Exception $e) {
                // fallback
            }
        }

        if ($existingValue) {
            return asset('storage/' . $existingValue);
        }

        return null;
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6">

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Homepage Editor</h1>
        </div>
        <a href="{{ route('superadmin.dashboard') }}" wire:navigate
           class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
            &larr; Back to Dashboard
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Hero Images --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Hero Section Images</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Background image --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero Background</label>
                    @php $bgPreview = $this->getImagePreviewUrl('heroBackgroundImage', $existingHeroBg); @endphp
                    @if($bgPreview)
                        <img src="{{ $bgPreview }}" class="w-full h-40 object-cover rounded-lg mb-3 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="heroBackgroundImage" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    @error('heroBackgroundImage') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Side image 1 --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Side Image 1 (Nature)</label>
                    @php $side1Preview = $this->getImagePreviewUrl('heroSideImage1', $existingSide1); @endphp
                    @if($side1Preview)
                        <img src="{{ $side1Preview }}" class="w-full h-40 object-cover rounded-lg mb-3 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="heroSideImage1" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    @error('heroSideImage1') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Side image 2 --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Side Image 2 (The Sanctuary)</label>
                    @php $side2Preview = $this->getImagePreviewUrl('heroSideImage2', $existingSide2); @endphp
                    @if($side2Preview)
                        <img src="{{ $side2Preview }}" class="w-full h-40 object-cover rounded-lg mb-3 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="heroSideImage2" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    @error('heroSideImage2') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Side image 3 --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Side Image 3 (Culture)</label>
                    @php $side3Preview = $this->getImagePreviewUrl('heroSideImage3', $existingSide3); @endphp
                    @if($side3Preview)
                        <img src="{{ $side3Preview }}" class="w-full h-40 object-cover rounded-lg mb-3 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="heroSideImage3" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    @error('heroSideImage3') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Side image 4 --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Side Image 4 (Discover)</label>
                    @php $side4Preview = $this->getImagePreviewUrl('heroSideImage4', $existingSide4); @endphp
                    @if($side4Preview)
                        <img src="{{ $side4Preview }}" class="w-full h-40 object-cover rounded-lg mb-3 border border-gray-200 dark:border-gray-700">
                    @endif
                    <input type="file" wire:model="heroSideImage4" accept="image/*"
                           class="w-full text-sm text-gray-700 dark:text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-50 dark:file:bg-primary-500/20 file:text-primary-700 dark:file:text-primary-300 hover:file:bg-primary-100 dark:hover:file:bg-primary-500/30 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    @error('heroSideImage4') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        {{-- Text Content --}}
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6 shadow-sm space-y-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Text Content</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Title</label>
                    <input type="text" wire:model="heroTitle"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                    @error('heroTitle') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Subtitle</label>
                    <input type="text" wire:model="heroSubtitle"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                    @error('heroSubtitle') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Description</label>
                    <textarea wire:model="heroDescription" rows="3"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                    @error('heroDescription') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discover Title</label>
                    <input type="text" wire:model="discoverTitle"
                           class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition">
                    @error('discoverTitle') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discover Description</label>
                    <textarea wire:model="discoverDescription" rows="4"
                              class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 transition"></textarea>
                    @error('discoverDescription') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
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