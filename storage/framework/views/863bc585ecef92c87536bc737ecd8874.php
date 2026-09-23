<div
    x-data
    x-map-route-group
    data-route-group-config="<?php echo e(json_encode([
        'id' => $id,
        'selectedRoute' => $selectedRoute,
        'fitBounds' => $fitBounds,
        'alternativeColor' => $alternativeColor,
        'alternativeOpacity' => $alternativeOpacity,
        'alternativeWidth' => $alternativeWidth,
        'lineCap' => $lineCap,
        'lineJoin' => $lineJoin,
        'clickable' => $clickable,
        'fetchDirections' => $fetchDirections,
        'directionsProfile' => $directionsProfile,
        'directionsUrl' => $directionsUrl,
        'animate' => $animate,
        'animateDuration' => $animateDuration,
        'withStops' => $withStops,
        'stopColor' => $stopColor,
        'activeColor' => $activeColor,
        'activeWidth' => $activeWidth,
        'hoverColor' => $hoverColor,
        'active' => $active,
        'dashArray' => $dashArray,
    ])); ?>"
    data-route-group-routes="<?php echo e(json_encode($routes)); ?>"></div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views\components\route-group.blade.php ENDPATH**/ ?>