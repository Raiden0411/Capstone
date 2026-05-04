<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new 
#[Layout('tenant.layouts.app')]
#[Title('Tourist Spot Overview')]
class extends Component {
    use WithFileUploads;

    public $newPhotos = [];
    public $gallery = [];
    public $coverPhoto;
    public $currentCover = '';
    public $description = '';
    public $tags = '';
    public $spotName = '';

    public function mount()
    {
        $tenant = Auth::user()->tenant;
        $this->spotName = $tenant->name ?? 'Your Business';

        // Gallery
        $gallerySetting = TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', 'business_gallery')
            ->first();
        $this->gallery = $gallerySetting ? $gallerySetting->value : [];

        // Cover photo
        $coverSetting = TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', 'spot_cover')
            ->first();
        $this->currentCover = $coverSetting ? $coverSetting->value : '';

        // Description
        $descSetting = TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', 'spot_description')
            ->first();
        $this->description = $descSetting ? $descSetting->value : '';

        // Tags
        $tagsSetting = TenantSetting::where('tenant_id', $tenant->id)
            ->where('key', 'spot_tags')
            ->first();
        $this->tags = $tagsSetting ? $tagsSetting->value : '';
    }

    // ---------- Cover Photo ----------
    public function updatedCoverPhoto()
    {
        $this->validate(['coverPhoto' => 'image|max:5120']);
        $path = $this->coverPhoto->store('spot-covers', 'public');
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_cover'],
            ['value' => $path]
        );
        $this->currentCover = $path;
        session()->flash('message', 'Cover photo updated.');
    }

    public function removeCover()
    {
        if ($this->currentCover && Storage::disk('public')->exists($this->currentCover)) {
            Storage::disk('public')->delete($this->currentCover);
        }
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_cover'],
            ['value' => '']
        );
        $this->currentCover = '';
        session()->flash('message', 'Cover photo removed.');
    }

    // ---------- Gallery ----------
    public function updatedNewPhotos()
    {
        $this->validate(['newPhotos.*' => 'image|max:5120']);
    }

    public function removeNewPhoto($index)
    {
        unset($this->newPhotos[$index]);
        $this->newPhotos = array_values($this->newPhotos);
    }

    public function uploadGallery()
    {
        $this->validate(['newPhotos.*' => 'required|image|max:5120']);
        foreach ($this->newPhotos as $photo) {
            $path = $photo->store('business-gallery', 'public');
            $this->gallery[] = $path;
        }
        $this->saveGallery();
        $this->newPhotos = [];
        session()->flash('message', 'Gallery photos uploaded.');
    }

    public function deletePhoto($index)
    {
        $path = $this->gallery[$index];
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        unset($this->gallery[$index]);
        $this->gallery = array_values($this->gallery);
        $this->saveGallery();
        session()->flash('message', 'Photo removed.');
    }

    protected function saveGallery()
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'business_gallery'],
            ['value' => $this->gallery]
        );
    }

    // ---------- Description & Tags ----------
    public function saveOverview()
    {
        $this->validate([
            'description' => 'nullable|string|max:5000',
            'tags'        => 'nullable|string|max:500',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_description'],
            ['value' => $this->description]
        );
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_tags'],
            ['value' => $this->tags]
        );

        session()->flash('message', 'Overview saved.');
        $this->dispatch('overview-saved');    // ← triggers the “Saved!” badge
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-[85rem] mx-auto text-gray-900 dark:text-white space-y-10">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold">{{ $spotName }} — Public Overview</h1>
            <p class="text-gray-500 dark:text-slate-400 mt-1">Manage how your tourist spot appears to visitors.</p>
        </div>
        <a href="{{ route('tenant.settings.index') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Business Profile
        </a>
    </div>

    {{-- ========== COVER PHOTO HERO ========== --}}
    <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-slate-700/50 shadow-sm relative group">
        <div class="h-64 sm:h-80 bg-gray-100 dark:bg-slate-800 flex items-center justify-center relative">
            @if($currentCover)
                <img src="{{ Storage::url($currentCover) }}" class="w-full h-full object-cover" alt="Cover">
            @else
                <div class="text-center text-gray-400 dark:text-slate-500">
                    <svg class="mx-auto h-12 w-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm">No cover photo yet</p>
                </div>
            @endif
            {{-- Upload / Change cover --}}
            <div class="absolute bottom-4 right-4 flex gap-2">
                <label class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-slate-300 cursor-pointer hover:bg-white dark:hover:bg-slate-800 transition shadow data-loading:cursor-wait"
                       wire:target="coverPhoto">
                    <span class="in-data-loading:hidden">
                        <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Change Cover
                    </span>
                    <span class="not-in-data-loading:hidden flex items-center gap-1">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                        Uploading…
                    </span>
                    <input type="file" wire:model="coverPhoto" accept="image/*" class="hidden">
                </label>
                @if($currentCover)
                    <button type="button" wire:click="removeCover" class="bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10 transition shadow">
                        Remove
                    </button>
                @endif
            </div>
        </div>
        @error('coverPhoto') <span class="text-red-500 dark:text-red-400 text-xs p-3 block">{{ $message }}</span> @enderror
    </div>

    {{-- ========== GALLERY SECTION ========== --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">Photo Gallery</h2>
            <span class="text-xs text-gray-500 dark:text-slate-400">{{ count($gallery) }} photos</span>
        </div>

        {{-- Existing Gallery --}}
        @if(count($gallery) > 0)
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3 mb-6">
                @foreach($gallery as $index => $path)
                    <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700/50 bg-gray-100 dark:bg-slate-800">
                        <img src="{{ Storage::url($path) }}" class="w-full h-40 object-cover" alt="Gallery image">
                        <button type="button" wire:click="deletePhoto({{ $index }})" 
                                class="absolute top-2 right-2 bg-red-500/90 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition hover:bg-red-600"
                                title="Remove photo">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-12 text-gray-400 dark:text-slate-500 border-2 border-dashed border-gray-200 dark:border-slate-700/50 rounded-lg mb-6">
                <svg class="mx-auto h-12 w-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-sm">No photos in your gallery yet.</p>
                <p class="text-xs mt-1">Upload your first photo using the form below.</p>
            </div>
        @endif

        {{-- Upload New Photos --}}
        <div class="border-2 border-dashed border-gray-300 dark:border-slate-600 rounded-lg p-6 text-center hover:border-blue-400 dark:hover:border-blue-400 transition cursor-pointer">
            <input type="file" wire:model="newPhotos" multiple accept="image/*" class="hidden" id="spot-photos-upload">
            <label for="spot-photos-upload" class="cursor-pointer block">
                <svg class="mx-auto h-10 w-10 text-gray-400 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="mt-2 text-sm text-gray-600 dark:text-slate-400">Drag and drop or <span class="text-blue-600 dark:text-blue-400 font-medium">browse</span></p>
                <p class="text-xs text-gray-400 dark:text-slate-500">PNG, JPG up to 5 MB each</p>
            </label>
        </div>
        @error('newPhotos.*') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror

        {{-- Preview new photos --}}
        @if(count($newPhotos) > 0)
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3 mt-4">
                @foreach($newPhotos as $index => $photo)
                    <div class="relative group rounded-lg overflow-hidden border border-gray-200 dark:border-slate-700/50">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-40 object-cover">
                        <button type="button" wire:click="removeNewPhoto({{ $index }})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 shadow hover:bg-red-600">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-end mt-4">
                <button type="button" wire:click="uploadGallery" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-5 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                        Upload All
                    </span>
                    <span class="not-in-data-loading:hidden flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/></svg>
                        Uploading…
                    </span>
                </button>
            </div>
        @endif
    </div>

    {{-- ========== DESCRIPTION & TAGS ========== --}}
    <div class="bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        <h2 class="text-lg font-semibold mb-6">Description & Tags</h2>
        
        <form wire:submit="saveOverview" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">About {{ $spotName }}</label>
                <textarea wire:model="description" rows="6" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" 
                          placeholder="Tell visitors the story of your place, what makes it unique, its history, and what they can expect..."></textarea>
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-2">This description appears on your public profile. Maximum 5,000 characters.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Tags</label>
                <input type="text" wire:model="tags" class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="e.g. mountain view, eco park, adventure, hiking">
                <p class="text-xs text-gray-400 dark:text-slate-500 mt-2">Separate tags with commas. These help visitors find your spot in search.</p>
            </div>

            <div class="flex justify-end pt-2"
                 x-data="{ saved: false }"
                 @overview-saved.window="saved = true; setTimeout(() => saved = false, 2200)">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                    <span class="in-data-loading:hidden">Save Overview</span>
                    <span class="not-in-data-loading:hidden flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving…
                    </span>
                </button>
                {{-- Temporary “Saved!” badge --}}
                <span x-show="saved" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90"
                      class="ml-3 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 dark:bg-green-500/20 text-green-700 dark:text-green-400">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Saved!
                </span>
            </div>
        </form>
    </div>

    {{-- ========== PREVIEW LINK ========== --}}
    <div class="text-center text-sm text-gray-500 dark:text-slate-400">
        <a href="{{ route('tenant.show', Auth::user()->tenant->slug) }}" target="_blank" class="inline-flex items-center gap-1 text-blue-600 dark:text-blue-400 hover:underline">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            Preview your public profile
        </a>
    </div>
</div>