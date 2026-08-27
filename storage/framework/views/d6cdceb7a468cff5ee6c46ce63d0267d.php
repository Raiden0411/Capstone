
<?php
    $pendingTenants = \App\Models\Tenant::where('is_active', false)
                        ->latest()
                        ->take(5)
                        ->get();
    $pendingCount = \App\Models\Tenant::where('is_active', false)->count();

    $user = Auth::user();
    $userAvatarUrl = $user?->avatar ? asset('storage/' . $user->avatar) : null;
    $userInitial = strtoupper(substr($user?->name ?? 'SA', 0, 1));
?>

<header
    x-data="{
        minified: localStorage.getItem('sidebar_minified') === '1',
        dark: localStorage.getItem('hs_theme') === 'dark',
        toggleDark() {
            this.dark = !this.dark;
            localStorage.setItem('hs_theme', this.dark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', this.dark);
        }
    }"
    x-init="document.documentElement.classList.toggle('dark', dark)"
    @sidebar-minified.window="minified = $event.detail"
    :class="minified ? 'lg:ps-[3.25rem]' : 'lg:ps-64'"
    class="sticky top-0 inset-x-0 z-30 flex w-full flex-wrap md:flex-nowrap md:justify-start border-b border-gray-200 bg-white/80 dark:bg-gray-900/80 backdrop-blur py-2.5 text-sm transition-all duration-300 dark:border-gray-700"
>
  <nav class="mx-auto flex w-full basis-full items-center justify-between gap-2 px-4 sm:px-6">

    
    <div class="flex items-center gap-2 lg:hidden">
      <button type="button"
              class="flex size-8 items-center justify-center gap-x-2 rounded-full border border-gray-300 bg-white text-gray-700 transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
              @click="$dispatch('toggle-superadmin-sidebar')"
              aria-label="Toggle navigation">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>

    
    <div class="ms-auto flex items-center gap-1.5 sm:gap-2">

      
      <a href="<?php echo e(route('home')); ?>" target="_blank" rel="noopener"
         class="hidden items-center gap-2 rounded-full px-3 py-2 text-xs font-medium text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 sm:inline-flex dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white focus-visible:ring-2 focus-visible:ring-primary-500/50">
        <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/></svg>
        View Site
      </a>

      
      <div class="relative"
           x-data="{ open: false }"
           @click.outside="open = false"
           @keydown.escape.window="open = false">
        <button type="button"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-haspopup="true"
                class="relative flex items-center gap-2 rounded-full border border-gray-300 bg-white px-2.5 py-1.5 text-gray-700 transition-colors hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                aria-label="Notifications">
          <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span class="hidden text-xs font-medium md:inline">Notifications</span>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingCount > 0): ?>
            <span class="absolute -top-1 -right-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-800">
              <?php echo e($pendingCount > 99 ? '99+' : $pendingCount); ?>

            </span>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </button>

        <div x-cloak
             x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95 translate-y-1"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-1"
             class="absolute right-0 z-50 mt-2 w-72 sm:w-80 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">

          <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingCount > 0): ?>
              <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($pendingCount); ?> pending</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingTenants->isEmpty()): ?>
            <div class="p-6 text-center">
              <svg class="mx-auto mb-2 h-8 w-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
              <p class="text-sm text-gray-500 dark:text-gray-400">No pending applications</p>
            </div>
          <?php else: ?>
            <div class="max-h-80 overflow-y-auto">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $pendingTenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tenant): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <a href="<?php echo e(route('superadmin.tenants.preview', $tenant->id)); ?>" wire:navigate
                   @click="open = false"
                   class="flex items-start gap-3 border-b border-gray-100 px-4 py-3 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-bold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                    <?php echo e(strtoupper(substr($tenant->name, 0, 1))); ?>

                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white"><?php echo e($tenant->name); ?></p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Business application awaiting approval</p>
                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500"><?php echo e($tenant->created_at->diffForHumans()); ?></p>
                  </div>
                  <span class="shrink-0 text-xs font-medium text-primary-600 dark:text-primary-400">Review</span>
                </a>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <div class="border-t border-gray-200 p-2 dark:border-gray-700">
              <a href="<?php echo e(route('superadmin.tenants.index')); ?>" wire:navigate
                 @click="open = false"
                 class="flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-50 dark:hover:bg-primary-500/10">
                View all pending applications
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
              </a>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>

      
      <button type="button"
              @click="toggleDark()"
              class="flex size-9 items-center justify-center rounded-full border border-gray-300 bg-white text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
              aria-label="Toggle dark mode">
        <svg x-show="dark" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
        <svg x-show="!dark" class="size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
      </button>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
        
        <div class="relative"
             x-data="{ open: false }"
             @click.outside="open = false"
             @keydown.escape.window="open = false">
          <button type="button"
                  @click="open = !open"
                  :aria-expanded="open.toString()"
                  aria-haspopup="true"
                  class="flex items-center gap-2 rounded-full px-2 py-1.5 text-gray-600 transition-colors hover:bg-gray-100 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white">
            <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-600 text-xs font-bold text-white">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userAvatarUrl): ?>
                    <img src="<?php echo e($userAvatarUrl); ?>" alt="<?php echo e($user->name); ?>" class="h-full w-full object-cover">
                <?php else: ?>
                    <?php echo e($userInitial); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <span class="hidden max-w-[120px] truncate text-sm font-medium sm:inline"><?php echo e($user->name ?? 'Super Admin'); ?></span>
            <svg class="hidden h-3 w-3 transition-transform sm:block" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>

          <div x-cloak
               x-show="open"
               x-transition:enter="transition ease-out duration-150"
               x-transition:enter-start="opacity-0 scale-95 translate-y-1"
               x-transition:enter-end="opacity-100 scale-100 translate-y-0"
               x-transition:leave="transition ease-in duration-100"
               x-transition:leave-start="opacity-100 scale-100 translate-y-0"
               x-transition:leave-end="opacity-0 scale-95 translate-y-1"
               class="absolute right-0 z-50 mt-2 w-60 sm:w-64 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-800">

            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
              <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-600 text-sm font-bold text-white">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userAvatarUrl): ?>
                        <img src="<?php echo e($userAvatarUrl); ?>" alt="<?php echo e($user->name); ?>" class="h-full w-full object-cover">
                    <?php else: ?>
                        <?php echo e($userInitial); ?>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-gray-900 dark:text-white"><?php echo e($user->name); ?></p>
                  <p class="truncate text-xs text-gray-500 dark:text-gray-400"><?php echo e($user->email); ?></p>
                  <?php $role = $user->roles->first(); ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($role): ?>
                    <span class="mt-1 inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-500/20 dark:text-blue-300">
                      <?php echo e(ucwords(str_replace(['-', '_'], ' ', $role->name))); ?>

                    </span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              </div>
            </div>

            <div class="space-y-0.5 p-1.5">
              <a href="<?php echo e(route('superadmin.profile')); ?>" wire:navigate
                 class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900 dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white"
                 @click="open = false">
                <svg class="h-4 w-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                My Profile
              </a>

              <form method="POST" action="<?php echo e(route('logout')); ?>" class="block">
                <?php echo csrf_field(); ?>
                <button type="submit"
                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-sm text-red-600 transition-colors hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">
                  <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                  Sign Out
                </button>
              </form>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="<?php echo e(route('login')); ?>" class="inline-flex items-center gap-x-2 rounded-full bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700">Sign in</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </nav>
</header><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/admin/superadmin-header.blade.php ENDPATH**/ ?>