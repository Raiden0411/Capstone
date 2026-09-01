<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-mapcn.load_from_cdn', true)): ?>
<link href="<?php echo e(config('livewire-mapcn.cdn_css_url', 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.x/dist/maplibre-gl.css')); ?>" rel="stylesheet" />
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(config('livewire-mapcn.inject_assets', 'route') === 'published'): ?>
<link href="<?php echo e(asset('vendor/livewire-mapcn/livewire-mapcn.css')); ?>" rel="stylesheet" />
<?php else: ?>
<link href="<?php echo e(route('livewire-mapcn.css')); ?>" rel="stylesheet" />
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\laragon\www\Capstone\vendor\kwasii\livewire-mapcn\resources\views/styles.blade.php ENDPATH**/ ?>