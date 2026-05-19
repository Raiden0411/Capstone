<?php
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Business Profile</h1>
            <p class="text-white/60 mt-1">Update your public business information.</p>
        </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        
        <div class="space-y-6">
            
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
                
                <div class="space-y-4">
                    
                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-2">Business Logo</label>
                        <div class="flex items-center gap-4">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logo): ?>
                                <img src="<?php echo e($logo->temporaryUrl()); ?>" class="h-16 w-16 object-cover rounded-lg border border-white/10">
                            <?php elseif($tenant->logo): ?>
                                <img src="<?php echo e(asset('storage/'. $tenant->logo)); ?>" class="h-16 w-16 object-cover rounded-lg border border-white/10">
                            <?php else: ?>
                                <div class="h-16 w-16 rounded-lg bg-white/5 flex items-center justify-center text-white/30 border border-white/10">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <div>
                                <input type="file" wire:model="logo" accept="image/*" class="text-sm text-white/70 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-500/20 file:text-brand-300 hover:file:bg-brand-500/30 transition">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['logo'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-xs block mt-1"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Business Name *</label>
                            <input type="text" wire:model.live="name"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Public URL Slug</label>
                            <input type="text" wire:model="slug"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white/60 cursor-not-allowed"
                                   readonly>
                            <p class="text-xs text-white/40 mt-1">Your public page: /business/<?php echo e($slug); ?></p>
                        </div>
                    </div>

                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Contact Number</label>
                            <input type="text" wire:model="contact_number"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Public Email</label>
                            <input type="email" wire:model="email"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        </div>
                    </div>

                    
                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-1">Business Address</label>
                        <textarea wire:model="address" rows="2"
                                  class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"></textarea>
                    </div>

                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Latitude</label>
                            <input type="text" wire:model.live="latitude"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['latitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Longitude</label>
                            <input type="text" wire:model.live="longitude"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['longitude'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <button type="submit"
                    wire:loading.attr="disabled"
                    class="w-full bg-brand-600 hover:bg-brand-500 text-white font-bold py-3 px-6 rounded-xl shadow-lg shadow-brand-500/20 transition flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span wire:loading.remove>Save Changes</span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                    Saving...
                </span>
            </button>
        </div>

        
        <div class="h-fit sticky top-6">
            <div class="glass-card !rounded-xl p-2 overflow-hidden">
                <?php if (isset($component)) { $__componentOriginal16c0f022c2776f6d592c39df62bc5aa8 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal16c0f022c2776f6d592c39df62bc5aa8 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.location-map','data' => ['readonly' => false,'height' => '500px']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('location-map'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['readonly' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'height' => '500px']); ?>
<?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::processComponentKey($component); ?>

<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal16c0f022c2776f6d592c39df62bc5aa8)): ?>
<?php $attributes = $__attributesOriginal16c0f022c2776f6d592c39df62bc5aa8; ?>
<?php unset($__attributesOriginal16c0f022c2776f6d592c39df62bc5aa8); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal16c0f022c2776f6d592c39df62bc5aa8)): ?>
<?php $component = $__componentOriginal16c0f022c2776f6d592c39df62bc5aa8; ?>
<?php unset($__componentOriginal16c0f022c2776f6d592c39df62bc5aa8); ?>
<?php endif; ?>
            </div>
        </div>
    </form>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/db6a6493.blade.php ENDPATH**/ ?>