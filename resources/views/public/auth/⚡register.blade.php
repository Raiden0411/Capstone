<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Spatie\Permission\Models\Role;

new 
#[Layout('layouts.app')] 
#[Title('Register')]
class extends Component {
    
    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|string|email|max:255|unique:users,email')]
    public $email = '';

    #[Validate('required|string|min:8|confirmed')]
    public $password = '';

    public $password_confirmation = '';

    public function register()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
            'is_active' => true,
            'tenant_id' => null, 
        ]);

        $touristRole = Role::firstOrCreate(['name' => 'tourist']);
        $user->assignRole($touristRole);

        Auth::login($user);

        return $this->redirectRoute('home', navigate: true);
    }
};
?>

<div class="relative z-10 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md glass-card !rounded-3xl !p-0 overflow-hidden">
        <div class="p-8 sm:p-10">
            {{-- Brand --}}
            <div class="flex items-center gap-x-3 mb-7">
                <div class="size-10 rounded-xl bg-brand-600 inline-flex items-center justify-center shrink-0">
                    <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                </div>
                <span class="text-base font-semibold text-white">{{ config('app.name', 'Capstone') }}</span>
            </div>

            {{-- Heading --}}
            <h1 class="text-2xl font-bold text-white">Create an account</h1>
            <p class="mt-2 text-sm text-white/60">
                Already have an account?
                <a href="{{ route('login') }}" wire:navigate class="font-medium text-brand-400 decoration-2 hover:underline focus:outline-none focus:underline">Sign in here</a>
            </p>

            {{-- Form --}}
            <form wire:submit="register" class="mt-6 space-y-4">
                <div>
                    <label for="name" class="block text-sm font-medium mb-2 text-white/70">Full Name</label>
                    <input wire:model="name" id="name" type="text" placeholder="John Doe"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('name') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
                
                <div>
                    <label for="email" class="block text-sm font-medium mb-2 text-white/70">Email address</label>
                    <input wire:model="email" id="email" type="email" placeholder="example@email.com"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('email') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium mb-2 text-white/70">Password</label>
                    <input wire:model="password" id="password" type="password" placeholder="••••••••"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                    @error('password') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium mb-2 text-white/70">Confirm Password</label>
                    <input wire:model="password_confirmation" id="password_confirmation" type="password" placeholder="••••••••"
                           class="py-3 px-4 block w-full bg-white/5 border border-white/10 rounded-lg text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-brand-600 text-white hover:bg-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-black/30 disabled:opacity-50 disabled:pointer-events-none transition-colors shadow-lg shadow-brand-500/20">
                    <span wire:loading.remove>Register</span>
                    <span wire:loading class="inline-flex items-center gap-x-2">
                        <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                        Registering...
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>