<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

new 
#[Layout('layouts.app')] 
#[Title('Login')]
class extends Component {
    
    #[Validate('required|email')]
    public $email = '';

    #[Validate('required')]
    public $password = '';

    public $remember = false;

    public function login()
    {
        $this->validate();

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            session()->regenerate();

            $user = auth()->user();

            // 1. Super Admins go to the Platform Level Dashboard (superadmin namespace)
            if ($user->hasRole('super-admin')) {
                return $this->redirectRoute('superadmin.dashboard', navigate: true);
            }

            // 2. Business Owners go to their Business Level (tenant namespace)
            if ($user->hasRole('admin')) {
                return $this->redirectRoute('tenant.dashboard', navigate: true);
            }

            // 3. Regular public users go to the home page
            return $this->redirectRoute('home', navigate: true);
        }

        $this->addError('email', 'The provided credentials do not match our records.');
    }
};
?>

<div class="min-h-screen flex items-center justify-center bg-slate-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-xl shadow-sm border border-slate-200">
        <div>
            <h2 class="mt-4 text-center text-3xl font-extrabold text-slate-900">Sign in to your account</h2>
            <p class="mt-2 text-center text-sm text-slate-600">
                Or <a href="{{ route('register') }}" wire:navigate class="font-medium text-blue-600 hover:text-blue-500">create a new account</a>
            </p>
        </div>
        <form wire:submit="login" class="mt-8 space-y-6">
            <div class="space-y-4 shadow-sm">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email address</label>
                    <input wire:model="email" id="email" type="email" autocomplete="email" class="mt-1 appearance-none rounded-lg relative block w-full px-3 py-2 border border-slate-300 placeholder-slate-500 text-slate-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input wire:model="password" id="password" type="password" autocomplete="current-password" class="mt-1 appearance-none rounded-lg relative block w-full px-3 py-2 border border-slate-300 placeholder-slate-500 text-slate-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input wire:model="remember" id="remember_me" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-slate-300 rounded">
                    <label for="remember_me" class="ml-2 block text-sm text-slate-900"> Remember me </label>
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Sign in
                </button>
            </div>
        </form>
    </div>
</div>