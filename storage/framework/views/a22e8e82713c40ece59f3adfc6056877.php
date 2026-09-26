<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\SiteSetting;
use App\Services\SvgSanitizerService;
?>




<?php $stats = $this->stats; ?>

<div class="p-4 sm:p-6 lg:p-8 max-w-5xl mx-auto space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Map Management</span>
            </div>
            <h1 class="font-display text-3xl md:text-4xl font-semibold text-gray-900 dark:text-white">
                Marker <em class="italic text-primary-600 dark:text-primary-400">Categories</em>
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-2">
                Manage the categories used for sub-locations across the map.
            </p>
        </div>
    </div>

    
    <form wire:submit="addCategory"
          class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">

        <div class="flex items-center gap-3">
            <span class="w-5 h-px bg-primary-600"></span>
            <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Add New Category
            </h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-start">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Key (slug) <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model="newKey"
                       maxlength="50"
                       autocomplete="off"
                       class="input w-full font-mono text-sm"
                       placeholder="e.g. restaurant">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newKey'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Label <span class="text-rose-500">*</span>
                </label>
                <input type="text"
                       wire:model="newLabel"
                       maxlength="100"
                       autocomplete="off"
                       class="input w-full"
                       placeholder="e.g. Restaurant">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newLabel'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Color <span class="text-rose-500">*</span>
                </label>
                <div class="flex items-center gap-2">
                    <input type="color"
                           wire:model="newColor"
                           aria-label="Pick a color"
                           class="h-11 w-14 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-1 shrink-0 bg-white dark:bg-gray-800">
                    <input type="text"
                           wire:model="newColor"
                           maxlength="7"
                           autocomplete="off"
                           class="input w-full uppercase font-mono text-sm"
                           placeholder="#000000">
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newColor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Icon (SVG) <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                </label>
                <input type="file"
                       wire:model="newIcon"
                       accept=".svg,image/svg+xml"
                       class="block w-full text-sm text-gray-500 dark:text-gray-400
                              file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0
                              file:text-xs file:font-semibold
                              file:bg-primary-50 dark:file:bg-primary-500/15
                              file:text-primary-700 dark:file:text-primary-300
                              hover:file:bg-primary-100 dark:hover:file:bg-primary-500/25
                              transition cursor-pointer
                              focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newIcon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div wire:loading wire:target="newIcon" class="mt-1.5 text-xs text-primary-600 dark:text-primary-400 inline-flex items-center gap-1.5">
                    <svg class="animate-spin h-3 w-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Uploading…
                </div>
            </div>
        </div>

        <div class="flex justify-end pt-2">
            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="addCategory"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="addCategory" class="inline-flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Add Category
                </span>
                <span wire:loading wire:target="addCategory" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Adding…
                </span>
            </button>
        </div>
    </form>

    
    <div class="space-y-4">
        <div class="flex items-center justify-between px-1">
            <div class="flex items-center gap-3">
                <span class="w-5 h-px bg-primary-600"></span>
                <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Manage Categories
                </h2>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['total'] > 0): ?>
                <span class="text-xs text-gray-500 dark:text-gray-400 tabular-nums">
                    <strong class="text-gray-900 dark:text-white font-semibold"><?php echo e($stats['total']); ?></strong> total
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stats['inactive'] > 0): ?>
                        · <span class="text-amber-600 dark:text-amber-400 font-semibold"><?php echo e($stats['inactive']); ?> hidden</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php $isActive = $cat['is_active'] ?? true; ?>
            <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'category-'.e($cat['key'] ?: 'idx-' . $loop->index).''; ?>wire:key="category-<?php echo e($cat['key'] ?: 'idx-' . $loop->index); ?>"
                 class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 flex flex-col lg:flex-row lg:items-center gap-5 transition-all duration-200 hover:shadow-md <?php echo e(!$isActive ? 'opacity-70 bg-gray-50 dark:bg-gray-800/40' : ''); ?>">

                
                <div class="flex items-center gap-4 lg:w-1/4 shrink-0">
                    <div class="h-12 w-12 rounded-xl flex items-center justify-center shrink-0 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 shadow-sm">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($cat['icon_path']) && Storage::disk('public')->exists($cat['icon_path'])): ?>
                            
                            <img src="/storage/<?php echo e(ltrim($cat['icon_path'], '/')); ?>"
                                 class="w-full h-full object-contain"
                                 alt="<?php echo e($cat['label']); ?>"
                                 loading="lazy"
                                 decoding="async">
                        <?php elseif(!empty($cat['icon_svg'])): ?>
                            <div class="w-full h-full text-gray-700 dark:text-gray-300">
                                <?php if (isset($component)) { $__componentOriginal9906aeaff1ebf5f586496f6e330b3b36 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.safe-svg','data' => ['svg' => $cat['icon_svg'],'class' => 'w-full h-full stroke-current fill-none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('safe-svg'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['svg' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($cat['icon_svg']),'class' => 'w-full h-full stroke-current fill-none']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36)): ?>
<?php $attributes = $__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36; ?>
<?php unset($__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9906aeaff1ebf5f586496f6e330b3b36)): ?>
<?php $component = $__componentOriginal9906aeaff1ebf5f586496f6e330b3b36; ?>
<?php unset($__componentOriginal9906aeaff1ebf5f586496f6e330b3b36); ?>
<?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="w-4 h-4 rounded-full shadow-sm" style="background-color: <?php echo e($cat['color']); ?>;"></div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white truncate"><?php echo e($cat['label']); ?></p>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 font-mono bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded mt-0.5 inline-block">
                            <?php echo e($cat['key']); ?>

                        </p>
                    </div>
                </div>

                
                <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                    <div>
                        <input type="text"
                               wire:model="categories.<?php echo e($index); ?>.label"
                               maxlength="100"
                               class="input w-full text-sm"
                               placeholder="Label">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["categories.$index.label"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-[10px] block mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <div class="flex items-center gap-2">
                            <input type="color"
                                   wire:model="categories.<?php echo e($index); ?>.color"
                                   aria-label="Color for <?php echo e($cat['label']); ?>"
                                   class="h-11 w-12 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer p-0.5 shrink-0 bg-white dark:bg-gray-800">
                            <input type="text"
                                   wire:model="categories.<?php echo e($index); ?>.color"
                                   maxlength="7"
                                   class="input w-full text-sm uppercase font-mono px-2"
                                   placeholder="#000000">
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["categories.$index.color"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-[10px] block mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <input type="file"
                               wire:model="categories.<?php echo e($index); ?>.icon_file"
                               accept=".svg,image/svg+xml"
                               class="block w-full text-xs text-gray-500 dark:text-gray-400
                                      file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0
                                      file:text-xs file:font-medium
                                      file:bg-gray-100 dark:file:bg-gray-700
                                      file:text-gray-700 dark:file:text-gray-300
                                      hover:file:bg-gray-200 dark:hover:file:bg-gray-600
                                      cursor-pointer
                                      focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["categories.$index.icon_file"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-[10px] block mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div wire:loading wire:target="categories.<?php echo e($index); ?>.icon_file" class="mt-1 text-[10px] text-primary-600 dark:text-primary-400">
                            Uploading…
                        </div>
                    </div>
                </div>

                
                <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0 pt-4 lg:pt-0 border-t lg:border-t-0 border-gray-100 dark:border-gray-700/60">

                    
                    <button type="button"
                            wire:click="toggleActive(<?php echo e($index); ?>)"
                            wire:loading.attr="disabled"
                            wire:target="toggleActive"
                            aria-label="<?php echo e($isActive ? 'Deactivate' : 'Activate'); ?> <?php echo e($cat['label']); ?>"
                            class="inline-flex items-center gap-2 px-2 py-1 rounded-lg text-sm text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white
                                   transition-colors
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <div class="relative inline-flex h-5 w-9 items-center rounded-full transition-colors <?php echo e($isActive ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-gray-600'); ?>">
                            <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform <?php echo e($isActive ? 'translate-x-4' : 'translate-x-1'); ?>"></span>
                        </div>
                        <span class="text-xs font-medium"><?php echo e($isActive ? 'Active' : 'Hidden'); ?></span>
                    </button>

                    <div class="flex items-center gap-1.5">
                        
                        <button type="button"
                                wire:click="updateCategory(<?php echo e($index); ?>)"
                                wire:loading.attr="disabled"
                                wire:target="updateCategory"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm
                                       transition-all duration-200 active:scale-95
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            <span wire:loading.remove wire:target="updateCategory">Save</span>
                            <span wire:loading wire:target="updateCategory" class="inline-flex items-center gap-1.5">
                                <svg class="animate-spin h-3 w-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Saving…
                            </span>
                        </button>

                        
                        <button type="button"
                                x-on:click="if (confirm('Delete \'<?php echo e(addslashes($cat['label'])); ?>\'? Markers using this key will fall back to Uncategorized.')) $wire.removeCategory(<?php echo e($index); ?>)"
                                wire:loading.attr="disabled"
                                wire:target="removeCategory"
                                aria-label="Delete <?php echo e($cat['label']); ?>"
                                class="inline-flex items-center justify-center gap-1.5 h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800 text-rose-700 dark:text-rose-300
                                       text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50
                                       disabled:opacity-60 disabled:cursor-not-allowed">
                            Delete
                        </button>
                    </div>
                </div>
            </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-10 flex flex-col items-center justify-center text-center">
                <div class="p-3 bg-gray-100 dark:bg-gray-800 rounded-2xl mb-3 text-gray-400 dark:text-gray-500">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">No marker categories yet</p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Get started by adding your first category above.</p>
            </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    
    <div x-data="{ toasts: [] }"
         x-on:toast.window="
             const id = Date.now() + Math.random();
             toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
             setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
         "
         class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none"
         aria-live="polite"
         role="status">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                 :class="{
                     'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                     'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                     'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                 }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/be62ab84.blade.php ENDPATH**/ ?>