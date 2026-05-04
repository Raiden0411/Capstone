<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    
    <script>
        const html = document.querySelector('html');
        const isLightOrAuto = localStorage.getItem('hs_theme') === 'light' ||
            (localStorage.getItem('hs_theme') === 'auto' && !window.matchMedia('(prefers-color-scheme: dark)').matches);
        const isDarkOrAuto = localStorage.getItem('hs_theme') === 'dark' ||
            (localStorage.getItem('hs_theme') === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        if (isLightOrAuto && html.classList.contains('dark')) html.classList.remove('dark');
        else if (isDarkOrAuto && html.classList.contains('light')) html.classList.remove('light');
        else if (isDarkOrAuto && !html.classList.contains('dark')) html.classList.add('dark');
        else if (isLightOrAuto && !html.classList.contains('light')) html.classList.add('light');
    </script>

    <title><?php echo e($title ?? 'Business Dashboard'); ?></title>
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body x-data="{ minified: false }"
      class="bg-gray-50 dark:bg-[#0a0f1e] text-gray-900 dark:text-white transition-all duration-300">

    
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

    
    <?php if (isset($component)) { $__componentOriginalc454d3bdd12954b32744a87789eb44f4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc454d3bdd12954b32744a87789eb44f4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.headers.tenant.sidebar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('headers.tenant.sidebar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc454d3bdd12954b32744a87789eb44f4)): ?>
<?php $attributes = $__attributesOriginalc454d3bdd12954b32744a87789eb44f4; ?>
<?php unset($__attributesOriginalc454d3bdd12954b32744a87789eb44f4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc454d3bdd12954b32744a87789eb44f4)): ?>
<?php $component = $__componentOriginalc454d3bdd12954b32744a87789eb44f4; ?>
<?php unset($__componentOriginalc454d3bdd12954b32744a87789eb44f4); ?>
<?php endif; ?>

    
    <div class="w-full transition-all duration-300"
         :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-64'">
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($slot)): ?>
                <?php echo e($slot); ?>

            <?php else: ?>
                <?php echo $__env->yieldContent('content'); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/tenant/layouts/app.blade.php ENDPATH**/ ?>