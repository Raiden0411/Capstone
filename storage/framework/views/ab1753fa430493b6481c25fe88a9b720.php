<div
    x-data
    x-map-controls="{
        zoom: <?php echo e($zoom ? 'true' : 'false'); ?>,
        compass: <?php echo e($compass ? 'true' : 'false'); ?>,
        locate: <?php echo e($locate ? 'true' : 'false'); ?>,
        fullscreen: <?php echo e($fullscreen ? 'true' : 'false'); ?>,
        scale: <?php echo e($scale ? 'true' : 'false'); ?>,
        position: '<?php echo e($position); ?>'
    }"
    class="<?php echo e($class); ?>"></div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/components/controls.blade.php ENDPATH**/ ?>