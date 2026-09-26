<?php
use App\Mail\TenantAdminWelcome;
use App\Models\BusinessApplication;
use App\Models\BusinessDocument;
use App\Models\PropertyType;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TypeOfTenant;
use App\Models\User;
use App\Services\BusinessApplicationService;
use App\Services\ReverseGeocodeService;
use App\Services\SvgSanitizerService;
use App\Traits\HandlesImageUploads;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
?>




<div
    x-data="{ toasts: [] }"
    x-on:toast.window="
        const id = Date.now() + Math.random();
        toasts.push({ id, message: $event.detail.message, type: $event.detail.type || 'info' });
        setTimeout(() => { toasts = toasts.filter(t => t.id !== id) }, 4000);
    "
    x-on:scroll-to-top.window="window.scrollTo({ top: 0, behavior: 'smooth' })"
    class="p-4 sm:p-6 lg:p-8 max-w-[1440px] mx-auto space-y-6"
>

    
    <div class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        <template x-for="toast in toasts" :key="toast.id">
            <div
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="pointer-events-auto rounded-xl px-4 py-3 shadow-lg text-sm font-medium flex items-center gap-2 border"
                :class="{
                    'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300': toast.type === 'success',
                    'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300': toast.type === 'error',
                    'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-500/10 dark:border-blue-500/30 dark:text-blue-300': toast.type === 'info',
                }">
                <span x-text="toast.message"></span>
            </div>
        </template>
    </div>

    
    <div wire:loading.delay.longer wire:target="save"
         class="fixed inset-0 z-40 bg-white/60 dark:bg-gray-900/60 backdrop-blur-sm flex items-center justify-center pointer-events-none">
        <div class="flex items-center gap-3 bg-white dark:bg-gray-800 rounded-2xl shadow-xl px-6 py-4 pointer-events-auto">
            <svg class="animate-spin h-5 w-5 text-primary-600 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">Creating the business, uploading documents & admin account…</span>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 border-l-4 border-l-emerald-500 p-4 rounded-md text-sm text-emerald-700 dark:text-emerald-300 font-medium">
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <div class="flex items-center gap-2 mb-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Platform · Onboard Business</span>
            </div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Add Tenant</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Onboard a walk-in business. Legal documents will be stored and a KYB record created automatically.
            </p>
        </div>
        <button type="button" wire:click="goToTenantList"
                <?php if($step > 1 || $name !== ''): ?> wire:confirm="Leave without saving? Your progress on this tenant will be lost." <?php endif; ?>
                class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to tenants
        </button>
    </div>

    
    <div class="sticky top-0 z-30 bg-[#F8F7F3] dark:bg-gray-900/95 backdrop-blur-sm py-3 mb-2">
        <nav aria-label="Progress" class="max-w-5xl mx-auto">
            <ol class="flex items-center gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->stepLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stepNum => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'step-indicator-'.e($stepNum).''; ?>wire:key="step-indicator-<?php echo e($stepNum); ?>" class="flex items-center gap-2 min-w-0 <?php echo e($stepNum < count($this->stepLabels) ? 'flex-1' : ''); ?>">
                        <button type="button" wire:click="goToStep(<?php echo e($stepNum); ?>)"
                                <?php if($stepNum > $furthestStep): ?> disabled <?php endif; ?>
                                aria-current="<?php echo e($step === $stepNum ? 'step' : 'false'); ?>"
                                class="flex items-center gap-2 shrink-0 group <?php echo e($stepNum > $furthestStep ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'); ?> focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-semibold transition-colors
                                <?php echo e($step > $stepNum ? 'bg-emerald-500 text-white' : ($step === $stepNum ? 'bg-primary-600 text-white ring-4 ring-primary-500/20' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400')); ?>">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step > $stepNum): ?>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                <?php else: ?>
                                    <?php echo e($stepNum); ?>

                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="hidden lg:inline text-xs font-medium truncate <?php echo e($step >= $stepNum ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400'); ?>">
                                <?php echo e($label); ?>

                            </span>
                        </button>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stepNum < count($this->stepLabels)): ?>
                            <div class="flex-1 h-1 rounded bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full bg-primary-600 transition-all duration-500" style="width: <?php echo e($step > $stepNum ? '100%' : '0%'); ?>"></div>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ol>
        </nav>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-500/30 dark:bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300">
            <p class="font-semibold mb-1">Please fix <?php echo e($errors->count()); ?> field<?php echo e($errors->count() === 1 ? '' : 's'); ?> before continuing:</p>
            <ul class="list-disc list-inside space-y-0.5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <li><?php echo e($error); ?></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 1): ?>

        
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 1 — Business Details</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3 mb-5">Cover Photo <span class="text-sm font-normal text-gray-400">(optional)</span></h2>

            <div class="relative aspect-[3/1] w-full rounded-xl overflow-hidden bg-gradient-to-br from-primary-500 via-primary-600 to-primary-700">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->coverPreviewUrl()): ?>
                    <img src="<?php echo e($this->coverPreviewUrl()); ?>" alt="Cover photo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-white/85">
                        <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-xs font-semibold uppercase tracking-wider">Cover photo</p>
                    </div>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div
                x-data="imageCropper({
                    wireProperty: 'cover_photo',
                    aspect: 3,
                    title: 'Crop cover photo',
                    description: 'Drag to reposition · Scroll to zoom',
                })"
                x-init="init()"
                class="mt-3 flex items-center gap-2 flex-wrap"
            >
                <label for="cover-upload"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 text-xs font-semibold transition cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <?php echo e($cover_photo_path ? 'Replace cover' : 'Upload cover photo'); ?>

                    <input type="file" id="cover-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                </label>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cover_photo_path): ?>
                    <button type="button" wire:click="removeCoverPhoto" wire:confirm="Remove the cover photo?"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition">
                        Remove
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                <div wire:loading wire:target="cover_photo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                    <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Uploading…
                </div>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['cover_photo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block" role="alert"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>

        
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-5">
                <div class="shrink-0 w-20 h-20 rounded-2xl border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 flex items-center justify-center overflow-hidden">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($this->logoPreviewUrl()): ?>
                        <img src="<?php echo e($this->logoPreviewUrl()); ?>" alt="Business logo" loading="lazy" decoding="async" class="w-full h-full object-cover">
                    <?php else: ?>
                        <svg class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div
                    x-data="imageCropper({
                        wireProperty: 'logo',
                        aspect: 1,
                        title: 'Crop logo',
                        description: 'Square crop works best',
                    })"
                    x-init="init()"
                    class="flex-1 min-w-0"
                >
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Business Logo <span class="text-xs font-normal text-gray-400">(optional)</span></p>

                    <div class="flex items-center gap-2 flex-wrap">
                        <label for="logo-upload"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-2 text-xs font-semibold transition cursor-pointer focus-within:ring-2 focus-within:ring-primary-500/50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            <?php echo e($logo_path ? 'Replace logo' : 'Upload logo'); ?>

                            <input type="file" id="logo-upload" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="sr-only">
                        </label>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logo_path): ?>
                            <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition">
                                Remove
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div wire:loading wire:target="logo" class="flex items-center gap-2 text-xs text-primary-600 dark:text-primary-400">
                            <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['logo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block" role="alert"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-3 mb-5">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Business Information
                </span>
            </div>

            <div class="space-y-5">
                <div>
                    <label for="field-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Business Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="field-name" wire:model.live.debounce.300ms="name" class="input" placeholder="e.g. Gawahon Eco Park" maxlength="255">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="field-slug" class="block text-sm font-medium text-gray-700 dark:text-gray-300">URL slug</label>
                        <button type="button" wire:click="toggleSlugEdit" class="text-xs font-semibold text-primary-600 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                            <?php echo e($slugEditable ? 'Auto-generate' : 'Edit manually'); ?>

                        </button>
                    </div>
                    <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-900">
                        <span class="py-2.5 px-3 bg-gray-200 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400 border-r border-gray-300 dark:border-gray-600">spot/</span>
                        <input type="text" id="field-slug" wire:model.live.debounce.400ms="slug" <?php if(!$slugEditable): ?> readonly <?php endif; ?> class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm outline-none <?php echo e($slugEditable ? 'text-gray-900 dark:text-white cursor-text' : 'text-gray-500 dark:text-gray-400 cursor-default'); ?>">
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['slug'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="field-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Category <span class="text-rose-500">*</span></label>
                            <button type="button" wire:click="openNewTenantTypeModal"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded active:scale-95 transition-transform">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                                New
                            </button>
                        </div>
                        <select id="field-type" wire:model="type_of_tenant_id" class="select">
                            <option value="">— Select category —</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenantTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'type-'.e($type->id).''; ?>wire:key="type-<?php echo e($type->id); ?>" value="<?php echo e($type->id); ?>"><?php echo e($type->type); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['type_of_tenant_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label for="field-public-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Public Email <span class="text-rose-500">*</span></label>
                        <input type="email" id="field-public-email" wire:model.live.debounce.400ms="public_email" class="input" placeholder="business@email.com" maxlength="255">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['public_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label for="field-contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contact Number</label>
                        <input type="tel" id="field-contact" inputmode="numeric" pattern="[0-9]*" maxlength="11"
                               wire:model.live.debounce.400ms="contact_number"
                               x-on:input="event.target.value = event.target.value.replace(/[^0-9]/g, '').slice(0, 11)"
                               class="input" placeholder="09xxxxxxxxx">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['contact_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div>
                    <label for="field-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Short Description <span class="text-[10px] font-normal text-gray-400">(optional)</span></label>
                    <textarea id="field-description" wire:model="description" rows="3" class="textarea" placeholder="A short introduction to your business" maxlength="500"></textarea>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Business Hours</label>
                        <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-600 dark:text-gray-300">
                            <input type="checkbox" wire:model.live="open_24_hours" class="rounded border-gray-300 text-primary-600 focus:ring-primary-600">
                            Open 24 hours
                        </label>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 <?php echo e($open_24_hours ? 'opacity-50 pointer-events-none' : ''); ?>">
                        <div>
                            <label for="field-opening" class="text-xs text-gray-500 dark:text-gray-400">Opening time</label>
                            <input type="time" id="field-opening" wire:model="opening_time" class="input" <?php if($open_24_hours): ?> disabled <?php endif; ?>>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['opening_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label for="field-closing" class="text-xs text-gray-500 dark:text-gray-400">Closing time</label>
                            <input type="time" id="field-closing" wire:model="closing_time" class="input" <?php if($open_24_hours): ?> disabled <?php endif; ?>>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['closing_time'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        
        <section class="card p-6 mb-5">
            <div class="flex items-center gap-3 mb-5">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Legal &amp; Identity
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-business-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Registration Type <span class="text-rose-500">*</span>
                    </label>
                    <select id="field-business-type" wire:model.live="business_type" class="select">
                        <option value="">— Select type —</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->businessTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['business_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="field-regno" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Registration No. <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-regno"
                           wire:model="business_registration_number"
                           maxlength="50"
                           autocomplete="off"
                           placeholder="<?php echo e($this->registrationNumberPlaceholder); ?>"
                           x-on:input="
                               const up = $event.target.value.toUpperCase();
                               if (up !== $event.target.value) {
                                   $event.target.value = up;
                                   $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                               }
                           "
                           class="input font-mono">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['business_registration_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div class="md:col-span-2">
                    <label for="field-tin" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        TIN <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-tin"
                           wire:model="tin_number"
                           placeholder="123-456-789-000"
                           inputmode="numeric"
                           maxlength="15"
                           autocomplete="off"
                           x-on:blur="
                               const digits = ($event.target.value || '').replace(/\D/g, '').slice(0, 12);
                               let parts = [];
                               if (digits.length > 0) parts.push(digits.slice(0, 3));
                               if (digits.length > 3) parts.push(digits.slice(3, 6));
                               if (digits.length > 6) parts.push(digits.slice(6, 9));
                               if (digits.length > 9) parts.push(digits.slice(9, 12));
                               const formatted = parts.join('-');
                               if (formatted !== $event.target.value) {
                                   $event.target.value = formatted;
                                   $event.target.dispatchEvent(new Event('input', { bubbles: true }));
                               }
                           "
                           class="input font-mono">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['tin_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="field-owner-id-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner ID Type <span class="text-rose-500">*</span>
                    </label>
                    <select id="field-owner-id-type" wire:model.live="owner_id_type" class="select">
                        <option value="">— Select an accepted ID —</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->ownerIdTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                    </select>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['owner_id_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                <div>
                    <label for="field-owner-id-number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner ID Number <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="field-owner-id-number"
                           wire:model.blur="owner_id_number"
                           maxlength="<?php echo e($this->ownerIdMaxLength); ?>"
                           autocomplete="off"
                           placeholder="<?php echo e($this->ownerIdPlaceholder); ?>"
                           class="input font-mono">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['owner_id_number'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400"><?php echo e($this->ownerIdHint); ?></p>
                </div>

                <div class="md:col-span-2 sm:max-w-xs">
                    <label for="field-owner-birthdate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Owner Date of Birth <span class="text-[10px] font-normal text-gray-400">(optional)</span>
                    </label>
                    <input type="date"
                           id="field-owner-birthdate"
                           wire:model.blur="owner_birthdate"
                           max="<?php echo e(now()->subYears(18)->format('Y-m-d')); ?>"
                           min="1900-01-01"
                           class="input">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['owner_birthdate'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>
        </section>

        
        <section class="card p-6 mb-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        Required Documents
                    </span>
                </div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider
                    <?php echo e($this->uploadedRequiredCount === count($this->requiredDocuments)
                        ? 'bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30'
                        : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700'); ?>">
                    <?php echo e($this->uploadedRequiredCount); ?> / <?php echo e(count($this->requiredDocuments)); ?>

                </span>
            </div>

            <div class="space-y-2.5">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->requiredDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                    <?php
                        $file = $this->document_uploads[$docType] ?? null;
                        $label = $this->documentLabels[$docType] ?? $docType;
                        $isOwnerIdDoc = $docType === BusinessDocument::TYPE_OWNER_ID;
                        $ownerIdLabel = $this->selectedOwnerIdLabel;
                    ?>
                    <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'req-slot-'.e($docType).''; ?>wire:key="req-slot-<?php echo e($docType); ?>"
                         class="border rounded-xl p-3.5 transition
                            <?php echo e($file
                                ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900'); ?>">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($file): ?>
                            <div class="flex items-center gap-3">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                </svg>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($label); ?></p>
                                    <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 truncate">
                                        <?php echo e(method_exists($file, 'getClientOriginalName') ? $file->getClientOriginalName() : 'File ready'); ?>

                                    </p>
                                </div>
                                <button type="button"
                                        wire:click="clearDocumentUpload('<?php echo e($docType); ?>')"
                                        class="text-[11px] font-semibold text-rose-500 hover:text-rose-700 transition active:scale-95">
                                    Remove
                                </button>
                            </div>
                        <?php else: ?>
                            <label for="file-<?php echo e($docType); ?>" class="flex items-center gap-3 cursor-pointer group">
                                <div class="shrink-0 w-9 h-9 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate"><?php echo e($label); ?></p>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isOwnerIdDoc && $ownerIdLabel): ?>
                                        <p class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/30 rounded px-1.5 py-0.5">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/>
                                            </svg>
                                            Should be your <?php echo e($ownerIdLabel); ?>

                                        </p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </div>
                                <input type="file"
                                       id="file-<?php echo e($docType); ?>"
                                       wire:model="document_uploads.<?php echo e($docType); ?>"
                                       accept=".pdf,.jpg,.jpeg,.png,.webp"
                                       class="sr-only">
                            </label>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        <div wire:loading wire:target="document_uploads.<?php echo e($docType); ?>" class="mt-2 flex items-center gap-2 text-[11px] text-primary-600 dark:text-primary-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="animate-spin w-3 h-3 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Uploading…
                        </div>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ["document_uploads.{$docType}"];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </div>

            <details class="mt-4 border-t border-gray-100 dark:border-gray-700/60 pt-4">
                <summary class="cursor-pointer list-none flex items-center justify-between py-1 group">
                    <span class="text-[11px] font-semibold text-gray-600 dark:text-gray-300 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition">
                        Additional documents (optional)
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </summary>
                <div class="mt-3 space-y-2.5">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->optionalDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $docType): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                        <?php
                            $file = $this->document_uploads[$docType] ?? null;
                            $label = $this->documentLabels[$docType] ?? $docType;
                        ?>
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'opt-slot-'.e($docType).''; ?>wire:key="opt-slot-<?php echo e($docType); ?>"
                             class="border rounded-xl p-3 transition
                                <?php echo e($file
                                    ? 'border-emerald-200 dark:border-emerald-500/30 bg-emerald-50/40 dark:bg-emerald-500/[0.04]'
                                    : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900'); ?>">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($file): ?>
                                <div class="flex items-center gap-3">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <p class="flex-1 text-sm font-medium text-gray-900 dark:text-white truncate"><?php echo e($label); ?></p>
                                    <button type="button"
                                            wire:click="clearDocumentUpload('<?php echo e($docType); ?>')"
                                            class="text-[11px] font-semibold text-gray-500 hover:text-rose-600 transition">
                                        Remove
                                    </button>
                                </div>
                            <?php else: ?>
                                <label for="file-opt-<?php echo e($docType); ?>" class="flex items-center gap-3 cursor-pointer group">
                                    <div class="shrink-0 w-8 h-8 rounded-lg bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center text-gray-400 group-hover:border-primary-500 group-hover:text-primary-600 transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                        </svg>
                                    </div>
                                    <p class="flex-1 text-sm font-medium text-gray-700 dark:text-gray-300"><?php echo e($label); ?></p>
                                    <input type="file"
                                           id="file-opt-<?php echo e($docType); ?>"
                                           wire:model="document_uploads.<?php echo e($docType); ?>"
                                           accept=".pdf,.jpg,.jpeg,.png,.webp"
                                           class="sr-only">
                                </label>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            </details>
        </section>

        
        <section class="card p-6 mb-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Activate this business immediately?</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_active" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 inline-flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        Recommended Destination
                    </span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model="is_recommended" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="btn-primary active:scale-95 transition-transform">
                Next: Map Location →
            </button>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 2): ?>

        <div class="card p-6 space-y-5">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 2 — Location</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Map Location Setup</h2>

            
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="useMyLocation" <?php if($locationConfirmed): ?> disabled <?php endif; ?>
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white px-3 py-1.5 text-xs font-semibold transition active:scale-95
                               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:cursor-not-allowed">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Use my location
                </button>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($locationConfirmed): ?>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        Location locked
                    </span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div
                wire:ignore
                <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tenant-main-map-stable'; ?>wire:key="tenant-main-map-stable"
                x-data="locationPicker({
                    containerId: 'tenant-main-map',
                    wireMethod: 'setMainLocation',
                    geocodeMethod: 'resolveAddress',
                    initialLat: <?php echo e($latitude); ?>,
                    initialLng: <?php echo e($longitude); ?>,
                })"
                x-init="init()"
                class="relative rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-900"
                style="height: 420px;"
            >
                <?php if (isset($component)) { $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\Map::resolve(['center' => [(float) $longitude, (float) $latitude],'zoom' => 14,'height' => '100%','provider' => 'carto-voyager','theme' => 'auto','maxZoom' => 22,'class' => 'h-full w-full'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\Map::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'tenant-main-map']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                    <?php if (isset($component)) { $__componentOriginal30d4ce5150bc700b8142cf87b21ef225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapControls::resolve(['zoom' => true,'compass' => true,'locate' => false,'fullscreen' => true,'scale' => true,'position' => 'top-right'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-controls'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapControls::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $attributes = $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $component = $__componentOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $attributes = $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $component = $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>

                <div x-cloak
                     :class="hasPin ? 'hidden' : ''"
                     class="absolute inset-x-0 top-3 mx-auto w-max pointer-events-none rounded-full bg-gray-900/80 backdrop-blur-sm text-white text-[11px] font-semibold px-3 py-1.5">
                    Click the map to drop a pin
                </div>
            </div>

            
            <div class="relative -mt-3 ml-3 w-max">
                <div wire:loading wire:target="resolveAddress"
                     class="inline-flex items-center gap-1.5 rounded-full bg-gray-900/85 backdrop-blur-sm text-white text-[11px] font-semibold px-3 py-1.5 shadow-lg">
                    <svg class="animate-spin w-3 h-3 motion-reduce:animate-none" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Looking up address…
                </div>
            </div>

            
            <div class="flex flex-wrap items-center gap-3">
                <p class="text-[11px] font-mono text-gray-500 dark:text-gray-400 tabular-nums">
                    <?php echo e(number_format($latitude, 6)); ?>, <?php echo e(number_format($longitude, 6)); ?>

                </p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$locationConfirmed): ?>
                    <button type="button" wire:click="confirmLocation"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 text-xs font-semibold transition active:scale-95
                                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        Confirm location
                    </button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700/60">
                <div class="flex items-center gap-2 mb-3">
                    <span class="w-5 h-px bg-primary-600"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label for="field-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Street Address</label>
                        <input type="text" id="field-address" wire:model="address" class="input" placeholder="Street, Building, etc." maxlength="255">
                    </div>
                    <div>
                        <label for="field-barangay" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Barangay</label>
                        <input type="text" id="field-barangay" wire:model="barangay" class="input" maxlength="255">
                    </div>
                    <div>
                        <label for="field-city" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">City / Municipality</label>
                        <input type="text" id="field-city" wire:model="city" class="input" maxlength="255">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="field-province" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Province</label>
                        <input type="text" id="field-province" wire:model="province" class="input" maxlength="255">
                    </div>
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep"
                        <?php if(!$locationConfirmed): ?> disabled <?php endif; ?>
                        class="btn-primary active:scale-95 transition-transform disabled:opacity-50 disabled:cursor-not-allowed">
                    Next: Sub-Establishments →
                </button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 3): ?>
        <div class="card p-6 space-y-6">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 3 — Sub-Establishments</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Sub-Establishments</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">Optional: Add restaurants, cafes, parking, etc. linked to this main spot.</p>

            <div class="flex items-center gap-3">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Does this spot have sub-branches?</span>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="hasSubBranches" value="1" class="text-primary-600 focus:ring-primary-600">
                    <span class="text-sm">Yes</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="radio" wire:model.live="hasSubBranches" value="0" class="text-primary-600 focus:ring-primary-600">
                    <span class="text-sm">No</span>
                </label>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasSubBranches): ?>
                <div class="border-t border-gray-200 dark:border-gray-700 pt-5 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            Nearby places <span class="text-gray-400 font-normal">(<?php echo e(count($markers)); ?>/<?php echo e($this->maxMarkers); ?>)</span>
                        </span>
                        <div class="flex items-center gap-2">
                            <input type="text" wire:model.live.debounce.300ms="markerSearch"
                                   placeholder="Search places…"
                                   class="input !py-1.5 !w-48 text-sm">
                            <button type="button" wire:click="openAddCategoryModal"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 text-xs font-semibold border border-primary-200 dark:border-primary-500/30 hover:bg-primary-100 dark:hover:bg-primary-500/20 transition active:scale-95">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Category
                            </button>
                        </div>
                    </div>

                    <div class="card overflow-hidden relative" style="height: 400px;">
                        <div <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'tenant-sub-map-'.e($mapVersion).''; ?>wire:key="tenant-sub-map-<?php echo e($mapVersion); ?>">
                            <?php if (isset($component)) { $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\Map::resolve(['center' => [(float)$mapView['lng'], (float)$mapView['lat']],'zoom' => $mapView['zoom'],'height' => '400px','provider' => $satellite ? 'custom' : 'carto-voyager','style' => $satellite ? route('map.satellite.style') : null,'lightStyle' => $satellite ? route('map.satellite.style') : null,'darkStyle' => $satellite ? route('map.satellite.style') : null,'theme' => 'auto','class' => 'h-full w-full','events' => ['click', 'marker-clicked']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\Map::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['id' => 'tenant-sub-map']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                <?php if (isset($component)) { $__componentOriginal30d4ce5150bc700b8142cf87b21ef225 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapControls::resolve(['zoom' => true,'compass' => true,'locate' => true,'fullscreen' => true,'scale' => true,'position' => 'top-right'] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-controls'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapControls::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $attributes = $__attributesOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__attributesOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225)): ?>
<?php $component = $__componentOriginal30d4ce5150bc700b8142cf87b21ef225; ?>
<?php unset($__componentOriginal30d4ce5150bc700b8142cf87b21ef225); ?>
<?php endif; ?>

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $markers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $marker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                    <?php
                                        $type = $marker['type'] ?? '';
                                        $category = collect($this->markerCategories)->firstWhere('key', $type);
                                        $color = $category['color'] ?? '#94a3b8';
                                        $iconSvg = $category['icon_svg'] ?? null;
                                    ?>
                                    <?php if (isset($component)) { $__componentOriginalfdc07447b73c389f668e824ec2f32988 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfdc07447b73c389f668e824ec2f32988 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MapMarker::resolve(['lat' => $marker['lat'],'lng' => $marker['lng'],'color' => $color,'id' => 'sub-marker-'.e($index).'','draggable' => true] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('map-marker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MapMarker::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['wire:key' => 'sub-marker-'.e($marker['uid']).'-'.e($marker['type']).'']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                        <?php if (isset($component)) { $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5 = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerContent::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-content'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerContent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                            <div class="relative flex h-10 w-10 items-center justify-center transform-gpu will-change-transform transition-transform duration-200 group-hover:scale-110 active:scale-95" style="cursor: pointer;">
                                                <svg class="absolute inset-0 size-10 drop-shadow-md fill-white dark:fill-gray-900 stroke-slate-400 dark:stroke-slate-600 stroke-1" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                                                </svg>
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($iconSvg): ?>
                                                    <div class="absolute mb-1 size-[18px] text-gray-800 dark:text-white">
                                                        <?php if (isset($component)) { $__componentOriginal9906aeaff1ebf5f586496f6e330b3b36 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.safe-svg','data' => ['svg' => $iconSvg,'class' => 'size-full stroke-current fill-none']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('safe-svg'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['svg' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($iconSvg),'class' => 'size-full stroke-current fill-none']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36)): ?>
<?php $attributes = $__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36; ?>
<?php unset($__attributesOriginal9906aeaff1ebf5f586496f6e330b3b36); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9906aeaff1ebf5f586496f6e330b3b36)): ?>
<?php $component = $__componentOriginal9906aeaff1ebf5f586496f6e330b3b36; ?>
<?php unset($__componentOriginal9906aeaff1ebf5f586496f6e330b3b36); ?>
<?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="absolute mb-1 text-[10px] font-bold text-gray-800 dark:text-white">
                                                        <?php echo e(strtoupper(substr($type, 0, 1))); ?>

                                                    </span>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>
                                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $attributes = $__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__attributesOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5)): ?>
<?php $component = $__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5; ?>
<?php unset($__componentOriginal04becfd169bd0cc1508ca1844b5d8fa5); ?>
<?php endif; ?>
                                        <?php if (isset($component)) { $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce = $attributes; } ?>
<?php $component = Kwasii\LivewireMapcn\Components\MarkerPopup::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('marker-popup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Kwasii\LivewireMapcn\Components\MarkerPopup::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

                                            <div class="p-2">
                                                <strong class="text-gray-900 dark:text-white"><?php echo e($marker['name']); ?></strong>
                                                <p class="text-xs text-gray-500 dark:text-gray-400"><?php echo e($category['label'] ?? 'Uncategorized'); ?></p>
                                            </div>
                                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $attributes = $__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__attributesOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce)): ?>
<?php $component = $__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce; ?>
<?php unset($__componentOriginalb46b65f82f0c9b0d0107e2b30c1234ce); ?>
<?php endif; ?>
                                     <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $attributes = $__attributesOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__attributesOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalfdc07447b73c389f668e824ec2f32988)): ?>
<?php $component = $__componentOriginalfdc07447b73c389f668e824ec2f32988; ?>
<?php unset($__componentOriginalfdc07447b73c389f668e824ec2f32988); ?>
<?php endif; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $attributes = $__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__attributesOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d)): ?>
<?php $component = $__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d; ?>
<?php unset($__componentOriginal200d48706721e15bf0ceea6c3e5dfc4d); ?>
<?php endif; ?>
                        </div>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($this->filteredMarkers) > 0): ?>
                        <ul wire:sort="updateMarkerOrder" class="space-y-2">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->filteredMarkers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $marker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <?php
                                    $originalIndex = array_search($marker['uid'], array_column($this->markers, 'uid'));
                                    $type = $marker['type'] ?? '';
                                ?>
                                <li <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'marker-row-'.e($marker['uid']).''; ?>wire:key="marker-row-<?php echo e($marker['uid']); ?>"
                                    wire:sort:item="<?php echo e($marker['uid']); ?>"
                                    class="flex flex-wrap items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200 dark:border-gray-700 cursor-grab active:cursor-grabbing
                                            <?php echo e($selectedMarkerIndex === $originalIndex ? 'ring-2 ring-primary-500/40 border-primary-500/30' : ''); ?>"
                                    @click="$wire.selectedMarkerIndex = <?php echo e($originalIndex); ?>"
                                    title="Drag to reorder"
                                >
                                    <span class="shrink-0 text-gray-400 dark:text-gray-500 cursor-grab">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path d="M7 2a2 2 0 100 4 2 2 0 000-4zM7 8a2 2 0 100 4 2 2 0 000-4zM7 14a2 2 0 100 4 2 2 0 000-4zM13 2a2 2 0 100 4 2 2 0 000-4zM13 8a2 2 0 100 4 2 2 0 000-4zM13 14a2 2 0 100 4 2 2 0 000-4z"/></svg>
                                    </span>
                                    <input type="text" wire:model.debounce.500ms="markers.<?php echo e($originalIndex); ?>.name" placeholder="Place name" aria-label="Place name" class="input !py-2 flex-1 min-w-[140px]">
                                    <input type="number" step="any" min="-90" max="90" wire:model.debounce.500ms="markers.<?php echo e($originalIndex); ?>.lat" placeholder="Lat" aria-label="Latitude" class="input !py-2 !w-28 font-mono">
                                    <input type="number" step="any" min="-180" max="180" wire:model.debounce.500ms="markers.<?php echo e($originalIndex); ?>.lng" placeholder="Lng" aria-label="Longitude" class="input !py-2 !w-28 font-mono">
                                    <select wire:model.live="markers.<?php echo e($originalIndex); ?>.type"
                                            class="select !py-2 !w-48 <?php echo e(empty($marker['type']) ? 'border-rose-300 dark:border-rose-500' : ''); ?>">
                                        <option value="">Select category *</option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->markerCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                            <option <?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::$currentLoop['key'] = 'cat-'.e($cat['key']).''; ?>wire:key="cat-<?php echo e($cat['key']); ?>" value="<?php echo e($cat['key']); ?>"><?php echo e($cat['label']); ?></option>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                                    </select>
                                    <button type="button" wire:click="focusMarker(<?php echo e($originalIndex); ?>)" aria-label="Locate on map" class="text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 shrink-0 active:scale-95 transition-transform">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    </button>
                                    <button type="button" wire:click="removeMarker(<?php echo e($originalIndex); ?>)" aria-label="Remove nearby place" class="text-rose-500 hover:text-rose-700 shrink-0 active:scale-95 transition-transform">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </li>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <?php echo e($markerSearch !== '' ? 'No places match your search.' : 'Click anywhere on the map to add a nearby place.'); ?>

                        </p>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <div class="flex justify-between">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" class="btn-primary active:scale-95 transition-transform">
                    <?php echo e($hasSubBranches ? 'Next: Admin Account →' : 'Skip / Next: Admin Account →'); ?>

                </button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($step === 4): ?>
        <div class="card p-6 space-y-6">
            <div class="flex items-center gap-2">
                <span class="w-5 h-px bg-primary-600"></span>
                <span class="text-[10px] tracking-[0.22em] uppercase text-primary-600 dark:text-primary-400 font-bold">Step 4 — Admin Account</span>
            </div>
            <h2 class="font-display text-xl font-semibold text-gray-900 dark:text-white -mt-3">Admin Account Assignment</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500 -mt-4">A welcome email with these credentials will be sent to the admin automatically.</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="field-admin-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Full Name <span class="text-rose-500">*</span></label>
                    <input type="text" id="field-admin-name" wire:model="admin_name" class="input" maxlength="255">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div>
                    <label for="field-admin-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Admin Login Email <span class="text-rose-500">*</span></label>
                    <input type="email" id="field-admin-email" wire:model.live.debounce.400ms="admin_email" class="input" placeholder="admin@business.com" maxlength="255">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <p class="mt-1.5 text-[11px] text-gray-500 dark:text-gray-400">Must be different from the public email above.</p>
                </div>
            </div>

            
            <div
                x-data="avatarPreview()"
                x-on:avatar-preview.window="setUrl($event.detail.url)"
                x-on:avatar-cleared.window="clear()"
                class="flex items-center gap-5"
            >
                <div class="relative shrink-0 w-20 h-20">
                    <img
                        :src="previewUrl || '<?php echo e($this->avatarPreviewUrl() ?? ''); ?>'"
                        :class="(previewUrl || <?php echo e($this->avatarPreviewUrl() ? 'true' : 'false'); ?>) ? 'block' : 'hidden'"
                        class="w-20 h-20 rounded-full object-cover border-2 border-primary-500 shadow-md"
                        alt="Admin photo"
                        loading="eager"
                        decoding="async"
                    >
                    <div
                        :class="(previewUrl || <?php echo e($this->avatarPreviewUrl() ? 'true' : 'false'); ?>) ? 'hidden' : 'flex'"
                        class="w-20 h-20 rounded-full border-2 border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 items-center justify-center text-gray-400"
                    >
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>

                    <div
                        wire:loading.flex
                        wire:target="admin_avatar"
                        class="absolute inset-0 rounded-full bg-black/55 backdrop-blur-[2px] items-center justify-center z-10 pointer-events-none"
                        aria-hidden="true"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white animate-spin motion-reduce:animate-none" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>

                    <div
                        x-data="imageCropper({
                            wireProperty: 'admin_avatar',
                            aspect: 1,
                            title: 'Crop admin photo',
                            description: 'Square crop works best',
                            previewEvent: 'avatar-preview',
                        })"
                        x-init="init()"
                        class="absolute bottom-0 right-0 z-20"
                    >
                        <label class="block bg-primary-600 hover:bg-primary-500 text-white rounded-full p-2 cursor-pointer shadow-lg transition-colors focus-within:ring-2 focus-within:ring-primary-600/50 active:scale-95"
                               aria-label="Upload admin photo">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <input type="file" x-ref="input" x-on:change="pick($event)" accept="image/jpeg,image/png,image/webp" class="hidden">
                        </label>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Admin Photo <span class="text-xs font-normal text-gray-400">(optional)</span></p>

                    <div class="flex items-center gap-2 flex-wrap">
                        <button
                            type="button"
                            wire:click="removeAdminAvatar"
                            wire:confirm="Remove the photo?"
                            :class="previewUrl ? 'hidden' : (<?php echo e($this->avatarPreviewUrl() ? 'true' : 'false'); ?> ? 'inline-flex' : 'hidden')"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition"
                        >
                            Remove photo
                        </button>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($admin_avatar_path): ?>
                            <button
                                type="button"
                                wire:click="removeAdminAvatar"
                                wire:confirm="Remove the photo?"
                                :class="previewUrl ? 'hidden' : 'inline-flex'"
                                class="inline-flex items-center gap-1 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 px-3 py-2 text-xs font-semibold hover:border-rose-400 hover:text-rose-600 transition"
                            >
                                Remove photo
                            </button>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['admin_avatar'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
            </div>

            
            <div x-data="{ show: false, score: 0, pwd: '' }"
                 x-on:password-generated.window="pwd = $event.detail.password; score = window.pwStrength(pwd)">
                <label for="field-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password <span class="text-rose-500">*</span></label>
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <input :type="show ? 'text' : 'password'" id="field-password" wire:model="password"
                               @input="pwd = $event.target.value; score = window.pwStrength(pwd)"
                               class="input pr-10" placeholder="Min. 8 characters">
                        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 active:scale-95 transition-transform" :aria-label="show ? 'Hide password' : 'Show password'">
                            <svg :class="show ? 'hidden' : 'block'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg :class="show ? 'block' : 'hidden'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a10.025 10.025 0 012.132-3.592m3.16-2.11A9.958 9.958 0 0112 5c4.478 0 8.268 2.943 9.542 7a9.958 9.958 0 01-4.132 5.411M15 12a3 3 0 11-6 0 3 3 0 016 0z M3 3l18 18"/></svg>
                        </button>
                    </div>
                    <button type="button" wire:click="generatePassword" class="px-3 py-2 rounded-xl bg-gray-100 dark:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition active:scale-95 whitespace-nowrap">
                        Generate
                    </button>
                </div>

                <div class="mt-1.5 flex items-center gap-2" :class="pwd.length > 0 ? 'flex' : 'hidden'">
                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-300"
                             :class="{ 'bg-rose-500': score <= 1, 'bg-amber-500': score === 2 || score === 3, 'bg-emerald-500': score >= 4 }"
                             :style="`width: ${(score / 5) * 100}%`"></div>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 w-16 text-right"
                          x-text="['Too weak','Weak','Fair','Good','Strong','Excellent'][score]"></span>
                </div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div>
                <label for="field-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password <span class="text-rose-500">*</span></label>
                <input type="password" id="field-password-confirmation" wire:model="password_confirmation" class="input">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password_confirmation'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <p class="mt-1 text-xs text-rose-500"><?php echo e($message); ?></p> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="flex justify-between pt-2 border-t border-gray-200 dark:border-gray-700">
                <button type="button" wire:click="prevStep" class="btn-secondary active:scale-95 transition-transform">← Back</button>
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="relative px-8 py-3 rounded-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-70 text-white text-sm font-semibold shadow-lg shadow-emerald-500/20 transition focus-visible:ring-2 focus-visible:ring-emerald-500/50 active:scale-95">
                    <span wire:loading.remove wire:target="save">Complete Registration &amp; Save</span>
                    <span wire:loading wire:target="save" class="inline-flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Creating…
                    </span>
                </button>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showSuccessModal): ?>
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
             role="dialog" aria-modal="true" aria-labelledby="success-modal-title">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-8 max-w-md w-full shadow-2xl"
                 x-data="{ copiedAll: false, email: <?php echo \Illuminate\Support\Js::from($createdAdminEmail)->toHtml() ?>, password: <?php echo \Illuminate\Support\Js::from($createdAdminPassword)->toHtml() ?> }">
                <div class="text-center">
                    <div class="mx-auto w-14 h-14 rounded-full bg-emerald-100 dark:bg-emerald-500/20 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <h3 id="success-modal-title" class="text-xl font-bold text-gray-900 dark:text-white mb-1"><?php echo e($createdTenantName ?? 'Business'); ?> is live!</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-6">Credentials below — copy them if you need a manual fallback.</p>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($welcomeEmailSent): ?>
                        <div class="mb-4 flex items-center gap-2.5 rounded-xl border border-emerald-200/70 dark:border-emerald-500/30 bg-emerald-50/60 dark:bg-emerald-500/[0.06] px-3 py-2.5 text-xs text-emerald-800 dark:text-emerald-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span class="leading-relaxed text-left">Welcome email sent to <strong class="font-semibold"><?php echo e($createdAdminEmail); ?></strong> with login details.</span>
                        </div>
                    <?php else: ?>
                        <div class="mb-4 flex items-center gap-2.5 rounded-xl border border-amber-200/70 dark:border-amber-500/30 bg-amber-50/60 dark:bg-amber-500/[0.06] px-3 py-2.5 text-xs text-amber-800 dark:text-amber-300">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                            <span class="leading-relaxed text-left">Welcome email <strong class="font-semibold">could not be delivered</strong>. Share the credentials below manually.</span>
                        </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 text-left mb-4 space-y-3">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Admin email</p>
                            <p class="text-sm font-mono text-gray-900 dark:text-white break-all"><?php echo e($createdAdminEmail); ?></p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Password</p>
                            <p class="text-sm font-mono text-gray-900 dark:text-white break-all"><?php echo e($createdAdminPassword); ?></p>
                        </div>
                    </div>

                    <button type="button"
                            @click="navigator.clipboard.writeText(`Email: ${email}\nPassword: ${password}`); copiedAll = true; setTimeout(() => copiedAll = false, 2000)"
                            class="w-full mb-3 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-sm font-semibold text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                        <span :class="copiedAll ? 'hidden' : 'inline'">Copy credentials</span>
                        <span :class="copiedAll ? 'inline-flex items-center gap-1' : 'hidden'">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Copied to clipboard
                        </span>
                    </button>

                    <div class="flex flex-col sm:flex-row gap-2">
                        <button type="button" wire:click="createAnother" class="flex-1 px-6 py-3 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                            Add another
                        </button>
                        <button type="button" wire:click="goToTenantList" class="flex-1 px-6 py-3 rounded-full bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-primary-500/50 active:scale-95">
                            Go to Tenants List
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showAddCategoryModal): ?>
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 p-4"
             x-on:keydown.escape.window="$wire.closeAddCategoryModal()"
             @click.self="$wire.closeAddCategoryModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Marker Category</h3>
                    <button type="button" wire:click="closeAddCategoryModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key (slug)</label>
                        <input type="text" wire:model="newCategoryKey" class="input" placeholder="e.g. restaurant" maxlength="50">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryKey'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                        <input type="text" wire:model="newCategoryLabel" class="input" placeholder="Restaurant" maxlength="100">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryLabel'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                        <input type="color" wire:model="newCategoryColor" class="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryColor'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon (SVG)</label>
                        <input type="file" wire:model="newCategoryIcon" accept=".svg" class="input">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newCategoryIcon'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeAddCategoryModal" class="btn-secondary active:scale-95 transition-transform">Cancel</button>
                    <button type="button" wire:click="saveNewCategory" wire:loading.attr="disabled" wire:target="saveNewCategory" class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="saveNewCategory">Add Category</span>
                        <span wire:loading wire:target="saveNewCategory" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Adding…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showNewTenantTypeModal): ?>
        <div class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
             x-on:keydown.escape.window="$wire.closeNewTenantTypeModal()"
             @click.self="$wire.closeNewTenantTypeModal()">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6 border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg text-primary-600 dark:text-primary-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">Add Business Type</h3>
                    </div>
                    <button type="button" wire:click="closeNewTenantTypeModal"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 rounded-lg p-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50"
                            aria-label="Close">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mb-4">
                    Create a new business type. It will be available platform-wide for all tenants.
                </p>

                <div class="space-y-4">
                    <div>
                        <label for="field-new-tenant-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type Name <span class="text-rose-500">*</span></label>
                        <input type="text" id="field-new-tenant-type" wire:model="newTenantTypeName"
                               wire:keydown.enter.prevent="createTenantType" class="input"
                               placeholder="e.g. Beach Resort" autofocus maxlength="255">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newTenantTypeName'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>

                    <div>
                        <label for="field-new-tenant-type-desc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Description <span class="text-gray-400 dark:text-gray-500 font-normal">(Optional)</span>
                        </label>
                        <textarea id="field-new-tenant-type-desc" wire:model="newTenantTypeDescription" rows="3" class="textarea" placeholder="Briefly describe this type" maxlength="1000"></textarea>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['newTenantTypeDescription'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-rose-500 dark:text-rose-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" wire:click="closeNewTenantTypeModal" class="btn-secondary active:scale-95 transition-transform">Cancel</button>
                    <button type="button" wire:click="createTenantType" wire:loading.attr="disabled" wire:target="createTenantType"
                            class="btn-primary active:scale-95 transition-transform inline-flex items-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                        <span wire:loading.remove wire:target="createTenantType">Create Type</span>
                        <span wire:loading wire:target="createTenantType" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4 motion-reduce:animate-none" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Creating…
                        </span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <?php if (isset($component)) { $__componentOriginalb992f09e6b42df8ffd0c7230daf18927 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.image-crop-modal','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('image-crop-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $attributes = $__attributesOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__attributesOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927)): ?>
<?php $component = $__componentOriginalb992f09e6b42df8ffd0c7230daf18927; ?>
<?php unset($__componentOriginalb992f09e6b42df8ffd0c7230daf18927); ?>
<?php endif; ?>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework\views/livewire/views/a0592b6f.blade.php ENDPATH**/ ?>