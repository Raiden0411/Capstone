<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Storage;
?>

<?php $__env->startPush('styles'); ?>

<?php $__env->stopPush(); ?>

<div
    class="relative z-10 min-h-screen"
    x-data="{
        galleryOpen: false,
        lightboxSrc: null,
        lightboxIndex: 0,
        galleryImages: <?php echo e(Js::from(collect($galleryImages)->map(fn($p) => Storage::url($p))->values())); ?>,

        openGallery() {
            this.galleryOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeGallery() {
            this.galleryOpen = false;
            this.lightboxSrc = null;
            document.body.style.overflow = '';
        },
        openLightbox(src, idx) {
            this.lightboxSrc = src;
            this.lightboxIndex = idx;
        },
        prevImage() {
            this.lightboxIndex = (this.lightboxIndex - 1 + this.galleryImages.length) % this.galleryImages.length;
            this.lightboxSrc = this.galleryImages[this.lightboxIndex];
        },
        nextImage() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.galleryImages.length;
            this.lightboxSrc = this.galleryImages[this.lightboxIndex];
        }
    }"
    @keydown.escape.window="lightboxSrc ? lightboxSrc = null : closeGallery()"
    @keydown.arrow-left.window="lightboxSrc && prevImage()"
    @keydown.arrow-right.window="lightboxSrc && nextImage()"
>

    
    <div x-show="galleryOpen" x-cloak
         class="gallery-modal-overlay"
         x-transition:enter="transition-opacity duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        
        <div class="flex-none flex items-center justify-between px-8 py-5 border-b border-white/8">
            <div>
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="w-3 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Photo Gallery</span>
                </div>
                <h2 class="font-display text-xl font-semibold text-white">
                    <?php echo e($tenant->name); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($galleryTitle): ?>
                        <span class="text-white/40 font-normal mx-2">·</span>
                        <em class="italic text-brand-400 text-lg"><?php echo e($galleryTitle); ?></em>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </h2>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-white/30 tabular-nums hidden sm:block">
                    <?php echo e(count($galleryImages)); ?> photos
                </span>
                <button @click="closeGallery()"
                        class="w-10 h-10 rounded-full border border-white/15 flex items-center justify-center text-white/50 hover:text-white hover:border-white/40 hover:bg-white/8 transition-all">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        
        <div class="flex-1 overflow-y-auto p-6 md:p-8">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($galleryImages)): ?>
                <div class="gallery-masonry max-w-7xl mx-auto">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $galleryImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $imagePath): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gm-'.e($index).''; ?>wire:key="gm-<?php echo e($index); ?>"
                             class="gm-item relative overflow-hidden rounded-xl cursor-pointer group"
                             @click="openLightbox('<?php echo e(Storage::url($imagePath)); ?>', <?php echo e($index); ?>)"
                             style="animation: scaleIn 0.4s cubic-bezier(0.16,1,0.3,1) <?php echo e($index * 40); ?>ms both">
                            <img src="<?php echo e(Storage::url($imagePath)); ?>"
                                 class="w-full h-full object-cover brightness-90 group-hover:brightness-105 group-hover:scale-105 transition duration-700"
                                 alt="<?php echo e($tenant->name); ?> photo <?php echo e($index + 1); ?>" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <div class="absolute bottom-3 right-3 w-8 h-8 rounded-full bg-white/10 backdrop-blur border border-white/20 flex items-center justify-center text-white">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                                </div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="flex flex-col items-center justify-center h-64 text-white/30">
                    <svg class="w-12 h-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm">No photos yet</p>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gallerySubtitle): ?>
            <div class="flex-none border-t border-white/8 px-8 py-3 text-xs text-white/30 italic">
                <?php echo e($gallerySubtitle); ?>

            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div x-show="lightboxSrc" x-cloak class="lb-inner" @click.self="lightboxSrc = null">
        <button @click="prevImage()"
                class="absolute left-4 md:left-8 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/8 border border-white/15 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/15 transition-all z-10">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div class="relative max-w-[88vw] max-h-[88vh]">
            <img :src="lightboxSrc" class="max-w-full max-h-[88vh] object-contain rounded-lg shadow-2xl">
            <div class="absolute bottom-0 left-0 right-0 flex justify-between items-center px-4 py-3 bg-gradient-to-t from-black/80 to-transparent rounded-b-lg">
                <span class="text-xs text-white/50" x-text="(lightboxIndex + 1) + ' / ' + galleryImages.length"></span>
                <button @click="lightboxSrc = null" class="text-xs text-white/40 hover:text-white uppercase tracking-widest transition">✕ Close</button>
            </div>
        </div>
        <button @click="nextImage()"
                class="absolute right-4 md:right-8 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/8 border border-white/15 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/15 transition-all z-10">
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </button>
    </div>

    
    <section class="relative min-h-[72vh] flex items-end overflow-hidden pb-16 md:pb-20">

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($coverPhoto): ?>
            <img src="<?php echo e(Storage::url($coverPhoto)); ?>"
                 class="absolute inset-0 w-full h-full object-cover scale-105"
                 style="filter: brightness(0.38) saturate(1.2);" alt="">
        <?php else: ?>
            <div class="absolute inset-0 bg-gradient-to-br from-neutral-950 via-neutral-900 to-neutral-950"></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/20 to-transparent"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-transparent to-transparent"></div>

        
        <div class="absolute inset-0 opacity-[0.035]"
             style="background-image: url(\"data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E\"); background-size: 180px;"></div>

        <div class="relative z-10 max-w-7xl mx-auto px-6 md:px-16 w-full">

            
            <div class="anim-hero-meta mb-8">
                <a href="<?php echo e(route('tenant.show', $tenant->slug)); ?>" wire:navigate
                   class="inline-flex items-center gap-2 text-[10px] tracking-[0.2em] uppercase text-white/35 hover:text-brand-400 transition-colors group">
                    <svg class="w-3 h-3 group-hover:-translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 12H5m7-7l-7 7 7 7"/></svg>
                    Back to <?php echo e($tenant->name); ?>

                </a>
            </div>

            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-10">

                
                <div class="max-w-2xl">
                    <div class="anim-hero-meta flex items-center gap-2 mb-4">
                        <span class="w-6 h-px bg-brand-500"></span>
                        <span class="text-[10px] tracking-[0.25em] uppercase text-brand-400 font-bold">Offerings</span>
                    </div>
                    <h1 class="anim-hero-title font-display text-5xl md:text-7xl font-semibold text-white leading-[0.9] tracking-tight">
                        What<br>
                        <em class="italic bg-gradient-to-r from-brand-300 via-brand-400 to-cyan-400 bg-clip-text text-transparent">We Offer</em>
                    </h1>
                    <p class="anim-hero-meta mt-4 text-sm text-white/45 max-w-sm leading-relaxed">
                        Discover our spaces and services — crafted for comfort, built for memory.
                    </p>

                    
                    <div class="anim-hero-stats mt-8 flex items-center gap-6">
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-brand-400 tabular-nums"><?php echo e($this->properties->count()); ?></div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-0.5">Rooms</div>
                        </div>
                        <div class="w-px h-10 bg-white/10"></div>
                        <div class="text-center">
                            <div class="font-display text-4xl font-medium text-brand-400 tabular-nums"><?php echo e($this->services->count()); ?></div>
                            <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-0.5">Services</div>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($galleryImages)): ?>
                            <div class="w-px h-10 bg-white/10"></div>
                            <div class="text-center">
                                <div class="font-display text-4xl font-medium text-brand-400 tabular-nums"><?php echo e(count($galleryImages)); ?></div>
                                <div class="text-[10px] tracking-[0.18em] uppercase text-white/35 mt-0.5">Photos</div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($galleryImages)): ?>
                    <div class="anim-hero-ctas flex flex-col items-start lg:items-end gap-5">

                        
                        <div class="gallery-strip w-full lg:w-80 opacity-80 hover:opacity-100 transition-opacity cursor-pointer"
                             @click="openGallery()">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_slice($galleryImages, 0, 5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $img): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <div class="overflow-hidden <?php echo e($i === 0 ? 'rounded-l-xl' : ''); ?> <?php echo e($i === 4 ? 'rounded-r-xl' : ''); ?>">
                                    <img src="<?php echo e(Storage::url($img)); ?>"
                                         class="w-full h-full object-cover hover:scale-110 transition duration-700 brightness-75"
                                         alt="" loading="lazy">
                                </div>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </div>

                        
                        <button @click="openGallery()"
                                class="gallery-pill inline-flex items-center gap-2.5 px-5 py-2.5 rounded-full
                                       bg-white/8 border border-white/20 text-white/70
                                       hover:bg-brand-500/20 hover:border-brand-400/50 hover:text-white
                                       text-xs font-semibold uppercase tracking-widest transition-all duration-300
                                       shadow-lg shadow-black/20">
                            <svg class="w-3.5 h-3.5 text-brand-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            View All Photos
                        </button>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            </div>
        </div>
    </section>

    
    <div class="sticky top-16 z-20 py-3 bg-black/60 backdrop-blur-xl border-b border-white/6">
        <div class="max-w-7xl mx-auto px-6 md:px-16 flex items-center justify-between gap-4">
            <div class="flex items-center gap-2">
                <button wire:click="$set('activeTab','accommodations')"
                        class="tab-pill <?php echo e($activeTab === 'accommodations' ? 'active' : ''); ?>">
                    Accommodations
                    <span class="ml-1.5 text-[10px] opacity-70"><?php echo e($this->properties->count()); ?></span>
                </button>
                <button wire:click="$set('activeTab','services')"
                        class="tab-pill <?php echo e($activeTab === 'services' ? 'active' : ''); ?>">
                    Services
                    <span class="ml-1.5 text-[10px] opacity-70"><?php echo e($this->services->count()); ?></span>
                </button>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($galleryImages)): ?>
                <button @click="openGallery()"
                        class="hidden sm:inline-flex items-center gap-1.5 text-[10px] tracking-widest uppercase text-white/35 hover:text-brand-400 transition-colors font-semibold">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    Gallery
                </button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    
    <div class="max-w-7xl mx-auto px-6 md:px-16 py-12 md:py-16">

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTab === 'accommodations'): ?>
            <div class="anim-section mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Stay & Explore</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-white">
                    Available <em class="italic text-brand-400">Accommodations</em>
                </h2>
                <p class="mt-2 text-sm text-white/40 max-w-md">All rooms shown are immediately bookable for your stay.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->properties; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $property): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="prop-card flex flex-col" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'prop-'.e($property->id).''; ?>wire:key="prop-<?php echo e($property->id); ?>"
                         style="animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) <?php echo e($index * 80); ?>ms both">

                        
                        <div class="prop-card-image">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->images->isNotEmpty()): ?>
                                <img src="<?php echo e(asset('storage/'.$property->images->first()->image_path)); ?>"
                                     alt="<?php echo e($property->name); ?>">
                            <?php else: ?>
                                <div class="w-full h-full bg-white/4 flex items-center justify-center text-white/20">
                                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <div class="absolute top-3 left-3 flex flex-col gap-1.5">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->propertyType): ?>
                                    <span class="bg-black/65 backdrop-blur-sm text-[10px] font-bold text-brand-300 px-2.5 py-1 rounded-full tracking-wider uppercase">
                                        <?php echo e($property->propertyType->name); ?>

                                    </span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                            <span class="absolute top-3 right-3 bg-black/65 backdrop-blur-sm text-[10px] font-bold text-emerald-300 px-2.5 py-1 rounded-full flex items-center gap-1 uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-300 shadow-[0_0_6px_rgba(52,211,153,0.7)]"></span>
                                Available
                            </span>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->images->count() > 1): ?>
                                <span class="absolute bottom-3 right-3 bg-black/60 backdrop-blur-sm text-[10px] text-white/60 px-2 py-0.5 rounded-full flex items-center gap-1">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/></svg>
                                    <?php echo e($property->images->count()); ?>

                                </span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>

                        
                        <div class="p-5 flex flex-col flex-1">
                            <h3 class="font-display text-xl font-semibold text-white mb-2 leading-snug">
                                <?php echo e($property->name); ?>

                            </h3>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($property->description): ?>
                                <p class="text-sm text-white/50 line-clamp-2 mb-4 flex-1 leading-relaxed">
                                    <?php echo e($property->description); ?>

                                </p>
                            <?php else: ?>
                                <div class="flex-1"></div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            
                            <div class="flex items-center justify-between pt-4 mt-auto"
                                 style="border-top: 1px solid rgba(255,255,255,0.08)">
                                <div>
                                    <span class="font-display text-2xl font-semibold text-brand-400">
                                        ₱<?php echo e(number_format($property->price, 2)); ?>

                                    </span>
                                    <span class="text-[10px] text-white/60 ml-1 uppercase tracking-wider font-medium">/ night</span>
                                </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                                    <a href="<?php echo e(route('booking.create', ['publicproperty' => $property->id])); ?>"
                                       class="py-2 px-5 rounded-full bg-brand-600 hover:bg-brand-500 text-white text-[10px] font-bold uppercase tracking-widest transition-all shadow-lg shadow-brand-600/25 hover:shadow-brand-500/40 hover:-translate-y-0.5">
                                        Reserve
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo e(route('login', ['redirect' => url()->current()])); ?>"
                                       class="py-2 px-5 rounded-full border border-white/15 hover:bg-white/8 text-white/60 hover:text-white text-[10px] font-bold uppercase tracking-widest transition-all">
                                        Login to Book
                                    </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="col-span-full text-center py-20"
                         style="border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; background: rgba(255,255,255,0.02);">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <h3 class="font-display text-xl italic text-white/35">No accommodations listed yet.</h3>
                        <p class="text-xs text-white/25 mt-2 tracking-wide">Check back soon — new rooms may be added.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTab === 'services'): ?>
            <div class="anim-section mb-10">
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-5 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Enhance Your Stay</span>
                </div>
                <h2 class="font-display text-3xl md:text-5xl font-medium text-white">
                    Add‑on <em class="italic text-brand-400">Services</em>
                </h2>
                <p class="mt-2 text-sm text-white/40 max-w-md">Extras available to make your stay even more special.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $this->services; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $service): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div class="svc-card flex flex-col" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'svc-'.e($service->id).''; ?>wire:key="svc-<?php echo e($service->id); ?>"
                         style="animation: fadeUp 0.6s cubic-bezier(0.16,1,0.3,1) <?php echo e($index * 70); ?>ms both">

                        
                        <div class="w-10 h-10 rounded-xl bg-brand-500/15 border border-brand-400/20 flex items-center justify-center text-brand-400 mb-4 flex-none">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        </div>

                        <h3 class="font-display text-lg font-semibold text-white mb-2 leading-snug"><?php echo e($service->name); ?></h3>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($service->description): ?>
                            <p class="text-sm text-white/50 flex-1 mb-5 leading-relaxed"><?php echo e($service->description); ?></p>
                        <?php else: ?>
                            <div class="flex-1 mb-5"></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div class="flex items-center justify-between pt-4 mt-auto"
                             style="border-top: 1px solid rgba(255,255,255,0.07)">
                            <span class="font-display text-2xl font-semibold text-white">
                                ₱<?php echo e(number_format($service->price, 2)); ?>

                            </span>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                                <span class="text-[10px] font-bold uppercase tracking-widest text-white/30 bg-white/5 border border-white/8 rounded-full px-3 py-1">
                                    Add at checkout
                                </span>
                            <?php else: ?>
                                <a href="<?php echo e(route('login', ['redirect' => url()->current()])); ?>"
                                   class="text-[10px] font-bold uppercase tracking-widest text-brand-400 hover:text-brand-300 transition-colors">
                                    Login to add →
                                </a>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    <div class="col-span-full text-center py-20"
                         style="border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; background: rgba(255,255,255,0.02);">
                        <svg class="w-12 h-12 mx-auto mb-4 text-white/15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/></svg>
                        <h3 class="font-display text-xl italic text-white/35">No services available yet.</h3>
                        <p class="text-xs text-white/25 mt-2 tracking-wide">Check back soon — new add‑ons may appear.</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($galleryImages)): ?>
        <div class="section-rule mx-6 md:mx-16 mb-0"></div>
        <section class="max-w-7xl mx-auto px-6 md:px-16 py-14 flex flex-col sm:flex-row items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-4 h-px bg-brand-500"></span>
                    <span class="text-[10px] tracking-[0.22em] uppercase text-brand-500 font-bold">Photo Gallery</span>
                </div>
                <p class="text-white/50 text-sm">
                    Explore all <span class="text-white font-semibold"><?php echo e(count($galleryImages)); ?> photos</span> of <?php echo e($tenant->name); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gallerySubtitle): ?> — <em class="italic text-white/40"><?php echo e($gallerySubtitle); ?></em> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </p>
            </div>
            <button @click="openGallery()"
                    class="flex-none inline-flex items-center gap-2.5 px-7 py-3 rounded-full
                           bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold uppercase tracking-widest
                           transition-all shadow-xl shadow-brand-600/25 hover:shadow-brand-500/35 hover:-translate-y-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Open Gallery
            </button>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/ed8c353c.blade.php ENDPATH**/ ?>