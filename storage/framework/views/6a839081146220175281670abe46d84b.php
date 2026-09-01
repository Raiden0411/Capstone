<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-mapcn.load_from_cdn', true)): ?>
<script src="<?php echo e(config('livewire-mapcn.cdn_url', 'https://cdn.jsdelivr.net/npm/maplibre-gl@5.19.0/dist/maplibre-gl.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-mapcn.inject_assets', 'route') === 'published'): ?>
<script src="<?php echo e(asset('vendor/livewire-mapcn/livewire-mapcn.js')); ?>" defer></script>
<?php else: ?>
<script src="<?php echo e(route('livewire-mapcn.js')); ?>" defer></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/scripts.blade.php ENDPATH**/ ?>