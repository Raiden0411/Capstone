<?php
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
?>

<div class="p-6 max-w-5xl mx-auto space-y-8 text-white">
    <h1 class="text-3xl font-bold">Homepage Editor</h1>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session()->has('message')): ?>
        <div class="p-4 bg-green-500/20 border border-green-500 rounded-lg text-green-300 text-sm">
            <?php echo e(session('message')); ?>

        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form wire:submit="save" class="space-y-8">
        
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Hero Section Images</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div>
                    <label class="block text-sm font-medium mb-2">Hero Background</label>
                    <?php $bgPreview = $this->getImagePreviewUrl('heroBackgroundImage', $existingHeroBg); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($bgPreview): ?>
                        <img src="<?php echo e($bgPreview); ?>" class="w-full h-40 object-cover rounded-lg mb-3">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="heroBackgroundImage" class="input-glass text-sm">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['heroBackgroundImage'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-400 text-xs"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

                
                <div>
                    <label class="block text-sm font-medium mb-2">Side Image 1 (Nature)</label>
                    <?php $side1Preview = $this->getImagePreviewUrl('heroSideImage1', $existingSide1); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($side1Preview): ?>
                        <img src="<?php echo e($side1Preview); ?>" class="w-full h-40 object-cover rounded-lg mb-3">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="heroSideImage1" class="input-glass text-sm">
                </div>

                
                <div>
                    <label class="block text-sm font-medium mb-2">Side Image 2 (The Sanctuary)</label>
                    <?php $side2Preview = $this->getImagePreviewUrl('heroSideImage2', $existingSide2); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($side2Preview): ?>
                        <img src="<?php echo e($side2Preview); ?>" class="w-full h-40 object-cover rounded-lg mb-3">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="heroSideImage2" class="input-glass text-sm">
                </div>

                
                <div>
                    <label class="block text-sm font-medium mb-2">Side Image 3 (Culture)</label>
                    <?php $side3Preview = $this->getImagePreviewUrl('heroSideImage3', $existingSide3); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($side3Preview): ?>
                        <img src="<?php echo e($side3Preview); ?>" class="w-full h-40 object-cover rounded-lg mb-3">
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <input type="file" wire:model="heroSideImage3" class="input-glass text-sm">
                </div>
            </div>
        </div>

        
        <div class="glass-card p-6 space-y-6">
            <h2 class="text-xl font-semibold">Text Content</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Title</label>
                    <input type="text" wire:model="heroTitle" class="input-glass text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Hero Subtitle</label>
                    <input type="text" wire:model="heroSubtitle" class="input-glass text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Hero Description</label>
                    <textarea wire:model="heroDescription" rows="3" class="input-glass text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Discover Title</label>
                    <input type="text" wire:model="discoverTitle" class="input-glass text-sm">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Discover Description</label>
                    <textarea wire:model="discoverDescription" rows="4" class="input-glass text-sm"></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="bg-brand-600 hover:bg-brand-500 text-white py-3 px-8 rounded-xl font-semibold transition">
            Save Changes
        </button>
    </form>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/1c694f93.blade.php ENDPATH**/ ?>