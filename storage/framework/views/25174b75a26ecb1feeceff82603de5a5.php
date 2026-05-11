<?php
use Livewire\Component;
?>

<div class="relative z-10">
    
    <section class="relative w-full h-[70vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=2070"
                 class="w-full h-full object-cover opacity-40 dark:opacity-30 scale-110" alt="Victorias Forest">
            <div class="absolute inset-0 bg-gradient-to-t from-[#071412] via-transparent to-[#071412]/60"></div>
        </div>

        <div class="relative z-10 p-8 md:p-12 glass-card !rounded-3xl max-w-3xl mx-4 text-center">
            <span class="text-brand-400 font-bold tracking-[0.2em] uppercase text-sm mb-4 block">Region VI | Negros Occidental</span>
            <h1 class="font-display text-5xl md:text-8xl font-black text-white mb-6 drop-shadow-xl">VICTORIAS</h1>
            <p class="text-white/90 text-lg md:text-xl font-light leading-relaxed">
                Step into the "Sweet City of the North"—a harmonious blend of industrial heritage, spiritual art, and the raw beauty of the Northern Negros Natural Park.
            </p>
        </div>
    </section>

    
    <div class="max-w-6xl mx-auto px-6 py-24 grid md:grid-cols-2 gap-16 items-start">
        <div class="space-y-8">
            <div class="inline-flex items-center gap-2 text-brand-600 dark:text-brand-400 font-bold text-sm">
                <div class="w-8 h-[2px] bg-brand-600 dark:bg-brand-400"></div>
                THE STORY OF THE CITY
            </div>
            <h2 class="font-display text-4xl font-bold leading-tight text-gray-900 dark:text-white">Where Industry Meets the <span class="text-brand-600 dark:text-brand-400 italic">Wilderness</span></h2>

            <div class="space-y-4 text-gray-600 dark:text-white/60 leading-relaxed text-lg">
                <p>
                    Victorias City is widely recognized as the home of the <strong class="text-gray-900 dark:text-white">Victorias Milling Company (VMC)</strong>, one of the largest integrated sugar mills in the world. This industrial giant shaped the city's economy, yet the people have preserved the ecological soul of the land.
                </p>
                <p>
                    To the east, the city rises into the foothills of North Negros, offering a sanctuary for biodiversity. It is a place where you can witness the milling of sugar in the morning and hike through primary rainforests by the afternoon.
                </p>
            </div>

            <ul class="grid grid-cols-1 gap-4 pt-6">
                <li class="flex items-start gap-3">
                    <div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div>
                    <p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Eco-Tourism Hub:</strong> Gateway to the 7 Falls of Gawahon.</p>
                </li>
                <li class="flex items-start gap-3">
                    <div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div>
                    <p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Artistic Landmark:</strong> Home to the iconic "Angry Christ" mural.</p>
                </li>
                <li class="flex items-start gap-3">
                    <div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div>
                    <p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Sustainable Farming:</strong> Leader in organic and integrated agriculture.</p>
                </li>
            </ul>
        </div>

        
        <div class="relative grid grid-cols-2 gap-4">
            <div class="space-y-4 pt-12">
                <img src="https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?q=80&w=600" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Forest Detail">
                <img src="https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=600" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Mountain View">
            </div>
            <div class="space-y-4">
                <img src="https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Greenery">
                <div class="bg-brand-700 dark:bg-brand-600 h-64 rounded-2xl flex items-center justify-center p-6 text-white text-center">
                    <p class="font-medium italic">"Nature is the heart of Victorias, sugar is its lifeblood."</p>
                </div>
            </div>
        </div>
    </div>

    
    <div class="bg-gray-50 dark:bg-white/5 py-24 text-gray-900 dark:text-white font-sans">
        <div class="max-w-7xl mx-auto px-6">

            
            <div class="flex flex-col md:flex-row gap-16 items-center mb-32">
                <div class="w-full md:w-3/5 relative">
                    <div class="absolute -top-4 -left-4 w-24 h-24 bg-brand-200/50 dark:bg-brand-500/10 -z-10 rounded-full blur-2xl"></div>
                    <img src="https://images.unsplash.com/photo-1433086966358-54859d0ed716?q=80&w=800"
                         class="w-full h-[500px] object-cover rounded-2xl shadow-2xl grayscale hover:grayscale-0 transition-all duration-700"
                         alt="Gawahon">
                </div>
                <div class="w-full md:w-2/5">
                    <span class="text-brand-600 dark:text-brand-400 font-bold text-sm tracking-widest uppercase italic">01. Natural Wonder</span>
                    <h3 class="font-display text-4xl font-black mt-4 mb-6 uppercase tracking-tight">Gawahon <br/> Eco-Park</h3>
                    <p class="text-gray-600 dark:text-white/60 leading-relaxed text-lg mb-8">
                        Discover the rhythm of seven waterfalls cascading through ancient rainforests. Gawahon isn't just a destination; it's the city's green lung, offering a refreshing breath of mountain air.
                    </p>
                    <div class="h-px w-12 bg-brand-500"></div>
                </div>
            </div>

            
            <div class="flex flex-col md:flex-row-reverse gap-16 items-center mb-32">
                <div class="w-full md:w-3/5 relative">
                    <div class="absolute -bottom-4 -right-4 w-32 h-32 bg-purple-200/50 dark:bg-purple-500/10 -z-10 rounded-full blur-3xl"></div>
                    <img src="https://images.unsplash.com/photo-1518005020951-eccb494ad742?q=80&w=800"
                         class="w-full h-[500px] object-cover rounded-2xl shadow-2xl"
                         alt="Angry Christ">
                </div>
                <div class="w-full md:w-2/5 text-right md:text-left">
                    <span class="text-purple-500 dark:text-purple-400 font-bold text-sm tracking-widest uppercase italic">02. Spiritual Art</span>
                    <h3 class="font-display text-4xl font-black mt-4 mb-6 uppercase tracking-tight">The Angry <br/> Christ Mural</h3>
                    <p class="text-gray-600 dark:text-white/60 leading-relaxed text-lg mb-8">
                        An avant-garde explosion of color. Located in the St. Joseph the Worker Chapel, this world-famous mosaic represents the fiery, passionate faith of the Victorias community.
                    </p>
                    <div class="h-px w-12 bg-purple-500 ml-auto md:ml-0"></div>
                </div>
            </div>

            
            <div class="flex flex-col md:flex-row gap-16 items-center">
                <div class="w-full md:w-3/5">
                    <img src="https://images.unsplash.com/photo-1592388792816-621e508de543?q=80&w=800"
                         class="w-full h-[500px] object-cover rounded-2xl shadow-2xl grayscale hover:grayscale-0 transition-all duration-700"
                         alt="VMC">
                </div>
                <div class="w-full md:w-2/5">
                    <span class="text-brand-600 dark:text-brand-400 font-bold text-sm tracking-widest uppercase italic">03. Industrial Heritage</span>
                    <h3 class="font-display text-4xl font-black mt-4 mb-6 uppercase tracking-tight">The VMC <br/> Kingdom</h3>
                    <p class="text-gray-600 dark:text-white/60 leading-relaxed text-lg mb-8">
                        The heart of the world's sugar production. Here, vintage steam locomotives and massive mills sit alongside a tranquil bird sanctuary, proving that progress and nature can coexist.
                    </p>
                    <div class="h-px w-12 bg-brand-500"></div>
                </div>
            </div>

        </div>
    </div>

    
    <div class="py-24 px-6">
        <div class="max-w-4xl mx-auto rounded-[3rem] bg-gradient-to-br from-brand-900 to-[#062c1e] p-12 text-center shadow-2xl relative overflow-hidden group">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>
            <div class="relative z-10">
                <h2 class="font-display text-3xl md:text-5xl font-bold text-white mb-6 tracking-tight">
                    Come and enjoy the wonderful city of <span class="text-brand-400">Victorias</span>
                </h2>
                <p class="text-white/70 mb-10 text-lg max-w-xl mx-auto">
                    Experience the natural beauty and warm hospitality of our community.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="<?php echo e(route('explore.map')); ?>" wire:navigate class="px-8 py-4 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-2xl transition-all shadow-lg shadow-brand-500/20 hover:scale-105">
                        Explore Map
                    </a>
                    <a href="<?php echo e(route('about')); ?>" wire:navigate class="px-8 py-4 glass text-white font-bold rounded-2xl hover:bg-white/10 transition-all">
                        Learn More
                    </a>
                </div>
            </div>
        </div>
    </div>
</div><?php /**PATH C:\laragon\www\Capstone\storage\framework/views/livewire/views/771c1ec3.blade.php ENDPATH**/ ?>