<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
?>


<div class="relative z-10 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-4xl glass-card flex flex-col lg:flex-row overflow-hidden !rounded-3xl !p-0">

        
        <div class="hidden lg:block relative flex-1 bg-black/40 overflow-hidden border-r border-white/10">
            <svg class="absolute inset-0 w-full h-full" viewBox="0 0 480 620" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
                <rect x="28"  y="22"  width="98"  height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <rect x="142" y="22"  width="78"  height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <rect x="236" y="22"  width="108" height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <rect x="360" y="22"  width="96"  height="62" rx="6" fill="rgba(34,197,94,0.15)" opacity=".8"/>
                <ellipse cx="200" cy="102" rx="58" ry="24" fill="rgba(37, 99, 235, 0.2)" opacity=".6"/>
                <rect x="0"   y="98"  width="480" height="14" fill="rgba(255,255,255,0.05)"/>
                <rect x="124" y="0"   width="10"  height="620" fill="rgba(255,255,255,0.05)"/>
                <rect x="264" y="0"   width="10"  height="620" fill="rgba(255,255,255,0.05)"/>
                <rect x="0"   y="254" width="480" height="10"  fill="rgba(255,255,255,0.05)"/>
                <rect x="0"   y="394" width="480" height="10"  fill="rgba(255,255,255,0.05)"/>
                <line x1="200" y1="0" x2="200" y2="110" stroke="rgba(34,197,94,0.4)" stroke-width="2.5"/>
            </svg>

            <div class="absolute top-4 right-4 size-9 rounded-full bg-white/10 backdrop-blur flex items-center justify-center text-xs font-bold text-brand-400 z-10">N</div>

            <div class="absolute z-10 flex items-center gap-1.5 bg-white/10 backdrop-blur border border-white/10 rounded-lg px-2.5 py-1.5 shadow-md text-xs font-semibold text-white/80" style="top:170px; left:86px;">
                <span class="inline-block size-2 rounded-full bg-pink-500 shrink-0"></span>
                HQ
            </div>

            <div class="absolute z-10 flex flex-col items-center" style="top:128px; left:290px;">
                <span class="size-3.5 rounded-full bg-brand-400 border-2 border-white/30 shadow block"></span>
                <span class="w-px h-3 bg-brand-400 block"></span>
            </div>

            <div class="absolute z-10 flex flex-col items-center" style="top:210px; left:170px;">
                <span class="size-7 rounded-full border-[3px] border-brand-400/20 flex items-center justify-center">
                    <span class="size-3.5 rounded-full bg-brand-400 border-2 border-white/30 shadow block"></span>
                </span>
                <span class="w-px h-3 bg-brand-400 block"></span>
            </div>

            <div class="absolute z-10 flex flex-col items-center" style="top:316px; left:326px;">
                <span class="size-3.5 rounded-full bg-brand-400 border-2 border-white/30 shadow block"></span>
                <span class="w-px h-3 bg-brand-400 block"></span>
            </div>

            <div class="absolute z-10 flex flex-col items-center" style="top:348px; left:70px;">
                <span class="size-3.5 rounded-full bg-brand-400 border-2 border-white/30 shadow block"></span>
                <span class="w-px h-3 bg-brand-400 block"></span>
            </div>

            <div class="absolute bottom-5 right-5 z-10 inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-white/10 backdrop-blur text-white/80 border border-white/10">3 locations</div>

            <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 size-9 rounded-full bg-white/10 backdrop-blur flex items-center justify-center border border-white/10">
                <svg class="size-4 text-brand-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
            </div>
        </div>

        
        <div class="w-full lg:max-w-md p-8 sm:p-10 relative bg-black/30 backdrop-blur-sm">
            <div class="absolute top-4 right-4">
                <button type="button" class="size-8 inline-flex items-center justify-center gap-x-2 rounded-full text-white/40 hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition">
                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 256 256"><circle cx="128" cy="60" r="16"/><circle cx="128" cy="128" r="16"/><circle cx="128" cy="196" r="16"/></svg>
                </button>
            </div>

            
            <div class="flex items-center gap-x-3 mb-7">
                <div class="size-10 rounded-xl bg-brand-600 inline-flex items-center justify-center shrink-0">
                    <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                </div>
                <span class="text-base font-semibold text-white"><?php echo e(config('app.name', 'Capstone')); ?></span>
            </div>

            
            <h1 class="text-2xl font-bold text-white">Sign In</h1>
            <p class="mt-1 text-sm text-white/60">
                New here?
                <a href="<?php echo e(route('register')); ?>" class="font-medium text-brand-400 decoration-2 hover:underline focus:outline-none focus:underline">Create an account</a>
            </p>
            <p class="mt-1 text-sm text-white/60">
                Own a tourist spot?
                <a href="<?php echo e(route('register_business')); ?>" wire:navigate class="font-medium text-brand-400 decoration-2 hover:underline focus:outline-none focus:underline">Register your business</a>
            </p>

            
            <form wire:submit="login" class="space-y-4 mt-6">
                <div>
                    <label for="email" class="block text-sm font-medium mb-2 text-white/70">Email Address</label>
                    <input type="email" id="email" wire:model="email" placeholder="example@email.com"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-2 text-xs text-red-400"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium mb-2 text-white/70">Password</label>
                    <input type="password" id="password" wire:model="password" placeholder="Enter your password"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-2 text-xs text-red-400"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="flex items-center gap-x-3">
                    <input type="checkbox" id="remember" wire:model="remember"
                           class="shrink-0 mt-0.5 border-white/20 rounded text-brand-600 focus:ring-brand-500 bg-white/5 dark:checked:bg-brand-600 dark:checked:border-brand-600">
                    <label for="remember" class="text-sm text-white/60">Remember me</label>
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-brand-600 text-white hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-black/30 disabled:opacity-50 disabled:pointer-events-none transition-colors shadow-lg shadow-brand-500/20">
                    <span wire:loading.remove>Sign In</span>
                    <span wire:loading class="inline-flex items-center gap-x-2">
                        <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        Signing in...
                    </span>
                </button>
            </form>
        </div>

    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/9a19ae0d.blade.php ENDPATH**/ ?>