<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="color-scheme" content="dark light">
    <meta name="description" content="<?php echo e($description ?? config('app.name') . ' — Book your perfect stay.'); ?>">

    <meta property="og:title" content="<?php echo e($title ?? config('app.name')); ?>">
    <meta property="og:description" content="<?php echo e($description ?? 'Discover and book premium accommodations.'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo e(url()->current()); ?>">

    <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>" sizes="any">
    <link rel="icon" href="<?php echo e(asset('icon.svg')); ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?php echo e(asset('apple-touch-icon.png')); ?>">

    
    <script>
        !function() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }();
    </script>

    
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

    <title><?php echo e(isset($title) ? $title . ' — ' . config('app.name') : config('app.name')); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body class="font-sans antialiased flex flex-col min-h-screen bg-[#071412] text-white">

    
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

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

    <main class="flex-1 pt-[68px]">
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
    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html><?php /**PATH C:\laragon\www\Capstone\resources\views/layouts/app.blade.php ENDPATH**/ ?>