{{-- resources/views/public/pages/register-business.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Str;

new 
#[Layout('layouts.auth')]
#[Title('Register Your Business')]
class extends Component {
    
    // Business details
    public $business_name = '';
    public $type_of_tenant_id = '';
    public $address = '';
    public $contact_number = '';
    public $business_email = '';
    
    // Owner account details
    public $owner_name = '';
    public $owner_email = '';
    public $password = '';
    public $password_confirmation = '';

    public function getTenantTypesProperty()
    {
        return TypeOfTenant::all();
    }

    protected function rules()
    {
        return [
            'business_name' => [
                'required', 'string', 'max:255',
                Rule::unique('tenants', 'name'),
            ],
            'type_of_tenant_id' => ['required', 'integer', 'exists:type_of_tenants,id'],
            'address' => ['required', 'string', 'max:255'],
            'contact_number' => [
                'required', 'string', 'max:20',
                'regex:/^(09|\+639)\d{9}$/',
            ],
            'business_email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('tenants', 'email'),
            ],
            'owner_name' => ['required', 'string', 'min:3', 'max:255'],
            'owner_email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages()
    {
        return [
            'business_name.unique' => 'This business name is already registered.',
            'contact_number.regex' => 'Use a valid PH number: 09xxxxxxxxx or +639xxxxxxxxx.',
            'business_email.unique' => 'This business email is already registered.',
            'owner_email.unique' => 'This email is already in use.',
        ];
    }

    public function register()
    {
        $this->validate();

        // Create tenant (pending approval)
        $tenant = Tenant::create([
            'name'              => $this->business_name,
            'slug'              => Str::slug($this->business_name),
            'type_of_tenant_id' => $this->type_of_tenant_id,
            'address'           => $this->address,
            'contact_number'    => $this->contact_number,
            'email'             => $this->business_email,
            'is_active'         => false,
        ]);

        // Create owner user (inactive until approval)
        $user = User::create([
            'name'      => $this->owner_name,
            'email'     => $this->owner_email,
            'password'  => Hash::make($this->password),
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        session()->flash('message', 'Your business has been submitted for approval. You will be notified once it is activated.');
        return redirect()->route('login');
    }
};
?>

@php
    $siteName = \App\Models\SiteSetting::getValue('site_name', config('app.name'));
    $logoPath = \App\Models\SiteSetting::getValue('site_logo');
    $logoUrl = $logoPath ? asset('storage/' . $logoPath) : null;

    $heroPath = \App\Models\SiteSetting::getValue('hero_background_image');
    $heroUrl = $heroPath
        ? asset('storage/' . $heroPath)
        : 'https://images.unsplash.com/photo-1506748686214-e9df14d4d9d0?auto=format&fit=crop&w=1600&q=80';
@endphp

<main class="flex flex-col md:flex-row w-full min-h-screen">

    {{-- Left Side: Hero Image & Text --}}
    <div class="relative w-full md:w-3/5 min-h-[220px] md:min-h-screen order-1 md:order-1">
        <img src="{{ $heroUrl }}"
             alt="{{ $siteName }}"
             class="absolute inset-0 object-cover w-full h-full">

        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/25 to-transparent"></div>

        <div class="absolute inset-x-0 bottom-0 p-5 sm:p-8 md:p-16 lg:p-24 text-white">
            <span class="inline-flex items-center gap-2 px-3 py-1 text-[11px] font-bold tracking-widest text-white uppercase bg-black/40 rounded-full backdrop-blur-sm border border-white/10">
                <span class="w-1.5 h-1.5 bg-yellow-500 rounded-full"></span>
                {{ $siteName }}
            </span>

            <h1 class="mt-5 text-2xl sm:text-3xl md:text-5xl lg:text-[54px] font-extrabold tracking-tight leading-[1.1]">
                Register your business &<br />welcome the world
            </h1>

            <p class="max-w-2xl mt-4 text-sm font-medium leading-relaxed text-gray-200 md:text-base">
                Put your resort, inn, eco‑park, or restaurant on the map.
                Reach more visitors and share the best of Victorias City.
            </p>
        </div>
    </div>

    {{-- Right Side: Registration Form --}}
    <div class="flex items-center justify-center w-full px-4 sm:px-6 py-10 md:py-12 bg-white dark:bg-gray-900 md:w-2/5 lg:px-16 order-2 md:order-2 overflow-y-auto">
        <div class="w-full max-w-md">

            {{-- Back to Home --}}
            <a href="{{ route('home') }}" wire:navigate
               class="inline-flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors mb-6 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back to Home
            </a>

            {{-- Brand / Logo --}}
            <div class="flex items-center gap-3 mb-6">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $siteName }} logo"
                         class="w-10 h-10 object-contain rounded-lg shrink-0">
                @else
                    <div class="w-10 h-10 rounded-xl bg-primary-600 flex items-center justify-center text-white shrink-0">
                        {{ strtoupper(substr($siteName, 0, 1)) }}
                    </div>
                @endif
                <span class="text-lg font-semibold text-gray-900 dark:text-white">{{ $siteName }}</span>
            </div>

            <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-6">
                Register Your Business
            </h2>

            @if (session()->has('message'))
                <div class="mb-5 bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 rounded-xl p-3 text-sm text-green-700 dark:text-green-300">
                    {{ session('message') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-5 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/30 rounded-xl p-3 text-sm text-red-600 dark:text-red-400">
                    Please fix the errors below.
                </div>
            @endif

            <form wire:submit="register" class="space-y-5"
                  x-data="{ showPassword: false, showConfirmPassword: false }">

                {{-- Business Information --}}
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Business Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Business Name *
                            </label>
                            <div class="mt-1.5">
                                <input type="text" id="business_name" wire:model="business_name" placeholder="Your resort or inn name" autocomplete="organization"
                                       class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                            </div>
                            @error('business_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="type_of_tenant_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Business Type *
                            </label>
                            <div class="mt-1.5">
                                <select wire:model="type_of_tenant_id" id="type_of_tenant_id"
                                        class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none">
                                    <option value="">-- Select Type --</option>
                                    @foreach($this->tenantTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('type_of_tenant_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Full Address *
                            </label>
                            <div class="mt-1.5">
                                <input type="text" id="address" wire:model="address" placeholder="Complete street address" autocomplete="street-address"
                                       class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                            </div>
                            @error('address') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Contact Number *
                            </label>
                            <div class="mt-1.5">
                                <input type="text" id="contact_number" wire:model="contact_number" placeholder="09xxxxxxxxx" autocomplete="tel"
                                       class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                            </div>
                            @error('contact_number') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="business_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Business Email *
                            </label>
                            <div class="mt-1.5">
                                <input type="email" id="business_email" wire:model="business_email" placeholder="hello@yourbusiness.com" autocomplete="email"
                                       class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                            </div>
                            @error('business_email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Owner Account --}}
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Your Account (Owner)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="owner_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Full Name *
                            </label>
                            <div class="mt-1.5">
                                <input type="text" id="owner_name" wire:model="owner_name" placeholder="Juan dela Cruz" autocomplete="name"
                                       class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                            </div>
                            @error('owner_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Email *
                            </label>
                            <div class="mt-1.5">
                                <input type="email" id="owner_email" wire:model="owner_email" placeholder="you@example.com" autocomplete="email"
                                       class="block w-full px-4 py-3 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                            </div>
                            @error('owner_email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Password *
                            </label>
                            <div class="relative mt-1.5">
                                <input :type="showPassword ? 'text' : 'password'"
                                       id="password" wire:model="password" placeholder="Min. 8 characters" autocomplete="new-password" minlength="8"
                                       class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                                <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                        tabindex="-1" aria-label="Toggle password visibility">
                                    <svg x-show="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg x-show="showPassword" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Confirm Password *
                            </label>
                            <div class="relative mt-1.5">
                                <input :type="showConfirmPassword ? 'text' : 'password'"
                                       id="password_confirmation" wire:model="password_confirmation" placeholder="Re-enter password" autocomplete="new-password" minlength="8"
                                       class="block w-full px-4 py-3 pr-11 text-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-xl transition-colors focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 focus:outline-none placeholder:text-gray-400 dark:placeholder-gray-500">
                                <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                                        class="absolute inset-y-0 flex items-center right-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                                        tabindex="-1" aria-label="Toggle confirm password visibility">
                                    <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg x-show="showConfirmPassword" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                            </div>
                            @error('password_confirmation') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        wire:loading.attr="disabled"
                        class="w-full py-3.5 mt-2 text-[15px] font-semibold text-white transition-colors bg-primary-600 hover:bg-primary-700 rounded-xl shadow-sm disabled:opacity-50 disabled:cursor-not-allowed focus-visible:ring-2 focus-visible:ring-primary-500/50">
                    <span wire:loading.remove>Submit for Approval</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Submitting...
                    </span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                By registering, you agree to the platform's terms.
                Your business will require admin approval.
            </p>
        </div>
    </div>
</main>