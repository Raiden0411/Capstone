<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attribu tes\Layout;
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
    public $tagArray = [];
    public $message = '';

    public $gallerySubtitle = '';
    public $galleryTitle = '';

    public $footerTitle = '';
    public $footerDescription = '';
    public $footerThumb1 = '';
    public $footerThumb2 = '';
    public $footerBackground = '';
    public $footerThumb1File;
    public $footerThumb2File;
    public $footerBackgroundFile;

    public function mount()
    {
        $tenant = Auth::user()->tenant;
        $this->spotName = $tenant->name ?? 'Your Business';

        $this->gallery = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'business_gallery')->first()?->value ?? [];
        $this->currentCover = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'spot_cover')->first()?->value ?? '';
        $this->description = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'spot_description')->first()?->value ?? '';
        $this->tags = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'spot_tags')->first()?->value ?? '';
        $this->syncTagArray();

        $this->gallerySubtitle = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'gallery_subtitle')->first()?->value ?? '';
        $this->galleryTitle = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'gallery_title')->first()?->value ?? '';
        $this->footerTitle = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'footer_title')->first()?->value ?? '';
        $this->footerDescription = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'footer_description')->first()?->value ?? '';
        $this->footerThumb1 = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'footer_thumb_1')->first()?->value ?? '';
        $this->footerThumb2 = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'footer_thumb_2')->first()?->value ?? '';
        $this->footerBackground = TenantSetting::where('tenant_id', $tenant->id)->where('key', 'footer_background')->first()?->value ?? '';
    }

    protected function syncTagArray()
    {
        $this->tagArray = array_filter(array_map('trim', explode(',', $this->tags)));
    }

    public function addTag($tag)
    {
        $tag = trim($tag);
        if ($tag && !in_array($tag, $this->tagArray)) {
            $this->tagArray[] = $tag;
            $this->tags = implode(',', $this->tagArray);
        }
    }

    public function removeTag($index)
    {
        unset($this->tagArray[$index]);
        $this->tagArray = array_values($this->tagArray);
        $this->tags = implode(',', $this->tagArray);
    }

    public function updatedCoverPhoto()
    {
        $this->validate(['coverPhoto' => 'image|max:5120']);
        $path = $this->coverPhoto->store('spot-covers', 'public');
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_cover'],
            ['value' => $path]
        );
        $this->currentCover = $path;
        $this->message = 'Cover photo updated.';
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
        $this->message = 'Cover photo removed.';
    }

    // FIX: ensure newPhotos updates correctly
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
        if (count($this->newPhotos) === 0) {
            $this->message = 'No photos selected.';
            return;
        }

        $this->validate(['newPhotos.*' => 'required|image|max:5120']);

        foreach ($this->newPhotos as $photo) {
            $path = $photo->store('business-gallery', 'public');
            $this->gallery[] = $path;
        }
        $this->saveGallery();
        $this->newPhotos = []; // clear the temporary files
        $this->message = 'Gallery photos uploaded.';
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
        $this->message = 'Photo removed.';
    }

    public function updateGalleryOrder($orderedPaths)
    {
        $this->gallery = $orderedPaths;
        $this->saveGallery();
        $this->message = 'Gallery order updated.';
    }

    protected function saveGallery()
    {
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'business_gallery'],
            ['value' => $this->gallery]
        );
    }

    public function saveOverview()
    {
        $this->validate([
            'description' => 'nullable|string|max:5000',
            'tags'        => 'nullable|string|max:500',
            'gallerySubtitle' => 'nullable|string|max:255',
            'galleryTitle'    => 'nullable|string|max:255',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_description'],
            ['value' => $this->description]
        );
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'spot_tags'],
            ['value' => $this->tags]
        );
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'gallery_subtitle'],
            ['value' => $this->gallerySubtitle]
        );
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'gallery_title'],
            ['value' => $this->galleryTitle]
        );

        $this->message = 'Overview saved.';
        $this->dispatch('overview-saved');
    }

    public function getDescriptionPercent()
    {
        return min(100, (strlen($this->description) / 5000) * 100);
    }

    public function updatedFooterThumb1File()
    {
        $this->validate(['footerThumb1File' => 'image|max:5120']);
        $path = $this->footerThumb1File->store('footer-thumbs', 'public');
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_thumb_1'],
            ['value' => $path]
        );
        $this->footerThumb1 = $path;
        $this->message = 'Footer thumbnail 1 updated.';
    }

    public function removeFooterThumb1()
    {
        if ($this->footerThumb1 && Storage::disk('public')->exists($this->footerThumb1)) {
            Storage::disk('public')->delete($this->footerThumb1);
        }
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_thumb_1'],
            ['value' => '']
        );
        $this->footerThumb1 = '';
        $this->message = 'Footer thumbnail 1 removed.';
    }

    public function updatedFooterThumb2File()
    {
        $this->validate(['footerThumb2File' => 'image|max:5120']);
        $path = $this->footerThumb2File->store('footer-thumbs', 'public');
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_thumb_2'],
            ['value' => $path]
        );
        $this->footerThumb2 = $path;
        $this->message = 'Footer thumbnail 2 updated.';
    }

    public function removeFooterThumb2()
    {
        if ($this->footerThumb2 && Storage::disk('public')->exists($this->footerThumb2)) {
            Storage::disk('public')->delete($this->footerThumb2);
        }
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_thumb_2'],
            ['value' => '']
        );
        $this->footerThumb2 = '';
        $this->message = 'Footer thumbnail 2 removed.';
    }

    public function updatedFooterBackgroundFile()
    {
        $this->validate(['footerBackgroundFile' => 'image|max:5120']);
        $path = $this->footerBackgroundFile->store('footer-backgrounds', 'public');
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_background'],
            ['value' => $path]
        );
        $this->footerBackground = $path;
        $this->message = 'Footer background updated.';
    }

    public function removeFooterBackground()
    {
        if ($this->footerBackground && Storage::disk('public')->exists($this->footerBackground)) {
            Storage::disk('public')->delete($this->footerBackground);
        }
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_background'],
            ['value' => '']
        );
        $this->footerBackground = '';
        $this->message = 'Footer background removed.';
    }

    public function saveFooter()
    {
        $this->validate([
            'footerTitle' => 'nullable|string|max:500',
            'footerDescription' => 'nullable|string|max:2000',
        ]);

        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_title'],
            ['value' => $this->footerTitle]
        );
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'footer_description'],
            ['value' => $this->footerDescription]
        );

        $this->message = 'Footer settings saved.';
        $this->dispatch('footer-saved');
    }
};
?>

<div class="antialiased overflow-x-hidden bg-black text-white"
     x-data="{
        tagInput: '',
        previewImage: null,
        addTag() {
            if (this.tagInput.trim()) {
                $wire.addTag(this.tagInput.trim());
                this.tagInput = '';
            }
        },
        removeTag(index) {
            $wire.removeTag(index);
        },
        initSortable() {
            const el = document.getElementById('ranking-grid');
            if (el && typeof Sortable !== 'undefined') {
                new Sortable(el, {
                    animation: 250,
                    handle: '.drag-handle',
                    onEnd: (evt) => {
                        const ordered = Array.from(el.children).map(child => child.getAttribute('data-path'));
                        $wire.updateGalleryOrder(ordered);
                    }
                });
            }
        },
        initToast() {
            if ($wire.message) setTimeout(() => $wire.message = '', 4000);
        }
     }"
     x-init="initSortable(); initToast();">

    {{-- Toast notification --}}
    <div x-show="$wire.message" x-transition.duration.300ms class="fixed bottom-6 right-6 z-50 bg-emerald-600 text-white px-5 py-3 rounded-full shadow-xl flex items-center gap-2 text-sm font-medium">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span x-text="$wire.message"></span>
    </div>

    {{-- Lightbox modal --}}
    <div x-show="previewImage" x-cloak class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4" @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
        <div class="relative max-w-5xl max-h-[90vh]">
            <button @click="previewImage = null" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-sm">Close</button>
            <img :src="previewImage" class="rounded-2xl shadow-2xl max-h-[85vh] w-auto">
        </div>
    </div>

    {{-- HERO SECTION --}}
    <section class="relative min-h-screen flex flex-col justify-between pt-8 pb-12 bg-black">
        <div class="absolute inset-0 z-0">
            @if($currentCover)
                <img src="{{ Storage::url($currentCover) }}" class="w-full h-full object-cover object-center" alt="Cover photo" loading="eager">
            @else
                <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900"></div>
            @endif
        </div>
        <div class="absolute inset-0 bg-black/40 bg-gradient-to-b from-black/60 via-transparent to-black/90 z-1"></div>

        <nav class="relative z-10 px-6 md:px-12 flex justify-between items-center text-sm font-semibold tracking-wide">
            <div class="w-2.5 h-2.5"></div>
            <ul class="hidden md:flex gap-16 text-gray-300">
                @foreach($tagArray as $tag)
                    <li wire:key="tag-{{ $loop->index }}"><a href="#" class="{{ $loop->first ? 'text-white border-b-2 border-white pb-1' : 'hover:text-white transition-colors' }}">{{ $tag }}</a></li>
                @endforeach
            </ul>
            <div class="w-32 border-t border-gray-400 hidden md:block"></div>
        </nav>

        <div class="relative z-10 px-6 md:px-12 mt-20 flex-grow">
            <div class="max-w-4xl">
                <h1 class="text-6xl sm:text-7xl md:text-8xl lg:text-9xl font-black leading-[1.1] tracking-tight text-white">
                    {{ $spotName }}
                </h1>
                <div class="w-24 h-1 bg-red-500 mt-6"></div>
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-12 flex flex-col gap-6 w-full max-w-5xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 text-xs text-gray-300 leading-relaxed">
                @php
                    $descParagraphs = array_filter(array_map('trim', explode("\n", $description)));
                    $descParagraphs = array_pad($descParagraphs, 3, '');
                @endphp
                @foreach($descParagraphs as $i => $para)
                    <p wire:key="desc-{{ $i }}" class="break-words whitespace-normal">{{ $para ?: '' }}</p>
                @endforeach
            </div>
        </div>

        <div class="absolute bottom-6 right-6 z-20 flex gap-2">
            <label class="bg-black/60 backdrop-blur-md px-3 py-1 rounded-full text-white text-xs cursor-pointer hover:bg-black/80 transition">
                Change Cover
                <input type="file" wire:model="coverPhoto" accept="image/*" class="hidden">
            </label>
            @if($currentCover)
                <button wire:click="removeCover" class="bg-black/60 backdrop-blur-md px-3 py-1 rounded-full text-white text-xs hover:bg-red-500/80 transition">Remove</button>
            @endif
        </div>
        @error('coverPhoto') <p class="text-red-500 text-xs absolute bottom-2 left-6 z-20">{{ $message }}</p> @enderror
    </section>

    {{-- DESTINATION RECOMMENDATIONS --}}
    <section class="bg-black py-24 px-6 md:px-12 relative">
        <div class="text-center mb-16">
            @if($gallerySubtitle)
                <p class="text-gray-400 text-xs tracking-wider mb-2">{{ $gallerySubtitle }}</p>
            @endif
            @if($galleryTitle)
                <h2 class="text-2xl md:text-3xl font-bold tracking-wide">{{ $galleryTitle }}</h2>
            @endif
        </div>

        @if(count($gallery) > 0)
            <div id="ranking-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 max-w-7xl mx-auto" wire:ignore>
                @foreach($gallery as $index => $path)
                    <div data-path="{{ $path }}" wire:key="gallery-{{ $index }}" class="group cursor-pointer">
                        <div class="w-full h-[350px] overflow-hidden rounded-sm relative">
                            <img src="{{ Storage::url($path) }}" class="w-full h-full object-cover grayscale-[30%] group-hover:grayscale-0 group-hover:scale-105 transition-all duration-500" @click="previewImage = '{{ Storage::url($path) }}'" alt="Destination image" loading="lazy">
                            <button type="button" wire:click="deletePhoto({{ $index }})" class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition shadow-lg">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            <div class="absolute bottom-2 left-2 cursor-grab active:cursor-grabbing drag-handle bg-black/60 rounded-full p-1.5 text-white opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/><path d="M8 4v16M16 4v16"/></svg>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-16 text-gray-400 border-2 border-dashed border-gray-700 rounded-2xl max-w-7xl mx-auto">
                <svg class="mx-auto h-14 w-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="mt-3">No destinations yet</p>
                <p class="text-sm text-gray-500">Upload images below to create your ranking</p>
            </div>
        @endif

        <div class="mt-16 flex justify-between max-w-7xl mx-auto items-center">
            <div class="w-[45%] border-t border-gray-800"></div>
            <div class="w-[45%] border-t border-gray-800"></div>
        </div>
    </section>

    {{-- FOOTER SECTION --}}
    <section class="relative h-[80vh] bg-cover bg-center flex items-end pb-12 px-6 md:px-12"
        @if($footerBackground) style="background-image: url('{{ Storage::url($footerBackground) }}'); background-size: cover; background-position: center;" @else style="background-image: linear-gradient(to bottom right, #1a1a2e, #16213e);" @endif>
        <div class="absolute inset-0 bg-black/60 bg-gradient-to-r from-black/90 to-transparent"></div>

        <div class="relative z-10 w-full flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
            <div class="max-w-md">
                @if($footerTitle)
                    <h2 class="text-4xl md:text-5xl font-extrabold leading-tight mb-8 whitespace-pre-line">{{ $footerTitle }}</h2>
                @endif
                @if($footerDescription)
                    <p class="text-xs text-gray-300 leading-relaxed pr-8 break-words">{{ $footerDescription }}</p>
                @endif
            </div>

            <div class="flex flex-col items-end gap-6">
                <div class="flex gap-4">
                    <div class="w-48 h-28 relative overflow-hidden rounded-sm group cursor-pointer border border-gray-700">
                        @if($footerThumb1)
                            <img src="{{ Storage::url($footerThumb1) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer thumbnail 1" loading="lazy">
                        @else
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center text-gray-500 text-xs">No image</div>
                        @endif
                    </div>
                    <div class="w-48 h-28 overflow-hidden rounded-sm group cursor-pointer border border-gray-700">
                        @if($footerThumb2)
                            <img src="{{ Storage::url($footerThumb2) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer thumbnail 2" loading="lazy">
                        @else
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center text-gray-500 text-xs">No image</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ADMIN EDITING PANEL (FIXED UPLOAD) --}}
    <div class="max-w-7xl mx-auto px-6 md:px-12 pb-12 mt-12">
        <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800 space-y-8">
            <h3 class="text-lg font-semibold text-white">Manage Gallery & Content</h3>

            {{-- Gallery upload area – fixed with wire:key --}}
            <div class="border-2 border-dashed border-gray-700 rounded-xl p-6 text-center hover:border-blue-500 transition bg-black/40">
                <input type="file" wire:model.live="newPhotos" multiple accept="image/*" class="hidden" id="gallery-upload" wire:key="gallery-upload-input-{{ count($newPhotos) }}">
                <label for="gallery-upload" class="cursor-pointer block">
                    <svg class="mx-auto h-10 w-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <p class="mt-2 text-sm font-medium text-gray-300">Click to select images (multiple allowed)</p>
                    <p class="text-xs text-gray-500">PNG, JPG up to 5MB each</p>
                </label>
            </div>

            @error('newPhotos.*') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror

            @if(count($newPhotos) > 0)
                <div>
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm font-semibold text-gray-300">Pending ({{ count($newPhotos) }})</span>
                        <button wire:click="uploadGallery" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-full shadow transition">Upload all</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($newPhotos as $index => $photo)
                            <div class="relative" wire:key="pending-{{ $index }}">
                                <img src="{{ $photo->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg" alt="Preview">
                                <button wire:click="removeNewPhoto({{ $index }})" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Rest of the admin panel (description, tags, footer settings) unchanged --}}
            <form wire:submit="saveOverview" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                    <textarea wire:model="description" rows="5" class="w-full rounded-xl border-gray-700 bg-black text-white focus:ring-2 focus:ring-blue-500 px-4 py-2 resize-y break-words whitespace-pre-wrap" placeholder="Write a compelling description..."></textarea>
                    <div class="flex justify-between items-center mt-1 text-xs text-gray-500">
                        <span>Max 5000 characters</span>
                        <div class="w-32 bg-gray-800 rounded-full h-1.5">
                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: {{ $this->getDescriptionPercent() }}%"></div>
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Tags</label>
                    <div class="flex flex-wrap gap-2 mb-2">
                        <template x-for="(tag, idx) in $wire.tagArray" :key="idx">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-900/50 text-blue-300 rounded-full text-sm">
                                <span x-text="tag"></span>
                                <button type="button" @click="removeTag(idx)" class="hover:text-red-500">✕</button>
                            </span>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" x-model="tagInput" @keydown.enter.prevent="addTag" placeholder="Add category (e.g., wide sea, mountains, island)" class="flex-1 rounded-xl border-gray-700 bg-black text-white text-sm px-4 py-2">
                        <button type="button" @click="addTag" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-xl text-sm text-white">Add</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Gallery Subtitle</label>
                    <input type="text" wire:model="gallerySubtitle" class="w-full rounded-xl border-gray-700 bg-black text-white px-4 py-2" placeholder="e.g., confusion? These recommendation">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Gallery Title</label>
                    <input type="text" wire:model="galleryTitle" class="w-full rounded-xl border-gray-700 bg-black text-white px-4 py-2" placeholder="e.g., destination recommendations">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-full shadow transition flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                        Save Changes
                    </button>
                </div>
            </form>

            <div class="border-t border-gray-800 pt-6">
                <h4 class="text-md font-semibold text-white mb-4">Footer Settings</h4>
                <form wire:submit="saveFooter" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Footer Title</label>
                        <textarea wire:model="footerTitle" rows="3" class="w-full rounded-xl border-gray-700 bg-black text-white focus:ring-2 focus:ring-blue-500 px-4 py-2" placeholder="TRAVEL AND&#10;ENJOY YOUR&#10;HOLIDAY"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1">Footer Description</label>
                        <textarea wire:model="footerDescription" rows="3" class="w-full rounded-xl border-gray-700 bg-black text-white focus:ring-2 focus:ring-blue-500 px-4 py-2" placeholder="Write the footer paragraph..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Footer Thumbnail 1</label>
                        <div class="flex items-center gap-4">
                            @if($footerThumb1)
                                <img src="{{ Storage::url($footerThumb1) }}" class="h-16 w-16 object-cover rounded-lg" alt="Thumb 1">
                            @endif
                            <label class="bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded-full text-xs cursor-pointer">
                                Upload
                                <input type="file" wire:model="footerThumb1File" accept="image/*" class="hidden">
                            </label>
                            @if($footerThumb1)
                                <button type="button" wire:click="removeFooterThumb1" class="text-red-400 hover:text-red-300 text-xs">Remove</button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Footer Thumbnail 2</label>
                        <div class="flex items-center gap-4">
                            @if($footerThumb2)
                                <img src="{{ Storage::url($footerThumb2) }}" class="h-16 w-16 object-cover rounded-lg" alt="Thumb 2">
                            @endif
                            <label class="bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded-full text-xs cursor-pointer">
                                Upload
                                <input type="file" wire:model="footerThumb2File" accept="image/*" class="hidden">
                            </label>
                            @if($footerThumb2)
                                <button type="button" wire:click="removeFooterThumb2" class="text-red-400 hover:text-red-300 text-xs">Remove</button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Footer Background Image</label>
                        <div class="flex items-center gap-4">
                            @if($footerBackground)
                                <div class="w-20 h-20 rounded-lg overflow-hidden">
                                    <img src="{{ Storage::url($footerBackground) }}" class="w-full h-full object-cover" alt="Footer background">
                                </div>
                            @endif
                            <label class="bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded-full text-xs cursor-pointer">
                                Upload
                                <input type="file" wire:model="footerBackgroundFile" accept="image/*" class="hidden">
                            </label>
                            @if($footerBackground)
                                <button type="button" wire:click="removeFooterBackground" class="text-red-400 hover:text-red-300 text-xs">Remove</button>
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Recommended: 1920x1080 or wider.</p>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-6 rounded-full shadow transition flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                            Save Footer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @once
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    @endonce
</div>