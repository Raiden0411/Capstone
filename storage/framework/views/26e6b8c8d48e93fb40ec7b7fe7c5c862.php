<template x-ref="markerTooltip">
    <div class="lm-tooltip <?php echo e($class); ?>">
        <?php echo e($text); ?>

    </div>
</template>
<div x-data x-map-marker-tooltip="{
    text: '<?php echo e($text); ?>',
    anchor: '<?php echo e($anchor); ?>',
    offset: <?php echo e(json_encode($offset)); ?>

}"></div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views\components\marker-tooltip.blade.php ENDPATH**/ ?>