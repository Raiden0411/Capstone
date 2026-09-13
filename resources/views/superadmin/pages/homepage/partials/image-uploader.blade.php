{{-- resources/views/superadmin/pages/homepage/partials/image-uploader.blade.php --}}
@props([
    'key',                    // Livewire property name, e.g. 'heroImage'
    'existing' => null,       // URL of the currently-stored image, or null
    'label' => null,          // optional caption, e.g. 'Lead'
    'compact' => false,       // true for the small 3-across grid
])

@php
    $minHeight = $compact ? 'min-h-[110px]' : 'min-h-[140px]';
    $imgHeight = $compact ? 'max-h-20' : 'max-h-32';
@endphp

<div x-on:dragover.prevent="draggingKey = '{{ $key }}'"
     x-on:dragleave.prevent="draggingKey = null"
     x-on:drop.prevent="handleDrop($event, '{{ $key }}')"
     :class="draggingKey === '{{ $key }}'
         ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10'
         : 'border-gray-300 dark:border-gray-600'"
     class="relative flex items-center justify-center rounded-xl border-2 border-dashed p-3 transition-colors {{ $minHeight }} cursor-pointer overflow-hidden">

    {{-- 1. New file preview --}}
    <template x-if="filePreviews.{{ $key }}">
        <div class="w-full">
            <img :src="filePreviews.{{ $key }}" alt=""
                 class="w-full {{ $imgHeight }} object-cover rounded-lg border border-gray-200 dark:border-gray-700">
            <p class="mt-2 text-[11px] text-primary-600 dark:text-primary-400 font-semibold text-center">
                New — uploads on save
            </p>
        </div>
    </template>

    {{-- 2. Existing stored image --}}
    <template x-if="!filePreviews.{{ $key }} && @js((bool) $existing)">
        <div class="w-full">
            <img src="{{ $existing }}" alt=""
                 class="w-full {{ $imgHeight }} object-cover rounded-lg border border-gray-200 dark:border-gray-700">
            <p class="mt-2 text-[11px] text-gray-500 dark:text-gray-400 font-semibold text-center">
                Current — click to replace
            </p>
        </div>
    </template>

    {{-- 3. Empty --}}
    <template x-if="!filePreviews.{{ $key }} && !@js((bool) $existing)">
        <div class="flex flex-col items-center text-gray-400 dark:text-gray-500">
            <svg class="w-7 h-7 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M4 8h.01M4 4h16a1 1 0 011 1v14a1 1 0 01-1 1H4a1 1 0 01-1-1V5a1 1 0 011-1z"/>
            </svg>
            <span class="text-[11px]">Drag &amp; drop or click</span>
        </div>
    </template>

    {{-- Optional caption --}}
    @if($label)
        <span class="absolute bottom-1.5 left-1.5 rounded bg-black/60 backdrop-blur-sm px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white">
            {{ $label }}
        </span>
    @endif

    {{-- Clear button when a new file is pending --}}
    <button type="button"
            x-show="filePreviews.{{ $key }}"
            @click.stop="clearFile('{{ $key }}')"
            class="absolute top-1.5 right-1.5 z-10 inline-flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow-md hover:bg-red-600 active:scale-95 transition"
            aria-label="Clear new image"
            title="Discard pending upload">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>

    {{-- Real file input --}}
    <input x-ref="file-{{ $key }}"
           type="file"
           wire:model="{{ $key }}"
           accept="image/*"
           class="absolute inset-0 opacity-0 cursor-pointer"
           x-on:change="previewFile($event, '{{ $key }}')">
</div>