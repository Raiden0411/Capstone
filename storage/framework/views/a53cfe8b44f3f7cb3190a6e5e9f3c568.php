<div
    x-data
    x-map-route="{
        id: '<?php echo e($id); ?>',
        coordinates: <?php echo e(json_encode($coordinates)); ?>,
        color: '<?php echo e($color); ?>',
        width: <?php echo e($width); ?>,
        opacity: <?php echo e($opacity); ?>,
        dashArray: <?php echo e(json_encode($dashArray)); ?>,
        lineCap: '<?php echo e($lineCap); ?>',
        lineJoin: '<?php echo e($lineJoin); ?>',
        activeColor: '<?php echo e($activeColor); ?>',
        activeWidth: <?php echo e($activeWidth); ?>,
        hoverColor: <?php echo e(json_encode($hoverColor)); ?>,
        clickable: <?php echo e($clickable ? 'true' : 'false'); ?>,
        withStops: <?php echo e($withStops ? 'true' : 'false'); ?>,
        stopColor: '<?php echo e($stopColor); ?>',
        fetchDirections: <?php echo e($fetchDirections ? 'true' : 'false'); ?>,
        directionsProfile: '<?php echo e($directionsProfile); ?>',
        directionsUrl: '<?php echo e($directionsUrl); ?>',
        animate: <?php echo e($animate ? 'true' : 'false'); ?>,
        animateDuration: <?php echo e($animateDuration); ?>,
        active: <?php echo e($active ? 'true' : 'false'); ?>,
        alternatives: <?php echo e($alternatives ? 'true' : 'false'); ?>,
        maxAlternatives: <?php echo e($maxAlternatives); ?>,
        alternativeColor: '<?php echo e($alternativeColor); ?>',
        alternativeOpacity: <?php echo e($alternativeOpacity); ?>,
        alternativeWidth: <?php echo e($alternativeWidth); ?>

    }"></div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/components/route.blade.php ENDPATH**/ ?>