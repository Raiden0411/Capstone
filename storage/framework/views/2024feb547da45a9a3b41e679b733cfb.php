<div
    x-data
    x-map-marker="{
        id: '<?php echo e($id); ?>',
        lat: <?php echo e($lat); ?>,
        lng: <?php echo e($lng); ?>,
        draggable: <?php echo e($draggable ? 'true' : 'false'); ?>,
        color: '<?php echo e($color); ?>',
        anchor: '<?php echo e($anchor); ?>',
        offset: <?php echo e(json_encode($offset)); ?>,
        rotation: <?php echo e($rotation); ?>,
        rotationAlignment: '<?php echo e($rotationAlignment); ?>',
        pitchAlignment: '<?php echo e($pitchAlignment); ?>'
    }"
    class="hidden <?php echo e($class); ?>">
    <?php echo e($slot); ?>


    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!str_contains($slot->toHtml(), 'x-ref="markerContent"')): ?>
    <template x-ref="markerContent">
        <div class="w-4 h-4 rounded-full border-2 border-white shadow-md" style="background-color: <?php echo e($color); ?>;"></div>
    </template>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/components/marker.blade.php ENDPATH**/ ?>