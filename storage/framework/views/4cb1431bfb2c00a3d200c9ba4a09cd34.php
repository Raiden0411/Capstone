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

    <title><?php echo e($title ?? 'Super Admin Platform'); ?></title>
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>


    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
</head>


<body x-data="{ minified: false }"
      class="bg-gray-50 transition-all duration-300 dark:bg-[#0a0f1e]">
    <main id="content">
        
        
        <?php if (isset($component)) { $__componentOriginal9b4d83abd5ac679e250eb1db9174b666 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9b4d83abd5ac679e250eb1db9174b666 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.headers.admin.superadmin-header','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('headers.admin.superadmin-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9b4d83abd5ac679e250eb1db9174b666)): ?>
<?php $attributes = $__attributesOriginal9b4d83abd5ac679e250eb1db9174b666; ?>
<?php unset($__attributesOriginal9b4d83abd5ac679e250eb1db9174b666); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9b4d83abd5ac679e250eb1db9174b666)): ?>
<?php $component = $__componentOriginal9b4d83abd5ac679e250eb1db9174b666; ?>
<?php unset($__componentOriginal9b4d83abd5ac679e250eb1db9174b666); ?>
<?php endif; ?>

        
        <?php if (isset($component)) { $__componentOriginal7dbaa7343efa386733e3ffc0d117a3f6 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7dbaa7343efa386733e3ffc0d117a3f6 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.headers.admin.sidebar','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('headers.admin.sidebar'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7dbaa7343efa386733e3ffc0d117a3f6)): ?>
<?php $attributes = $__attributesOriginal7dbaa7343efa386733e3ffc0d117a3f6; ?>
<?php unset($__attributesOriginal7dbaa7343efa386733e3ffc0d117a3f6); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7dbaa7343efa386733e3ffc0d117a3f6)): ?>
<?php $component = $__componentOriginal7dbaa7343efa386733e3ffc0d117a3f6; ?>
<?php unset($__componentOriginal7dbaa7343efa386733e3ffc0d117a3f6); ?>
<?php endif; ?>

        
        <div class="w-full transition-all duration-300"
             :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-64'">
            <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
                <?php echo e($slot); ?>

            </div>
        </div>

    </main>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/superadmin/layouts/app.blade.php ENDPATH**/ ?>