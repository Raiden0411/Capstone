<?php
use Livewire\Component;
use App\Models\SiteSetting;
use App\Models\Tenant;
?>

<div class="relative z-10">
    
    <section class="relative w-full h-[70vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="<?php echo e($this->getImageUrl(SiteSetting::getValue('about_hero_image'), $this->defaultHeroImage())); ?>"
                 class="w-full h-full object-cover opacity-40 dark:opacity-30 scale-110" alt="Victorias Forest">
            <div class="absolute inset-0 bg-gradient-to-t from-[#071412] via-transparent to-[#071412]/60"></div>
        </div>

        <div class="relative z-10 p-8 md:p-12 glass-card !rounded-3xl max-w-3xl mx-4 text-center">
            <span class="text-brand-400 font-bold tracking-[0.2em] uppercase text-sm mb-4 block"><?php echo e($this->getSetting('about_hero_subheading', 'Region VI | Negros Occidental')); ?></span>
            <h1 class="font-display text-5xl md:text-8xl font-black text-white mb-6 drop-shadow-xl"><?php echo e($this->getSetting('about_hero_heading', 'VICTORIAS')); ?></h1>
            <p class="text-white/90 text-lg md:text-xl font-light leading-relaxed"><?php echo e($this->getSetting('about_hero_description')); ?></p>
        </div>
    </section>

    
    <div class="max-w-6xl mx-auto px-6 py-24 grid md:grid-cols-2 gap-16 items-start">
        <div class="space-y-8">
            <div class="inline-flex items-center gap-2 text-brand-600 dark:text-brand-400 font-bold text-sm">
                <div class="w-8 h-[2px] bg-brand-600 dark:bg-brand-400"></div>
                THE STORY OF THE CITY
            </div>
            <h2 class="font-display text-4xl font-bold leading-tight text-gray-900 dark:text-white">
                <?php echo e($this->getSetting('about_story_heading', 'Where Industry Meets the Wilderness')); ?>

            </h2>
            <div class="space-y-4 text-gray-600 dark:text-white/60 leading-relaxed text-lg">
                <p><?php echo e($this->getSetting('about_story_text1')); ?></p>
                <p><?php echo e($this->getSetting('about_story_text2')); ?></p>
            </div>
            <ul class="grid grid-cols-1 gap-4 pt-6">
                <li class="flex items-start gap-3"><div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div><p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Eco-Tourism Hub:</strong> Gateway to the 7 Falls of Gawahon.</p></li>
                <li class="flex items-start gap-3"><div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div><p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Artistic Landmark:</strong> Home to the iconic "Angry Christ" mural.</p></li>
                <li class="flex items-start gap-3"><div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div><p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Sustainable Farming:</strong> Leader in organic and integrated agriculture.</p></li>
            </ul>
        </div>

        <div class="relative grid grid-cols-2 gap-4">
            <div class="space-y-4 pt-12">
                <img src="<?php echo e($this->getImageUrl(SiteSetting::getValue('about_story_image1'), $this->defaultStoryImage(1))); ?>" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Forest Detail">
                <img src="<?php echo e($this->getImageUrl(SiteSetting::getValue('about_story_image2'), $this->defaultStoryImage(2))); ?>" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Mountain View">
            </div>
            <div class="space-y-4">
                <img src="<?php echo e($this->getImageUrl(SiteSetting::getValue('about_story_image3'), $this->defaultStoryImage(3))); ?>" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Greenery">
                <div class="bg-brand-700 dark:bg-brand-600 h-64 rounded-2xl flex items-center justify-center p-6 text-white text-center">
                    <p class="font-medium italic">"Nature is the heart of Victorias, sugar is its lifeblood."</p>
                </div>
            </div>
        </div>
    </div>

    
    <div class="bg-gray-50 dark:bg-white/5 py-24 text-gray-900 dark:text-white font-sans">
        <div class="max-w-7xl mx-auto px-6">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = [1,2,3]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
                <?php
                    $data = $this->getHighlightData($n);
                    $title    = $data['title'];
                    $text     = $data['text'];
                    $imageUrl = $data['imageUrl'];
                    $slug     = $data['slug'];
                    $reverse  = $n === 2;
                ?>

                <div class="flex flex-col <?php echo e($reverse ? 'md:flex-row-reverse' : 'md:flex-row'); ?> gap-16 items-center mb-32">
                    <div class="w-full md:w-3/5 relative">
                        <div class="absolute -top-4 -left-4 w-24 h-24 bg-brand-200/50 dark:bg-brand-500/10 -z-10 rounded-full blur-2xl"></div>
                        <img src="<?php echo e($imageUrl); ?>" class="w-full h-[500px] object-cover rounded-2xl shadow-2xl grayscale hover:grayscale-0 transition-all duration-700" alt="<?php echo e($title); ?>">
                    </div>
                    <div class="w-full md:w-2/5 <?php echo e($reverse ? 'text-right md:text-left' : ''); ?>">
                        <span class="text-brand-600 dark:text-brand-400 font-bold text-sm tracking-widest uppercase italic">0<?php echo e($n); ?>. Natural Wonder</span>
                        <h3 class="font-display text-4xl font-black mt-4 mb-6 uppercase tracking-tight"><?php echo e($title); ?></h3>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($text): ?>
                            <p class="text-gray-600 dark:text-white/60 leading-relaxed text-lg mb-8"><?php echo e($text); ?></p>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($slug): ?>
                            <a href="<?php echo e(route('tenant.show', $slug)); ?>" wire:navigate class="inline-flex items-center gap-2 text-brand-600 dark:text-brand-400 font-semibold hover:underline">
                                Visit this place →
                            </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="h-px w-12 bg-brand-500 <?php echo e($reverse ? 'ml-auto md:ml-0' : ''); ?> mt-4"></div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>

    
    <div class="py-24 px-6">
        <div class="max-w-4xl mx-auto rounded-[3rem] bg-gradient-to-br from-brand-900 to-[#062c1e] p-12 text-center shadow-2xl relative overflow-hidden group">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>
            <div class="relative z-10">
                <h2 class="font-display text-3xl md:text-5xl font-bold text-white mb-6 tracking-tight">
                    <?php echo e($this->getSetting('about_cta_heading', 'Come and enjoy the wonderful city of Victorias')); ?>

                </h2>
                <p class="text-white/70 mb-10 text-lg max-w-xl mx-auto"><?php echo e($this->getSetting('about_cta_text')); ?></p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="px-8 py-4 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-2xl transition-all shadow-lg shadow-brand-500/20 hover:scale-105">Explore Map</a>
                    <a href="<?php echo e(route('about')); ?>" wire:navigate class="px-8 py-4 glass text-white font-bold rounded-2xl hover:bg-white/10 transition-all">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/771c1ec3.blade.php ENDPATH**/ ?>