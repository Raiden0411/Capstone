{{-- resources/views/superadmin/pages/map-marker/⚡manage-marker-categories.blade.php --}}
<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use App\Models\SiteSetting;

new
#[Layout('superadmin.layouts.app')]
#[Title('Manage Marker Categories')]
class extends Component
{
    use WithFileUploads;

    public array $categories = [];
    public string $newKey = '';
    public string $newLabel = '';
    public string $newColor = '#3b82f6';
    public $newIcon;

    public function mount(): void
    {
        $this->categories = SiteSetting::getValue('marker_categories', []);
    }

    protected function rules(): array
    {
        return [
            'categories.*.key'   => ['required', 'string', 'max:50'],
            'categories.*.label' => ['required', 'string', 'max:100'],
            'categories.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function addCategory(): void
    {
        $this->validate([
            'newKey' => 'required|alpha_dash|max:50',
            'newLabel' => 'required|string|max:100',
            'newColor' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'newIcon' => 'nullable|file|mimes:svg|max:1024',
        ]);

        $iconPath = null;
        if ($this->newIcon) {
            $iconPath = $this->newIcon->store('marker-icons', 'public');
        }

        $this->categories[] = [
            'key'       => $this->newKey,
            'label'     => $this->newLabel,
            'color'     => $this->newColor,
            'icon_path' => $iconPath,
        ];

        $this->saveCategories();
        $this->reset(['newKey', 'newLabel', 'newColor', 'newIcon']);
        $this->dispatch('toast', message: 'Category added.', type: 'success');
    }

    public function updateCategory(int $index): void
    {
        $this->validate([
            "categories.$index.label" => 'required|string|max:100',
            "categories.$index.color" => 'required|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        if (isset($this->categories[$index]['icon_file']) && $this->categories[$index]['icon_file']) {
            $file = $this->categories[$index]['icon_file'];
            $newPath = $file->store('marker-icons', 'public');
            if (!empty($this->categories[$index]['icon_path'])) {
                Storage::disk('public')->delete($this->categories[$index]['icon_path']);
            }
            $this->categories[$index]['icon_path'] = $newPath;
            unset($this->categories[$index]['icon_file']);
        }

        $this->saveCategories();
        $this->dispatch('toast', message: 'Category updated.', type: 'success');
    }

    public function removeCategory(int $index): void
    {
        if (!empty($this->categories[$index]['icon_path'])) {
            Storage::disk('public')->delete($this->categories[$index]['icon_path']);
        }
        unset($this->categories[$index]);
        $this->categories = array_values($this->categories);
        $this->saveCategories();
        $this->dispatch('toast', message: 'Category removed.', type: 'success');
    }

    public function toggleActive(int $index): void
    {
        $this->categories[$index]['is_active'] = !($this->categories[$index]['is_active'] ?? true);
        $this->saveCategories();
    }

    protected function saveCategories(): void
    {
        SiteSetting::setValue('marker_categories', $this->categories);
    }
};
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-6 border-b border-gray-200 dark:border-gray-700">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-gray-900 dark:text-white">Marker Categories</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage the categories used for sub‑locations across the map.</p>
        </div>
    </div>

    @if (session()->has('message'))
        <div class="bg-green-50 dark:bg-green-500/10 border border-green-200 dark:border-green-500/30 border-l-4 border-l-green-500 p-4 rounded-lg text-sm text-green-700 dark:text-green-300 font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Add new category --}}
    <div class="card p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Add Category</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Key (slug)</label>
                <input type="text" wire:model="newKey" class="input" placeholder="e.g. restaurant">
                @error('newKey') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Label</label>
                <input type="text" wire:model="newLabel" class="input" placeholder="Restaurant">
                @error('newLabel') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                <input type="color" wire:model="newColor" class="h-10 w-full rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer">
                @error('newColor') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Icon (SVG)</label>
                <input type="file" wire:model="newIcon" accept=".svg" class="input">
                @error('newIcon') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
            </div>
        </div>
        <button type="button" wire:click="addCategory" class="btn-primary mt-4">Add Category</button>
    </div>

    {{-- Existing categories --}}
    <div class="space-y-4">
        @foreach($categories as $index => $cat)
            <div class="card p-6 flex flex-wrap items-center gap-4">
                <div class="flex items-center gap-3 min-w-[200px]">
                    @if(!empty($cat['icon_path']))
                        <img src="{{ Storage::url($cat['icon_path']) }}" class="h-8 w-8 shrink-0" alt="{{ $cat['label'] }}">
                    @else
                        <div class="h-8 w-8 bg-gray-200 dark:bg-gray-700 rounded-full"></div>
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900 dark:text-white">{{ $cat['label'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cat['key'] }}</p>
                    </div>
                </div>

                <div class="flex flex-1 flex-wrap items-center gap-3">
                    <input type="text" wire:model="categories.{{ $index }}.label" class="input !w-40" placeholder="Label">
                    <input type="color" wire:model="categories.{{ $index }}.color" class="h-10 w-16 rounded-lg border border-gray-300 dark:border-gray-600 cursor-pointer">
                    <input type="file" wire:model="categories.{{ $index }}.icon_file" accept=".svg" class="input !w-52">
                    @if(!empty($cat['icon_path']))
                        <span class="text-xs text-gray-400">Upload to replace</span>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="updateCategory({{ $index }})" class="btn-primary !py-2 !px-4 text-xs">Save</button>
                    <button type="button" wire:click="removeCategory({{ $index }})" class="btn-danger !py-2 !px-4 text-xs">Remove</button>
                </div>
            </div>
        @endforeach
    </div>
</div>