<div
    x-data
    x-map-cluster-layer
    data-cluster-id="<?php echo e($id); ?>"
    data-cluster-config="<?php echo e(json_encode([
        'id' => $id,
        'url' => $url,
        'clusterMaxZoom' => $clusterMaxZoom,
        'clusterRadius' => $clusterRadius,
        'clusterMinPoints' => $clusterMinPoints,
        'clusterColor' => $clusterColor,
        'clusterTextColor' => $clusterTextColor,
        'clusterSizeStops' => $clusterSizeStops,
        'pointColor' => $pointColor,
        'pointRadius' => $pointRadius,
        'showCount' => $showCount,
        'popupProperty' => $popupProperty,
        'popupTemplate' => $popupTemplate,
        'clickZoom' => $clickZoom,
        'buffer' => $buffer,
        'tolerance' => $tolerance,
        'disablePopup' => $disablePopup,
    ])); ?>"
    <?php if(!$url): ?>
    <?php if(count($geoJsonData['features'] ?? [])> $maxFeaturesToInline): ?>
    x-init="$el._clusterData = <?php echo e(json_encode($geoJsonData)); ?>"
    <?php else: ?>
    data-cluster-data="<?php echo e(json_encode($geoJsonData)); ?>"
    <?php endif; ?>
    <?php endif; ?>
    class="<?php echo e($class); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($popup)): ?>
    <template x-ref="clusterPopup"><?php echo e($popup); ?></template>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views\components\cluster-layer.blade.php ENDPATH**/ ?>