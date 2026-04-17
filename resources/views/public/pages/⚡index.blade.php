<?php

use Livewire\Component;
use App\Models\Property;
use App\Models\Tenant;

new class extends Component {
    public $featuredProperties;
    public $previewTenant = null;
    public $isPreviewMode = false;

    public function mount()
    {
        $this->isPreviewMode = session()->has('preview_tenant_id');
        if ($this->isPreviewMode) {
            $this->previewTenant = Tenant::find(session('preview_tenant_id'));
        }
        $this->loadProperties();
    }

    public function loadProperties()
    {
        $query = Property::query()->where('is_active', true);
        
        if ($this->isPreviewMode) {
            $query->where('tenant_id', session('preview_tenant_id'));
        }
        
        $this->featuredProperties = $query->with('images', 'propertyType', 'tenant')
            ->latest()
            ->take(6)
            ->get();
    }

    // 移除 render() 方法，Livewire 会自动使用本文件中的 Blade 模板
};
?>

<div class="min-h-screen bg-slate-50">
    {{-- Preview Mode Banner --}}
    @if($isPreviewMode)
        <div class="bg-amber-50 border-b border-amber-200 py-3 px-4 text-center">
            <div class="max-w-7xl mx-auto flex items-center justify-between">
                <span class="text-amber-800">
                    <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    You are previewing the site as <strong>{{ $previewTenant->name ?? 'Tenant' }}</strong>. Content shown is what your customers would see.
                </span>
                <a href="{{ route('preview.exit') }}" class="px-4 py-1.5 bg-white border border-amber-300 text-amber-700 rounded-lg text-sm font-medium hover:bg-amber-50 transition">
                    Exit Preview
                </a>
            </div>
        </div>
    @endif

    {{-- Hero Section --}}
    <section class="bg-gradient-to-r from-lime-50 to-emerald-50 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-4xl md:text-5xl font-bold text-slate-800 mb-4">
                Find Your Perfect Stay
            </h1>
            <p class="text-lg text-slate-600 mb-8 max-w-2xl mx-auto">
                Discover beautiful properties from the best accommodations in the area.
            </p>
            <a href="{{ route('public.bookings') }}" class="inline-block bg-lime-600 hover:bg-lime-700 text-white font-semibold py-3 px-8 rounded-lg shadow-md transition">
                Book Now
            </a>
        </div>
    </section>

    {{-- Featured Properties --}}
    <section class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-800 mb-6">Featured Properties</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($featuredProperties as $property)
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden hover:shadow-md transition">
                        @if($property->images->isNotEmpty())
                            <img src="{{ asset('storage/' . $property->images->first()->image_path) }}" alt="{{ $property->name }}" class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-slate-200 flex items-center justify-center text-slate-400">
                                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        @endif
                        <div class="p-4">
                            <h3 class="font-semibold text-lg text-slate-800">{{ $property->name }}</h3>
                            <p class="text-sm text-slate-500">{{ $property->propertyType->name ?? 'Property' }} • {{ $property->tenant->name ?? '' }}</p>
                            <div class="flex items-center justify-between mt-3">
                                <span class="text-lime-600 font-bold text-lg">₱{{ number_format($property->price, 2) }}</span>
                                <span class="text-xs px-2 py-1 rounded-full {{ $property->status === 'available' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                                    {{ ucfirst($property->status) }}
                                </span>
                            </div>
                            <div class="mt-2 text-sm text-slate-500">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                Up to {{ $property->capacity }} persons
                            </div>
                            <a href="{{ route('destination.details', $property->id) }}" class="mt-4 block w-full text-center bg-lime-600 hover:bg-lime-700 text-white font-medium py-2 rounded-lg transition">
                                View Details
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12 text-slate-500">
                        No properties available at this time.
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="bg-white border-t border-slate-200 mt-12 py-6">
        <div class="max-w-7xl mx-auto px-4 text-center text-slate-500 text-sm">
            &copy; {{ date('Y') }} Capstone. All rights reserved.
        </div>
    </footer>
</div>