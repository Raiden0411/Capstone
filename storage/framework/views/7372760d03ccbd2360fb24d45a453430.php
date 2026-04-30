<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo e($title ?? 'Business Dashboard'); ?></title>
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body class="bg-white dark:bg-black text-black dark:text-white font-sans antialiased">

    

    <?php if (isset($component)) { $__componentOriginalce533146c48ec08ce0d62e944a6bd65b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce533146c48ec08ce0d62e944a6bd65b = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.headers.tenant.tenant-header','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('headers.tenant.tenant-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce533146c48ec08ce0d62e944a6bd65b)): ?>
<?php $attributes = $__attributesOriginalce533146c48ec08ce0d62e944a6bd65b; ?>
<?php unset($__attributesOriginalce533146c48ec08ce0d62e944a6bd65b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce533146c48ec08ce0d62e944a6bd65b)): ?>
<?php $component = $__componentOriginalce533146c48ec08ce0d62e944a6bd65b; ?>
<?php unset($__componentOriginalce533146c48ec08ce0d62e944a6bd65b); ?>
<?php endif; ?>

    <main class="min-h-screen p-8">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($slot)): ?>
            <?php echo e($slot); ?>

        <?php else: ?>
            <?php echo $__env->yieldContent('content'); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </main>

    <?php if (isset($component)) { $__componentOriginal24534affdca2a816eba9e725222b1b43 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal24534affdca2a816eba9e725222b1b43 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.footers.tenant.tenant-footer','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('footers.tenant.tenant-footer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal24534affdca2a816eba9e725222b1b43)): ?>
<?php $attributes = $__attributesOriginal24534affdca2a816eba9e725222b1b43; ?>
<?php unset($__attributesOriginal24534affdca2a816eba9e725222b1b43); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal24534affdca2a816eba9e725222b1b43)): ?>
<?php $component = $__componentOriginal24534affdca2a816eba9e725222b1b43; ?>
<?php unset($__componentOriginal24534affdca2a816eba9e725222b1b43); ?>
<?php endif; ?>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/tenant/layouts/app.blade.php ENDPATH**/ ?>