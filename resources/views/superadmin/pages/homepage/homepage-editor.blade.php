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
           class="btn-secondary active:scale-95 transition-transform focus-visible:ring-2 focus-visible:ring-primary-500/50 inline-flex items-center justify-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Dashboard
        </a>
    </div>

    <form wire:submit="save" class="space-y-6">

        {{-- Hero Images --}}
        <div class="card p-6 space-y-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Hero Section Images</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Background image --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Hero Background</label>
                    <div
                        x-data="{ previewUrl: null, dragging: false }"
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                        :class="dragging ? 'border-primary-600 bg-blue-50 dark:bg-blue-500/10' : 'border-gray-300 dark:border-gray-600'"
                        class="relative flex items-center justify-center rounded-xl border-2 border-dashed p-4 transition-colors"
                    >
                        <template x-if="previewUrl">
                            <img :src="previewUrl" class="max-h-40 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                        </template>
                        <template x-if="!previewUrl && @js($existingHeroBg)">
                            <img src="{{ asset('storage/' . $existingHeroBg) }}" class="max-h-40 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                        </template>
                        <template x-if="!previewUrl && !@js($existingHeroBg)">
                            <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
                                <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                <span class="text-xs">Drag & drop or click</span>
                            </div>
                        </template>
                        <input x-ref="fileInput" type="file" wire:model="heroBackgroundImage" accept="image/*"
                               @change="previewUrl = URL.createObjectURL($event.target.files[0])"
                               class="absolute inset-0 opacity-0 cursor-pointer">
                    </div>
                    @error('heroBackgroundImage') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                {{-- Side images 1-4 --}}
                @foreach([
                    ['property' => 'heroSideImage1', 'existing' => $existingSide1, 'label' => 'Side Image 1 (Nature)'],
                    ['property' => 'heroSideImage2', 'existing' => $existingSide2, 'label' => 'Side Image 2 (The Sanctuary)'],
                    ['property' => 'heroSideImage3', 'existing' => $existingSide3, 'label' => 'Side Image 3 (Culture)'],
                    ['property' => 'heroSideImage4', 'existing' => $existingSide4, 'label' => 'Side Image 4 (Discover)'],
                ] as $sideImage)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">{{ $sideImage['label'] }}</label>
                        <div
                            x-data="{ previewUrl: null, dragging: false }"
                            x-on:dragover.prevent="dragging = true"
                            x-on:dragleave.prevent="dragging = false"
                            x-on:drop.prevent="dragging = false; $refs.fileInput.files = $event.dataTransfer.files; $refs.fileInput.dispatchEvent(new Event('change'))"
                            :class="dragging ? 'border-primary-600 bg-blue-50 dark:bg-blue-500/10' : 'border-gray-300 dark:border-gray-600'"
                            class="relative flex items-center justify-center rounded-xl border-2 border-dashed p-4 transition-colors"
                        >
                            <template x-if="previewUrl">
                                <img :src="previewUrl" class="max-h-40 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                            </template>
                            <template x-if="!previewUrl && @js($sideImage['existing'])">
                                <img src="{{ asset('storage/' . $sideImage['existing']) }}" class="max-h-40 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                            </template>
                            <template x-if="!previewUrl && !@js($sideImage['existing'])">
                                <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
                                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/></svg>
                                    <span class="text-xs">Drag & drop or click</span>
                                </div>
                            </template>
                            <input x-ref="fileInput" type="file" wire:model="{{ $sideImage['property'] }}" accept="image/*"
                                   @change="previewUrl = URL.createObjectURL($event.target.files[0])"
                                   class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                        @error($sideImage['property']) <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Text Content --}}
        <div class="card p-6 space-y-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Text Content</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Title</label>
                    <input type="text" wire:model="heroTitle" class="input">
                    @error('heroTitle') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Subtitle</label>
                    <input type="text" wire:model="heroSubtitle" class="input">
                    @error('heroSubtitle') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Hero Description</label>
                    <textarea wire:model="heroDescription" rows="3" class="textarea"></textarea>
                    @error('heroDescription') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discover Title</label>
                    <input type="text" wire:model="discoverTitle" class="input">
                    @error('discoverTitle') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Discover Description</label>
                    <textarea wire:model="discoverDescription" rows="4" class="textarea"></textarea>
                    @error('discoverDescription') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="btn-primary active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>
</div>