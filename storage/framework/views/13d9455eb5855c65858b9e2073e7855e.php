<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="color-scheme" content="light dark">

    
    <meta name="theme-color" content="#F8F7F3" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#111827" media="(prefers-color-scheme: dark)">

    <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>" sizes="any">

    
    <style>[x-cloak]{display:none!important}</style>

    
    <script>
        function applyTheme() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
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

    <?php echo view('livewire-mapcn::styles')->render(); ?>

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body
    x-data="{
        // Persisted across wire:navigate — otherwise the sidebar collapses
        // back to expanded every time the user navigates to a new page.
        //
        // KEY CONTRACT (must match the sidebar + header):
        //   - localStorage key: `sidebar_minified` ('1' = collapsed)
        //   - window event:     `sidebar-minified` with detail = boolean
        //
        // The sidebar owns the toggle and fires the event; the header and
        // this layout consume it. Both also read localStorage directly so
        // a cold page load with a collapsed sidebar renders correctly
        // without waiting for the event.
        minified: localStorage.getItem('sidebar_minified') === '1',
    }"
    @sidebar-minified.window="minified = $event.detail"
    x-init="
        $watch('minified', v => localStorage.setItem('sidebar_minified', v ? '1' : '0'));
    "
    class="font-sans antialiased min-h-screen min-h-[100dvh] bg-[#F8F7F3] dark:bg-gray-900 text-gray-900 dark:text-gray-100"
>

    
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 bg-linear-to-br from-white via-gray-50 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900"></div>
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

    
    <div class="w-full transition-[padding] duration-300 ease-out"
         :class="minified ? 'lg:ps-20' : 'lg:ps-64'">
        <div class="p-4 sm:p-6 space-y-4 sm:space-y-6">
            <?php echo e($slot); ?>

        </div>
    </div>

    

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

    <?php echo view('livewire-mapcn::scripts')->render(); ?>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>

</html><?php /**PATH C:\laragon\www\Capstone\resources\views\superadmin\layouts\app.blade.php ENDPATH**/ ?>