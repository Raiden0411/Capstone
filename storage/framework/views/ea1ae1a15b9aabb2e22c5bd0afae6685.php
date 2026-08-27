
<?php
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;

    $heroPath = \App\Models\SiteSetting::getValue('hero_background_image');
    $heroUrl = $heroPath
        ? asset('storage/' . $heroPath)
        : 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1600&q=80';

    $redirectTo = request()->input('redirect');
?>

<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title>Login - <?php echo e($siteName); ?></title>

    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::styles(); ?>


    
    <script>
        !function() {
            var t = localStorage.getItem('hs_theme');
            var dark = t === 'dark' || (t !== 'light' && matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        }();
    </script>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500;1,600&display=swap" rel="stylesheet">
</head>
<body class="font-sans antialiased text-gray-900 bg-[#F8F7F3] dark:bg-gray-900 dark:text-white min-h-screen"
      x-data="{ dark: localStorage.getItem('hs_theme') === 'dark' }"
      x-init="
          document.documentElement.classList.toggle('dark', dark);
          $watch('dark', val => {
              localStorage.setItem('hs_theme', val ? 'dark' : 'light');
              document.documentElement.classList.toggle('dark', val);
          });
      ">

    
    <button type="button"
            @click="dark = !dark"
            class="fixed top-4 right-4 z-50 flex items-center justify-center size-10 md:size-9 rounded-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 shadow-sm transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
            aria-label="Toggle dark mode">
        <svg x-show="dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
        <svg x-show="!dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
    </button>

    <main class="flex flex-col md:flex-row w-full min-h-screen">

        
        <div class="relative w-full md:w-3/5 min-h-[220px] md:min-h-screen order-1 md:order-1">
            <img src="<?php echo e($heroUrl); ?>"
                 alt="Victorias City"
                 class="absolute inset-0 object-cover w-full h-full">

            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

            <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-16 lg:p-24 text-white">
                <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase bg-black/40 rounded-full backdrop-blur-sm border border-white/10">
                    <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                    <?php echo e($siteName); ?>

                </span>

                <h1 class="mt-5 text-2xl sm:text-3xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight leading-[1.1]">
                    Your journey to the heart of the<br />wilderness starts here
                </h1>

                <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                    Discover the unmapped ecotrails, pristine waterfalls, and rich history of Victorias City.
                    Let us show you a side of the world you've never seen.
                </p>
            </div>
        </div>

        
        <div class="flex items-center justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2">
            <div class="w-full max-w-md">

                
                <a href="<?php echo e(route('home')); ?>"
                   class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-6 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Back to Home
                </a>

                
                <div class="flex items-center gap-3 mb-6">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logoUrl): ?>
                        <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($siteName); ?> logo"
                             class="w-10 h-10 object-contain rounded-lg shrink-0">
                    <?php else: ?>
                        <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0">
                            <?php echo e(strtoupper(substr($siteName, 0, 1))); ?>

                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <span class="text-lg font-semibold text-gray-900 dark:text-white"><?php echo e($siteName); ?></span>
                </div>

                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
                    Login to your account
                </h2>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
                    <div class="mb-5 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-xl p-3 text-sm text-red-600 dark:text-red-400">
                        <?php echo e($errors->first()); ?>

                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <form method="POST" action="<?php echo e(route('login')); ?>" class="space-y-5"
                      x-data="{ showPassword: false, loading: false }"
                      @submit="loading = true">

                    <?php echo csrf_field(); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($redirectTo): ?>
                        <input type="hidden" name="redirect" value="<?php echo e($redirectTo); ?>">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Email Address
                        </label>
                        <div class="mt-1.5">
                            <input id="email"
                                   type="email"
                                   name="email"
                                   value="<?php echo e(old('email')); ?>"
                                   required
                                   autofocus
                                   placeholder="example@email.com"
                                   class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-xs text-red-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div>
                        <div class="flex items-center justify-between">
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Password
                            </label>
                            <a href="#" onclick="event.preventDefault(); alert('Password reset is not yet available.');"
                               class="text-sm font-medium text-primary-600 hover:underline">
                                Forgot?
                            </a>
                        </div>

                        <div class="relative mt-1.5">
                            <input :type="showPassword ? 'text' : 'password'"
                                   id="password"
                                   name="password"
                                   required
                                   placeholder="Enter your password"
                                   class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">

                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                    tabindex="-1" aria-label="Toggle password visibility">
                                <svg x-show="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-xs text-red-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    
                    <div class="flex items-center">
                        <label class="flex items-center text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" id="remember" name="remember"
                                   class="shrink-0 rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500 bg-white dark:bg-gray-700">
                            <span class="ml-2">Remember me</span>
                        </label>
                    </div>

                    
                    <button type="submit"
                            :disabled="loading"
                            class="w-full py-3.5 mt-2 text-[15px] font-semibold text-white transition-colors bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-primary-500/50">
                        <span x-show="!loading">Login now</span>
                        <span x-show="loading" class="inline-flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            Signing in…
                        </span>
                    </button>
                </form>

                
                <p class="mt-8 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    Don't Have An Account?
                    <a href="<?php echo e(route('register', $redirectTo ? ['redirect' => $redirectTo] : [])); ?>"
                       class="text-primary-600 hover:underline ml-1 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Sign Up
                    </a>
                </p>

                
                <p class="mt-2 text-sm font-medium text-center text-gray-500 dark:text-gray-400">
                    Own a tourist spot?
                    <a href="<?php echo e(route('register_business', $redirectTo ? ['redirect' => $redirectTo] : [])); ?>"
                       class="text-primary-600 hover:underline ml-1 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                        Register your business
                    </a>
                </p>

            </div>
        </div>
    </main>

    <?php echo \Livewire\Mechanisms\FrontendAssets\FrontendAssets::scripts(); ?>

</body>
</html><?php /**PATH C:\laragon\www\Capstone\resources\views/public/auth/login-form.blade.php ENDPATH**/ ?>