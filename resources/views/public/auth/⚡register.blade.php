{{-- resources/views/public/pages/register-business.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;
use App\Models\TypeOfTenant;
use Illuminate\Support\Str;

new 
#[Layout('layouts.app')] 
#[Title('Register your Business')]
class extends Component {
    
    #[Validate('required|string|max:255')]
    public $businessName = '';

    #[Validate('required|string|max:255')]
    public $ownerName = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public $password = '';

    public $password_confirmation = '';

    #[Validate('required|exists:type_of_tenants,id')]
    public $businessType = '';

    #[Validate('required|string|max:20')]
    public $contactNumber = '';

    #[Validate('required|string|max:255')]
    public $address = '';

    public function getBusinessTypesProperty()
    {
        return TypeOfTenant::orderBy('type')->get();
    }

    public function register()
    {
        $this->validate();

        // Create tenant
        $tenant = Tenant::create([
            'name'            => $this->businessName,
            'slug'            => Str::slug($this->businessName),
            'type_of_tenant_id' => $this->businessType,
            'address'         => $this->address,
            'contact_number'  => $this->contactNumber,
            'email'           => $this->email,
            'is_active'       => false, // needs admin approval
        ]);

        // Create admin user for the business
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name'      => $this->ownerName,
            'email'     => $this->email,
            'password'  => Hash::make($this->password),
            'is_active' => false,
        ]);

        $user->assignRole('admin');

        Auth::login($user);

        return redirect()->route('tenant.dashboard')
            ->with('message', 'Registration successful! Your business is pending approval.');
    }
};
?>

@push('styles')
<style>
    /* Fix invisible dropdown options in glass-style selects */
    select option {
        background: #1e293b;
        color: #e2e8f0;
    }
</style>
@endpush

<div class="relative z-10 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-2xl glass-card !rounded-3xl !p-0 overflow-hidden">
        <div class="p-8 sm:p-10">
            {{-- Brand --}}
            <div class="flex items-center gap-x-3 mb-7">
                <div class="size-10 rounded-xl bg-brand-600 inline-flex items-center justify-center shrink-0">
                    <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                </div>
                <span class="text-base font-semibold text-white">{{ config('app.name', 'Capstone') }}</span>
            </div>

            {{-- Heading --}}
            <h1 class="text-2xl font-bold text-white">Register your Business</h1>
            <p class="mt-2 text-sm text-white/60">
                Already have an account?
                <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-400 decoration-2 hover:underline focus:outline-none focus:underline">Sign in here</a>
            </p>

            {{-- Form --}}
            <form wire:submit="register" class="mt-6 space-y-4">
                {{-- Business Name --}}
                <div>
                    <label for="businessName" class="block text-sm font-medium mb-2 text-white/70">Business Name *</label>
                    <input wire:model="businessName" id="businessName" type="text" placeholder="Your resort, inn, etc."
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('businessName') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Owner Name --}}
                <div>
                    <label for="ownerName" class="block text-sm font-medium mb-2 text-white/70">Your Name *</label>
                    <input wire:model="ownerName" id="ownerName" type="text" placeholder="Juan Dela Cruz"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('ownerName') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Business Type --}}
                <div>
                    <label for="businessType" class="block text-sm font-medium mb-2 text-white/70">Business Type *</label>
                    <select wire:model="businessType" id="businessType"
                            class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition appearance-none">
                        <option value="">-- Select Type --</option>
                        @foreach($this->businessTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->type }}</option>
                        @endforeach
                    </select>
                    @error('businessType') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium mb-2 text-white/70">Email address *</label>
                    <input wire:model="email" id="email" type="email" placeholder="example@email.com"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('email') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Contact Number --}}
                <div>
                    <label for="contactNumber" class="block text-sm font-medium mb-2 text-white/70">Contact Number *</label>
                    <input wire:model="contactNumber" id="contactNumber" type="text" placeholder="09123456789"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('contactNumber') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Address --}}
                <div>
                    <label for="address" class="block text-sm font-medium mb-2 text-white/70">Business Address *</label>
                    <input wire:model="address" id="address" type="text" placeholder="123 Main St, Victorias City"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('address') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-sm font-medium mb-2 text-white/70">Password *</label>
                    <input wire:model="password" id="password" type="password" placeholder="••••••••"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('password') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium mb-2 text-white/70">Confirm Password *</label>
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" placeholder="••••••••"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-brand-600 text-white hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-black/30 disabled:opacity-50 disabled:pointer-events-none transition-colors shadow-lg shadow-brand-500/20">
                    <span wire:loading.remove>Register Business</span>
                    <span wire:loading class="inline-flex items-center gap-x-2">
                        <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        Registering...
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
