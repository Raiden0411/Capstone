<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Tenant;
use Spatie\Permission\Models\Role;

new 
#[Layout('superadmin.layouts.app')] 
#[Title('Edit Global User')] 
class extends Component {
    
    public User $user;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate]
    public $email = '';
    
    public $password = '';
    public $password_confirmation = '';
    
    #[Validate('nullable|exists:tenants,id')]
    public $tenant_id = '';
    
    #[Validate('required|exists:roles,name')]
    public $role = '';
    
    public string $tenantSearch = '';
    public bool $isPlatformUser = false;

    public function mount(User $user)
    {
        $this->user = $user;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->tenant_id = $user->tenant_id;
        $this->isPlatformUser = is_null($user->tenant_id);
        
        if ($this->tenant_id) {
            $tenant = Tenant::find($this->tenant_id);
            $this->tenantSearch = $tenant?->name ?? '';
        }
        
        $this->role = $user->roles->first()?->name ?? '';
    }

    protected function rules()
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($this->user->id),
            ],
        ];
    }

    #[Computed]
    public function tenants() 
    { 
        return Tenant::orderBy('name')
            ->when($this->tenantSearch, function ($query) {
                $query->where('name', 'like', '%' . $this->tenantSearch . '%');
            })
            ->limit(50)
            ->get();
    }

    #[Computed]
    public function roles() 
    { 
        return Role::orderBy('name')->get();
    }

    public function updatedIsPlatformUser($value)
    {
        if ($value) {
            $this->tenant_id = '';
            $this->tenantSearch = '';
        }
    }

    public function selectTenant($id, $name)
    {
        $this->tenant_id = $id;
        $this->tenantSearch = $name;
        $this->isPlatformUser = false;
    }

    public function clearTenant()
    {
        $this->tenant_id = '';
        $this->tenantSearch = '';
    }

    public function update()
    {
        $this->validate();

        // Validate password confirmation if a new password is provided
        if (!empty($this->password) && $this->password !== $this->password_confirmation) {
            $this->addError('password_confirmation', 'The password confirmation does not match.');
            return;
        }

        // Prevent super-admin from losing their own super-admin role
        if ($this->user->id === auth()->id() && $this->user->hasRole('super-admin') && $this->role !== 'super-admin') {
            $this->addError('role', 'You cannot remove your own Super Admin role.');
            return;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'tenant_id' => $this->isPlatformUser ? null : ($this->tenant_id ?: null),
        ];

        if (!empty($this->password)) {
            $data['password'] = Hash::make($this->password);
        }

        $this->user->update($data);
        $this->user->syncRoles([$this->role]);
        
        session()->flash('message', "User '{$this->user->name}' updated successfully.");
        return $this->redirectRoute('superadmin.users.index', navigate: true);
    }
};
?>

<div>
    {{-- Tom Select CDN --}}
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet" data-navigate-once>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js" data-navigate-once></script>

    <div class="p-6 sm:p-10 max-w-3xl mx-auto">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-slate-800">Edit User</h1>
            <p class="text-slate-500">Update platform access, business assignments, or reset passwords.</p>
        </div>

        <form wire:submit="update" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-6">
            {{-- Basic Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Full Name</label>
                    <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                    @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email Address</label>
                    <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                    @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Password (Optional) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        New Password <span class="text-slate-400 font-normal">(Optional)</span>
                    </label>
                    <input type="password" wire:model="password" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="Leave blank to keep current">
                    @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                    <input type="password" wire:model="password_confirmation" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500" placeholder="••••••••">
                    @error('password_confirmation') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Tenant & Role Section --}}
            <div class="pt-4 border-t border-slate-100">
                <h3 class="text-lg font-medium text-slate-800 mb-4">Access & Permissions</h3>
                
                {{-- Platform User Toggle --}}
                <div class="mb-4">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="isPlatformUser" class="sr-only peer">
                        <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        <span class="ms-3 text-sm font-medium text-slate-700">Platform User (No Business Affiliation)</span>
                    </label>
                    <p class="text-xs text-slate-500 mt-1 ml-14">Enable if this user should have global platform access without being tied to a specific business.</p>
                </div>

                {{-- Tenant Selection --}}
                @if(!$isPlatformUser)
                <div class="mb-4" x-data="tenantSelector()" x-init="init()">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Assign Business (Tenant)</label>
                    
                    <div wire:ignore>
                        <select x-ref="select" class="w-full">
                            <option value="">Search for a business...</option>
                            @foreach($this->tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    @error('tenant_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                    <p class="text-xs text-slate-500 mt-1">Select the business this user belongs to.</p>
                </div>
                @endif

                {{-- Role Selection --}}
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Assign System Role</label>
                    <select wire:model="role" class="w-full rounded-lg border-slate-300 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- Select a Role --</option>
                        @foreach($this->roles as $roleData)
                            <option value="{{ $roleData->name }}">
                                {{ ucwords(str_replace(['-', '_'], ' ', $roleData->name)) }}
                                @if($roleData->name === 'super-admin')
                                    (Full Platform Access)
                                @elseif($roleData->name === 'admin')
                                    (Business Owner)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>
            </div>
            
            {{-- Form Actions --}}
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2.5 px-6 rounded-lg shadow-sm transition flex items-center gap-2 data-loading:opacity-75">
                    <span class="in-data-loading:hidden">Update User</span>
                    <span class="not-in-data-loading:hidden">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Saving...
                    </span>
                </button>
                <a href="{{ route('superadmin.users.index') }}" wire:navigate class="bg-white text-slate-700 border border-slate-300 hover:bg-slate-50 font-medium py-2.5 px-6 rounded-lg transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    function tenantSelector() {
        return {
            ts: null,
            init() {
                let checkInterval = setInterval(() => {
                    if (typeof TomSelect !== 'undefined') {
                        clearInterval(checkInterval);
                        this.initTomSelect();
                    }
                }, 100);
            },
            initTomSelect() {
                const tenantData = @js($this->tenants->map(fn($t) => ['id' => $t->id, 'name' => $t->name]));
                const currentTenantId = @js($this->tenant_id);
                
                this.ts = new TomSelect(this.$refs.select, {
                    create: false,
                    placeholder: 'Search for a business...',
                    valueField: 'id',
                    labelField: 'name',
                    searchField: ['name'],
                    options: tenantData,
                    items: currentTenantId ? [currentTenantId] : [],
                    onChange: (value) => {
                        if (value) {
                            const option = this.ts.options[value];
                            @this.selectTenant(value, option.name);
                        } else {
                            @this.clearTenant();
                        }
                    }
                });

                @this.$watch('tenantSearch', (value) => {
                    if (!value && this.ts) {
                        this.ts.clear();
                    }
                });
                
                @this.$watch('isPlatformUser', (value) => {
                    if (value && this.ts) {
                        this.ts.clear();
                    }
                });
            }
        };
    }
</script>