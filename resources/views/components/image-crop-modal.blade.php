{{--
    Image crop modal — singleton per page. Cropper.js v2 (web components).

    Include once per SFC that uses `imageCropper()`:

        <x-image-crop-modal />

    Notes
    - Alpine calls `init()` automatically, so there is NO `x-init` here.
    - Visibility is always toggled with :class, never x-show.
    - wire:ignore keeps the custom-element state alive across Livewire morphs.
    - The selection id is generated per instance so two modals on the same
      page never share a `#crop-selection` target.
    - The aspect ratio is locked to 1:1 in the SFC factory.
--}}
@php
    $iconBtn = 'inline-flex items-center justify-center h-11 w-11 sm:h-8 sm:w-8 rounded-lg text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:cursor-not-allowed';
    $label   = 'text-xs font-semibold text-gray-700 dark:text-gray-300';
    $cropId  = 'crop-' . \Illuminate\Support\Str::random(10);
@endphp

<div
    x-data="cropModal({ selectionId: '{{ $cropId }}' })"
    x-on:keydown.escape.window="open && cancel()"
    :class="open ? '' : 'hidden'"
    wire:ignore
    tabindex="-1"
    class="fixed inset-0 z-[3000] outline-none"
    role="dialog"
    aria-modal="true"
    aria-labelledby="crop-modal-title"
>
    {{-- Backdrop --}}
    <div
        x-on:click="cancel()"
        class="absolute inset-0 bg-black/75 backdrop-blur-sm"
        aria-hidden="true"
    ></div>

    {{-- Dialog shell --}}
    <div class="absolute inset-0 flex items-center justify-center p-3 sm:p-4 pointer-events-none">
        <div class="relative w-full max-w-4xl bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[94vh] pointer-events-auto">

            {{-- Header --}}
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-200 dark:border-gray-800 shrink-0">
                <div class="min-w-0">
                    <h3 id="crop-modal-title" class="text-sm font-semibold text-gray-900 dark:text-white truncate" x-text="title"></h3>
                    <p
                        x-text="description || ''"
                        :class="description ? 'block' : 'hidden'"
                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate"
                    ></p>
                </div>
                <button
                    type="button"
                    x-on:click="cancel()"
                    class="shrink-0 {{ $iconBtn }}"
                    aria-label="Close"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="flex-1 min-h-0 overflow-y-auto md:overflow-hidden md:flex">

                {{-- Canvas + tools --}}
                <div class="md:flex-1 md:min-w-0 flex flex-col">
                    <div class="bg-gray-100 dark:bg-gray-950">
                        <div class="w-full h-[42vh] md:h-[56vh] max-h-[620px] min-h-[260px]">
                            <cropper-canvas background style="width: 100%; height: 100%; display: block;">
                                <cropper-image
                                    alt=""
                                    rotatable
                                    scalable
                                    skewable
                                    translatable
                                ></cropper-image>

                                <cropper-shade hidden></cropper-shade>
                                <cropper-handle action="select" plain></cropper-handle>

                                <cropper-selection
                                    id="{{ $cropId }}"
                                    initial-coverage="0.85"
                                    outlined
                                    movable
                                    resizable
                                    aspect-ratio="1"
                                >
                                    <cropper-grid role="grid" bordered covered :hidden="!grid"></cropper-grid>
                                    <cropper-crosshair centered></cropper-crosshair>
                                    <cropper-handle action="move" theme-color="rgba(255,255,255,0.35)"></cropper-handle>
                                    <cropper-handle action="n-resize"></cropper-handle>
                                    <cropper-handle action="e-resize"></cropper-handle>
                                    <cropper-handle action="s-resize"></cropper-handle>
                                    <cropper-handle action="w-resize"></cropper-handle>
                                    <cropper-handle action="ne-resize"></cropper-handle>
                                    <cropper-handle action="nw-resize"></cropper-handle>
                                    <cropper-handle action="se-resize"></cropper-handle>
                                    <cropper-handle action="sw-resize"></cropper-handle>
                                </cropper-selection>
                            </cropper-canvas>
                        </div>
                    </div>

                    {{-- Tool row --}}
                    <div class="flex items-center gap-1 px-4 py-2 border-t border-gray-200 dark:border-gray-800 flex-wrap">
                        <button type="button" x-on:click="rotate(-90)" class="{{ $iconBtn }}" title="Rotate left" aria-label="Rotate left">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                            </svg>
                        </button>
                        <button type="button" x-on:click="rotate(90)" class="{{ $iconBtn }}" title="Rotate right" aria-label="Rotate right">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2m18-10l-6 6m6-6l-6-6"/>
                            </svg>
                        </button>
                        <button type="button" x-on:click="flip('h')" class="{{ $iconBtn }}" title="Flip horizontally" aria-label="Flip horizontally">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v18M9 6l-6 6 6 6M15 6l6 6-6 6"/>
                            </svg>
                        </button>
                        <button type="button" x-on:click="flip('v')" class="{{ $iconBtn }}" title="Flip vertically" aria-label="Flip vertically">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12h18M6 9l6-6 6 6M6 15l6 6 6-6"/>
                            </svg>
                        </button>
                        <button
                            type="button"
                            x-on:click="grid = !grid"
                            :class="grid ? 'text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10' : ''"
                            class="{{ $iconBtn }}"
                            title="Toggle grid"
                            aria-label="Toggle grid"
                            :aria-pressed="grid"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4zM9.33 4v16M14.67 4v16M4 9.33h16M4 14.67h16"/>
                            </svg>
                        </button>
                        <button type="button" x-on:click="reset()" class="{{ $iconBtn }}" title="Reset" aria-label="Reset">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>

                        <p class="ml-auto hidden sm:block text-xs text-gray-500 dark:text-gray-400">Drag to move, scroll to zoom</p>
                    </div>
                </div>

                {{-- Side panel --}}
                <div class="md:w-72 shrink-0 border-t md:border-t-0 md:border-l border-gray-200 dark:border-gray-800 p-4 space-y-5 md:overflow-y-auto">

                    {{-- Preview --}}
                    <div>
                        <p class="{{ $label }} mb-2">Preview</p>
                        <div class="flex items-end gap-4">
                            <div
                                :class="round ? 'rounded-full' : 'rounded-lg'"
                                class="overflow-hidden bg-gray-200 dark:bg-gray-800 ring-1 ring-gray-300 dark:ring-gray-700"
                            >
                                <cropper-viewer selection="#{{ $cropId }}" resize="vertical" style="width: 112px; display: block;"></cropper-viewer>
                            </div>
                            <div
                                :class="round ? 'rounded-full' : 'rounded-md'"
                                class="overflow-hidden bg-gray-200 dark:bg-gray-800 ring-1 ring-gray-300 dark:ring-gray-700"
                            >
                                <cropper-viewer selection="#{{ $cropId }}" resize="vertical" style="width: 40px; display: block;"></cropper-viewer>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400" x-text="dims ? ('Output ' + dims) : ''"></p>
                        <p
                            :class="lowRes ? 'block' : 'hidden'"
                            class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                            x-text="'Low resolution. Aim for at least ' + minSize + ' px.'"
                        ></p>
                    </div>

                    {{-- Zoom --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="crop-zoom" class="{{ $label }}">Zoom</label>
                            <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="zoomPct + '%'"></span>
                        </div>
                        <div class="flex items-center gap-1">
                            <button type="button" x-on:click="zoomBy(-0.15)" class="{{ $iconBtn }}" title="Zoom out" aria-label="Zoom out">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.2-5.2m0 0A7.5 7.5 0 105.2 5.2a7.5 7.5 0 0010.6 10.6zM13.5 10.5h-6"/>
                                </svg>
                            </button>
                            <input
                                id="crop-zoom"
                                type="range" min="25" max="400" step="1"
                                :value="zoomPct"
                                x-on:input="setZoom($event.target.value)"
                                class="flex-1 min-w-0 accent-primary-600"
                            >
                            <button type="button" x-on:click="zoomBy(0.15)" class="{{ $iconBtn }}" title="Zoom in" aria-label="Zoom in">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.2-5.2m0 0A7.5 7.5 0 105.2 5.2a7.5 7.5 0 0010.6 10.6zM10.5 7.5v6m3-3h-6"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Straighten --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="crop-straighten" class="{{ $label }}">Straighten</label>
                            <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400" x-text="angle + '°'"></span>
                        </div>
                        <input
                            id="crop-straighten"
                            type="range" min="-45" max="45" step="0.5"
                            :value="angle"
                            x-on:input="setStraighten($event.target.value)"
                            x-on:dblclick="setStraighten(0)"
                            class="w-full accent-primary-600"
                        >
                    </div>

                    {{-- Round crop --}}
                    <label class="flex items-center gap-2 min-h-[44px] text-xs text-gray-700 dark:text-gray-300 cursor-pointer select-none">
                        <input type="checkbox" x-model="round" class="rounded border-gray-300 dark:border-gray-600 text-primary-600 focus:ring-primary-500/50">
                        Round crop (transparent corners)
                    </label>

                    <p
                        :class="error ? 'block' : 'hidden'"
                        class="text-xs font-medium text-red-600 dark:text-red-400"
                        role="alert"
                        x-text="error"
                    ></p>
                </div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-800 shrink-0 flex-wrap">
                <button
                    type="button"
                    x-on:click="cancelAll()"
                    :disabled="processing"
                    :class="queueTotal > 1 ? 'block' : 'hidden'"
                    class="mr-auto px-4 py-2 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500/50 disabled:opacity-50"
                >
                    Cancel all
                </button>

                <button
                    type="button"
                    x-on:click="cancel()"
                    :disabled="processing"
                    x-text="queueTotal > 1 ? 'Skip this image' : 'Cancel'"
                    class="px-4 py-2 min-h-[44px] sm:min-h-0 rounded-lg text-xs font-semibold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-50 disabled:cursor-not-allowed"
                ></button>

                <button
                    type="button"
                    x-on:click="confirm()"
                    :disabled="processing"
                    class="inline-flex items-center gap-2 px-4 py-2 min-h-[44px] sm:min-h-0 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition active:scale-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    <svg
                        :class="processing ? 'block animate-spin motion-reduce:animate-none' : 'hidden'"
                        xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" aria-hidden="true"
                    >
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span x-text="processing ? 'Processing…' : confirmLabel"></span>
                </button>
            </div>
        </div>
    </div>
</div>