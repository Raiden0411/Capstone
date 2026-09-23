
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'key',                    // Livewire property name, e.g. 'heroImage'
    'existing' => null,       // URL of the currently-stored image, or null
    'label' => null,          // optional caption, e.g. 'Lead'
    'compact' => false,       // true for the small 3-across grid
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'key',                    // Livewire property name, e.g. 'heroImage'
    'existing' => null,       // URL of the currently-stored image, or null
    'label' => null,          // optional caption, e.g. 'Lead'
    'compact' => false,       // true for the small 3-across grid
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $minHeight = $compact ? 'min-h-[110px]' : 'min-h-[140px]';
    $imgHeight = $compact ? 'max-h-20' : 'max-h-32';
?>

<div x-on:dragover.prevent="draggingKey = '<?php echo e($key); ?>'"
     x-on:dragleave.prevent="draggingKey = null"
     x-on:drop.prevent="handleDrop($event, '<?php echo e($key); ?>')"
     :class="draggingKey === '<?php echo e($key); ?>'
         ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10'
         : 'border-gray-300 dark:border-gray-600'"
     class="relative flex items-center justify-center rounded-xl border-2 border-dashed p-3 transition-colors
            <?php echo e($minHeight); ?> cursor-pointer overflow-hidden
            focus-within:ring-2 focus-within:ring-primary-500/50">

    
    <template x-if="filePreviews.<?php echo e($key); ?>">
        <div class="w-full">
            <img :src="filePreviews.<?php echo e($key); ?>" alt=""
                 class="w-full <?php echo e($imgHeight); ?> object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                 decoding="async">
            <p class="mt-2 text-[11px] text-primary-600 dark:text-primary-400 font-semibold text-center">
                New — uploads on save
            </p>
        </div>
    </template>

    
    <template x-if="!filePreviews.<?php echo e($key); ?> && <?php echo \Illuminate\Support\Js::from((bool) $existing)->toHtml() ?>">
        <div class="w-full">
            <img src="<?php echo e($existing); ?>" alt=""
                 class="w-full <?php echo e($imgHeight); ?> object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                 decoding="async">
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-semibold text-center">
                Current — click to replace
            </p>
        </div>
    </template>

    
    <template x-if="!filePreviews.<?php echo e($key); ?> && !<?php echo \Illuminate\Support\Js::from((bool) $existing)->toHtml() ?>">
        <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
            <svg class="w-7 h-7 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/>
            </svg>
            <span class="text-[11px]">Drag &amp; drop or click</span>
        </div>
    </template>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($label): ?>
        <span class="absolute bottom-1.5 left-1.5 rounded bg-black/60 backdrop-blur-sm px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white">
            <?php echo e($label); ?>

        </span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <button type="button"
            x-show="filePreviews.<?php echo e($key); ?>"
            x-cloak
            @click.stop="clearFile('<?php echo e($key); ?>')"
            class="absolute top-1.5 right-1.5 z-10 inline-flex h-6 w-6 items-center justify-center rounded-full
                   bg-rose-500 text-white shadow-md hover:bg-rose-600
                   active:scale-95 transition
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500/50 focus-visible:ring-offset-2
                   dark:focus-visible:ring-offset-gray-900"
            aria-label="Clear new image"
            title="Discard pending upload">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    
    <input x-ref="file-<?php echo e($key); ?>"
           type="file"
           wire:model="<?php echo e($key); ?>"
           accept="image/jpeg,image/png,image/webp"
           class="absolute inset-0 opacity-0 cursor-pointer"
           x-on:change="previewFile($event, '<?php echo e($key); ?>')">
</div><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\pages\homepage\partials\image-uploader.blade.php ENDPATH**/ ?>