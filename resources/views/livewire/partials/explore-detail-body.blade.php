@php
    $sec  = 'border-b border-gray-100 px-5 py-5 dark:border-gray-800';
    $h3   = 'mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400';
    $link = 'inline-flex min-h-[44px] items-center rounded font-medium text-primary-600 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:text-primary-400';
    $pin  = 'M17.657 16.657 13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z';

    $rows = [];
    if ($dt->address || $dt->barangay) {
        $rows[] = [$pin, e($dt->address) . ($dt->barangay && ! str_contains($dt->address ?? '', $dt->barangay) ? '<span class="block text-xs text-gray-500 dark:text-gray-400">' . e($dt->barangay) . '</span>' : '')];
    }
    if ($dt->contact_number) {
        $rows[] = ['M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z', '<a href="tel:' . e($dt->contact_number) . '" class="' . $link . '">' . e($dt->contact_number) . '</a>'];
    }
    if ($dt->email) {
        $rows[] = ['M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z', '<a href="mailto:' . e($dt->email) . '" class="' . $link . ' break-all">' . e($dt->email) . '</a>'];
    }
    if (! empty($hours['opening']) && ! empty($hours['closing'])) {
        $rows[] = ['M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z', '<span class="tabular-nums">' . e($hours['opening']) . ' – ' . e($hours['closing']) . '</span> <span class="ml-1 text-xs font-semibold ' . ($isOpen ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400') . '">(' . e($statusLabel) . ')</span>'];
    }
    $links = collect(['Website' => $site, 'Facebook' => $fb, 'Instagram' => $ig])->filter();
    if ($links->isNotEmpty()) {
        $rows[] = ['M21 12a9 9 0 11-18 0 9 9 0 0118 0zM2 12h20M12 3a15 15 0 014 9 15 15 0 01-4 9 15 15 0 01-4-9 15 15 0 014-9z',
            $links->map(fn ($u, $l) => '<a href="' . e($u) . '" target="_blank" rel="noopener noreferrer" class="' . $link . ' mr-4 text-sm">' . $l . '</a>')->implode('')];
    }
@endphp

@if($isEst)
    <div class="border-b border-gray-200 py-4 text-center dark:border-gray-800">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">Distance from you</p>
        <p class="mt-1 text-base font-semibold tabular-nums text-gray-900 dark:text-white">
            {{ $distText }}@if($durText)<span class="text-xs font-normal text-gray-500 dark:text-gray-400"> · {{ $durText }} drive</span>@endif
        </p>
    </div>

    <section class="{{ $sec }}">
        <h3 class="{{ $h3 }}">{{ $displayType }}</h3>
        <p class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">
            Part of <strong class="font-semibold text-gray-900 dark:text-white">{{ $dt->name }}</strong>.
        </p>
        @if($dt->address)
            <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ $dt->address }}@if($dt->barangay && ! str_contains($dt->address, $dt->barangay)), {{ $dt->barangay }}@endif</p>
        @endif
        <p class="mt-3 font-mono text-xs tabular-nums text-gray-500 dark:text-gray-400">
            {{ number_format((float) ($coord['lat'] ?? 0), 6) }}, {{ number_format((float) ($coord['lng'] ?? 0), 6) }}
        </p>
    </section>
@else
    <div class="grid grid-cols-3 gap-px border-b border-gray-200 bg-gray-200 dark:border-gray-800 dark:bg-gray-800">
        @foreach([['Distance', $distText, $durText ? $durText . ' drive' : null], ['Places', $dt->properties->count(), null], ['Services', $dt->services->count(), null]] as [$label, $value, $sub])
            <div class="bg-white py-3 text-center dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-sm font-semibold tabular-nums text-gray-900 dark:text-white">{{ $value }}</p>
                @if($sub)<p class="text-xs text-gray-400 dark:text-gray-500">{{ $sub }}</p>@endif
            </div>
        @endforeach
    </div>

    @if($desc)
        <section class="{{ $sec }}">
            <h3 class="{{ $h3 }}">About</h3>
            <p class="whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $desc }}</p>
        </section>
    @endif

    @if($rows)
        <section class="{{ $sec }}">
            <h3 class="{{ $h3 }}">Contact &amp; hours</h3>
            <dl class="space-y-3 text-sm">
                @foreach($rows as [$icon, $html])
                    <div class="flex items-start gap-3">
                        <svg class="mt-0.5 size-4 shrink-0 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icon }}"/></svg>
                        <dd class="min-w-0 flex-1 leading-snug text-gray-800 dark:text-gray-200">{!! $html !!}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endif

    @if($dt->properties->isNotEmpty())
        <section class="{{ $sec }}">
            <h3 class="{{ $h3 }}">Places available</h3>
            <div class="space-y-2">
                @foreach($dt->properties as $prop)
                    @php $img = $prop->images->first(); @endphp
                    <div wire:key="{{ $prefix }}-prop-{{ $prop->id }}" class="flex gap-3 rounded-xl p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <div class="size-14 shrink-0 overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                            @if($img)
                                <img src="/storage/{{ ltrim($img->image_path, '/') }}" alt="{{ $prop->name }}" class="size-full object-cover" loading="lazy" decoding="async">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $prop->name }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $prop->propertyType?->name ?? 'Place' }}@if($prop->capacity) · Fits {{ $prop->capacity }}@endif</p>
                            <p class="mt-1 text-sm font-bold tabular-nums text-primary-600 dark:text-primary-400">
                                ₱{{ number_format((float) $prop->price, 2) }} <span class="text-xs font-normal text-gray-500 dark:text-gray-400">/ night</span>
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($dt->services->isNotEmpty())
        <section class="{{ $sec }}">
            <h3 class="{{ $h3 }}">Services</h3>
            <div class="space-y-1.5">
                @foreach($dt->services as $svc)
                    <div wire:key="{{ $prefix }}-svc-{{ $svc->id }}" class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-gray-800/50">
                        <span class="truncate text-sm text-gray-800 dark:text-gray-200">{{ $svc->name }}</span>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-gray-900 dark:text-white">₱{{ number_format((float) $svc->price, 2) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($events->isNotEmpty())
        <section class="{{ $sec }}">
            <h3 class="{{ $h3 }}">Upcoming events</h3>
            <div class="space-y-2">
                @foreach($events as $ev)
                    <div wire:key="{{ $prefix }}-event-{{ $ev->id }}" class="flex items-start gap-3 rounded-xl p-2 transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <div class="min-w-[52px] shrink-0 rounded-xl border border-purple-200/60 bg-purple-50 px-2 py-1.5 text-center dark:border-purple-500/20 dark:bg-purple-900/20">
                            <p class="text-xs font-semibold leading-none text-purple-700 dark:text-purple-300">{{ $ev->start_date->format('M') }}</p>
                            <p class="text-base font-extrabold leading-tight tabular-nums text-purple-700 dark:text-purple-200">{{ $ev->start_date->format('d') }}</p>
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $ev->name }}</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $ev->type }} · {{ $ev->start_date->format('h:i A') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endif