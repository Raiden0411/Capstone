<?php

use Livewire\Component;
use App\Models\SiteSetting;
use App\Models\Tenant;

new class extends Component {
    public function getSetting(string $key, string $default = ''): string
    {
        return SiteSetting::getValue($key, $default);
    }

    public function getImageUrl(?string $path, string $defaultUrl): string
    {
        return $path ? asset('storage/' . $path) : $defaultUrl;
    }

    public function defaultHeroImage(): string
    {
        return 'https://images.unsplash.com/photo-1448375240586-882707db888b?q=80&w=2070';
    }

    public function defaultStoryImage(int $n): string
    {
        $images = [
            1 => 'https://images.unsplash.com/photo-1542273917363-3b1817f69a2d?q=80&w=600',
            2 => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=600',
            3 => 'https://images.unsplash.com/photo-1502082553048-f009c37129b9?q=80&w=600',
        ];
        return $images[$n] ?? $images[1];
    }

    public function defaultHighlightImage(int $n): string
    {
        $images = [
            1 => 'https://images.unsplash.com/photo-1433086966358-54859d0ed716?q=80&w=800',
            2 => 'https://images.unsplash.com/photo-1518005020951-eccb494ad742?q=80&w=800',
            3 => 'https://images.unsplash.com/photo-1592388792816-621e508de543?q=80&w=800',
        ];
        return $images[$n] ?? $images[1];
    }

    public function getHighlightData(int $n): array
    {
        $tenantId = SiteSetting::getValue("about_highlight{$n}_tenant_id");
        $overrideTitle = SiteSetting::getValue("about_highlight{$n}_title");
        $overrideText  = SiteSetting::getValue("about_highlight{$n}_text");
        $overrideImage = SiteSetting::getValue("about_highlight{$n}_image");

        $defaultTitle = $n === 1 ? 'Gawahon Eco-Park' : ($n === 2 ? 'The Angry Christ Mural' : 'The VMC Kingdom');
        $defaultText = '';

        if ($tenantId) {
            $tenant = Tenant::with('settings')->find($tenantId);
            if ($tenant) {
                $title = $overrideTitle ?: ($tenant->settings->where('key', 'spot_name')->first()?->value ?? $tenant->name);
                $text  = $overrideText ?: ($tenant->settings->where('key', 'spot_description')->first()?->value ?? '');
                $image = $overrideImage ?: ($tenant->settings->where('key', 'spot_cover')->first()?->value ?? null);
                $slug  = $tenant->slug;
            } else {
                $title = $overrideTitle ?: $defaultTitle;
                $text  = $overrideText ?: $defaultText;
                $image = $overrideImage;
                $slug  = null;
            }
        } else {
            $title = $overrideTitle ?: $defaultTitle;
            $text  = $overrideText ?: $defaultText;
            $image = $overrideImage;
            $slug  = null;
        }

        $imageUrl = $image
            ? asset('storage/' . $image)
            : $this->defaultHighlightImage($n);

        return [
            'title'    => $title,
            'text'     => $text,
            'imageUrl' => $imageUrl,
            'slug'     => $slug,
        ];
    }
};
?>

<div class="relative z-10">
    {{-- Hero --}}
    <section class="relative w-full h-[70vh] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_hero_image'), $this->defaultHeroImage()) }}"
                 class="w-full h-full object-cover opacity-40 dark:opacity-30 scale-110" alt="Victorias Forest">
            <div class="absolute inset-0 bg-gradient-to-t from-[#071412] via-transparent to-[#071412]/60"></div>
        </div>

        <div class="relative z-10 p-8 md:p-12 glass-card !rounded-3xl max-w-3xl mx-4 text-center">
            <span class="text-brand-400 font-bold tracking-[0.2em] uppercase text-sm mb-4 block">{{ $this->getSetting('about_hero_subheading', 'Region VI | Negros Occidental') }}</span>
            <h1 class="font-display text-5xl md:text-8xl font-black text-white mb-6 drop-shadow-xl">{{ $this->getSetting('about_hero_heading', 'VICTORIAS') }}</h1>
            <p class="text-white/90 text-lg md:text-xl font-light leading-relaxed">{{ $this->getSetting('about_hero_description') }}</p>
        </div>
    </section>

    {{-- Story Section --}}
    <div class="max-w-6xl mx-auto px-6 py-24 grid md:grid-cols-2 gap-16 items-start">
        <div class="space-y-8">
            <div class="inline-flex items-center gap-2 text-brand-600 dark:text-brand-400 font-bold text-sm">
                <div class="w-8 h-[2px] bg-brand-600 dark:bg-brand-400"></div>
                THE STORY OF THE CITY
            </div>
            <h2 class="font-display text-4xl font-bold leading-tight text-gray-900 dark:text-white">
                {{ $this->getSetting('about_story_heading', 'Where Industry Meets the Wilderness') }}
            </h2>
            <div class="space-y-4 text-gray-600 dark:text-white/60 leading-relaxed text-lg">
                <p>{{ $this->getSetting('about_story_text1') }}</p>
                <p>{{ $this->getSetting('about_story_text2') }}</p>
            </div>
            <ul class="grid grid-cols-1 gap-4 pt-6">
                <li class="flex items-start gap-3"><div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div><p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Eco-Tourism Hub:</strong> Gateway to the 7 Falls of Gawahon.</p></li>
                <li class="flex items-start gap-3"><div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div><p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Artistic Landmark:</strong> Home to the iconic "Angry Christ" mural.</p></li>
                <li class="flex items-start gap-3"><div class="p-1 bg-brand-100 dark:bg-brand-500/20 rounded-full text-brand-700 dark:text-brand-400">✓</div><p class="text-gray-700 dark:text-white/70"><strong class="text-gray-900 dark:text-white">Sustainable Farming:</strong> Leader in organic and integrated agriculture.</p></li>
            </ul>
        </div>

        <div class="relative grid grid-cols-2 gap-4">
            <div class="space-y-4 pt-12">
                <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_story_image1'), $this->defaultStoryImage(1)) }}" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Forest Detail">
                <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_story_image2'), $this->defaultStoryImage(2)) }}" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Mountain View">
            </div>
            <div class="space-y-4">
                <img src="{{ $this->getImageUrl(SiteSetting::getValue('about_story_image3'), $this->defaultStoryImage(3)) }}" class="rounded-2xl shadow-lg border-4 border-white dark:border-white/10" alt="Greenery">
                <div class="bg-brand-700 dark:bg-brand-600 h-64 rounded-2xl flex items-center justify-center p-6 text-white text-center">
                    <p class="font-medium italic">"Nature is the heart of Victorias, sugar is its lifeblood."</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Highlights (dynamic from linked tenants) --}}
    <div class="bg-gray-50 dark:bg-white/5 py-24 text-gray-900 dark:text-white font-sans">
        <div class="max-w-7xl mx-auto px-6">
            @foreach([1,2,3] as $n)
                @php
                    $data = $this->getHighlightData($n);
                    $title    = $data['title'];
                    $text     = $data['text'];
                    $imageUrl = $data['imageUrl'];
                    $slug     = $data['slug'];
                    $reverse  = $n === 2;
                @endphp

                <div class="flex flex-col {{ $reverse ? 'md:flex-row-reverse' : 'md:flex-row' }} gap-16 items-center mb-32">
                    <div class="w-full md:w-3/5 relative">
                        <div class="absolute -top-4 -left-4 w-24 h-24 bg-brand-200/50 dark:bg-brand-500/10 -z-10 rounded-full blur-2xl"></div>
                        <img src="{{ $imageUrl }}" class="w-full h-[500px] object-cover rounded-2xl shadow-2xl grayscale hover:grayscale-0 transition-all duration-700" alt="{{ $title }}">
                    </div>
                    <div class="w-full md:w-2/5 {{ $reverse ? 'text-right md:text-left' : '' }}">
                        <span class="text-brand-600 dark:text-brand-400 font-bold text-sm tracking-widest uppercase italic">0{{ $n }}. Natural Wonder</span>
                        <h3 class="font-display text-4xl font-black mt-4 mb-6 uppercase tracking-tight">{{ $title }}</h3>
                        @if($text)
                            <p class="text-gray-600 dark:text-white/60 leading-relaxed text-lg mb-8">{{ $text }}</p>
                        @endif
                        @if($slug)
                            <a href="{{ route('tenant.show', $slug) }}" wire:navigate class="inline-flex items-center gap-2 text-brand-600 dark:text-brand-400 font-semibold hover:underline">
                                Visit this place →
                            </a>
                        @endif
                        <div class="h-px w-12 bg-brand-500 {{ $reverse ? 'ml-auto md:ml-0' : '' }} mt-4"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- CTA --}}
    <div class="py-24 px-6">
        <div class="max-w-4xl mx-auto rounded-[3rem] bg-gradient-to-br from-brand-900 to-[#062c1e] p-12 text-center shadow-2xl relative overflow-hidden group">
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-brand-500/10 rounded-full blur-3xl"></div>
            <div class="relative z-10">
                <h2 class="font-display text-3xl md:text-5xl font-bold text-white mb-6 tracking-tight">
                    {{ $this->getSetting('about_cta_heading', 'Come and enjoy the wonderful city of Victorias') }}
                </h2>
                <p class="text-white/70 mb-10 text-lg max-w-xl mx-auto">{{ $this->getSetting('about_cta_text') }}</p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="{{ route('explore.map') }}" wire:navigate class="px-8 py-4 bg-brand-600 hover:bg-brand-500 text-white font-bold rounded-2xl transition-all shadow-lg shadow-brand-500/20 hover:scale-105">Explore Map</a>
                    <a href="{{ route('about') }}" wire:navigate class="px-8 py-4 glass text-white font-bold rounded-2xl hover:bg-white/10 transition-all">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</div>