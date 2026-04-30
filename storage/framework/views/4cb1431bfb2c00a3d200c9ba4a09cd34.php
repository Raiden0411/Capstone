<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo e($title ?? 'Super Admin Platform'); ?></title>
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body class="bg-slate-50 text-slate-800 font-sans antialiased" x-data="{ sidebarOpen: false }">
    
    
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

    
    <div class="lg:pl-72">
        
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

        
        <main class="py-8 px-4 sm:px-6 lg:px-8">
            <?php echo e($slot); ?>

        </main>

        
        <?php if (isset($component)) { $__componentOriginalf388d3b7a20bab75c6cf15ad5731ae14 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalf388d3b7a20bab75c6cf15ad5731ae14 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.footers.admin.superadmin-footer','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('footers.admin.superadmin-footer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalf388d3b7a20bab75c6cf15ad5731ae14)): ?>
<?php $attributes = $__attributesOriginalf388d3b7a20bab75c6cf15ad5731ae14; ?>
<?php unset($__attributesOriginalf388d3b7a20bab75c6cf15ad5731ae14); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalf388d3b7a20bab75c6cf15ad5731ae14)): ?>
<?php $component = $__componentOriginalf388d3b7a20bab75c6cf15ad5731ae14; ?>
<?php unset($__componentOriginalf388d3b7a20bab75c6cf15ad5731ae14); ?>
<?php endif; ?>
    </div>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/superadmin/layouts/app.blade.php ENDPATH**/ ?>