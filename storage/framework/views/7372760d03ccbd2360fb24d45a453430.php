<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">

<head>
    <meta charset="utf-8">
    
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="color-scheme" content="light dark">

    
    <meta name="theme-color" content="#F8F7F3">

    <link rel="icon" href="<?php echo e(asset('favicon.ico')); ?>" sizes="any">

    
    <style>[x-cloak]{display:none!important}</style>

    
    <script>
        function applyTheme() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);

            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', dark ? '#111827' : '#F8F7F3');
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    </script>

    <title><?php echo e($title ?? 'Business Dashboard'); ?></title>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap">
    <link rel="stylesheet"
          media="print"
          onload="this.media='all'"
          href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap">
    <noscript>
        <link rel="stylesheet"
              href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap">
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
        // KEY CONTRACT (must match the tenant sidebar + tenant header):
        //   - localStorage key: `tenant_sidebar_minified` ('1' = collapsed)
        //   - window event:     `sidebar-minified-tenant` with detail = boolean
        //
        // The sidebar owns the toggle and fires the event; the header and
        // this layout consume it. Both also read localStorage directly so
        // a cold page load with a collapsed sidebar renders correctly
        // without waiting for the event.
        minified: localStorage.getItem('tenant_sidebar_minified') === '1',
    }"
    @sidebar-minified-tenant.window="minified = $event.detail"
    x-init="
        $watch('minified', v => {
            try { localStorage.setItem('tenant_sidebar_minified', v ? '1' : '0'); } catch (e) {}
        });
    "
    class="font-sans antialiased min-h-screen min-h-[100dvh] bg-[#F8F7F3] dark:bg-gray-900 text-gray-900 dark:text-gray-100"
>

    
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div class="absolute inset-0 bg-linear-to-br from-white via-gray-50 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-900"></div>
    </div>

    
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

</html><?php /**PATH C:\laragon\www\Capstone\resources\views/tenant/layouts/app.blade.php ENDPATH**/ ?>