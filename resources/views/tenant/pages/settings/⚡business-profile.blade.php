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

new 
#[Layout('tenant.layouts.app')]
#[Title('Business Profile')]
class extends Component {
    use WithFileUploads;

    public Tenant $tenant;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    public $slug = '';
    
    #[Validate('nullable|string')]
    public $address = '';
    
    #[Validate('nullable|string|max:20')]
    public $contact_number = '';
    
    #[Validate('nullable|email|max:255')]
    public $email = '';
    
    #[Validate('nullable|image|max:2048')]
    public $logo;
    
    #[Validate('nullable|numeric')]
    public $latitude = 10.900977766937142;
    
    #[Validate('nullable|numeric')]
    public $longitude = 123.07055771888716;

    public function mount()
    {
        $this->tenant = Auth::user()->tenant;
        $this->name = $this->tenant->name;
        $this->slug = $this->tenant->slug;
        $this->address = $this->tenant->address;
        $this->contact_number = $this->tenant->contact_number;
        $this->email = $this->tenant->email;
        $this->latitude = $this->tenant->latitude ?? 10.900977766937142;
        $this->longitude = $this->tenant->longitude ?? 123.07055771888716;
    }

    public function updatedName($value)
    {
        $this->slug = Str::slug($value);
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $this->tenant->id,
        ]);

        $data = [
            'name' => $this->name,
            'slug' => $this->slug,
            'address' => $this->address,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];

        if ($this->logo) {
            $path = $this->logo->store('tenant-logos', 'public');
            $data['logo'] = $path;
        }

        $this->tenant->update($data);

        session()->flash('message', 'Business profile updated successfully.');
        return redirect()->route('tenant.settings.index');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white">Business Profile</h1>
            <p class="text-white/60 mt-1">Update your public business information.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
            <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Left Column: Form Fields --}}
        <div class="space-y-6">
            {{-- Basic Information --}}
            <div class="glass-card !rounded-xl p-5 sm:p-6">
                <h2 class="text-lg font-semibold text-white mb-4">Basic Information</h2>
                
                <div class="space-y-4">
                    {{-- Logo --}}
                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-2">Business Logo</label>
                        <div class="flex items-center gap-4">
                            @if($logo)
                                <img src="{{ $logo->temporaryUrl() }}" class="h-16 w-16 object-cover rounded-lg border border-white/10">
                            @elseif($tenant->logo)
                                <img src="{{ Storage::url($tenant->logo) }}" class="h-16 w-16 object-cover rounded-lg border border-white/10">
                            @else
                                <div class="h-16 w-16 rounded-lg bg-white/5 flex items-center justify-center text-white/30 border border-white/10">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                            <div>
                                <input type="file" wire:model="logo" accept="image/*" class="text-sm text-white/70 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand-500/20 file:text-brand-300 hover:file:bg-brand-500/30 transition">
                                @error('logo') <span class="text-red-400 text-xs block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Name & Slug --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Business Name *</label>
                            <input type="text" wire:model.live="name"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Public URL Slug</label>
                            <input type="text" wire:model="slug"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white/60 cursor-not-allowed"
                                   readonly>
                            <p class="text-xs text-white/40 mt-1">Your public page: /business/{{ $slug }}</p>
                        </div>
                    </div>

                    {{-- Contact Info --}}
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

                    {{-- Address --}}
                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-1">Business Address</label>
                        <textarea wire:model="address" rows="2"
                                  class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"></textarea>
                    </div>

                    {{-- Coordinates --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Latitude</label>
                            <input type="text" wire:model.live="latitude"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            @error('latitude') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Longitude</label>
                            <input type="text" wire:model.live="longitude"
                                   class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            @error('longitude') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
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

        {{-- Right Column: Map --}}
        <div class="h-fit sticky top-6">
            <div class="glass-card !rounded-xl p-2 overflow-hidden">
                <x-location-map :readonly="false" height="500px" />
            </div>
        </div>
    </form>
</div>