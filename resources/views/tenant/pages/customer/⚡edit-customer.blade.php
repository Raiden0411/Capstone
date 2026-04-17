<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use App\Models\Customer;

new 
#[Layout('tenant.layouts.app')]
#[Title('Edit Customer')]
class extends Component {
    public Customer $customer;
    
    #[Validate('required|string|max:255')]
    public $name = '';
    
    #[Validate('nullable|string|max:20')]
    public $phone = '';
    
    #[Validate('nullable|email|max:255')]
    public $email = '';
    
    #[Validate('nullable|string')]
    public $address = '';
    
    #[Validate('nullable|string')]
    public $notes = '';

    public function mount(Customer $customer)
    {
        $this->customer = $customer;
        $this->name = $customer->name;
        $this->phone = $customer->phone;
        $this->email = $customer->email;
        $this->address = $customer->address;
        $this->notes = $customer->notes;
    }

    public function update()
    {
        $this->validate();
        $this->customer->update([
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
        ]);
        session()->flash('message', 'Customer updated successfully.');
        return $this->redirectRoute('tenant.customers.index', navigate: true);
    }
};
?>

<div class="p-6 max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Edit Customer</h1>
    <form wire:submit="update" class="bg-white p-6 rounded-xl shadow space-y-4">
        <div>
            <label class="block text-sm font-medium mb-1">Full Name *</label>
            <input type="text" wire:model="name" class="w-full rounded-lg border-slate-300">
            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Phone</label>
                <input type="text" wire:model="phone" class="w-full rounded-lg border-slate-300">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Email</label>
                <input type="email" wire:model="email" class="w-full rounded-lg border-slate-300">
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Address</label>
            <textarea wire:model="address" rows="2" class="w-full rounded-lg border-slate-300"></textarea>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Notes</label>
            <textarea wire:model="notes" rows="2" class="w-full rounded-lg border-slate-300"></textarea>
        </div>
        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg">Update Customer</button>
            <a href="{{ route('tenant.customers.index') }}" wire:navigate class="border px-6 py-2 rounded-lg hover:bg-slate-50">Cancel</a>
        </div>
    </form>
</div>