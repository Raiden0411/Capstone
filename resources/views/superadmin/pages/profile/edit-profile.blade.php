<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

new 
#[Layout('superadmin.layouts.app')]
#[Title('My Profile')]
class extends Component {
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('required|email|max:255')]
    public $email = '';
    
    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    public function mount()
    {
        $this->name  = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function rules()
    {
        return [
            'name'  => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore(Auth::id()),
            ],
            'new_password' => 'nullable|min:8|confirmed',
        ];
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function update()
    {
        $this->validate();

        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];

        if ($this->new_password) {
            $data['password'] = Hash::make($this->new_password);
        }

        Auth::user()->update($data);

        session()->flash('message', 'Profile updated successfully.');
        return redirect()->route('superadmin.profile');
    }
};
?>

<div class="p-4 sm:p-6 lg:p-10 max-w-2xl mx-auto text-gray-900 dark:text-white space-y-6">

    {{-- Flash Message --}}
    @if (session()->has('message'))
        <div class="p-4 bg-green-50 dark:bg-green-500/10 border-l-4 border-green-500 rounded-md shadow-sm">
            <p class="text-sm text-green-700 dark:text-green-400 font-medium">{{ session('message') }}</p>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">My Profile</h1>
        <a href="{{ route('superadmin.dashboard') }}" wire:navigate class="text-gray-500 dark:text-slate-400 hover:text-gray-700 dark:hover:text-white font-medium transition-colors">
            &larr; Dashboard
        </a>
    </div>

    <form wire:submit="update" class="space-y-6 bg-white dark:bg-[#0b0f19] rounded-xl border border-gray-200 dark:border-slate-700/50 shadow-sm p-5 sm:p-6">
        
        {{-- Role Badge --}}
        <div class="flex items-center gap-2 pb-4 border-b border-gray-200 dark:border-slate-700/50">
            <span class="text-sm text-gray-600 dark:text-slate-400">System Role:</span>
            @php $role = Auth::user()->roles->first(); @endphp
            @if($role)
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 dark:bg-purple-500/20 text-purple-800 dark:text-purple-400">
                    {{ ucwords(str_replace(['-', '_'], ' ', $role->name)) }}
                </span>
            @else
                <span class="text-gray-400 dark:text-slate-500 text-xs italic">No role assigned</span>
            @endif
        </div>

        {{-- Name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Name</label>
            <input type="text" wire:model="name" 
                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Email --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Email</label>
            <input type="email" wire:model="email" 
                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>

        {{-- Divider --}}
        <hr class="border-gray-200 dark:border-slate-700/50">

        {{-- Password Section --}}
        <p class="text-sm text-gray-500 dark:text-slate-400">Change password – leave blank to keep current.</p>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">New Password</label>
            <input type="password" wire:model="new_password" 
                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
            @error('new_password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-slate-300 mb-1">Confirm New Password</label>
            <input type="password" wire:model="new_password_confirmation" 
                   class="w-full rounded-lg border-gray-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-gray-900 dark:text-slate-200 placeholder-gray-400 dark:placeholder-slate-500 focus:ring-blue-500 focus:border-blue-500">
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-xl shadow-sm transition-colors flex items-center gap-2 data-loading:opacity-75 data-loading:cursor-not-allowed">
                <span class="in-data-loading:hidden">Save Changes</span>
                <span class="not-in-data-loading:hidden flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.dashboard') }}" wire:navigate class="bg-white dark:bg-slate-800 text-gray-700 dark:text-slate-300 border border-gray-300 dark:border-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700 font-medium py-2.5 px-6 rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>