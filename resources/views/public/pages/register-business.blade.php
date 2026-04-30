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

<div class="min-h-screen bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-2xl shadow-sm border border-slate-200">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-slate-900">Register Your Business</h1>
            <p class="text-slate-500 mt-2">Fill in the details below. Your business will be reviewed by our team before it goes live.</p>
        </div>

        @if (session()->has('message'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                {{ session('message') }}
            </div>
        @endif

        <form wire:submit="register" class="space-y-6">
            {{-- Business Information --}}
            <div class="border-b border-slate-200 pb-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Business Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Name *</label>
                        <input type="text" wire:model="business_name" class="w-full rounded-lg border-slate-300">
                        @error('business_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Type *</label>
                        <select wire:model="type_of_tenant_id" class="w-full rounded-lg border-slate-300">
                            <option value="">-- Select --</option>
                            @foreach($this->tenantTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->type }}</option>
                            @endforeach
                        </select>
                        @error('type_of_tenant_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Address *</label>
                        <input type="text" wire:model="address" class="w-full rounded-lg border-slate-300">
                        @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Contact Number *</label>
                        <input type="text" wire:model="contact_number" class="w-full rounded-lg border-slate-300">
                        @error('contact_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Business Email *</label>
                        <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300">
                        @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            {{-- Owner Account --}}
            <div class="border-b border-slate-200 pb-6">
                <h2 class="text-lg font-semibold text-slate-800 mb-4">Your Account (Business Owner)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Full Name *</label>
                        <input type="text" wire:model="owner_name" class="w-full rounded-lg border-slate-300">
                        @error('owner_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                        <input type="email" wire:model="owner_email" class="w-full rounded-lg border-slate-300">
                        @error('owner_email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Password *</label>
                        <input type="password" wire:model="password" class="w-full rounded-lg border-slate-300">
                        @error('password') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Confirm Password *</label>
                        <input type="password" wire:model="password_confirmation" class="w-full rounded-lg border-slate-300">
                    </div>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition">
                    Submit for Approval
                </button>
                <a href="{{ route('home') }}" class="border border-slate-300 text-slate-700 hover:bg-slate-50 font-medium py-2.5 px-6 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>