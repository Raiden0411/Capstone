<!-- ========== HEADER ========== -->
<header class="sticky top-0 inset-x-0 flex flex-wrap md:justify-start md:flex-nowrap z-30 w-full bg-white dark:bg-[#0b0f19] border-b border-gray-200 dark:border-slate-700/50 text-sm py-2.5 transition-all duration-300"
        :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-65'">
  <nav class="px-4 sm:px-6 flex basis-full items-center w-full mx-auto justify-between">
    
    
    <div class="flex items-center gap-2 lg:hidden me-5">
      <button type="button"
              class="size-8 flex justify-center items-center gap-x-2 rounded-lg border bg-white dark:bg-slate-800 border-gray-200 dark:border-slate-700 text-gray-700 dark:text-slate-400 hover:bg-gray-100 dark:hover:bg-slate-700"
              data-hs-overlay="#hs-application-sidebar"
              aria-controls="hs-application-sidebar"
              aria-label="Toggle navigation">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <a class="flex-none font-bold text-xl text-gray-900 dark:text-white" href="<?php echo e(route('superadmin.dashboard')); ?>" wire:navigate aria-label="Brand">
        <svg class="w-7 h-7 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10l-2 1m0 0l-2-1m2 1v2.5M20 7l-2 1m2-1l-2-1m2 1v2.5M14 4l-2-1-2 1M4 7l2-1M4 7l2 1M4 7v2.5M12 21l-2-1m2 1l2-1m-2 1v-2.5M6 18l-2-1v-2.5M18 18l2-1v-2.5"/>
        </svg>
        <span>System<span class="text-blue-600 dark:text-blue-400">Admin</span></span>
      </a>
    </div>

    
    <div class="flex items-center gap-2 ms-auto">
      
      <button type="button"
              x-data="{ dark: localStorage.getItem('hs_theme') === 'dark' }"
              x-init="
                  document.documentElement.classList.toggle('dark', dark);
                  $watch('dark', val => {
                      localStorage.setItem('hs_theme', val ? 'dark' : 'light');
                      document.documentElement.classList.toggle('dark', val);
                  });
              "
              @click="dark = !dark"
              class="flex justify-center items-center size-9 rounded-lg text-gray-500 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-800 focus:outline-none transition-colors"
              aria-label="Toggle dark mode">
        <svg x-show="dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
        <svg x-show="!dark" class="shrink-0 size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      </button>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
        
        <div class="relative"
             x-data="{ open: false }"
             @click.outside="open = false"
             @keydown.escape.window="open = false">
          
          
          <button type="button"
                  @click="open = !open"
                  class="flex items-center gap-2 py-1.5 px-2 rounded-lg text-gray-600 dark:text-slate-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-800 transition-colors focus:outline-none"
                  aria-expanded="open"
                  aria-haspopup="true">
            <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
              <?php echo e(substr(auth()->user()->name ?? 'SA', 0, 1)); ?>

            </div>
            <span class="hidden sm:inline max-w-[120px] truncate text-sm font-medium"><?php echo e(auth()->user()->name ?? 'Super Admin'); ?></span>
            <svg class="hidden sm:block w-3 h-3 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>

          
          <div x-cloak
               x-show="open"
               x-transition:enter="transition ease-out duration-150"
               x-transition:enter-start="opacity-0 scale-95 translate-y-1"
               x-transition:enter-end="opacity-100 scale-100 translate-y-0"
               x-transition:leave="transition ease-in duration-100"
               x-transition:leave-start="opacity-100 scale-100 translate-y-0"
               x-transition:leave-end="opacity-0 scale-95 translate-y-1"
               class="absolute right-0 mt-2 w-64 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-xl shadow-lg z-50 overflow-hidden">
            
            
            <div class="px-4 py-3 border-b border-gray-200 dark:border-slate-700">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-sm shrink-0">
                  <?php echo e(substr(auth()->user()->name ?? 'SA', 0, 1)); ?>

                </div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e(auth()->user()->name); ?></p>
                  <p class="text-xs text-gray-500 dark:text-slate-400 truncate"><?php echo e(auth()->user()->email); ?></p>
                  <?php $role = auth()->user()->roles->first(); ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($role): ?>
                    <span class="mt-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-500/20 text-purple-800 dark:text-purple-400">
                      <?php echo e(ucwords(str_replace(['-', '_'], ' ', $role->name))); ?>

                    </span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              </div>
            </div>

            
            <div class="p-1.5 space-y-0.5">
              <a href="<?php echo e(route('superadmin.profile')); ?>" wire:navigate
                 class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-700 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 transition-colors"
                 @click="open = false">
                <svg class="w-4 h-4 text-gray-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                My Profile
              </a>

              <form method="POST" action="<?php echo e(route('logout')); ?>" class="block">
                <?php echo csrf_field(); ?>
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  Sign Out
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="<?php echo e(route('login')); ?>" class="py-2 px-4 inline-flex items-center gap-x-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700">Sign in</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </nav>
</header><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/admin/superadmin-header.blade.php ENDPATH**/ ?>