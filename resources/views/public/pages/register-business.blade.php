<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

new 
#[Layout('layouts.app')]
#[Title('Register Your Business')]
class extends Component {
    
    // Business details
    #[Validate('required|string|max:255|unique:tenants,name')]
    public $business_name = '';
    
    #[Validate('required|integer|exists:type_of_tenants,id')]
    public $type_of_tenant_id = '';
    
    #[Validate('required|string|max:255')]
    public $address = '';
    
    #[Validate('required|string|max:20')]
    public $contact_number = '';
    
    #[Validate('required|email|max:255|unique:tenants,email')]
    public $email = '';
    
    // Owner account details
    #[Validate('required|string|max:255')]
    public $owner_name = '';
    
    #[Validate('required|email|max:255|unique:users,email')]
    public $owner_email = '';
    
    #[Validate('required|min:8')]
    public $password = '';
    
    #[Validate('required|same:password')]
    public $password_confirmation = '';

    public function getTenantTypesProperty()
    {
        return TypeOfTenant::all();
    }

    public function register()
    {
        $this->validate();

        // Create the tenant as inactive (pending approval)
        $tenant = Tenant::create([
            'name'              => $this->business_name,
            'slug'              => Str::slug($this->business_name),
            'type_of_tenant_id' => $this->type_of_tenant_id,
            'address'           => $this->address,
            'contact_number'    => $this->contact_number,
            'email'             => $this->email,
            'is_active'         => false, // Pending approval
        ]);

        // Create the owner user account (also inactive)
        $user = User::create([
            'name'      => $this->owner_name,
            'email'     => $this->owner_email,
            'password'  => Hash::make($this->password),
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);

        session()->flash('message', 'Your business has been submitted for approval. You will be notified once it is activated.');
        return $this->redirectRoute('home');
    }
};
?>

<div class="min-h-screen flex items-center justify-center bg-[#F8F7F3] dark:bg-[#0C0F0B] px-4 py-12">
    <div class="w-full max-w-3xl">
        <!-- Card -->
        <div class="bg-white dark:bg-[#0F1A14] rounded-3xl shadow-2xl shadow-green-900/10 dark:shadow-black/40 overflow-hidden p-8 sm:p-10">

            <!-- Brand mark -->
            <div class="flex items-center gap-3 mb-10">
                <div class="w-10 h-10 rounded-xl bg-brand-600 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </div>
                <span class="text-gray-900 dark:text-white font-bold text-lg">Victorias Tourism</span>
            </div>

            <!-- Header -->
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white mb-2">Register Your Business</h1>
            <p class="text-gray-500 dark:text-gray-400">Fill in the details below. We’ll review your listing and activate it shortly.</p>

            <!-- Success Message -->
            @if (session()->has('message'))
                <div class="mt-6 mb-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700/30 text-emerald-700 dark:text-emerald-300 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ session('message') }}
                </div>
            @endif

            <form wire:submit="register" class="mt-8 space-y-8">
                {{-- Business Information --}}
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        Business Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Business Name *</label>
                            <input type="text" id="business_name" wire:model="business_name" placeholder="Your resort or inn name" class="input-base">
                            @error('business_name') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400 flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="type_of_tenant_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Business Type *</label>
                            <select wire:model="type_of_tenant_id" id="type_of_tenant_id" class="input-base">
                                <option value="">-- Select Type --</option>
                                @foreach($this->tenantTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->type }}</option>
                                @endforeach
                            </select>
                            @error('type_of_tenant_id') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div class="md:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Address *</label>
                            <input type="text" id="address" wire:model="address" placeholder="Complete street address" class="input-base">
                            @error('address') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contact Number *</label>
                            <input type="text" id="contact_number" wire:model="contact_number" placeholder="+63 9XX XXX XXXX" class="input-base">
                            @error('contact_number') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Business Email *</label>
                            <input type="email" id="email" wire:model="email" placeholder="hello@yourbusiness.com" class="input-base">
                            @error('email') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- Owner Account --}}
                <div>
                    <h2 class="text-base font-semibold text-gray-800 dark:text-white flex items-center gap-2 mb-5">
                        <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        Your Account (Owner)
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <label for="owner_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Full Name *</label>
                            <input type="text" id="owner_name" wire:model="owner_name" placeholder="Juan dela Cruz" class="input-base">
                            @error('owner_name') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="owner_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Email *</label>
                            <input type="email" id="owner_email" wire:model="owner_email" placeholder="you@example.com" class="input-base">
                            @error('owner_email') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Password *</label>
                            <input type="password" id="password" wire:model="password" placeholder="Min. 8 characters" class="input-base">
                            @error('password') <p class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirm Password *</label>
                            <input type="password" id="password_confirmation" wire:model="password_confirmation" placeholder="Re-enter password" class="input-base">
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="flex flex-col sm:flex-row gap-4 pt-2">
                    <button type="submit" wire:loading.attr="disabled"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 py-3 px-8 rounded-xl bg-brand-700 hover:bg-brand-800 text-white font-semibold text-sm transition-all shadow-lg shadow-brand-900/20 disabled:opacity-60 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-brand-600 focus:ring-offset-2 dark:focus:ring-offset-[#0F1A14]">
                        <span wire:loading.remove>Submit for Approval</span>
                        <span wire:loading class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            Submitting…
                        </span>
                    </button>
                    <a href="{{ route('home') }}" wire:navigate
                       class="w-full sm:w-auto inline-flex items-center justify-center py-3 px-8 rounded-xl border-2 border-gray-200 dark:border-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:border-brand-600 hover:text-brand-700 dark:hover:border-brand-500 dark:hover:text-brand-400 transition-colors">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>