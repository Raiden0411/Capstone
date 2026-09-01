{{-- resources/views/superadmin/pages/user/⚡edit-user.blade.php --}}
<?php

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

new
#[Layout('superadmin.layouts.app')]
#[Title('Edit User')]
class extends Component {

    use WithFileUploads;

    public User $user;

    public $name = '';
    public $email = '';
    public $password = '';
    public $password_confirmation = '';
    public $phone = '';
    public $avatar;
    public $tenant_id = '';
    public $role = '';
    public string $tenantSearch = '';
    public bool $isPlatformUser = false;
    public bool $is_active = true;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone;
        $this->tenant_id = $user->tenant_id;
        $this->isPlatformUser = is_null($user->tenant_id);
        $this->is_active = (bool) $user->is_active;

        if ($this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
            $this->tenantSearch = $tenant?->name ?? '';
        }

        $this->role = $user->roles->first()?->name ?? '';
    }

    public function updated($property)
    {
        if (in_array($property, ['name', 'email', 'phone'])) {
            $this->$property = trim($this->$property);
        }
    }

    protected function rules()
    {
        return [
            'name'     => ['required', 'string', 'min:3', 'max:255'],
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user->id)],
            'phone'    => ['required', 'string', 'max:20', 'regex:/^(09|\+639)\d{9}$/'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'avatar'   => ['nullable', 'image', 'max:2048'],
            'tenant_id'=> ['nullable', 'exists:tenants,id'],
            'role'     => ['required', 'exists:roles,name'],
            'is_active'=> ['boolean'],
        ];
    }

    public function messages()
    {
        return [
            'phone.regex' => 'Invalid Philippine phone number. Use 09xxxxxxxxx or +639xxxxxxxxx.',
        ];
    }

    #[Computed]
    public function tenants()
    {
        return Tenant::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->when($this->tenantSearch, fn($q) => $q->where('name', 'like', '%' . $this->tenantSearch . '%'))
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::query()
            ->select('name')
            ->orderBy('name')
            ->get()
            ->filter(function ($role) {
                if ($role->name === 'super-admin') {
                    return $this->user->hasRole('super-admin');
                }
                return true;
            })
            ->map(fn($role) => [
                'name'  => $role->name,
                'label' => ucwords(str_replace(['-', '_'], ' ', $role->name)),
            ])
            ->values();
    }

    public function updatedIsPlatformUser($value)
    {
        if ($value) {
            $this->tenant_id = '';
            $this->tenantSearch = '';
        } else {
            if ($this->role === 'tourist') {
                $this->role = '';
            }
        }
    }

    public function clearTenant()
    {
        $this->tenant_id = '';
        $this->tenantSearch = '';
    }

    public function update()
    {
        $this->validate();

        $tenantId = $this->isPlatformUser ? null : ($this->tenant_id ?: null);

        if (!$this->isPlatformUser && !$tenantId) {
            $this->addError('tenant_id', 'Please select a business for this user.');
            return;
        }

        if ($this->user->id === auth()->id() && $this->user->hasRole('super-admin') && $this->role !== 'super-admin') {
            $this->addError('role', 'You cannot remove your own Super Admin role.');
            return;
        }

        DB::transaction(function () use ($tenantId) {
            $avatarPath = $this->user->avatar;
            if ($this->avatar) {
                $avatarPath = $this->avatar->store('user-avatars', 'public');
            }

            $data = [
                'name'      => $this->name,
                'email'     => $this->email,
                'phone'     => $this->phone,
                'avatar'    => $avatarPath,
                'tenant_id' => $tenantId,
                'is_active' => $this->is_active,
            ];

            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            }

            $this->user->update($data);
            $this->user->syncRoles([$this->role]);
        });

        session()->flash('message', "User '{$this->user->name}' updated successfully.");
        return $this->redirectRoute('superadmin.users.index', navigate: true);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-3xl mx-auto space-y-6"
     x-data="{ showPassword: false, showConfirmPassword: false, avatarPreview: null }">

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-md text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Edit User</h1>
        </div>
        <a href="{{ route('superadmin.users.index') }}" wire:navigate
           class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-full border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 text-sm font-semibold transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Users
        </a>
    </div>

    <form wire:submit="update" class="card p-6 space-y-6">

        {{-- Basic Information --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Full Name *</label>
                <input type="text" wire:model="name" class="input">
                @error('name') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email Address *</label>
                <input type="email" wire:model="email" class="input">
                @error('email') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Phone *</label>
                <input type="text" wire:model="phone" class="input" placeholder="09123456789">
                @error('phone') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profile Picture (Optional)</label>
                <div
                    x-data="{ dragging: false }"
                    x-on:dragover.prevent="dragging = true"
                    x-on:dragleave.prevent="dragging = false"
                    x-on:drop.prevent="dragging = false; $refs.avatarInput.files = $event.dataTransfer.files; $refs.avatarInput.dispatchEvent(new Event('change'))"
                    :class="dragging ? 'border-primary-600 bg-blue-50 dark:bg-blue-500/10' : 'border-gray-300 dark:border-gray-600'"
                    class="relative flex items-center gap-4 rounded-xl border-2 border-dashed p-4 transition-colors"
                >
                    @if($user->avatar && !$avatar)
                        <img src="{{ asset('storage/' . $user->avatar) }}" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                    @else
                        <template x-if="avatarPreview">
                            <img :src="avatarPreview" class="h-16 w-16 object-cover rounded-lg border border-gray-200 dark:border-gray-700 shrink-0">
                        </template>
                        <template x-if="!avatarPreview">
                            <div class="h-16 w-16 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-gray-400 shrink-0">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 11c2.209 0 4-1.791 4-4s-1.791-4-4-4-4 1.791-4 4 1.791 4 4 4zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z"/></svg>
                            </div>
                        </template>
                    @endif
                    <div class="flex-1 min-w-0">
                        <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-600">
                            {{ $avatar ? 'Change photo' : 'Upload a photo' }}
                        </span>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Drag & drop, or click to browse. PNG/JPG up to 2MB.</p>
                        <div wire:loading wire:target="avatar" class="text-xs text-blue-500 mt-1 flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                            Uploading…
                        </div>
                    </div>
                    @if ($avatar)
                        <button type="button" wire:click="$set('avatar', null)" @click="avatarPreview = null" class="relative z-10 shrink-0 text-xs font-semibold text-red-500 hover:text-red-700 active:scale-95 transition-transform">Remove</button>
                    @endif
                    <input x-ref="avatarInput" type="file" wire:model="avatar" accept="image/*"
                           @change="avatarPreview = URL.createObjectURL($refs.avatarInput.files[0])"
                           class="absolute inset-0 opacity-0 cursor-pointer">
                </div>
                @error('avatar') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Password --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    New Password <span class="text-gray-400 dark:text-gray-500 font-normal">(Optional)</span>
                </label>
                <div class="relative">
                    <input :type="showPassword ? 'text' : 'password'" wire:model="password" class="input pr-10" placeholder="Leave blank to keep current">
                    <button type="button" @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                            tabindex="-1" aria-label="Toggle password visibility">
                        <svg x-show="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                @error('password') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm New Password</label>
                <div class="relative">
                    <input :type="showConfirmPassword ? 'text' : 'password'" wire:model="password_confirmation" class="input pr-10" placeholder="••••••••">
                    <button type="button" @click="showConfirmPassword = !showConfirmPassword"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition active:scale-95 focus-visible:ring-2 focus-visible:ring-primary-500/50 rounded"
                            tabindex="-1" aria-label="Toggle confirm password visibility">
                        <svg x-show="!showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <svg x-show="showConfirmPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                    </button>
                </div>
                @error('password_confirmation') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        {{-- Access & Permissions --}}
        <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Access & Permissions</h3>

            {{-- Platform User Toggle --}}
            <div class="mb-4">
                <div class="flex items-center gap-3">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="isPlatformUser" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                    </label>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Platform User (No Business Affiliation)</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 ml-14">Enable if this user should have global platform access without being tied to a specific business.</p>
            </div>

            {{-- Tenant Selection --}}
            @if(!$isPlatformUser)
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Business (Tenant) *</label>
                    <input type="text" wire:model.live.debounce.300ms="tenantSearch"
                           placeholder="Search businesses…"
                           class="input mb-2">
                    <select wire:model="tenant_id" class="select">
                        <option value="">-- Select a business --</option>
                        @foreach($this->tenants as $tenant)
                            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                        @endforeach
                    </select>
                    @error('tenant_id') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            @endif

            {{-- Role Select --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Assign Role *</label>
                <select wire:model="role" class="select">
                    <option value="">-- Select a role --</option>
                    @foreach($this->availableRoles as $roleData)
                        <option value="{{ $roleData['name'] }}">{{ $roleData['label'] }}</option>
                    @endforeach
                </select>
                @error('role') <span class="text-red-500 dark:text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            {{-- Active Toggle --}}
            <div class="mt-4 flex items-center gap-3">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" wire:model="is_active" class="sr-only peer">
                    <div class="w-11 h-6 bg-gray-200 dark:bg-gray-600 rounded-full peer peer-checked:bg-primary-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-full"></div>
                </label>
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Active Account</span>
            </div>
        </div>

        {{-- Form Actions --}}
        <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <button type="submit" wire:loading.attr="disabled"
                    class="btn-primary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                <span wire:loading.remove>Update User</span>
                <span wire:loading class="inline-flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Saving...
                </span>
            </button>
            <a href="{{ route('superadmin.users.index') }}" wire:navigate
               class="btn-secondary w-full sm:w-auto active:scale-95 transition-transform inline-flex items-center justify-center gap-2 focus-visible:ring-2 focus-visible:ring-primary-500/50">
                Cancel
            </a>
        </div>
    </form>
</div>