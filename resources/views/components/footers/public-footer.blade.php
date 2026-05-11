<?php

use Livewire\Component;

new class extends Component
{
    //
};
?>

<footer class="relative z-10 mt-auto w-full bg-[#020c08]/90 backdrop-blur-xl border-t border-white/10 pt-12 pb-8 px-4 sm:px-6 lg:px-8 font-sans text-white">
    <div class="max-w-7xl mx-auto">

        {{-- Top grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-10">

            {{-- Brand column --}}
            <div class="col-span-1 sm:col-span-2 lg:col-span-1">
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2 mb-4">
                    <div class="w-8 h-8 rounded-lg bg-brand-600 flex items-center justify-center text-white font-black text-sm">V</div>
                    <span class="font-display text-xl font-semibold tracking-tight text-white">Victorias</span>
                </a>
                <p class="text-sm text-white/50 leading-relaxed mb-5">
                    Discover the sweet city of the North — breathtaking nature, rich heritage, and warm Filipino hospitality.
                </p>
                {{-- Newsletter signup --}}
                <form class="flex gap-2" x-data="{ email: '' }">
                    <input type="email" x-model="email" placeholder="Your email"
                           class="flex-1 bg-white/5 border border-white/10 rounded-xl py-2.5 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 transition">
                    <button type="submit"
                            class="bg-brand-600 hover:bg-brand-500 text-white font-medium text-sm px-5 rounded-xl transition shadow-lg shadow-brand-500/20">
                        Join
                    </button>
                </form>
                <p class="text-xs text-white/30 mt-2">Stay updated with the latest spots and events.</p>
            </div>

            {{-- Quick Links --}}
            <div>
                <h4 class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Quick Links</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ route('home') }}" wire:navigate class="text-white/50 hover:text-brand-400 transition-colors">Home</a></li>
                    <li><a href="{{ route('about') }}" wire:navigate class="text-white/50 hover:text-brand-400 transition-colors">About Victorias</a></li>
                    <li><a href="{{ route('explore.map') }}" wire:navigate class="text-white/50 hover:text-brand-400 transition-colors">Explore Map</a></li>
                    <li><a href="{{ route('learnmore') }}" wire:navigate class="text-white/50 hover:text-brand-400 transition-colors">Places to Visit</a></li>
                </ul>
            </div>

            {{-- Information --}}
            <div>
                <h4 class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Information</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Tourist Spot Directory</a></li>
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Cultural Heritage</a></li>
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Travel Guidelines</a></li>
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Local Government</a></li>
                </ul>
            </div>

            {{-- Connect --}}
            <div>
                <h4 class="text-xs font-semibold text-white uppercase tracking-widest mb-4">Connect</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Contact Us</a></li>
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Facebook Page</a></li>
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Instagram</a></li>
                    <li><a href="#" class="text-white/50 hover:text-brand-400 transition-colors">Twitter / X</a></li>
                </ul>
                <div class="flex items-center gap-3 mt-5">
                    <a href="#" class="text-white/30 hover:text-brand-400 transition-colors" aria-label="Facebook">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M9 8H6v4h3v12h5V12h3.642L18 8h-4V6.333C14 5.378 14.192 5 15.115 5H18V0h-3.808C10.596 0 9 1.583 9 4.615V8z"/></svg>
                    </a>
                    <a href="#" class="text-white/30 hover:text-brand-400 transition-colors" aria-label="Instagram">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3.2"/><path d="M9 2h6a7 7 0 017 7v6a7 7 0 01-7 7H9a7 7 0 01-7-7V9a7 7 0 017-7zm0 2a5 5 0 00-5 5v6a5 5 0 005 5h6a5 5 0 005-5V9a5 5 0 00-5-5H9zm6.5 1.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/></svg>
                    </a>
                    <a href="#" class="text-white/30 hover:text-brand-400 transition-colors" aria-label="Twitter / X">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                    </a>
                    <a href="#" class="text-white/30 hover:text-brand-400 transition-colors" aria-label="YouTube">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="pt-6 mt-6 border-t border-white/10 flex flex-col sm:flex-row justify-between items-center gap-4">
            <p class="text-xs text-white/30">
                &copy; {{ date('Y') }} Victorias City Tourism. Negros Occidental, Philippines.
            </p>
            <div class="flex gap-6 text-xs text-white/30">
                <a href="#" class="hover:text-brand-400 transition-colors">Privacy Policy</a>
                <a href="#" class="hover:text-brand-400 transition-colors">Terms of Service</a>
                <a href="#" class="hover:text-brand-400 transition-colors">Accessibility</a>
            </div>
        </div>
    </div>
</footer>