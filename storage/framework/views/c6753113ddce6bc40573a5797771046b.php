<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\SiteSetting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
?>

<div class="p-6 max-w-5xl mx-auto space-y-8 text-white">
    <h1 class="text-3xl font-bold">About Page Editor</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-green-300 text-sm">
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="save" class="space-y-8">
        
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Hero Section</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Heading</label>
                    <input type="text" wire:model="heroHeading" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Subheading</label>
                    <input type="text" wire:model="heroSubheading" class="input-glass text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Description</label>
                    <textarea wire:model="heroDescription" rows="2" class="input-glass text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Background</label>
                    <?php $url = $this->previewUrl('heroImage', $existingHeroImage); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($url): ?> <img src="<?php echo e($url); ?>" class="w-full h-32 object-cover rounded-lg mb-2"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="heroImage" class="input-glass text-sm">
                </div>
            </div>
        </div>

        
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Story Section</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Story Heading</label>
                    <input type="text" wire:model="storyHeading" class="input-glass text-sm">
                </div>
                <div></div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Story Text 1</label>
                    <textarea wire:model="storyText1" rows="4" class="input-glass text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Story Text 2</label>
                    <textarea wire:model="storyText2" rows="4" class="input-glass text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Story Image 1</label>
                    <?php $url = $this->previewUrl('storyImage1', $existingStoryImage1); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($url): ?> <img src="<?php echo e($url); ?>" class="w-full h-32 object-cover rounded-lg mb-2"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="storyImage1" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Story Image 2</label>
                    <?php $url = $this->previewUrl('storyImage2', $existingStoryImage2); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($url): ?> <img src="<?php echo e($url); ?>" class="w-full h-32 object-cover rounded-lg mb-2"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="storyImage2" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Story Image 3</label>
                    <?php $url = $this->previewUrl('storyImage3', $existingStoryImage3); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($url): ?> <img src="<?php echo e($url); ?>" class="w-full h-32 object-cover rounded-lg mb-2"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="storyImage3" class="input-glass text-sm">
                </div>
            </div>
        </div>

        
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Highlights (linked to Businesses)</h2>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [1,2,3]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $tenantIdProp = "highlight{$n}TenantId";
                    $titleProp    = "highlight{$n}Title";
                    $textProp     = "highlight{$n}Text";
                    $imgProp      = "highlight{$n}Image";
                    $existingProp = "existingHighlight{$n}Image";
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-white/10 pt-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Highlight <?php echo e($n); ?> – Tenant</label>
                        <select wire:model="<?php echo e($tenantIdProp); ?>" class="input-glass text-sm">
                            <option value="">-- Select a business --</option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $this->tenants; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                                <option value="<?php echo e($t->id); ?>"><?php echo e($t->name); ?></option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Override Title (optional)</label>
                        <input type="text" wire:model="<?php echo e($titleProp); ?>" class="input-glass text-sm" placeholder="Leave blank to use business name">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Override Text (optional)</label>
                        <textarea wire:model="<?php echo e($textProp); ?>" rows="3" class="input-glass text-sm" placeholder="Leave blank to use business description"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Override Image (optional)</label>
                        <?php $url = $this->previewUrl($imgProp, $this->{$existingProp}); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($url): ?> <img src="<?php echo e($url); ?>" class="w-full h-32 object-cover rounded-lg mb-2"> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <input type="file" wire:model="<?php echo e($imgProp); ?>" class="input-glass text-sm">
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>

        
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Call to Action</h2>
            <div class="grid grid-cols-1 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">CTA Heading</label>
                    <input type="text" wire:model="ctaHeading" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">CTA Text</label>
                    <textarea wire:model="ctaText" rows="2" class="input-glass text-sm"></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="bg-brand-600 hover:bg-brand-500 text-white py-3 px-8 rounded-xl font-semibold transition">
            Save All Changes
        </button>
    </form>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/1a722734.blade.php ENDPATH**/ ?>