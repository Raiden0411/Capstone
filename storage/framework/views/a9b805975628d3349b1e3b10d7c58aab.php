<div
    wire:ignore
    x-data
    x-map="{
        id: '<?php echo e($attributes->get('id', \Illuminate\Support\Str::random(8))); ?>',
        center: <?php echo e(json_encode($center)); ?>,
        zoom: <?php echo e($zoom); ?>,
        minZoom: <?php echo e($minZoom); ?>,
        maxZoom: <?php echo e($maxZoom); ?>,
        style: <?php echo e(json_encode($resolvedStyle)); ?>,
        lightStyle: <?php echo e(json_encode($resolvedLightStyle)); ?>,
        darkStyle: <?php echo e(json_encode($resolvedDarkStyle)); ?>,
        theme: '<?php echo e($theme); ?>',
        bearing: <?php echo e($bearing); ?>,
        pitch: <?php echo e($pitch); ?>,
        interactive: <?php echo e($interactive ? 'true' : 'false'); ?>,
        scrollZoom: <?php echo e($scrollZoom ? 'true' : 'false'); ?>,
        doubleClickZoom: <?php echo e($doubleClickZoom ? 'true' : 'false'); ?>,
        dragPan: <?php echo e($dragPan ? 'true' : 'false'); ?>,
        customEvents: <?php echo e(json_encode($events)); ?>

    }"
    style="height: <?php echo e($height); ?>; width: <?php echo e($width); ?>;"
    <?php echo e($attributes->merge(['class' => 'relative overflow-hidden ' . $class])); ?>>
    <div x-ref="mapContainer" class="absolute inset-0 w-full h-full"></div>

    <?php echo e($slot); ?>

</div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/components/map.blade.php ENDPATH**/ ?>