<template x-ref="markerPopup">
    <div class="p-2 <?php echo e($class); ?>" style="max-width: <?php echo e($maxWidth); ?>;">
        <?php echo e($slot); ?>

    </div>
</template>
<div x-data x-map-marker-popup="{
    maxWidth: '<?php echo e($maxWidth); ?>',
    closeButton: <?php echo e($closeButton ? 'true' : 'false'); ?>,
    closeOnClickMap: <?php echo e($closeOnClickMap ? 'true' : 'false'); ?>,
    closeOnMove: <?php echo e($closeOnMove ? 'true' : 'false'); ?>,
    anchor: '<?php echo e($anchor); ?>',
    offset: <?php echo e(json_encode($offset)); ?>

}"></div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/components/marker-popup.blade.php ENDPATH**/ ?>