<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

new 
#[Layout('layouts.app')]
#[Title('My Profile')]
class extends Component {
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public $name = '';

    #[Validate('required|email|max:255')]
    public $email = '';

    public $avatar = null;          // freshly picked file
    public $currentAvatar = null;   // stored relative path (e.g., avatars/abc.jpg)
    public $avatarPreview = null;   // temporary URL for instant preview

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
            'new_password'     => 'nullable|min:8|confirmed',
        ];
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email'])) {
            $this->$property = trim($this->$property);
        }
    }

    public function updatedAvatar()
    {
        $this->validateOnly('avatar');
        $this->avatarPreview = $this->avatar ? $this->avatar->temporaryUrl() : null;
    }

    public function removeAvatar()
    {
        $user = Auth::user();
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
        $user->update(['avatar' => null]);
        $this->currentAvatar = null;
        $this->avatarPreview = null;
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
            $this->avatarPreview = null;
            $this->avatar = null;
        }

        if ($this->new_password) {
            $data['password'] = Hash::make($this->new_password);
        }

        $user->update($data);

        // ✅ Dispatch the correct URL (Storage::url) so the header updates instantly
        $this->dispatch('avatar-updated', url: $newAvatarPath ? Storage::url($newAvatarPath) : null);

        session()->flash('message', 'Profile updated successfully.');
        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
    }

    /** Helper for the Blade template – returns the actual public URL */
    public function avatarUrl(?string $path): ?string
    {
        if (!$path) return null;
        if (str_starts_with($path, 'http')) return $path;   // temp preview
        return Storage::url($path);
    }
};
?>

<div class="relative z-10 min-h-screen py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-2xl mx-auto space-y-6">
        @if (session()->has('message'))
            <div class="glass-card border-l-4 border-l-brand-400 p-4 text-sm text-white/80 flex items-center gap-3">
                <svg class="w-4 h-4 text-brand-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                {{ session('message') }}
            </div>
        @endif

        <div class="flex items-center justify-between">
            <h1 class="font-display text-3xl font-bold text-white">My Profile</h1>
            <a href="{{ route('home') }}" wire:navigate class="text-sm font-medium text-white/50 hover:text-brand-400 transition-colors flex items-center gap-1">&larr; Home</a>
        </div>

        <form wire:submit="updateProfile" class="space-y-6">
            <div class="glass-card !rounded-2xl p-6 sm:p-8">
                {{-- Avatar Section --}}
                <div class="flex flex-col sm:flex-row items-center gap-6 mb-8">
                    <div class="relative">
                        @php $displayUrl = $avatarPreview ?: $this->avatarUrl($currentAvatar); @endphp
                        @if($displayUrl)
                            <img src="{{ $displayUrl }}" class="w-24 h-24 rounded-full object-cover border-2 border-brand-400" alt="Avatar">
                        @else
                            <div class="w-24 h-24 rounded-full bg-brand-500/20 flex items-center justify-center text-brand-400 text-3xl font-bold border-2 border-brand-400/30">
                                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
                            </div>
                        @endif
                        <label class="absolute bottom-0 right-0 bg-brand-600 hover:bg-brand-500 text-white rounded-full p-1.5 cursor-pointer shadow-lg transition-colors"
                               wire:loading.class="opacity-50 pointer-events-none"
                               wire:target="avatar">
                            <svg wire:loading.remove wire:target="avatar" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <svg wire:loading wire:target="avatar" class="w-4 h-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <input type="file" wire:model="avatar" accept="image/*" class="hidden">
                        </label>
                    </div>
                    <div class="flex-1">
                        <h2 class="text-xl font-semibold text-white">{{ Auth::user()->name }}</h2>
                        <p class="text-sm text-white/60">{{ Auth::user()->email }}</p>
                        @if($displayUrl)
                            <button type="button" wire:click="removeAvatar" class="mt-2 text-xs text-red-400 hover:text-red-300 transition-colors">Remove photo</button>
                        @endif
                    </div>
                </div>

                {{-- Name & Email --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-1">Full Name</label>
                        <input type="text" wire:model="name" class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        @error('name') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white/70 mb-1">Email Address</label>
                        <input type="email" wire:model="email" class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        @error('email') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- Password Change --}}
                <div class="border-t border-white/10 pt-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Change Password</h3>
                    <p class="text-sm text-white/40 mb-4">Leave blank to keep your current password.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">New Password</label>
                            <input type="password" wire:model="new_password" class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                            @error('new_password') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-white/70 mb-1">Confirm New Password</label>
                            <input type="password" wire:model="new_password_confirmation" class="w-full bg-white/5 border border-white/10 rounded-xl py-3 px-4 text-sm text-white placeholder-white/30 focus:outline-none focus:ring-2 focus:ring-brand-500/50 focus:border-brand-500 transition">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" wire:loading.attr="disabled"
                        class="bg-brand-600 hover:bg-brand-500 text-white font-medium py-3 px-8 rounded-xl shadow-lg shadow-brand-500/20 transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                    <span wire:loading.remove>Save Changes</span>
                    <span wire:loading class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
                <a href="{{ route('home') }}" wire:navigate class="glass px-6 py-3 rounded-xl text-white/80 hover:bg-white/10 font-medium transition">Cancel</a>
            </div>
        </form>
    </div>
</div>