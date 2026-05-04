<?php
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attribu tes\Layout;
use Livewire\Attributes\Title;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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

    
    <div x-show="$wire.message" x-transition.duration.300ms class="fixed bottom-6 right-6 z-50 bg-emerald-600 text-white px-5 py-3 rounded-full shadow-xl flex items-center gap-2 text-sm font-medium">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span x-text="$wire.message"></span>
    </div>

    
    <div x-show="previewImage" x-cloak class="fixed inset-0 z-50 bg-black/90 flex items-center justify-center p-4" @click.self="previewImage = null" @keydown.escape.window="previewImage = null">
        <div class="relative max-w-5xl max-h-[90vh]">
            <button @click="previewImage = null" class="absolute -top-12 right-0 text-white hover:text-gray-300 text-sm">Close</button>
            <img :src="previewImage" class="rounded-2xl shadow-2xl max-h-[85vh] w-auto">
        </div>
    </div>

    
    <section class="relative min-h-screen flex flex-col justify-between pt-8 pb-12 bg-black">
        <div class="absolute inset-0 z-0">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentCover): ?>
                <img src="<?php echo e(Storage::url($currentCover)); ?>" class="w-full h-full object-cover object-center" alt="Cover photo" loading="eager">
            <?php else: ?>
                <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900"></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <div class="absolute inset-0 bg-black/40 bg-gradient-to-b from-black/60 via-transparent to-black/90 z-1"></div>

        <nav class="relative z-10 px-6 md:px-12 flex justify-between items-center text-sm font-semibold tracking-wide">
            <div class="w-2.5 h-2.5"></div>
            <ul class="hidden md:flex gap-16 text-gray-300">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tagArray; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tag-'.e($loop->index).''; ?>wire:key="tag-<?php echo e($loop->index); ?>"><a href="#" class="<?php echo e($loop->first ? 'text-white border-b-2 border-white pb-1' : 'hover:text-white transition-colors'); ?>"><?php echo e($tag); ?></a></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
            <div class="w-32 border-t border-gray-400 hidden md:block"></div>
        </nav>

        <div class="relative z-10 px-6 md:px-12 mt-20 flex-grow">
            <div class="max-w-4xl">
                <h1 class="text-6xl sm:text-7xl md:text-8xl lg:text-9xl font-black leading-[1.1] tracking-tight text-white">
                    <?php echo e($spotName); ?>

                </h1>
                <div class="w-24 h-1 bg-red-500 mt-6"></div>
            </div>
        </div>

        <div class="relative z-10 px-6 md:px-12 flex flex-col gap-6 w-full max-w-5xl">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-12 text-xs text-gray-300 leading-relaxed">
                <?php
                    $descParagraphs = array_filter(array_map('trim', explode("\n", $description)));
                    $descParagraphs = array_pad($descParagraphs, 3, '');
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $descParagraphs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $para): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <p <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'desc-'.e($i).''; ?>wire:key="desc-<?php echo e($i); ?>" class="break-words whitespace-normal"><?php echo e($para ?: ''); ?></p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        </div>

        <div class="absolute bottom-6 right-6 z-20 flex gap-2">
            <label class="bg-black/60 backdrop-blur-md px-3 py-1 rounded-full text-white text-xs cursor-pointer hover:bg-black/80 transition">
                Change Cover
                <input type="file" wire:model="coverPhoto" accept="image/*" class="hidden">
            </label>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentCover): ?>
                <button wire:click="removeCover" class="bg-black/60 backdrop-blur-md px-3 py-1 rounded-full text-white text-xs hover:bg-red-500/80 transition">Remove</button>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['coverPhoto'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs absolute bottom-2 left-6 z-20"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>

    
    <section class="bg-black py-24 px-6 md:px-12 relative">
        <div class="text-center mb-16">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gallerySubtitle): ?>
                <p class="text-gray-400 text-xs tracking-wider mb-2"><?php echo e($gallerySubtitle); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($galleryTitle): ?>
                <h2 class="text-2xl md:text-3xl font-bold tracking-wide"><?php echo e($galleryTitle); ?></h2>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($gallery) > 0): ?>
            <div id="ranking-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 max-w-7xl mx-auto" wire:ignore>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gallery; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $path): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <div data-path="<?php echo e($path); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gallery-'.e($index).''; ?>wire:key="gallery-<?php echo e($index); ?>" class="group cursor-pointer">
                        <div class="w-full h-[350px] overflow-hidden rounded-sm relative">
                            <img src="<?php echo e(Storage::url($path)); ?>" class="w-full h-full object-cover grayscale-[30%] group-hover:grayscale-0 group-hover:scale-105 transition-all duration-500" @click="previewImage = '<?php echo e(Storage::url($path)); ?>'" alt="Destination image" loading="lazy">
                            <button type="button" wire:click="deletePhoto(<?php echo e($index); ?>)" class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1.5 opacity-0 group-hover:opacity-100 transition shadow-lg">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                            <div class="absolute bottom-2 left-2 cursor-grab active:cursor-grabbing drag-handle bg-black/60 rounded-full p-1.5 text-white opacity-0 group-hover:opacity-100 transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16"/><path d="M8 4v16M16 4v16"/></svg>
                            </div>
                        </div>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-16 text-gray-400 border-2 border-dashed border-gray-700 rounded-2xl max-w-7xl mx-auto">
                <svg class="mx-auto h-14 w-14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="mt-3">No destinations yet</p>
                <p class="text-sm text-gray-500">Upload images below to create your ranking</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="mt-16 flex justify-between max-w-7xl mx-auto items-center">
            <div class="w-[45%] border-t border-gray-800"></div>
            <div class="w-[45%] border-t border-gray-800"></div>
        </div>
    </section>

    
    <section class="relative h-[80vh] bg-cover bg-center flex items-end pb-12 px-6 md:px-12"
        <?php if($footerBackground): ?> style="background-image: url('<?php echo e(Storage::url($footerBackground)); ?>'); background-size: cover; background-position: center;" <?php else: ?> style="background-image: linear-gradient(to bottom right, #1a1a2e, #16213e);" <?php endif; ?>>
        <div class="absolute inset-0 bg-black/60 bg-gradient-to-r from-black/90 to-transparent"></div>

        <div class="relative z-10 w-full flex flex-col md:flex-row justify-between items-start md:items-end gap-8">
            <div class="max-w-md">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerTitle): ?>
                    <h2 class="text-4xl md:text-5xl font-extrabold leading-tight mb-8 whitespace-pre-line"><?php echo e($footerTitle); ?></h2>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerDescription): ?>
                    <p class="text-xs text-gray-300 leading-relaxed pr-8 break-words"><?php echo e($footerDescription); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="flex flex-col items-end gap-6">
                <div class="flex gap-4">
                    <div class="w-48 h-28 relative overflow-hidden rounded-sm group cursor-pointer border border-gray-700">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                            <img src="<?php echo e(Storage::url($footerThumb1)); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer thumbnail 1" loading="lazy">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center text-gray-500 text-xs">No image</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="w-48 h-28 overflow-hidden rounded-sm group cursor-pointer border border-gray-700">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                            <img src="<?php echo e(Storage::url($footerThumb2)); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" alt="Footer thumbnail 2" loading="lazy">
                        <?php else: ?>
                            <div class="w-full h-full bg-gray-800 flex items-center justify-center text-gray-500 text-xs">No image</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    
    <div class="max-w-7xl mx-auto px-6 md:px-12 pb-12 mt-12">
        <div class="bg-gray-900 rounded-2xl p-6 border border-gray-800 space-y-8">
            <h3 class="text-lg font-semibold text-white">Manage Gallery & Content</h3>

            
            <div class="border-2 border-dashed border-gray-700 rounded-xl p-6 text-center hover:border-blue-500 transition bg-black/40">
                <input type="file" wire:model.live="newPhotos" multiple accept="image/*" class="hidden" id="gallery-upload" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gallery-upload-input-'.e(count($newPhotos)).''; ?>wire:key="gallery-upload-input-<?php echo e(count($newPhotos)); ?>">
                <label for="gallery-upload" class="cursor-pointer block">
                    <svg class="mx-auto h-10 w-10 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    <p class="mt-2 text-sm font-medium text-gray-300">Click to select images (multiple allowed)</p>
                    <p class="text-xs text-gray-500">PNG, JPG up to 5MB each</p>
                </label>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newPhotos.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-red-500 text-xs mt-1"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($newPhotos) > 0): ?>
                <div>
                    <div class="flex justify-between items-center mb-3">
                        <span class="text-sm font-semibold text-gray-300">Pending (<?php echo e(count($newPhotos)); ?>)</span>
                        <button wire:click="uploadGallery" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded-full shadow transition">Upload all</button>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $newPhotos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <div class="relative" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'pending-'.e($index).''; ?>wire:key="pending-<?php echo e($index); ?>">
                                <img src="<?php echo e($photo->temporaryUrl()); ?>" class="w-full h-24 object-cover rounded-lg" alt="Preview">
                                <button wire:click="removeNewPhoto(<?php echo e($index); ?>)" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 shadow">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <form wire:submit="saveOverview" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">Description</label>
                    <textarea wire:model="description" rows="5" class="w-full rounded-xl border-gray-700 bg-black text-white focus:ring-2 focus:ring-blue-500 px-4 py-2 resize-y break-words whitespace-pre-wrap" placeholder="Write a compelling description..."></textarea>
                    <div class="flex justify-between items-center mt-1 text-xs text-gray-500">
                        <span>Max 5000 characters</span>
                        <div class="w-32 bg-gray-800 rounded-full h-1.5">
                            <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?php echo e($this->getDescriptionPercent()); ?>%"></div>
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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                                <img src="<?php echo e(Storage::url($footerThumb1)); ?>" class="h-16 w-16 object-cover rounded-lg" alt="Thumb 1">
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <label class="bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded-full text-xs cursor-pointer">
                                Upload
                                <input type="file" wire:model="footerThumb1File" accept="image/*" class="hidden">
                            </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                                <button type="button" wire:click="removeFooterThumb1" class="text-red-400 hover:text-red-300 text-xs">Remove</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Footer Thumbnail 2</label>
                        <div class="flex items-center gap-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                                <img src="<?php echo e(Storage::url($footerThumb2)); ?>" class="h-16 w-16 object-cover rounded-lg" alt="Thumb 2">
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <label class="bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded-full text-xs cursor-pointer">
                                Upload
                                <input type="file" wire:model="footerThumb2File" accept="image/*" class="hidden">
                            </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                                <button type="button" wire:click="removeFooterThumb2" class="text-red-400 hover:text-red-300 text-xs">Remove</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Footer Background Image</label>
                        <div class="flex items-center gap-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerBackground): ?>
                                <div class="w-20 h-20 rounded-lg overflow-hidden">
                                    <img src="<?php echo e(Storage::url($footerBackground)); ?>" class="w-full h-full object-cover" alt="Footer background">
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <label class="bg-gray-800 hover:bg-gray-700 px-3 py-1 rounded-full text-xs cursor-pointer">
                                Upload
                                <input type="file" wire:model="footerBackgroundFile" accept="image/*" class="hidden">
                            </label>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerBackground): ?>
                                <button type="button" wire:click="removeFooterBackground" class="text-red-400 hover:text-red-300 text-xs">Remove</button>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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

    <?php if (! $__env->hasRenderedOnce('456f0af7-3a14-448d-afd6-9e8789f43389')): $__env->markAsRenderedOnce('456f0af7-3a14-448d-afd6-9e8789f43389'); ?>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
    <?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/a5cec608.blade.php ENDPATH**/ ?>