<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

new 
#[Layout('superadmin.layouts.app')]
#[Title('Register New Tenant')]
class extends Component {
    
    #[Validate('required|min:3|max:255|unique:tenants,name')]
    public $name = '';
    #[Validate('required|string|max:255|unique:tenants,slug|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')]
    public $slug = '';
    #[Validate('required|integer|exists:type_of_tenants,id')]
    public $type_of_tenant_id = '';
    #[Validate('required|string|max:255')]
    public $address = '';
    #[Validate('required|email|max:255|unique:tenants,email|unique:users,email')]
    public $email = '';
    #[Validate('nullable|string|max:20|regex:/^[0-9\+\-\s\(\)]+$/')]
    public $contact_number = '';
    #[Validate('required|numeric|min:-90|max:90')]
    public $latitude = 10.900977766937142;
    #[Validate('required|numeric|min:-180|max:180')]
    public $longitude = 123.07055771888716;
    #[Validate('required|string|max:255')]
    public $admin_name = '';
    #[Validate('required|min:8')]
    public $password = '';

    #[Computed]
    public function tenantTypes() { return TypeOfTenant::all(); }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
            'slug.regex'   => 'Slug may only contain lowercase letters, numbers, and hyphens.',
        ];
    }

    protected function sanitize(): void
    {
        foreach (['name','address','contact_number','admin_name','email'] as $f) {
            if (is_string($this->$f)) $this->$f = trim(strip_tags($this->$f));
        }
        $this->slug = Str::slug($this->name);
    }

    public function updatedName($value) { $this->slug = Str::slug(trim($value)); }
    public function updated($property)
    {
        if (in_array($property, ['name','address','contact_number','admin_name','email'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function save()
    {
        $this->sanitize();
        $this->validate();

        DB::transaction(function () {
            $tenant = Tenant::create([
                'name'              => $this->name,
                'slug'              => $this->slug,
                'type_of_tenant_id' => $this->type_of_tenant_id,
                'address'           => $this->address,
                'email'             => $this->email,
                'contact_number'    => $this->contact_number,
                'latitude'          => $this->latitude,
                'longitude'         => $this->longitude,
                'is_active'         => true,
            ]);
            $user = User::create([
                'name'      => $this->admin_name,
                'email'     => $this->email,
                'password'  => Hash::make($this->password),
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
            $user->assignRole('admin');
        });

        session()->flash('message', 'Business & admin account successfully created!');
        return $this->redirectRoute('superadmin.tenants.index', navigate: true);
    }
};
?>

@push('styles')
<style>
    /* Fix invisible options in glass-style selects */
    select option {
        background: #1e293b;
        color: #e2e8f0;
    }
</style>
@endpush

<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8 space-y-6">
    {{-- Flash Message --}}
    @if(session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-400 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-white/10">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand-600 dark:text-brand-400 flex items-center gap-2 mb-2">
                <span class="w-1.5 h-1.5 rounded-full bg-brand-500 shadow-[0_0_8px_var(--color-brand-500)]"></span>
                Super Admin · Tenants
            </p>
            <h1 class="font-display text-3xl md:text-4xl font-bold text-gray-900 dark:text-white">Register New Business</h1>
        </div>
        <a href="{{ route('superadmin.tenants.index') }}" wire:navigate class="text-sm font-medium text-gray-500 dark:text-white/50 hover:text-brand-600 dark:hover:text-brand-400 transition-colors flex items-center gap-1">
            &larr; Back to tenants
        </a>
    </div>

    <form wire:submit="save" class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        {{-- Left Column: Form fields --}}
        <div class="space-y-6">
            {{-- Business Details --}}
            <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center gap-3">
                    <span class="w-6 h-6 rounded-md bg-brand-100 dark:bg-brand-500/20 border border-brand-200 dark:border-brand-400/20 flex items-center justify-center font-mono text-xs font-medium text-brand-600 dark:text-brand-400 shrink-0">1</span>
                    <h2 class="font-display text-base font-semibold text-gray-900 dark:text-white">Business details</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Business name <span class="text-red-500">*</span></label>
                            <input type="text" wire:model.live.debounce.300ms="name"
                                   class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                   placeholder="e.g. Islas Beach Resort">
                            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">URL slug</label>
                            <div class="flex rounded-xl overflow-hidden border border-gray-300 dark:border-white/10 bg-gray-100 dark:bg-white/5">
                                <span class="py-2.5 px-3 bg-gray-200 dark:bg-white/10 text-xs font-mono text-gray-500 dark:text-white/40 border-r border-gray-300 dark:border-white/10">spot/</span>
                                <input type="text" wire:model="slug" readonly
                                       class="flex-1 bg-transparent border-none py-2.5 px-4 text-sm text-gray-500 dark:text-white/50 cursor-default outline-none">
                            </div>
                            @error('slug') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Business type <span class="text-red-500">*</span></label>
                            <select wire:model="type_of_tenant_id"
                                    class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                                <option value="">— Select type —</option>
                                @foreach($this->tenantTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->type }}</option>
                                @endforeach
                            </select>
                            @error('type_of_tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Contact number</label>
                            <input type="text" wire:model="contact_number"
                                   class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                                   placeholder="+63 9xx xxx xxxx">
                            @error('contact_number') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Headquarters address <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="address"
                               class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                               placeholder="Street, City, Province">
                        @error('address') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">GPS coordinates <span class="text-red-500">*</span></label>
                        <div class="flex gap-3">
                            <div class="flex-1">
                                <input type="text" wire:model.live="latitude" onfocus="this.select()"
                                       class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition font-mono"
                                       placeholder="Latitude">
                                @error('latitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div class="flex-1">
                                <input type="text" wire:model.live="longitude" onfocus="this.select()"
                                       class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition font-mono"
                                       placeholder="Longitude">
                                @error('longitude') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 dark:text-white/40 mt-1">Or drag the map marker to set coordinates.</p>
                    </div>
                </div>
            </div>

            {{-- Admin Account --}}
            <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10 flex items-center gap-3">
                    <span class="w-6 h-6 rounded-md bg-brand-100 dark:bg-brand-500/20 border border-brand-200 dark:border-brand-400/20 flex items-center justify-center font-mono text-xs font-medium text-brand-600 dark:text-brand-400 shrink-0">2</span>
                    <h2 class="font-display text-base font-semibold text-gray-900 dark:text-white">Admin account setup</h2>
                    <span class="ml-auto text-xs text-gray-400 dark:text-white/40">Creates the business owner's login</span>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Admin full name <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="admin_name"
                               class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                               placeholder="e.g. Juan dela Cruz">
                        @error('admin_name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Login email <span class="text-red-500">*</span></label>
                        <input type="email" wire:model="email"
                               class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                               placeholder="owner@business.com">
                        <p class="text-xs text-gray-400 dark:text-white/40 mt-1">Acts as both the public contact and admin login ID.</p>
                        @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-white/70 mb-1">Initial password <span class="text-red-500">*</span></label>
                        <input type="password" wire:model="password"
                               class="w-full bg-white dark:bg-white/10 border border-gray-300 dark:border-white/10 rounded-xl py-2.5 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition"
                               placeholder="Min. 8 characters">
                        @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Submit --}}
            <button type="submit"
                    wire:loading.attr="disabled"
                    class="w-full flex items-center justify-center gap-2 py-3 px-6 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-sm font-semibold shadow-lg shadow-brand-500/20 transition hover:scale-[1.02] disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100">
                <span wire:loading.remove>
                    <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Register business &amp; admin
                </span>
                <span wire:loading class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" opacity=".25"/><path fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" opacity=".75"/></svg>
                    Creating account…
                </span>
            </button>
        </div>

        {{-- Right Column: Map --}}
        <div class="lg:sticky lg:top-6 h-fit">
            <div class="bg-white dark:bg-white/5 dark:backdrop-blur-md border border-gray-200 dark:border-white/10 rounded-2xl overflow-hidden shadow-sm dark:shadow-none">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <h3 class="font-display text-base font-semibold text-gray-900 dark:text-white">Map Preview</h3>
                    <p class="text-xs text-gray-500 dark:text-white/40 mt-1">Coordinates will be saved for this business.</p>
                </div>
                <div class="p-2">
                    <x-location-map :readonly="false" height="550px" />
                </div>
            </div>
        </div>
    </form>
</div>