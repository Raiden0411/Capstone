<?php

use Livewire\Component;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    // 1. Define your component properties
    public string $name = '';
    public string $email = '';
    public string $contact_number = '';
    public string $address = '';

    // 2. Load the current tenant's data when the page loads
    public function mount()
    {
        $tenant = Auth::user()->tenant;
        
        if ($tenant) {
            $this->name = $tenant->name;
            $this->email = $tenant->email;
            $this->contact_number = $tenant->contact_number;
            $this->address = $tenant->address;
        }
    }

    // 3. Save the updated data
    public function save()
    {
        // Real-time validation
        $this->validate([
            'name' => 'required|max:255',
            'email' => 'required|email|max:255',
            'contact_number' => 'required|max:50',
            'address' => 'required|max:255',
        ]);

        // Update the tenant
        $tenant = Auth::user()->tenant;
        $tenant->update([
            'name' => $this->name,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'address' => $this->address,
        ]);

        // Optional: Flash a success message
        session()->flash('message', 'Business Profile updated successfully!');
    }
};
?>

<div>
    <h2 class="text-2xl font-bold mb-4">Business Profile Settings</h2>

    @if (session()->has('message'))
        <div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50">
            {{ session('message') }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-4 max-w-lg">
        <div>
            <label class="block text-sm font-medium">Business Name</label>
            <input type="text" wire:model="name" class="border p-2 w-full rounded">
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Email</label>
            <input type="email" wire:model="email" class="border p-2 w-full rounded">
            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Contact Number</label>
            <input type="text" wire:model="contact_number" class="border p-2 w-full rounded">
            @error('contact_number') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Address</label>
            <textarea wire:model="address" class="border p-2 w-full rounded" rows="3"></textarea>
            @error('address') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Save Changes
        </button>
    </form>
</div>