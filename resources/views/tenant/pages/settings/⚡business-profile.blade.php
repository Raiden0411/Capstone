<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Tenant;
use App\Models\TenantSetting;
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

    // Gallery
    public $businessPhotos = [];
    public $newBusinessPhotos = [];

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

        $setting = TenantSetting::where('tenant_id', Auth::user()->tenant_id)
            ->where('key', 'business_gallery')
            ->first();
        $this->businessPhotos = $setting ? $setting->value : [];
    }

    public function updatedName($value)
    {
        $this->slug = Str::slug($value);
    }

    public function removeNewBusinessPhoto($index)
    {
        unset($this->newBusinessPhotos[$index]);
        $this->newBusinessPhotos = array_values($this->newBusinessPhotos);
    }

    public function deleteBusinessPhoto($path)
    {
        $this->businessPhotos = array_values(array_filter($this->businessPhotos, fn($p) => $p !== $path));
        Storage::disk('public')->delete($path);
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'business_gallery'],
            ['value' => $this->businessPhotos]
        );
    }

    public function save()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:tenants,slug,' . $this->tenant->id,
            'newBusinessPhotos.*' => 'nullable|image|max:5120',
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

        // Handle new business photos
        foreach ($this->newBusinessPhotos as $photo) {
            $path = $photo->store('business-gallery', 'public');
            $this->businessPhotos[] = $path;
        }
        TenantSetting::updateOrCreate(
            ['tenant_id' => Auth::user()->tenant_id, 'key' => 'business_gallery'],
            ['value' => $this->businessPhotos]
        );

        $this->tenant->update($data);

        session()->flash('message', 'Business profile updated successfully.');
        return redirect()->route('tenant.settings.index');
    }
};
?>

<div class="p-6 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-slate-800 mb-6">Business Profile</h1>

    @if (session()->has('message'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Left Column: Form Fields --}}
        <div class="space-y-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Basic Information</h2>
                
                <div class="space-y-4">
                    {{-- Logo --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Business Logo</label>
                        <div class="flex items-center gap-4">
                            @if($logo)
                                <img src="{{ $logo->temporaryUrl() }}" class="h-16 w-16 object-cover rounded-lg border">
                            @elseif($tenant->logo)
                                <img src="{{ asset('storage/' . $tenant->logo) }}" class="h-16 w-16 object-cover rounded-lg border">
                            @else
                                <div class="h-16 w-16 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400 border">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                </div>
                            @endif
                            <div>
                                <input type="file" wire:model="logo" accept="image/*" class="text-sm">
                                @error('logo') <span class="text-red-500 text-xs block mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Name & Slug --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Business Name *</label>
                            <input type="text" wire:model.live="name" class="w-full rounded-lg border-slate-300">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Public URL Slug</label>
                            <input type="text" wire:model="slug" class="w-full rounded-lg border-slate-300 bg-slate-50" readonly>
                            <p class="text-xs text-slate-400 mt-1">Your public page: /business/{{ $slug }}</p>
                        </div>
                    </div>

                    {{-- Contact Info --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Contact Number</label>
                            <input type="text" wire:model="contact_number" class="w-full rounded-lg border-slate-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Public Email</label>
                            <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300">
                        </div>
                    </div>

                    {{-- Address --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Address</label>
                        <textarea wire:model="address" rows="2" class="w-full rounded-lg border-slate-300"></textarea>
                    </div>

                    {{-- Editable Coordinates --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Latitude</label>
                            <input type="text" wire:model.live="latitude" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                            @error('latitude') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Longitude</label>
                            <input type="text" wire:model.live="longitude" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                            @error('longitude') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Business Gallery --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b pb-2">Business Gallery</h2>
                <p class="text-sm text-slate-500 mb-4">Upload photos of your tourist spot to showcase on your public profile.</p>
                <input type="file" wire:model="newBusinessPhotos" multiple accept="image/*" class="w-full mb-3">
                @error('newBusinessPhotos.*') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                @if(count($newBusinessPhotos) > 0)
                    <div class="grid grid-cols-3 gap-3 mt-3">
                        @foreach($newBusinessPhotos as $index => $photo)
                            <div class="relative">
                                <img src="{{ $photo->temporaryUrl() }}" class="w-full h-24 object-cover rounded-lg">
                                <button type="button" wire:click="removeNewBusinessPhoto({{ $index }})" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1">&times;</button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="grid grid-cols-3 gap-3 mt-5">
                    @foreach($businessPhotos as $path)
                        <div class="relative group">
                            <img src="{{ Storage::url($path) }}" class="w-full h-24 object-cover rounded-lg border">
                            <button type="button" wire:click="deleteBusinessPhoto('{{ $path }}')" class="absolute top-1 right-1 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition">&times;</button>
                        </div>
                    @endforeach
                </div>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-md transition flex items-center justify-center gap-2">
                Save Changes
            </button>
        </div>

        {{-- Right Column: Interactive Map --}}
        <div class="h-fit sticky top-6">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-2">
                <x-location-map :readonly="false" height="500px" />
            </div>
        </div>
    </form>
</div>