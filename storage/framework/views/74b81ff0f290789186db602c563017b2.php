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

<div class="relative z-10" x-data="{ previewImage: null }" @keydown.escape.window="previewImage = null">

    
    <div x-show="previewImage" x-cloak class="lightbox-overlay fixed inset-0 z-50 flex items-center justify-center bg-black/95 backdrop-blur-md animate-fadeIn"
         @click.self="previewImage = null">
        <div class="relative max-w-[90vw] max-h-[90vh]">
            <button @click="previewImage = null" class="absolute -top-8 right-0 text-white/60 hover:text-brand-400 text-xs uppercase tracking-wider flex items-center gap-1">✕ Close</button>
            <img :src="previewImage" class="max-w-full max-h-[88vh] rounded-md shadow-2xl object-contain">
        </div>
    </div>

    
    <section class="hero-section relative h-screen min-h-[700px] overflow-hidden flex flex-col justify-end">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($coverPhoto): ?>
            <img src="<?php echo e(Storage::url($coverPhoto)); ?>" class="hero-img absolute inset-0 w-full h-full object-cover filter brightness-75" alt="<?php echo e($tenant->name); ?>" loading="eager">
        <?php else: ?>
            <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900"></div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        
        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/30 to-transparent"></div>

        
        <div class="hidden md:block absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl h-72 opacity-20 blur-sm rounded-3xl glass-card"></div>

        <div class="relative z-10 px-6 md:px-16 pb-10 md:pb-16 max-w-7xl mx-auto w-full">
            <h1 class="font-display text-6xl md:text-8xl lg:text-9xl font-semibold leading-[0.9] tracking-tight text-white drop-shadow-md">
                <?php echo e($tenant->name); ?>

            </h1>

            <?php $desc = $this->getDescParagraphs(); ?>
            <div class="mt-8 grid grid-cols-1 md:grid-cols-[1.2fr,1.8fr,1fr] gap-6 text-sm font-light text-white/70">
                <p><?php echo e($desc[0]); ?></p>
                <p class="text-white/50"><?php echo e($desc[1]); ?></p>
                <p class="text-white/50"><?php echo e($desc[2]); ?></p>
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-16 pb-8 max-w-7xl mx-auto w-full flex flex-wrap items-center gap-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs uppercase tracking-widest shadow-lg shadow-brand-500/30 transition transform hover:-translate-y-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Reserve Your Experience
                </a>
            <?php else: ?>
                <a href="<?php echo e(route('login', ['redirect' => url()->current()])); ?>" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs uppercase tracking-widest shadow-lg shadow-brand-500/30 transition transform hover:-translate-y-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Book Now
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <a href="#gallery" class="text-xs uppercase tracking-[0.2em] text-white/50 hover:text-brand-400 transition flex items-center gap-1">
                Explore
                <svg class="w-4 h-4 animate-bounce text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
            </a>
        </div>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($galleryImages) && $this->galleryTitle): ?>
        <section id="gallery" class="py-20 md:py-32 px-6 md:px-16">
            <div class="max-w-7xl mx-auto">
                <h2 class="font-display text-4xl md:text-6xl lg:text-7xl font-medium leading-tight mb-12 tracking-tight text-white">
                    <?php echo $this->getGalleryTitleHtml(); ?>

                </h2>

                <div class="gallery-grid grid grid-cols-12 auto-rows-[180px] gap-4">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $galleryImages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $imagePath): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gallery-'.e($index).''; ?>wire:key="gallery-<?php echo e($index); ?>" class="gallery-item relative overflow-hidden rounded-xl cursor-pointer group shadow-sm hover:shadow-xl transition-shadow"
                             @click="previewImage = '<?php echo e(Storage::url($imagePath)); ?>'">
                            <img src="<?php echo e(Storage::url($imagePath)); ?>" class="w-full h-full object-cover filter brightness-95 group-hover:brightness-110 group-hover:scale-105 transition duration-700" alt="<?php echo e($tenant->name); ?> photo <?php echo e($index + 1); ?>" loading="lazy">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent opacity-0 group-hover:opacity-100 transition flex items-end p-3">
                                <div class="ml-auto w-9 h-9 rounded-full border border-white/80 text-white flex items-center justify-center backdrop-blur-sm group-hover:bg-brand-500/40 transition-colors">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                                </div>
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerTitle || $footerDescription || $footerThumb1 || $footerThumb2): ?>
        <section class="relative min-h-[90vh] flex flex-col justify-end overflow-hidden">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerBackground): ?>
                <img src="<?php echo e(Storage::url($footerBackground)); ?>" class="absolute inset-0 w-full h-full object-cover filter brightness-50" alt="" loading="lazy">
            <?php else: ?>
                <div class="absolute inset-0 bg-gradient-to-br from-gray-900 via-gray-800 to-black"></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="absolute inset-0 bg-gradient-to-bl from-black/90 via-black/50 to-black/30"></div>

            <div class="relative z-10 px-6 md:px-16 py-20 max-w-7xl mx-auto w-full grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-8 items-end">
                <div class="max-w-2xl">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerTitle): ?>
                        <h2 class="font-display text-4xl md:text-6xl lg:text-7xl font-medium text-white leading-tight mb-6 whitespace-pre-line">
                            <?php echo e($footerTitle); ?>

                        </h2>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerDescription): ?>
                        <p class="text-sm font-light text-white/70 leading-relaxed"><?php echo e($footerDescription); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="mt-12">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                            <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs uppercase tracking-widest shadow-lg shadow-brand-500/30 transition transform hover:-translate-y-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Plan Your Visit
                            </a>
                        <?php else: ?>
                            <a href="<?php echo e(route('login', ['redirect' => url()->current()])); ?>" class="inline-flex items-center gap-2 px-6 py-3.5 rounded-full bg-brand-600 hover:bg-brand-500 text-white font-semibold text-xs uppercase tracking-widest shadow-lg shadow-brand-500/30 transition transform hover:-translate-y-0.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                Book Now
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1 || $footerThumb2): ?>
                    <div class="flex lg:flex-col gap-4 shrink-0">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                            <div class="w-40 h-28 lg:w-48 lg:h-32 rounded-xl overflow-hidden shadow-xl hover:scale-105 transition-transform">
                                <img src="<?php echo e(Storage::url($footerThumb1)); ?>" class="w-full h-full object-cover filter brightness-90 hover:brightness-110 transition" alt="Preview 1">
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                            <div class="w-40 h-28 lg:w-48 lg:h-32 rounded-xl overflow-hidden shadow-xl hover:scale-105 transition-transform">
                                <img src="<?php echo e(Storage::url($footerThumb2)); ?>" class="w-full h-full object-cover filter brightness-90 hover:brightness-110 transition" alt="Preview 2">
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/3403fac3.blade.php ENDPATH**/ ?>