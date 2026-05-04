<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\Storage;
?>

<div class="antialiased overflow-x-hidden bg-white dark:bg-black transition-colors duration-300"
     x-data="{ previewImage: null }">

    
    <div x-show="previewImage" x-cloak class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4" @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
        <div class="relative max-w-5xl max-h-[90vh]">
            <button @click="previewImage = null" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-sm">Close</button>
            <img :src="previewImage" class="rounded-2xl shadow-2xl max-h-[85vh] w-auto" alt="Enlarged view">
        </div>
    </div>

    
    <section class="relative min-h-screen flex flex-col justify-between pt-8 pb-12 bg-white dark:bg-black">
        <div class="absolute inset-0 z-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->coverPhoto): ?>
                <img src="<?php echo e(Storage::url($this->coverPhoto)); ?>" class="w-full h-full object-cover object-center" alt="<?php echo e($tenant->name); ?> cover" loading="eager">
            <?php else: ?>
                <div class="w-full h-full bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900"></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="absolute inset-0 bg-black/30 dark:bg-black/60 bg-gradient-to-b from-black/20 via-transparent to-black/50 dark:from-black/60 dark:via-transparent dark:to-black/90 z-1"></div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($this->tagArray) > 0): ?>
            <nav class="relative z-10 px-6 md:px-12 flex justify-between items-center text-sm font-semibold tracking-wide">
                <div class="w-2.5 h-2.5"></div>
                <ul class="hidden md:flex gap-16 text-white">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tagArray; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tag-'.e($index).''; ?>wire:key="tag-<?php echo e($index); ?>">
                            <a href="#" 
                               class="<?php echo e($index === 0 ? 'border-b-2 border-red-500 pb-1 text-white' : 'hover:text-gray-300 transition-colors text-white'); ?>">
                                <?php echo e($tag); ?>

                            </a>
                        </li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </ul>
                <div class="w-32 border-t border-white/30 hidden md:block"></div>
            </nav>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="relative z-10 px-6 md:px-12 mt-20 flex-grow">
            <div class="max-w-4xl">
                <h1 class="text-6xl sm:text-7xl md:text-8xl lg:text-9xl font-black leading-[1.1] tracking-tight text-white">
                    <?php echo e($tenant->name); ?>

                </h1>
                <div class="w-24 h-1 bg-red-500 mt-6"></div>
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-12 flex flex-col gap-6 w-full max-w-5xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 text-sm text-white/90 leading-relaxed">
                <?php
                    $descParagraphs = array_filter(array_map('trim', explode("\n", $this->description)));
                    $descParagraphs = array_pad($descParagraphs, 3, '');
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $descParagraphs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $para): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <p <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'desc-'.e($i).''; ?>wire:key="desc-<?php echo e($i); ?>" class="break-words whitespace-normal"><?php echo e($para ?: ''); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-12 mt-8 flex flex-wrap gap-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <a href="<?php echo e(route('business.offerings', $tenant->slug)); ?>" 
                   class="inline-flex items-center gap-2 bg-blue-600 dark:bg-red-600 hover:bg-blue-700 dark:hover:bg-red-700 text-white font-medium py-2.5 px-5 rounded-full shadow-sm transition-all duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Book Now
                </a>
            <?php else: ?>
                <?php
                    $returnUrl = url()->current();
                    $loginUrl = route('login', ['redirect' => $returnUrl]);
                ?>
                <a href="<?php echo e($loginUrl); ?>" 
                   class="inline-flex items-center gap-2 bg-blue-600 dark:bg-red-600 hover:bg-blue-700 dark:hover:bg-red-700 text-white font-medium py-2.5 px-5 rounded-full shadow-sm transition-all duration-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Book
                </a>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </section>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($this->galleryImages())): ?>
        <section class="bg-white dark:bg-black py-24 px-6 md:px-12 relative">
            <div class="max-w-7xl mx-auto">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->gallerySubtitle || $this->galleryTitle): ?>
                    <div class="text-center mb-12">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->gallerySubtitle): ?>
                            <p class="text-gray-300 text-sm tracking-wider mb-2"><?php echo e($this->gallerySubtitle); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->galleryTitle): ?>
                            <h2 class="text-3xl md:text-4xl font-bold text-white"><?php echo e($this->galleryTitle); ?></h2>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->galleryImages(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $imagePath): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gallery-'.e($index).''; ?>wire:key="gallery-<?php echo e($index); ?>" class="group cursor-pointer">
                            <div class="w-full h-[350px] overflow-hidden rounded-sm relative bg-gray-100 dark:bg-gray-800">
                                <img src="<?php echo e(Storage::url($imagePath)); ?>" 
                                     class="w-full h-full object-cover grayscale-[30%] group-hover:grayscale-0 group-hover:scale-105 transition-all duration-500" 
                                     @click="previewImage = '<?php echo e(Storage::url($imagePath)); ?>'" 
                                     alt="<?php echo e($tenant->name); ?> gallery image" 
                                     loading="lazy">
                            </div>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </div>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->footerTitle || $this->footerDescription || $this->footerThumb1 || $this->footerThumb2): ?>
        <section class="relative h-[80vh] bg-cover bg-center flex items-end pb-12 px-6 md:px-12"
            <?php if($this->footerBackground): ?> style="background-image: url('<?php echo e(Storage::url($this->footerBackground)); ?>'); background-size: cover; background-position: center;" <?php else: ?> style="background-image: linear-gradient(to bottom right, #0a0a2a, #1a1a3a);" <?php endif; ?>>
            <div class="absolute inset-0 bg-black/40 dark:bg-black/70 bg-gradient-to-r from-black/70 to-transparent"></div>

            <div class="relative z-10 w-full flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
                <div class="max-w-md">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->footerTitle): ?>
                        <h2 class="text-4xl md:text-5xl font-extrabold leading-tight mb-8 whitespace-pre-line text-white"><?php echo e($this->footerTitle); ?></h2>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->footerDescription): ?>
                        <p class="text-sm text-white/80 leading-relaxed pr-8 break-words"><?php echo e($this->footerDescription); ?></p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->footerThumb1 || $this->footerThumb2): ?>
                    <div class="flex gap-4">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->footerThumb1): ?>
                            <div class="w-48 h-28 overflow-hidden rounded-sm group cursor-pointer border border-white/20">
                                <img src="<?php echo e(Storage::url($this->footerThumb1)); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer image 1" loading="lazy">
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->footerThumb2): ?>
                            <div class="w-48 h-28 overflow-hidden rounded-sm group cursor-pointer border border-white/20">
                                <img src="<?php echo e(Storage::url($this->footerThumb2)); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer image 2" loading="lazy">
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </section>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/3403fac3.blade.php ENDPATH**/ ?>