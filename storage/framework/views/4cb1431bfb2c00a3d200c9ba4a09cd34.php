<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    
    <script>
        !function() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }();
    </script>

    <title><?php echo e($title ?? 'Super Admin Platform'); ?></title>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap">
    <link rel="stylesheet"
          media="print"
          onload="this.media='all'"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap">
    <noscript>
        <link rel="stylesheet"
              href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap">
    </noscript>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>


    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
</head>

<body x-data="{ minified: false }"
      class="font-sans antialiased min-h-screen text-white">

    
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    
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

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>


    
    <script src="https://unpkg.com/preline/dist/preline.js"></script>
</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/superadmin/layouts/app.blade.php ENDPATH**/ ?>