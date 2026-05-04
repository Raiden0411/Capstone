<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Storage;

new 
#[Layout('layouts.app')]
#[Title('Business Details')]
class extends Component {
    public Tenant $tenant;

    public function mount($slug)
    {
        $this->tenant = Tenant::where('slug', $slug)->firstOrFail();
    }

    #[Computed]
    public function coverPhoto()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'spot_cover')
            ->first();
        return $setting ? $setting->value : null;
    }

    #[Computed]
    public function description()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'spot_description')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function tags()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'spot_tags')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function tagArray()
    {
        return array_filter(array_map('trim', explode(',', $this->tags)));
    }

    #[Computed]
    public function properties()
    {
        return $this->tenant->properties()
            ->withoutGlobalScope(TenantScope::class)
            ->where('is_active', true)
            ->where('status', 'available')
            ->with([
                'propertyType' => fn($q) => $q->withoutGlobalScope(TenantScope::class),
                'images'       => fn($q) => $q->withoutGlobalScope(TenantScope::class),
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
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function galleryImages()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'business_gallery')
            ->first();
        return $setting ? $setting->value : [];
    }

    // Gallery headings (displayed on public page)
    #[Computed]
    public function gallerySubtitle()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'gallery_subtitle')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function galleryTitle()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'gallery_title')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function footerTitle()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'footer_title')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function footerDescription()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'footer_description')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function footerThumb1()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'footer_thumb_1')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function footerThumb2()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'footer_thumb_2')
            ->first();
        return $setting ? $setting->value : '';
    }

    #[Computed]
    public function footerBackground()
    {
        $setting = $this->tenant->settings()
            ->withoutGlobalScope(TenantScope::class)
            ->where('key', 'footer_background')
            ->first();
        return $setting ? $setting->value : '';
    }
};
?>

<div class="antialiased overflow-x-hidden bg-white dark:bg-black transition-colors duration-300"
     x-data="{ previewImage: null }">

    {{-- Lightbox modal --}}
    <div x-show="previewImage" x-cloak class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4" @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
        <div class="relative max-w-5xl max-h-[90vh]">
            <button @click="previewImage = null" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-sm">Close</button>
            <img :src="previewImage" class="rounded-2xl shadow-2xl max-h-[85vh] w-auto" alt="Enlarged view">
        </div>
    </div>

    {{-- HERO SECTION --}}
    <section class="relative min-h-screen flex flex-col justify-between pt-8 pb-12 bg-white dark:bg-black">
        <div class="absolute inset-0 z-0">
            @if($this->coverPhoto)
                <img src="{{ Storage::url($this->coverPhoto) }}" class="w-full h-full object-cover object-center" alt="{{ $tenant->name }} cover" loading="eager">
            @else
                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
            @endif
        </div>
        <div class="absolute inset-0 bg-black/30 dark:bg-black/60 bg-gradient-to-b from-black/20 via-transparent to-black/50 dark:from-black/60 dark:via-transparent dark:to-black/90 z-1"></div>

        @if(count($this->tagArray) > 0)
            <nav class="relative z-10 px-6 md:px-12 flex justify-between items-center text-sm font-semibold tracking-wide">
                <div class="w-2.5 h-2.5"></div>
                <ul class="hidden md:flex gap-16 text-white">
                    @foreach($this->tagArray as $index => $tag)
                        <li wire:key="tag-{{ $index }}">
                            <a href="#" 
                               class="{{ $index === 0 ? 'border-b-2 border-red-500 pb-1 text-white' : 'hover:text-gray-300 transition-colors text-white' }}">
                                {{ $tag }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                <div class="w-32 border-t border-white/30 hidden md:block"></div>
            </nav>
        @endif

        <div class="relative z-10 px-6 md:px-12 mt-20 flex-grow">
            <div class="max-w-4xl">
                <h1 class="text-6xl sm:text-7xl md:text-8xl lg:text-9xl font-black leading-[1.1] tracking-tight text-white">
                    {{ $tenant->name }}
                </h1>
                <div class="w-24 h-1 bg-red-500 mt-6"></div>
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-12 flex flex-col gap-6 w-full max-w-5xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 text-sm text-white/90 leading-relaxed">
                @php
                    $descParagraphs = array_filter(array_map('trim', explode("\n", $this->description)));
                    $descParagraphs = array_pad($descParagraphs, 3, '');
                @endphp
                @foreach($descParagraphs as $i => $para)
                    <p wire:key="desc-{{ $i }}" class="break-words whitespace-normal">{{ $para ?: '' }}</p>
                @endforeach
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-12 mt-8 flex flex-wrap gap-3">
            @auth
                <a href="{{ route('business.offerings', $tenant->slug) }}" 
                   class="inline-flex items-center gap-2 bg-blue-600 dark:bg-red-600 hover:bg-blue-700 dark:hover:bg-red-700 text-white font-medium py-2.5 px-5 rounded-full shadow-sm transition-all duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Book Now
                </a>
            @else
                @php
                    $returnUrl = url()->current();
                    $loginUrl = route('login', ['redirect' => $returnUrl]);
                @endphp
                <a href="{{ $loginUrl }}" 
                   class="inline-flex items-center gap-2 bg-blue-600 dark:bg-red-600 hover:bg-blue-700 dark:hover:bg-red-700 text-white font-medium py-2.5 px-5 rounded-full shadow-sm transition-all duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Book
                </a>
            @endauth
        </div>
    </section>

    {{-- GALLERY SECTION with subtitle and title --}}
    @if(!empty($this->galleryImages()))
        <section class="bg-white dark:bg-black py-24 px-6 md:px-12 relative">
            <div class="max-w-7xl mx-auto">
                {{-- Gallery Headings --}}
                @if($this->gallerySubtitle || $this->galleryTitle)
                    <div class="text-center mb-12">
                        @if($this->gallerySubtitle)
                            <p class="text-gray-300 text-sm tracking-wider mb-2">{{ $this->gallerySubtitle }}</p>
                        @endif
                        @if($this->galleryTitle)
                            <h2 class="text-3xl md:text-4xl font-bold text-white">{{ $this->galleryTitle }}</h2>
                        @endif
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($this->galleryImages() as $index => $imagePath)
                        <div wire:key="gallery-{{ $index }}" class="group cursor-pointer">
                            <div class="w-full h-[350px] overflow-hidden rounded-sm relative bg-gray-100 dark:bg-gray-800">
                                <img src="{{ Storage::url($imagePath) }}" 
                                     class="w-full h-full object-cover grayscale-[30%] group-hover:grayscale-0 group-hover:scale-105 transition-all duration-500" 
                                     @click="previewImage = '{{ Storage::url($imagePath) }}'" 
                                     alt="{{ $tenant->name }} gallery image" 
                                     loading="lazy">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- FOOTER SECTION --}}
    @if($this->footerTitle || $this->footerDescription || $this->footerThumb1 || $this->footerThumb2)
        <section class="relative h-[80vh] bg-cover bg-center flex items-end pb-12 px-6 md:px-12"
            @if($this->footerBackground) style="background-image: url('{{ Storage::url($this->footerBackground) }}'); background-size: cover; background-position: center;" @else style="background-image: linear-gradient(to bottom right, #0a0a2a, #1a1a3a);" @endif>
            <div class="absolute inset-0 bg-black/40 dark:bg-black/70 bg-gradient-to-r from-black/70 to-transparent"></div>

            <div class="relative z-10 w-full flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
                <div class="max-w-md">
                    @if($this->footerTitle)
                        <h2 class="text-4xl md:text-5xl font-extrabold leading-tight mb-8 whitespace-pre-line text-white">{{ $this->footerTitle }}</h2>
                    @endif
                    @if($this->footerDescription)
                        <p class="text-sm text-white/80 leading-relaxed pr-8 break-words">{{ $this->footerDescription }}</p>
                    @endif
                </div>

                @if($this->footerThumb1 || $this->footerThumb2)
                    <div class="flex gap-4">
                        @if($this->footerThumb1)
                            <div class="w-48 h-28 overflow-hidden rounded-sm group cursor-pointer border border-white/20">
                                <img src="{{ Storage::url($this->footerThumb1) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer image 1" loading="lazy">
                            </div>
                        @endif
                        @if($this->footerThumb2)
                            <div class="w-48 h-28 overflow-hidden rounded-sm group cursor-pointer border border-white/20">
                                <img src="{{ Storage::url($this->footerThumb2) }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer image 2" loading="lazy">
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>
    @endif
</div>