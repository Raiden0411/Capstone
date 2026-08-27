{{-- resources/views/public/pages/⚡profile.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new
#[Layout('layouts.app')]
#[Title('My Profile')]
class extends Component {
    use WithFileUploads;

    public $name = '';
    public $email = '';

    public $avatar = null;          // freshly picked file
    public $currentAvatar = null;   // stored relative path (e.g., avatars/abc.jpg)

    public $current_password = '';
    public $new_password = '';
    public $new_password_confirmation = '';

    public function mount()
    {
        $user = Auth::user();
        $this->name  = $user->name;
        $this->email = $user->email;
        $this->currentAvatar = $user->avatar;
    }

    public function rules()
    {
        return [
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore(Auth::id())],
            'avatar'           => 'nullable|image|max:2048',
            'current_password' => ['nullable', 'required_with:new_password', 'current_password'],
            'new_password'     => 'nullable|min:8|confirmed',
        ];
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function removeAvatar()
    {
        $user = Auth::user();
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->update(['avatar' => null]);
        $this->currentAvatar = null;
        $this->avatar = null;
        $this->dispatch('avatar-updated', url: null);
        session()->flash('message', 'Profile photo removed.');
    }

    public function updateProfile()
    {
        $this->validate();

        $user = Auth::user();
        $data = [
            'name'  => $this->name,
            'email' => $this->email,
        ];

        $newAvatarPath = null;

        if ($this->avatar) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $newAvatarPath = $this->avatar->store('avatars', 'public');
            $data['avatar'] = $newAvatarPath;
            $this->currentAvatar = $newAvatarPath;
            $this->avatar = null;
        }

        if ($this->new_password) {
            $data['password'] = Hash::make($this->new_password);
        }

        $user->update($data);

        $this->dispatch('avatar-updated', url: $newAvatarPath ? asset('storage/'. $newAvatarPath) : null);

        session()->flash('message', 'Profile updated successfully.');
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
    }

    /** Helper for the Blade template – returns the actual public URL */
    public function avatarUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;   // temp preview
        return asset('storage/'. $path);
    }
};
?>

<div class="relative z-10 min-h-screen py-10 md:py-12 px-4 sm:px-6 lg:px-8 bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
    <div class="max-w-2xl mx-auto space-y-6">

        {{-- Flash Message --}}
        @if (session()->has('message'))
            <div class="bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 p-4 rounded-2xl text-sm flex items-center gap-3 shadow-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('message') }}
            </div>
        @endif

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <h1 class="font-display text-3xl font-bold text-gray-900 dark:text-white">My Profile</h1>
            <a href="{{ route('home') }}" wire:navigate
               class="text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-primary-600 dark:hover:text-primary-400 transition-colors flex items-center gap-1">
                &larr; Home
            </a>
        </div>

        <form wire:submit="updateProfile" class="space-y-6">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-5 sm:p-8">

                {{-- Avatar Section with Alpine instant preview --}}
                <div class="flex flex-col sm:flex-row items-center gap-6 mb-8"
                     x-data="{ previewUrl: null }">
                    <div class="relative">
                        @php $currentAvatarUrl = $this->avatarUrl($currentAvatar); @endphp
                        <img x-show="previewUrl || @js($currentAvatarUrl) !== null"
                             :src="previewUrl || '{{ $currentAvatarUrl }}'"
                             class="w-24 h-24 rounded-full object-cover border-2 border-primary-500 shadow-md"
                             alt="Profile photo"
                             x-cloak>
                        <div x-show="!previewUrl && !@js($currentAvatarUrl)"
                             class="w-24 h-24 rounded-full bg-primary-50 dark:bg-primary-900/30 flex items-center justify-center text-primary-700 dark:text-primary-400 text-3xl font-bold border-2 border-primary-200 dark:border-primary-500/30">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                        </div>

                        <label class="absolute bottom-0 right-0 bg-primary-600 hover:bg-primary-500 text-white rounded-full p-2 cursor-pointer shadow-lg transition-colors focus-within:ring-2 focus-within:ring-primary-600/50"
                               wire:loading.class="opacity-50 pointer-events-none"
                               wire:target="avatar"
                               aria-label="Upload profile photo">
                            <svg wire:loading.remove wire:target="avatar" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg wire:loading wire:target="avatar" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <input type="file"
                                   wire:model="avatar"
                                   x-ref="avatarInput"
                                   accept="image/*"
                                   class="hidden"
                                   @change="previewUrl = URL.createObjectURL($refs.avatarInput.files[0])">
                        </label>
                    </div>

                    <div class="flex-1 text-center sm:text-left">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">{{ Auth::user()->name }}</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p>
                        @if($currentAvatarUrl || $avatar)
                            <button type="button"
                                    @click="previewUrl = null; $wire.removeAvatar()"
                                    class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 transition-colors focus-visible:ring-2 focus-visible:ring-red-500/50 rounded">
                                Remove photo
                            </button>
                        @endif
                        @error('avatar')
                            <span class="text-red-600 dark:text-red-400 text-xs mt-2 block">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                {{-- Name & Email --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name</label>
                        <input type="text" wire:model="name" autocomplete="name"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors duration-200">
                        @error('name') <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address</label>
                        <input type="email" wire:model="email" autocomplete="email"
                               class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors duration-200">
                        @error('email') <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Password Change --}}
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Change Password</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Leave blank to keep your current password.</p>
                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Current Password</label>
                            <input type="password" wire:model="current_password" autocomplete="current-password"
                                   class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors duration-200">
                            @error('current_password') <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                                <input type="password" wire:model="new_password" autocomplete="new-password" minlength="8"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors duration-200">
                                @error('new_password') <span class="text-red-600 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                                <input type="password" wire:model="new_password_confirmation" autocomplete="new-password" minlength="8"
                                       class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl py-3 px-4 text-sm text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500/50 focus:border-primary-500 transition-colors duration-200">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="flex items-center gap-4">
                <button type="submit" wire:loading.attr="disabled"
                        class="bg-primary-600 hover:bg-primary-500 text-white font-medium py-3 px-8 rounded-xl shadow-lg shadow-primary-500/20 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-600/50">
                    <span wire:loading.remove>Save Changes</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
                <a href="{{ route('home') }}" wire:navigate
                   class="px-6 py-3 rounded-xl border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 font-medium transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>