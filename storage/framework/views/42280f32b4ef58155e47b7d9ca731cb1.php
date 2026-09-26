<?php
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\PropertyImage;
use App\Models\PropertyAvailability;
use App\Scopes\TenantScope;
use App\Traits\ChecksTenantPermissions;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\CarbonPeriod;
use Carbon\Carbon;
?>




<div class="p-4 sm:p-6 lg:p-8 max-w-6xl mx-auto space-y-6
            pb-[max(1rem,env(safe-area-inset-bottom))]">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 4000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 border-l-4 border-l-emerald-500 p-4 rounded-xl text-xs sm:text-sm text-emerald-800 dark:text-emerald-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span><?php echo e(session('message')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-200 hover:bg-emerald-100 dark:hover:bg-emerald-500/10
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('error')): ?>
        <div x-data="{ show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             :class="show ? '' : 'hidden'"
             class="flex items-center justify-between bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 border-l-4 border-l-rose-500 p-4 rounded-xl text-xs sm:text-sm text-rose-800 dark:text-rose-300 font-medium shadow-sm">
            <div class="flex items-center gap-2.5">
                <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span><?php echo e(session('error')); ?></span>
            </div>
            <button type="button" @click="show = false"
                    class="inline-flex items-center justify-center h-11 w-11 sm:h-7 sm:w-7 rounded-md text-rose-500 hover:text-rose-700 dark:hover:text-rose-200 hover:bg-rose-100 dark:hover:bg-rose-500/10
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50"
                    aria-label="Dismiss">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-800">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-xs tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Inventory</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Add New Activity
            </h1>
            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                Create a bookable activity your customers can reserve.
            </p>
        </div>
        <a href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate
           class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                  transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>Back to Activities</span>
        </a>
    </div>

    <form wire:submit="save">
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <div class="lg:col-span-3 space-y-6">

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Activity Details
                        </h2>
                    </div>

                    <div>
                        <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Activity Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="field-name" wire:model="name" class="input" placeholder="e.g. Gawahon Falls Tour">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Activity Type <span class="text-rose-500">*</span>
                            </label>
                            <button type="button"
                                    wire:click="openNewTypeModal"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline
                                           py-2.5 -my-2.5 px-1 -mx-1 rounded
                                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95 transition-transform">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                                </svg>
                                <span>Add New Type</span>
                            </button>
                        </div>
                        <select id="field-type" wire:model="property_type_id" class="select">
                            <option value="">— Select a Type —</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->propertyTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($type->id); ?>" <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'type-opt-'.e($type->id).''; ?>wire:key="type-opt-<?php echo e($type->id); ?>">
                                    <?php echo e($type->name); ?><?php echo e(is_null($type->tenant_id) ? ' (Global)' : ' (Custom)'); ?>

                                </option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['property_type_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description
                        </label>
                        <textarea id="field-description"
                                  wire:model="description"
                                  rows="4"
                                  class="textarea"
                                  placeholder="Optional details about this activity"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Pricing &amp; Capacity
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label for="field-price" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Price (₱ / day)
                            </label>
                            <input type="number" id="field-price" step="0.01" min="0" wire:model="price" class="input" placeholder="0.00">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['price'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label for="field-capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Capacity (persons)
                            </label>
                            <input type="number" id="field-capacity" wire:model="capacity" min="1" class="input">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['capacity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label for="field-quantity" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Quantity (units)
                            </label>
                            <input type="number" id="field-quantity" wire:model="quantity" min="1" class="input">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['quantity'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <div class="lg:col-span-2 space-y-6">

                
                <div
                    x-data="{
                        ...imageCropper({
                            wireProperty: 'image',
                            aspect: 16 / 9,
                            title: 'Crop activity photo',
                            description: 'Wide 16:9 crop works best',
                            previewEvent: 'property-image-preview',
                        }),
                        ...avatarPreview(),
                        dragging: false,
                    }"
                    x-init="init()"
                    x-on:property-image-preview.window="setUrl($event.detail.url)"
                    x-on:property-image-cleared.window="clear()"
                    class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-4"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Activity Photo
                        </h2>
                    </div>

                    <div
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop.prevent="
                            dragging = false;
                            const dt = new DataTransfer();
                            for (const f of $event.dataTransfer.files) dt.items.add(f);
                            $refs.input.files = dt.files;
                            $refs.input.dispatchEvent(new Event('change'));
                        "
                        :class="dragging
                            ? 'border-primary-600 bg-primary-50 dark:bg-primary-500/10'
                            : 'border-gray-300 dark:border-gray-600'"
                        class="relative aspect-video border-2 border-dashed rounded-xl overflow-hidden transition-colors"
                    >
                        <input
                            x-ref="input"
                            id="property-image-input"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            x-on:change="pick($event)"
                        >

                        <img
                            :src="previewUrl || ''"
                            :class="previewUrl ? 'block' : 'hidden'"
                            alt="Activity photo preview"
                            class="absolute inset-0 w-full h-full object-cover"
                            loading="lazy"
                            decoding="async"
                        >

                        <label
                            for="property-image-input"
                            :class="previewUrl ? 'hidden' : 'flex'"
                            class="absolute inset-0 flex-col items-center justify-center p-6 text-center cursor-pointer
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]"
                        >
                            <svg class="h-10 w-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                Click or drag an image here
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5 max-w-xs mx-auto">
                                PNG, JPG, or WebP · max 5 MB · auto-cropped + compressed
                            </p>
                        </label>

                        <div
                            wire:loading.flex
                            wire:target="image"
                            class="absolute inset-0 bg-black/45 backdrop-blur-[2px] items-center justify-center pointer-events-none"
                            aria-hidden="true"
                        >
                            <span class="inline-flex items-center gap-2 text-xs font-semibold text-white">
                                <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                Uploading…
                            </span>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['image'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div :class="previewUrl ? 'flex' : 'hidden'" class="items-center justify-end gap-2">
                        <label for="property-image-input"
                               class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                      border border-gray-300 dark:border-gray-600
                                      bg-white dark:bg-gray-800
                                      text-gray-700 dark:text-gray-200
                                      text-xs font-semibold cursor-pointer
                                      transition-all duration-200 active:scale-95
                                      hover:bg-gray-50 dark:hover:bg-gray-700
                                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                      focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span>Replace</span>
                        </label>

                        <button type="button"
                                wire:click="removeImage"
                                class="inline-flex items-center justify-center gap-1.5 h-11 sm:h-9 px-3.5 rounded-lg
                                       border border-rose-300 dark:border-rose-500/40
                                       bg-white dark:bg-gray-800
                                       text-rose-700 dark:text-rose-300
                                       text-xs font-semibold
                                       transition-all duration-200 active:scale-95
                                       hover:bg-rose-50 dark:hover:bg-rose-500/10
                                       [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                       focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Remove</span>
                        </button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Publishing
                        </h2>
                    </div>

                    <div>
                        <label for="field-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Current Status
                        </label>
                        <select id="field-status" wire:model="status" class="select">
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="reserved">Reserved</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <label class="flex items-center gap-3 cursor-pointer select-none pt-1 min-h-[44px]
                                  [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]">
                        <span class="relative inline-flex items-center shrink-0">
                            <input type="checkbox" wire:model="is_active" class="sr-only peer">
                            <span class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full
                                         peer peer-checked:bg-primary-600
                                         after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                                         after:bg-white after:rounded-full after:h-5 after:w-5
                                         after:transition-all peer-checked:after:translate-x-full"></span>
                        </span>
                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Active
                            <span class="text-gray-400 dark:text-gray-500">— visible to customers</span>
                        </span>
                    </label>
                </div>

                <div class="bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200/80 dark:border-gray-700/80 shadow-sm p-5 sm:p-6 space-y-5">
                    <div class="flex items-center gap-3">
                        <span class="w-5 h-px bg-primary-600"></span>
                        <h2 class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            Availability <span class="text-gray-400 dark:text-gray-500 font-medium normal-case tracking-normal">(optional)</span>
                        </h2>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400 -mt-2">
                        Block out dates when this activity isn't bookable.
                    </p>

                    <div>
                        <label for="field-unavail-from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            From
                        </label>
                        <input type="date" id="field-unavail-from" wire:model="unavailableFrom" class="input">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['unavailableFrom'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-unavail-to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            To
                        </label>
                        <input type="date" id="field-unavail-to" wire:model="unavailableTo" class="input">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['unavailableTo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3 pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
            <a href="<?php echo e(route('tenant.properties.index')); ?>" wire:navigate
               class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                      transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                      [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                      focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                <span>Cancel</span>
            </a>

            <button type="submit"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                           transition-all duration-200 active:scale-95
                           [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                           disabled:opacity-60 disabled:cursor-not-allowed">
                <span wire:loading.remove wire:target="save">Create Activity</span>
                <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving…
                </span>
            </button>
        </div>
    </form>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showNewTypeModal): ?>
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.closeNewTypeModal()"
             x-on:click.self="$wire.closeNewTypeModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg text-primary-600 dark:text-primary-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l5 5a2 2 0 01.586 1.414V19a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Activity Type</h3>
                    </div>
                    <button type="button"
                            wire:click="closeNewTypeModal"
                            aria-label="Close"
                            class="inline-flex items-center justify-center h-11 w-11 sm:h-9 sm:w-9 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Create a custom type for your business. It will be selectable only within your tenant.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="field-new-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Type Name <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="field-new-type"
                               wire:model="newTypeName"
                               wire:keydown.enter.prevent="createType"
                               class="input"
                               placeholder="e.g. Island Hopping"
                               autofocus>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newTypeName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($this->propertyTypes->whereNull('tenant_id')->all())): ?>
                        <div class="rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-2">
                                Existing Global Types
                            </p>
                            <div class="flex flex-wrap gap-1">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->propertyTypes->whereNull('tenant_id'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $global): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <span <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'global-type-'.e($global->id).''; ?>wire:key="global-type-<?php echo e($global->id); ?>"
                                          class="inline-flex items-center px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-[10px] font-medium text-gray-600 dark:text-gray-300">
                                        <?php echo e($global->name); ?>

                                    </span>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button"
                            wire:click="closeNewTypeModal"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 text-sm font-semibold
                                   transition-all duration-200 active:scale-95 hover:bg-gray-50 dark:hover:bg-gray-700
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900">
                        <span>Cancel</span>
                    </button>
                    <button type="button"
                            wire:click="createType"
                            wire:loading.attr="disabled"
                            wire:target="createType"
                            class="inline-flex items-center justify-center gap-2 h-11 px-5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold shadow-sm
                                   transition-all duration-200 active:scale-95
                                   [touch-action:manipulation] [-webkit-tap-highlight-color:transparent]
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-900
                                   disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="createType">Create Type</span>
                        <span wire:loading wire:target="createType" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 text-white motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            Creating…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <?php if (isset($component)) { $__componentOriginalb992f09e6b42df8ffd0c7230daf18927 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.image-crop-modal','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('image-crop-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $attributes = $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $component = $__componentOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/c4e9181d.blade.php ENDPATH**/ ?>