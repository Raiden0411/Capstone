<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?php echo e($title ?? 'Welcome'); ?></title>
    
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

</head>

<body class="font-sans antialiased">
    <?php if (isset($component)) { $__componentOriginal6b03705b1bb6b0a0138b7a37efc303f2 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6b03705b1bb6b0a0138b7a37efc303f2 = $attributes; } ?>
<?php $component = App\View\Components\Headers\PublicHeader::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('headers.public-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Headers\PublicHeader::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6b03705b1bb6b0a0138b7a37efc303f2)): ?>
<?php $attributes = $__attributesOriginal6b03705b1bb6b0a0138b7a37efc303f2; ?>
<?php unset($__attributesOriginal6b03705b1bb6b0a0138b7a37efc303f2); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6b03705b1bb6b0a0138b7a37efc303f2)): ?>
<?php $component = $__componentOriginal6b03705b1bb6b0a0138b7a37efc303f2; ?>
<?php unset($__componentOriginal6b03705b1bb6b0a0138b7a37efc303f2); ?>
<?php endif; ?>
    
    <main class="min-h-screen">
        <?php echo e($slot); ?>

    </main>
    
    <?php if (isset($component)) { $__componentOriginala1f5baf47967cb18379ffb95063ad85d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala1f5baf47967cb18379ffb95063ad85d = $attributes; } ?>
<?php $component = App\View\Components\Footers\PublicFooter::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('footers.public-footer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\Footers\PublicFooter::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala1f5baf47967cb18379ffb95063ad85d)): ?>
<?php $attributes = $__attributesOriginala1f5baf47967cb18379ffb95063ad85d; ?>
<?php unset($__attributesOriginala1f5baf47967cb18379ffb95063ad85d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala1f5baf47967cb18379ffb95063ad85d)): ?>
<?php $component = $__componentOriginala1f5baf47967cb18379ffb95063ad85d; ?>
<?php unset($__componentOriginala1f5baf47967cb18379ffb95063ad85d); ?>
<?php endif; ?>
    
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

    <script src="https://unpkg.com/preline/dist/preline.js"></script>
</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/layouts/app.blade.php ENDPATH**/ ?>