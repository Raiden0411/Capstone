<header class="sticky top-0 z-30 flex h-16 items-center justify-between bg-white border-b border-slate-200 px-4 sm:px-6 lg:px-8">
    
    <button @click="sidebarOpen = true" class="lg:hidden rounded-lg p-1.5 text-slate-500 hover:bg-slate-100">
        <svg class="size-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <span class="sr-only">Open sidebar</span>
    </button>

    
    <div class="flex-1 lg:hidden">
        
    </div>

    
    <div class="flex items-center gap-2">
        
        <a href="<?php echo e(route('superadmin.tenants.create')); ?>" wire:navigate class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Onboard
        </a>
    </div>
</header><?php /**PATH C:\laragon\www\Capstone\resources\views/components/headers/admin/superadmin-header.blade.php ENDPATH**/ ?>