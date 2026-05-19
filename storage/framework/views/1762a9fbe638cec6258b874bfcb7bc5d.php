<?php
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
?>

<?php $__env->startPush('styles'); ?>

<?php $__env->stopPush(); ?>

<div class="h-screen flex flex-col">
    
    <div class="h-16 flex items-center justify-between px-6 border-b border-white/10 bg-black/40 backdrop-blur-md shrink-0">
        <div>
            <h1 class="text-lg font-bold text-white">Tourist Spot Profile</h1>
            <p class="text-xs text-white/50">Edit your public listing – preview on the right</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasUnsavedChanges): ?>
                <span class="text-xs text-amber-400 flex items-center gap-1">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm1 11H9v-2h2v2zm0-4H9V5h2v4z"/></svg>
                    Unsaved changes
                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <button wire:click="saveAll"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-medium shadow transition disabled:opacity-60">
                <span wire:loading.remove>Save All</span>
                <span wire:loading class="flex items-center gap-1">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving…
                </span>
            </button>
        </div>
    </div>

    
    <div class="flex-1 flex overflow-hidden">
        
        <div class="w-full lg:w-5/12 xl:w-4/12 border-r border-white/10 overflow-y-auto bg-black/30 backdrop-blur-sm">
            
            <nav class="flex overflow-x-auto border-b border-white/10 px-4 py-2 space-x-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [
                    'cover'   => ['Cover', 'image'],
                    'details' => ['Details', 'pencil'],
                    'gallery' => ['Gallery', 'photograph'],
                    'footer'  => ['Footer', 'document'],
                ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => [$label, $icon]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <button wire:click="$set('activeSection', '<?php echo e($key); ?>')"
                            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium transition
                                   <?php echo e($activeSection === $key
                                      ? 'bg-brand-500/20 text-brand-300'
                                      : 'text-white/60 hover:bg-white/10 hover:text-white'); ?>">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($icon === 'image'): ?> <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <?php elseif($icon === 'pencil'): ?> <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        <?php elseif($icon === 'photograph'): ?> <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-2l-2-2H9L7 5H5a2 2 0 00-2 2z"/><path d="M12 17a4 4 0 100-8 4 4 0 000 8z"/></svg>
                        <?php elseif($icon === 'document'): ?> <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 12h6m-3-3v6m-7 4h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <span class="hidden sm:inline"><?php echo e($label); ?></span>
                    </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </nav>

            
            <div class="p-5 space-y-8">
                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeSection === 'cover'): ?>
                    <div>
                        <h2 class="text-base font-semibold text-white mb-4">Cover Photo</h2>
                        <div class="space-y-4">
                            <div class="aspect-video rounded-xl border-2 border-dashed border-white/20 overflow-hidden bg-white/5 flex items-center justify-center relative">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentCover): ?>
                                    <img src="<?php echo e(asset('storage/'. $currentCover)); ?>" class="w-full h-full object-cover" alt="Cover" loading="lazy">
                                <?php else: ?>
                                    <div class="text-center text-white/40">
                                        <svg class="mx-auto h-10 w-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <p class="text-sm">No cover photo yet</p>
                                    </div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="absolute bottom-3 right-3 flex gap-2">
                                    <label class="bg-white/10 hover:bg-white/20 px-3 py-1.5 rounded-lg text-xs font-medium cursor-pointer shadow text-white">
                                        Change
                                        <input type="file" wire:model="coverPhoto" accept="image/*" class="hidden">
                                    </label>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentCover): ?>
                                        <button wire:click="removeCover" class="bg-red-500/80 hover:bg-red-500 text-white px-2 py-1.5 rounded-lg text-xs">Remove</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                            <p class="text-xs text-white/40">Recommended: 1920x1080, max 5MB</p>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeSection === 'details'): ?>
                    <div>
                        <h2 class="text-base font-semibold text-white mb-4">Description & Tags</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-white/70 mb-1">Description</label>
                                <textarea wire:model.live.debounce.400ms="description" rows="6"
                                          class="w-full rounded-xl bg-white/5 border border-white/10 text-sm px-4 py-2.5 text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                          placeholder="Write a compelling description…"></textarea>
                                <div class="mt-1 text-xs text-white/40 flex justify-between">
                                    <span><?php echo e(mb_strlen($description)); ?>/5000 characters</span>
                                    <div class="w-24 bg-white/10 rounded-full h-1.5">
                                        <div class="bg-brand-600 h-1.5 rounded-full" style="width: <?php echo e($this->getDescriptionPercent()); ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-white/70 mb-1">Tags</label>
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tagArray; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-brand-500/20 text-brand-300 rounded-full text-sm">
                                            <?php echo e($tag); ?>

                                            <button type="button" wire:click="removeTag(<?php echo e($index); ?>)" class="hover:text-red-400">&times;</button>
                                        </span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                                <div class="flex gap-2" x-data="{ tagInput: '' }">
                                    <input type="text" x-ref="tagInput" x-model="tagInput"
                                           class="flex-1 rounded-xl bg-white/5 border border-white/10 text-sm px-3 py-2 text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                           placeholder="e.g., beach, mountain…"
                                           @keydown.enter.prevent="$wire.addTag(tagInput); tagInput=''">
                                    <button type="button"
                                            @click="$wire.addTag(tagInput); tagInput=''"
                                            class="px-4 py-2 bg-white/10 rounded-xl text-sm text-white/80 hover:bg-white/20 transition">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeSection === 'gallery'): ?>
                    <div>
                        <h2 class="text-base font-semibold text-white mb-4">Photo Gallery</h2>
                        <div class="space-y-4">
                            <div class="border-2 border-dashed border-white/20 rounded-xl p-6 text-center hover:border-brand-400/50 transition">
                                <input type="file" wire:model.live="newPhotos" multiple accept="image/*" class="hidden" id="gallery-input">
                                <label for="gallery-input" class="cursor-pointer">
                                    <svg class="mx-auto h-8 w-8 text-white/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                                    <p class="mt-2 text-sm text-white/60">Click to add images</p>
                                </label>
                            </div>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($newPhotos)): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-medium text-white">Pending (<?php echo e(count($newPhotos)); ?>)</span>
                                    <button wire:click="uploadGallery" class="bg-brand-600 hover:bg-brand-500 text-white text-xs px-3 py-1 rounded-full">Upload</button>
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $newPhotos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $photo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div class="relative rounded-lg overflow-hidden">
                                            <img src="<?php echo e($photo->temporaryUrl()); ?>" class="w-full h-24 object-cover" loading="lazy">
                                            <button wire:click="removeNewPhoto(<?php echo e($index); ?>)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 text-xs">&times;</button>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($gallery)): ?>
                                <div id="ranking-grid" class="grid grid-cols-3 gap-3" wire:ignore>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $gallery; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $path): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <div data-path="<?php echo e($path); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'gallery-'.e($index).''; ?>wire:key="gallery-<?php echo e($index); ?>" class="relative group cursor-pointer rounded-lg overflow-hidden">
                                            <img src="<?php echo e(asset('storage/'. $path)); ?>" class="w-full h-24 object-cover" loading="lazy">
                                            <button wire:click="deletePhoto(<?php echo e($index); ?>)" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition">&times;</button>
                                            <div class="drag-handle absolute bottom-1 left-1 bg-black/60 rounded p-0.5 text-white text-xs cursor-grab opacity-0 group-hover:opacity-100">⣿</div>
                                        </div>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                                <p class="text-xs text-white/40">Drag to reorder</p>
                            <?php else: ?>
                                <p class="text-sm text-white/40 italic">No photos yet.</p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                            <div class="space-y-3 pt-2">
                                <div>
                                    <label class="block text-sm font-medium text-white/70 mb-1">Gallery Subtitle</label>
                                    <input type="text" wire:model.live.debounce.400ms="gallerySubtitle"
                                           class="w-full rounded-xl bg-white/5 border border-white/10 text-sm px-3 py-2 text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                           placeholder="e.g., Pick your dream">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white/70 mb-1">Gallery Title</label>
                                    <input type="text" wire:model.live.debounce.400ms="galleryTitle"
                                           class="w-full rounded-xl bg-white/5 border border-white/10 text-sm px-3 py-2 text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                           placeholder="e.g., Destination Recommendations">
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeSection === 'footer'): ?>
                    <div>
                        <h2 class="text-base font-semibold text-white mb-4">Footer Section</h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-white/70 mb-1">Footer Title</label>
                                <textarea wire:model.live.debounce.400ms="footerTitle" rows="2"
                                          class="w-full rounded-xl bg-white/5 border border-white/10 text-sm px-3 py-2 text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                          placeholder="TRAVEL AND ENJOY YOUR HOLIDAY"></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-white/70 mb-1">Footer Description</label>
                                <textarea wire:model.live.debounce.400ms="footerDescription" rows="2"
                                          class="w-full rounded-xl bg-white/5 border border-white/10 text-sm px-3 py-2 text-white placeholder-white/30 focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                          placeholder="Slogan or description"></textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-white/70 mb-1">Thumbnail 1</label>
                                    <div class="flex items-center gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                                            <img src="<?php echo e(asset('storage/'. $footerThumb1)); ?>" class="h-12 w-12 object-cover rounded-lg" loading="lazy">
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <label class="bg-white/10 px-2 py-1 rounded text-xs cursor-pointer text-white/80 hover:bg-white/20 transition">
                                            Upload
                                            <input type="file" wire:model="footerThumb1File" accept="image/*" class="hidden">
                                        </label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                                            <button wire:click="removeFooterAsset('footerThumb1', 'footer_thumb_1')" class="text-red-400 text-xs">Remove</button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white/70 mb-1">Thumbnail 2</label>
                                    <div class="flex items-center gap-2">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                                            <img src="<?php echo e(asset('storage/'. $footerThumb2)); ?>" class="h-12 w-12 object-cover rounded-lg" loading="lazy">
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <label class="bg-white/10 px-2 py-1 rounded text-xs cursor-pointer text-white/80 hover:bg-white/20 transition">
                                            Upload
                                            <input type="file" wire:model="footerThumb2File" accept="image/*" class="hidden">
                                        </label>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                                            <button wire:click="removeFooterAsset('footerThumb2', 'footer_thumb_2')" class="text-red-400 text-xs">Remove</button>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-white/70 mb-1">Footer Background</label>
                                <div class="flex items-center gap-4">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerBackground): ?>
                                        <div class="w-16 h-16 rounded-lg overflow-hidden">
                                            <img src="<?php echo e(asset('storage/'. $footerBackground)); ?>" class="w-full h-full object-cover" loading="lazy">
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <label class="bg-white/10 px-2 py-1 rounded text-xs cursor-pointer text-white/80 hover:bg-white/20 transition">
                                        Upload
                                        <input type="file" wire:model="footerBackgroundFile" accept="image/*" class="hidden">
                                    </label>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerBackground): ?>
                                        <button wire:click="removeFooterAsset('footerBackground', 'footer_background')" class="text-red-400 text-xs">Remove</button>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>

        
        <div class="hidden lg:flex flex-1 bg-black/40 backdrop-blur-sm overflow-y-auto">
            <div class="w-full p-6 flex items-start justify-center">
                <div class="w-full max-w-md glass-card overflow-hidden border border-white/10 rounded-3xl shadow-2xl">
                    <div class="h-[600px] overflow-y-auto scrollbar-thin">
                        <!-- Hero -->
                        <div class="relative h-48 bg-cover bg-center <?php echo e($currentCover ? '' : 'bg-gradient-to-br from-gray-800 to-gray-900'); ?>"
                             <?php if($currentCover): ?> style="background-image: url('<?php echo e(asset('storage/'. $currentCover)); ?>');" <?php endif; ?>>
                            <div class="absolute inset-0 bg-black/40"></div>
                            <div class="absolute bottom-4 left-4 right-4">
                                <h1 class="text-white text-xl font-bold"><?php echo e($spotName); ?></h1>
                            </div>
                        </div>
                        <!-- Description -->
                        <div class="p-4 space-y-3">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($description): ?>
                                <p class="text-sm text-white/80 whitespace-pre-line"><?php echo e($description); ?></p>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($tagArray)): ?>
                                <div class="flex flex-wrap gap-1.5">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $tagArray; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                        <span class="px-2 py-0.5 bg-brand-500/20 text-brand-300 rounded-full text-xs"><?php echo e($tag); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($galleryTitle || $gallerySubtitle): ?>
                                <div class="pt-2">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($gallerySubtitle): ?>
                                        <p class="text-[10px] text-white/40 uppercase tracking-wider"><?php echo e($gallerySubtitle); ?></p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($galleryTitle): ?>
                                        <h3 class="text-base font-semibold text-white mt-0.5"><?php echo e($galleryTitle); ?></h3>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div class="grid grid-cols-3 gap-2">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = array_slice($gallery, 0, 6); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $img): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="aspect-square rounded-lg overflow-hidden bg-white/10">
                                        <img src="<?php echo e(asset('storage/'. $img)); ?>" class="w-full h-full object-cover" loading="lazy">
                                    </div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php for($i = count($gallery); $i < 3; $i++): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <div class="aspect-square rounded-lg bg-white/5 flex items-center justify-center text-white/30 text-2xl">+</div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>

                            
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerTitle || $footerDescription || $footerThumb1 || $footerThumb2 || $footerBackground): ?>
                                <div class="mt-6 pt-4 border-t border-white/10 relative overflow-hidden rounded-lg"
                                     <?php if($footerBackground): ?>
                                         style="background-image: url('<?php echo e(asset('storage/'. $footerBackground)); ?>');
                                                background-size: cover;
                                                background-position: center;"
                                     <?php endif; ?>
                                >
                                    <div class="absolute inset-0 bg-black/40"></div>
                                    <div class="relative z-10 p-3">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerTitle): ?>
                                            <h2 class="text-sm font-bold text-white whitespace-pre-line"><?php echo e($footerTitle); ?></h2>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerDescription): ?>
                                            <p class="text-xs text-white/80 mt-1"><?php echo e($footerDescription); ?></p>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <div class="flex gap-2 mt-3">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb1): ?>
                                                <img src="<?php echo e(asset('storage/'. $footerThumb1)); ?>" class="h-16 w-16 object-cover rounded-lg" loading="lazy">
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($footerThumb2): ?>
                                                <img src="<?php echo e(asset('storage/'. $footerThumb2)); ?>" class="h-16 w-16 object-cover rounded-lg" loading="lazy">
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        
        <div class="lg:hidden fixed bottom-4 right-4 z-30 flex gap-2">
            <button class="bg-brand-600 text-white px-4 py-2 rounded-full shadow-lg text-sm" @click="showPreview = !showPreview">
                <span x-text="showPreview ? 'Edit' : 'Preview'"></span>
            </button>
        </div>
    </div>

    
    <div x-data="{ show: false, message: '' }"
         x-on:saved.window="show = true; message = 'All changes saved.'; setTimeout(() => show = false, 3000)"
         x-show="show"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-brand-600 text-white px-5 py-2 rounded-full text-sm shadow-lg"
         x-transition>
        ⚡ <span x-text="message"></span>
    </div>

    <?php if (! $__env->hasRenderedOnce('e636689b-8fe3-45a2-ae43-a4c37b770a28')): $__env->markAsRenderedOnce('e636689b-8fe3-45a2-ae43-a4c37b770a28'); ?>
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
        <script>
            document.addEventListener('livewire:navigated', function () {
                const grid = document.getElementById('ranking-grid');
                if (grid && window.Sortable) {
                    new Sortable(grid, {
                        animation: 250,
                        handle: '.drag-handle',
                        onEnd: function (evt) {
                            const paths = Array.from(grid.children).map(c => c.getAttribute('data-path'));
                            window.Livewire.find('<?php echo e($_instance->getId()); ?>').updateGalleryOrder(paths);
                        }
                    });
                }
            });
        </script>
    <?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/a5cec608.blade.php ENDPATH**/ ?>