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
            $user = Auth::user();

            // Block inactive accounts (deactivated or pending approval)
            if (!$user->is_active) {
                Auth::logout();
                $this->addError('email', 'Your account is not yet active. If you recently registered a business, please wait for approval. Otherwise, contact support.');
                return;
            }

            session()->regenerate();

            if ($user->hasRole('super-admin')) {
                return $this->redirectRoute('superadmin.dashboard', navigate: true);
            }

            if ($user->hasRole('admin')) {
                return $this->redirectRoute('tenant.dashboard', navigate: true);
            }

            return $this->redirectRoute('home', navigate: true);
        }

        $this->addError('email', 'The provided credentials do not match our records.');
    }
};
?>
<div>
    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4 dark:bg-neutral-900">
        <div class="w-full max-w-4xl bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden flex dark:bg-neutral-800 dark:border-neutral-700">

            {{-- ── LEFT: MAP PANEL ── --}}
            <div class="hidden lg:block relative flex-1 bg-green-50 overflow-hidden dark:bg-neutral-700/30">
                <svg class="absolute inset-0 w-full h-full" viewBox="0 0 480 620" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid slice">
                    <rect x="28"  y="22"  width="98"  height="62" rx="6" fill="#bbdabb" opacity=".65"/>
                    <rect x="142" y="22"  width="78"  height="62" rx="6" fill="#bbdabb" opacity=".65"/>
                    <rect x="236" y="22"  width="108" height="62" rx="6" fill="#bbdabb" opacity=".65"/>
                    <rect x="360" y="22"  width="96"  height="62" rx="6" fill="#bbdabb" opacity=".65"/>
                    <ellipse cx="200" cy="102" rx="58" ry="24" fill="#bccede" opacity=".55"/>
                    <rect x="0"   y="98"  width="480" height="14" fill="#cddacd"/>
                    <rect x="124" y="0"   width="10"  height="620" fill="#cddacd"/>
                    <rect x="264" y="0"   width="10"  height="620" fill="#cddacd"/>
                    <rect x="0"   y="254" width="480" height="10"  fill="#cddacd"/>
                    <rect x="0"   y="394" width="480" height="10"  fill="#cddacd"/>
                    <line x1="200" y1="0" x2="200" y2="110" stroke="#b0c8b0" stroke-width="2.5"/>
                </svg>

                <div class="absolute top-4 right-4 size-9 rounded-full bg-white shadow-md flex items-center justify-center text-xs font-bold text-green-700 dark:bg-neutral-800 dark:text-green-400 z-10">N</div>

                <div class="absolute z-10 flex items-center gap-1.5 bg-white border border-gray-100 rounded-lg px-2.5 py-1.5 shadow-md text-xs font-semibold text-gray-700 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200" style="top:170px; left:86px;">
                    <span class="inline-block size-2 rounded-full bg-pink-500 shrink-0"></span>
                    HQ
                </div>

                <div class="absolute z-10 flex flex-col items-center" style="top:128px; left:290px;">
                    <span class="size-3.5 rounded-full bg-green-700 border-2 border-white shadow block dark:border-neutral-800"></span>
                    <span class="w-px h-3 bg-green-700 block"></span>
                </div>

                <div class="absolute z-10 flex flex-col items-center" style="top:210px; left:170px;">
                    <span class="size-7 rounded-full border-[3px] border-green-700/20 flex items-center justify-center">
                        <span class="size-3.5 rounded-full bg-green-700 border-2 border-white shadow block dark:border-neutral-800"></span>
                    </span>
                    <span class="w-px h-3 bg-green-700 block"></span>
                </div>

                <div class="absolute z-10 flex flex-col items-center" style="top:316px; left:326px;">
                    <span class="size-3.5 rounded-full bg-green-700 border-2 border-white shadow block dark:border-neutral-800"></span>
                    <span class="w-px h-3 bg-green-700 block"></span>
                </div>

                <div class="absolute z-10 flex flex-col items-center" style="top:348px; left:70px;">
                    <span class="size-3.5 rounded-full bg-green-700 border-2 border-white shadow block dark:border-neutral-800"></span>
                    <span class="w-px h-3 bg-green-700 block"></span>
                </div>

                <div class="absolute bottom-5 right-5 z-10 inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-full text-xs font-medium bg-white text-gray-700 shadow-sm dark:bg-neutral-800 dark:text-neutral-300">3 locations</div>

                <div class="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 size-9 rounded-full bg-white shadow-md flex items-center justify-center dark:bg-neutral-800">
                    <svg class="size-4 text-green-700 dark:text-green-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
                </div>
            </div>

            {{-- ── RIGHT: FORM PANEL ── --}}
            <div class="w-full lg:max-w-md p-8 sm:p-10 relative">
                <div class="absolute top-4 right-4">
                    <button type="button" class="size-8 inline-flex items-center justify-center gap-x-2 rounded-full text-gray-400 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300 transition dark:text-neutral-500 dark:hover:bg-neutral-700 dark:focus:ring-neutral-600">
                        <svg class="size-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 256 256"><circle cx="128" cy="60" r="16"/><circle cx="128" cy="128" r="16"/><circle cx="128" cy="196" r="16"/></svg>
                    </button>
                </div>

                {{-- Brand --}}
                <div class="flex items-center gap-x-3 mb-7">
                    <div class="size-10 rounded-xl bg-green-900 inline-flex items-center justify-center shrink-0">
                        <svg class="size-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
                    </div>
                    <span class="text-base font-semibold text-gray-800 dark:text-neutral-200">{{ config('app.name', 'Capstone') }}</span>
                </div>

                {{-- Heading --}}
                <h1 class="text-2xl font-bold text-gray-800 dark:text-neutral-200">Sign In</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    New here?
                    <a href="{{ route('register') }}" class="font-medium text-green-700 decoration-2 hover:underline focus:outline-none focus:underline dark:text-green-500">Create an account</a>
                </p>
                {{-- Register business link (using underscore route name) --}}
                <p class="mt-1 text-sm text-gray-500 dark:text-neutral-400">
                    Own a tourist spot?
                    <a href="{{ route('register_business') }}" wire:navigate class="font-medium text-green-700 decoration-2 hover:underline focus:outline-none focus:underline dark:text-green-500">Register your business</a>
                </p>

                {{-- Form --}}
                <form wire:submit="login" class="space-y-4 mt-6">
                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-medium mb-2 text-gray-700 dark:text-neutral-300">Email Address</label>
                        <input type="email" id="email" wire:model="email" placeholder="example@email.com" class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-green-700 focus:ring-green-700 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                        @error('email') <p class="mt-2 text-xs text-red-600 dark:text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Password --}}
                    <div>
                        <label for="password" class="block text-sm font-medium mb-2 text-gray-700 dark:text-neutral-300">Password</label>
                        <input type="password" id="password" wire:model="password" placeholder="Enter your password" class="py-3 px-4 block w-full border-gray-200 rounded-lg text-sm focus:border-green-700 focus:ring-green-700 disabled:opacity-50 disabled:pointer-events-none dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-400 dark:placeholder-neutral-500 dark:focus:ring-neutral-600">
                        @error('password') <p class="mt-2 text-xs text-red-600 dark:text-red-500">{{ $message }}</p> @enderror
                    </div>

                    {{-- Remember me --}}
                    <div class="flex items-center gap-x-3">
                        <input type="checkbox" id="remember" wire:model="remember" class="shrink-0 mt-0.5 border-gray-200 rounded text-green-700 focus:ring-green-700 dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-green-700 dark:checked:border-green-700 dark:focus:ring-offset-gray-800">
                        <label for="remember" class="text-sm text-gray-600 dark:text-neutral-400">Remember me</label>
                    </div>

                    {{-- Submit --}}
                    <button type="submit" wire:loading.attr="disabled" class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-lg border border-transparent bg-green-900 text-white hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-700 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none transition-colors dark:focus:ring-offset-neutral-800">
                        <span wire:loading.remove>Sign In</span>
                        <span wire:loading class="inline-flex items-center gap-x-2">
                            <svg class="animate-spin size-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                            Signing in...
                        </span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>