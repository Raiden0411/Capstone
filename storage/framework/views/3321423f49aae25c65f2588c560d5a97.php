<div>
<header class="flex flex-wrap sm:justify-start sm:flex-nowrap w-full bg-[#062c1f] text-sm py-4">
  <nav class="max-w-[85rem] w-full mx-auto px-4 flex flex-wrap basis-full items-center justify-between" aria-label="Global">
   
    <a class="flex flex-col group" href="/">
      <span class="bg-clip-text text-transparent bg-gradient-to-r from-yellow-500 via-yellow-200 to-yellow-500 bg-[length:200%_auto] group-hover:bg-[100%_center] transition-all duration-500 font-bold text-xl">
        Victorias
      </span>
      <span class="text-emerald-100/80 font-light text-xs tracking-[0.2em] uppercase block leading-none mt-1">
        Tourism Management
      </span>
    </a>

    <div class="flex items-center gap-x-6 ms-auto lg:order-3">
     
      <div id="navbar-collapse" class="hs-collapse hidden overflow-hidden transition-all duration-300 basis-full grow lg:block lg:w-auto lg:basis-auto">
        <div class="flex flex-col gap-y-4 gap-x-0 mt-5 lg:flex-row lg:items-center lg:justify-end lg:gap-y-0 lg:gap-x-8 lg:mt-0">
          <a class="relative text-xs font-bold tracking-[0.15em] uppercase text-yellow-400 lg:after:absolute lg:after:bottom-[-4px] lg:after:left-0 lg:after:h-[2px] lg:after:w-full lg:after:bg-yellow-400 transition-all" href="/">Home</a>
          <a class="text-xs font-bold tracking-[0.15em] uppercase text-emerald-100/80 hover:text-yellow-400 hover:translate-y-[-1px] transition-all duration-300" href="/about-page">About</a>
          <a class="text-xs font-bold tracking-[0.15em] uppercase text-emerald-100/80 hover:text-yellow-400 hover:translate-y-[-1px] transition-all duration-300" href="#">Contact</a>
        </div>
      </div>

      <div class="flex items-center gap-x-3">
        <a href="#" class="hidden sm:inline-flex py-2 px-5 items-center text-xs font-bold tracking-[0.1em] uppercase rounded-full text-emerald-100 border border-emerald-700/50 hover:border-yellow-400 hover:text-yellow-400 transition-all duration-300">
          Places
        </a>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>
          <a href="<?php echo e(route('login')); ?>" wire:navigate class="py-2 px-6 inline-flex items-center text-xs font-bold tracking-[0.1em] uppercase rounded-full bg-yellow-400 border border-yellow-400 text-[#062c1f] hover:bg-transparent hover:text-yellow-400 transition-all duration-300">
            Sign in
          </a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
          <div class="hs-dropdown relative inline-flex">
            <button type="button" class="hs-dropdown-toggle py-2 px-4 inline-flex items-center gap-x-2 text-xs font-bold tracking-[0.1em] uppercase rounded-full bg-yellow-400 border border-yellow-400 text-[#062c1f] hover:bg-transparent hover:text-yellow-400 transition-all duration-300">
              <?php echo e(Auth::user()->name); ?>

              <svg class="hs-dropdown-open:rotate-180 size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path d="m19 9-7 7-7-7"/></svg>
            </button>

            <div class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-[200px] bg-[#062c1f] border border-emerald-700/50 rounded-xl p-2 mt-2 shadow-xl z-50">
              <a class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-emerald-100/80 hover:bg-emerald-900/40 hover:text-yellow-400 transition-colors" href="<?php echo e(route('explore.map')); ?>">
                <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Explore Map
              </a>
              <div class="border-t border-emerald-700/50 my-2"></div>
              <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="flex items-center gap-x-3 py-2 px-3 rounded-lg text-sm text-red-400 hover:bg-red-900/10 w-full text-left transition-colors">
                  <svg class="size-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                  Logout
                </button>
              </form>
            </div>
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <div class="lg:hidden">
          <button type="button" class="hs-collapse-toggle size-9 flex justify-center items-center text-white rounded-full bg-emerald-900/40 border border-emerald-700/50" data-hs-collapse="#navbar-collapse" aria-controls="navbar-collapse" aria-label="Toggle navigation">
            <svg class="hs-collapse-open:hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" x2="21" y1="6" y2="6"/><line x1="3" x2="21" y1="12" y2="12"/><line x1="3" x2="21" y1="18" y2="18"/></svg>
            <svg class="hs-collapse-open:block hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
          </button>
        </div>
      </div>
    </div>
  </nav>
</header>
</div><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/public-header.blade.php ENDPATH**/ ?>