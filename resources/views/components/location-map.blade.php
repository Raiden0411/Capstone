{{--
 ╔══════════════════════════════════════════════════════════╗
 ║  MAP PICKER — Premium Location Selector Component        ║
 ║  Props:                                                  ║
 ║    readonly (bool)  — display-only, no interaction       ║
 ║    height   (str)   — CSS height, default 460px          ║
 ║  Wire binds: $wire.latitude, $wire.longitude             ║
 ╚══════════════════════════════════════════════════════════╝
--}}

@props(['readonly' => false, 'height' => '460px'])

{{-- ── Fonts + Leaflet assets ── --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Mono:wght@400;500&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link  rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

{{-- ════════════════════════════════════════════
     COMPONENT STYLES
═════════════════════════════════════════════ --}}
<style>
/* ── Design tokens ── */
.mpc-root {
    --mp-base:       #050f0d;
    --mp-surface:    #071512;
    --mp-card:       rgba(7,21,18,0.95);
    --mp-brand:      #10b981;
    --mp-brand-dim:  rgba(16,185,129,0.15);
    --mp-brand-glow: rgba(16,185,129,0.3);
    --mp-gold:       #f5c842;
    --mp-red:        #f87171;
    --mp-text:       #ecfdf5;
    --mp-muted:      rgba(236,253,245,0.45);
    --mp-faint:      rgba(236,253,245,0.14);
    --mp-border:     rgba(16,185,129,0.15);
    --mp-border-hi:  rgba(16,185,129,0.4);
    --mp-radius:     14px;
    --mp-radius-sm:  8px;
    --mp-font-head:  'Syne', sans-serif;
    --mp-font-body:  'DM Sans', sans-serif;
    --mp-font-mono:  'DM Mono', monospace;
}

/* ── Root wrapper ── */
.mpc-root {
    font-family: var(--mp-font-body);
    background: var(--mp-base);
    border-radius: 18px;
    border: 1px solid var(--mp-border);
    overflow: hidden;
    position: relative;
    box-shadow:
        0 0 0 1px rgba(16,185,129,0.06),
        0 24px 60px rgba(0,0,0,0.6),
        inset 0 1px 0 rgba(255,255,255,0.04);
}

/* ── Toolbar ── */
.mpc-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 12px;
    border-bottom: 1px solid var(--mp-border);
    background: rgba(5,12,10,0.7);
    backdrop-filter: blur(12px);
    position: relative;
    z-index: 20;
    flex-wrap: wrap;
}

/* ── Search bar ── */
.mpc-search-wrap {
    flex: 1; min-width: 180px;
    position: relative;
}
.mpc-search-icon {
    position: absolute; left: 10px; top: 50%;
    transform: translateY(-50%);
    width: 14px; height: 14px;
    color: var(--mp-muted);
    pointer-events: none;
    flex-shrink: 0;
}
.mpc-search-input {
    width: 100%; box-sizing: border-box;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--mp-border);
    border-radius: 10px;
    padding: 8px 30px 8px 32px;
    font-size: 12px;
    font-family: var(--mp-font-body);
    color: var(--mp-text);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.mpc-search-input::placeholder { color: var(--mp-muted); }
.mpc-search-input:focus {
    border-color: var(--mp-border-hi);
    box-shadow: 0 0 0 3px var(--mp-brand-dim);
}
.mpc-search-clear {
    position: absolute; right: 8px; top: 50%;
    transform: translateY(-50%);
    background: rgba(255,255,255,0.08); border: none;
    border-radius: 50%; width: 16px; height: 16px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; font-size: 9px; color: var(--mp-muted);
    transition: all 0.15s; line-height: 1;
}
.mpc-search-clear:hover { background: var(--mp-brand); color: #fff; }

/* ── Autocomplete dropdown ── */
.mpc-autocomplete {
    position: absolute; top: calc(100% + 4px); left: 0; right: 0;
    background: rgba(7,18,15,0.98);
    backdrop-filter: blur(20px);
    border: 1px solid var(--mp-border-hi);
    border-radius: 12px;
    z-index: 1000;
    overflow: hidden;
    box-shadow: 0 16px 40px rgba(0,0,0,0.6);
    animation: mpFadeDown 0.15s ease;
    max-height: 220px; overflow-y: auto;
}
@keyframes mpFadeDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.mpc-ac-item {
    padding: 9px 12px;
    cursor: pointer;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background 0.12s;
    display: flex; align-items: flex-start; gap: 8px;
}
.mpc-ac-item:last-child { border-bottom: none; }
.mpc-ac-item:hover { background: var(--mp-brand-dim); }
.mpc-ac-item-icon {
    margin-top: 1px; flex-shrink: 0;
    color: var(--mp-brand); opacity: 0.7;
    width: 12px; height: 12px;
}
.mpc-ac-item-name {
    font-size: 12px; font-weight: 600;
    color: var(--mp-text); line-height: 1.3;
}
.mpc-ac-item-sub {
    font-size: 10px; color: var(--mp-muted);
    margin-top: 1px; line-height: 1.3;
}
.mpc-ac-empty {
    padding: 14px 12px; text-align: center;
    font-size: 12px; color: var(--mp-muted);
}
.mpc-ac-loading {
    padding: 14px 12px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    font-size: 11px; color: var(--mp-brand);
}

/* ── Map style group ── */
.mpc-style-group {
    display: flex; gap: 3px;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--mp-border);
    border-radius: 9px;
    padding: 3px;
    flex-shrink: 0;
}
.mpc-style-btn {
    padding: 4px 9px;
    border-radius: 6px; border: 1px solid transparent;
    background: transparent;
    font-size: 10px; font-weight: 600;
    color: var(--mp-muted); cursor: pointer;
    font-family: var(--mp-font-body);
    transition: all 0.18s;
    display: flex; align-items: center; gap: 3px;
    white-space: nowrap;
}
.mpc-style-btn:hover { color: var(--mp-text); }
.mpc-style-btn.active {
    background: var(--mp-brand-dim);
    border-color: var(--mp-border-hi);
    color: var(--mp-brand);
}

/* ── Icon buttons ── */
.mpc-icon-btn {
    width: 32px; height: 32px; border-radius: 9px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--mp-border);
    color: var(--mp-muted); cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.18s; flex-shrink: 0;
    font-size: 14px;
}
.mpc-icon-btn:hover {
    border-color: var(--mp-border-hi); color: var(--mp-brand);
    background: var(--mp-brand-dim);
}
.mpc-icon-btn.active {
    background: var(--mp-brand-dim);
    border-color: var(--mp-brand-glow);
    color: var(--mp-brand);
    box-shadow: 0 0 12px var(--mp-brand-glow);
}
.mpc-icon-btn:disabled { opacity: 0.35; cursor: not-allowed; }

/* ── GPS button (special) ── */
.mpc-gps-btn {
    display: flex; align-items: center; gap: 5px;
    padding: 0 11px; height: 32px;
    border-radius: 9px;
    background: var(--mp-brand-dim);
    border: 1px solid var(--mp-border-hi);
    color: var(--mp-brand);
    font-size: 11px; font-weight: 700;
    font-family: var(--mp-font-body);
    cursor: pointer; transition: all 0.2s;
    white-space: nowrap; flex-shrink: 0;
}
.mpc-gps-btn:hover {
    background: var(--mp-brand); color: #fff;
    box-shadow: 0 4px 16px rgba(16,185,129,0.3);
}
.mpc-gps-btn.scanning {
    background: rgba(16,185,129,0.25);
    animation: mpGpsGlow 1s ease-in-out infinite;
}
@keyframes mpGpsGlow {
    0%, 100% { box-shadow: 0 0 8px rgba(16,185,129,0.3); }
    50%       { box-shadow: 0 0 20px rgba(16,185,129,0.6); }
}

/* ── Map wrapper ── */
.mpc-map-wrap {
    position: relative;
    overflow: hidden;
}

/* ── Crosshair overlay ── */
.mpc-crosshair {
    position: absolute; inset: 0;
    pointer-events: none; z-index: 450;
    display: flex; align-items: center; justify-content: center;
}
.mpc-crosshair-inner {
    position: relative; width: 40px; height: 40px;
}
.mpc-crosshair-h {
    position: absolute; top: 50%; left: 0; right: 0;
    height: 1px; background: rgba(16,185,129,0.5);
    transform: translateY(-50%);
}
.mpc-crosshair-v {
    position: absolute; left: 50%; top: 0; bottom: 0;
    width: 1px; background: rgba(16,185,129,0.5);
    transform: translateX(-50%);
}
.mpc-crosshair-dot {
    position: absolute; top: 50%; left: 50%;
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--mp-brand);
    transform: translate(-50%,-50%);
    box-shadow: 0 0 8px var(--mp-brand);
}
/* Corner brackets */
.mpc-crosshair-corner {
    position: absolute; width: 8px; height: 8px;
    border-color: rgba(16,185,129,0.6); border-style: solid;
}
.mpc-crosshair-corner.tl { top: 0; left: 0; border-width: 1.5px 0 0 1.5px; }
.mpc-crosshair-corner.tr { top: 0; right: 0; border-width: 1.5px 1.5px 0 0; }
.mpc-crosshair-corner.bl { bottom: 0; left: 0; border-width: 0 0 1.5px 1.5px; }
.mpc-crosshair-corner.br { bottom: 0; right: 0; border-width: 0 1.5px 1.5px 0; }

/* Scan animation */
.mpc-scan-line {
    position: absolute; inset: 0;
    pointer-events: none; z-index: 440;
    overflow: hidden;
}
.mpc-scan-line::after {
    content: '';
    position: absolute; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, rgba(16,185,129,0.5), transparent);
    animation: mpScan 2s ease-in-out infinite;
}
@keyframes mpScan {
    0%   { top: 0%;   opacity: 1; }
    90%  { top: 100%; opacity: 0.3; }
    100% { top: 100%; opacity: 0; }
}

/* ── Coordinate bar (bottom HUD) ── */
.mpc-coord-bar {
    position: absolute; bottom: 0; left: 0; right: 0;
    z-index: 800;
    background: linear-gradient(0deg, rgba(5,12,10,0.97) 0%, rgba(5,12,10,0.85) 70%, transparent 100%);
    backdrop-filter: blur(12px);
    padding: 14px 14px 12px;
}
.mpc-coord-row {
    display: flex; align-items: center; gap: 8px;
    flex-wrap: wrap;
}
.mpc-coord-card {
    display: flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,0.04);
    border: 1px solid var(--mp-border);
    border-radius: 9px;
    padding: 6px 10px;
    transition: border-color 0.2s;
}
.mpc-coord-card:hover { border-color: var(--mp-border-hi); }
.mpc-coord-label {
    font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em;
    color: var(--mp-brand); font-family: var(--mp-font-mono);
}
.mpc-coord-value {
    font-family: var(--mp-font-mono);
    font-size: 13px; font-weight: 500;
    color: var(--mp-text);
    letter-spacing: -0.01em;
    min-width: 90px;
}
.mpc-coord-copy {
    background: none; border: none; cursor: pointer;
    color: var(--mp-muted); padding: 2px;
    border-radius: 4px; transition: color 0.15s;
    display: flex; align-items: center;
    font-size: 11px;
}
.mpc-coord-copy:hover { color: var(--mp-brand); }

/* ── Address badge ── */
.mpc-address-row {
    margin-top: 6px;
    display: flex; align-items: center; gap: 6px;
}
.mpc-address-tag {
    display: flex; align-items: center; gap: 5px;
    background: var(--mp-brand-dim);
    border: 1px solid rgba(16,185,129,0.2);
    border-radius: 20px;
    padding: 3px 10px;
    font-size: 11px; color: var(--mp-brand);
    max-width: 100%; overflow: hidden;
}
.mpc-address-text {
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    font-family: var(--mp-font-body);
}
.mpc-address-loading {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; color: var(--mp-muted);
    font-style: italic;
}

/* ── Accuracy badge ── */
.mpc-accuracy-badge {
    display: flex; align-items: center; gap: 4px;
    background: rgba(245,200,66,0.1);
    border: 1px solid rgba(245,200,66,0.25);
    border-radius: 20px; padding: 3px 8px;
    font-size: 10px; font-weight: 600;
    color: var(--mp-gold);
    font-family: var(--mp-font-mono);
    flex-shrink: 0;
}
.mpc-accuracy-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--mp-gold);
    flex-shrink: 0;
}

/* ── Top-right map controls ── */
.mpc-map-controls {
    position: absolute; top: 10px; right: 10px;
    z-index: 800;
    display: flex; flex-direction: column; gap: 5px;
    align-items: flex-end;
}

/* ── Crosshair mode toggle ── */
.mpc-crosshair-toggle {
    display: flex; align-items: center; gap: 5px;
    background: rgba(5,12,10,0.88);
    backdrop-filter: blur(12px);
    border: 1px solid var(--mp-border);
    border-radius: 8px; padding: 5px 9px;
    font-size: 10px; font-weight: 600;
    color: var(--mp-muted); cursor: pointer;
    font-family: var(--mp-font-body);
    transition: all 0.18s;
}
.mpc-crosshair-toggle:hover { border-color: var(--mp-border-hi); color: var(--mp-text); }
.mpc-crosshair-toggle.active {
    background: var(--mp-brand-dim);
    border-color: var(--mp-border-hi);
    color: var(--mp-brand);
}

/* ── Zoom controls (Leaflet override) ── */
.mpc-root .leaflet-control-zoom { border: none !important; box-shadow: none !important; }
.mpc-root .leaflet-control-zoom a {
    width: 30px !important; height: 30px !important;
    line-height: 30px !important;
    background: rgba(5,12,10,0.9) !important;
    backdrop-filter: blur(12px) !important;
    color: var(--mp-text) !important;
    border: 1px solid var(--mp-border) !important;
    border-radius: 8px !important;
    margin: 3px 0 !important;
    font-weight: 700 !important; font-size: 15px !important;
    transition: all 0.15s !important;
}
.mpc-root .leaflet-control-zoom a:hover {
    background: var(--mp-brand-dim) !important;
    border-color: var(--mp-border-hi) !important;
    color: var(--mp-brand) !important;
}

/* ── Leaflet popup styling ── */
.mpc-root .leaflet-popup-content-wrapper {
    background: rgba(7,20,16,0.97) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid var(--mp-border-hi) !important;
    border-radius: 14px !important;
    box-shadow: 0 16px 40px rgba(0,0,0,0.6) !important;
    color: var(--mp-text) !important;
    padding: 0 !important;
}
.mpc-root .leaflet-popup-tip { background: rgba(7,20,16,0.97) !important; }
.mpc-root .leaflet-popup-content { margin: 0 !important; }
.mpc-root .leaflet-popup-close-button {
    color: var(--mp-muted) !important; font-size: 16px !important;
    top: 8px !important; right: 10px !important;
}
.mpc-root .leaflet-popup-close-button:hover { color: var(--mp-text) !important; background: none !important; }

/* ── Map tile area ── */
.mpc-root .leaflet-container {
    background: var(--mp-base) !important;
    font-family: var(--mp-font-body) !important;
}
.mpc-root .leaflet-control-attribution {
    background: rgba(5,12,10,0.7) !important;
    color: rgba(236,253,245,0.2) !important;
    font-size: 9px !important;
    backdrop-filter: blur(4px);
    border-radius: 6px 0 0 0 !important;
}
.mpc-root .leaflet-control-attribution a { color: rgba(16,185,129,0.5) !important; }

/* ── Tile z-index ── */
.mpc-root .leaflet-pane.leaflet-tile-pane    { z-index: 1 !important; }
.mpc-root .leaflet-pane.leaflet-overlay-pane { z-index: 2 !important; }
.mpc-root .leaflet-pane.leaflet-shadow-pane  { z-index: 3 !important; }
.mpc-root .leaflet-pane.leaflet-marker-pane  { z-index: 4 !important; }
.mpc-root .leaflet-pane.leaflet-tooltip-pane { z-index: 5 !important; }
.mpc-root .leaflet-pane.leaflet-popup-pane   { z-index: 6 !important; }

/* ── Custom pin marker ── */
.mp-custom-pin { display: flex; flex-direction: column; align-items: center; }
.mp-pin-body {
    width: 20px; height: 20px; border-radius: 50% 50% 50% 0;
    background: var(--mp-brand);
    transform: rotate(-45deg);
    border: 2.5px solid #fff;
    box-shadow: 0 0 0 3px rgba(16,185,129,0.3), 0 4px 16px rgba(0,0,0,0.4);
    transition: transform 0.2s;
}
.mp-pin-pulse {
    position: absolute; top: 0; left: 0;
    width: 20px; height: 20px; border-radius: 50%;
    background: rgba(16,185,129,0.4);
    animation: mpPinPulse 2s ease-out infinite;
}
@keyframes mpPinPulse {
    0%   { transform: scale(0.8) rotate(-45deg); opacity: 0.8; }
    70%  { transform: scale(2.2) rotate(-45deg); opacity: 0; }
    100% { transform: scale(0.8) rotate(-45deg); opacity: 0; }
}
.mp-pin-shadow {
    width: 8px; height: 4px; border-radius: 50%;
    background: rgba(0,0,0,0.3);
    margin-top: -2px;
    animation: mpShadowPulse 2s ease-out infinite;
}
@keyframes mpShadowPulse {
    0%, 100% { transform: scale(1); opacity: 0.4; }
    50%       { transform: scale(1.2); opacity: 0.2; }
}

/* ── Pin drop animation ── */
@keyframes mpPinDrop {
    0%   { transform: translateY(-30px); opacity: 0; }
    70%  { transform: translateY(4px); }
    85%  { transform: translateY(-3px); }
    100% { transform: translateY(0); opacity: 1; }
}
.mp-pin-dropping { animation: mpPinDrop 0.4s cubic-bezier(0.34,1.56,0.64,1) both; }

/* ── User location marker ── */
@keyframes mpUserPing {
    0%   { transform: translate(-50%,-50%) scale(1); opacity: 0.7; }
    70%  { transform: translate(-50%,-50%) scale(2.8); opacity: 0; }
    100% { transform: translate(-50%,-50%) scale(1); opacity: 0; }
}

/* ── Read-only overlay badge ── */
.mpc-readonly-badge {
    position: absolute; top: 10px; left: 10px;
    z-index: 800;
    background: rgba(5,12,10,0.92);
    backdrop-filter: blur(12px);
    border: 1px solid var(--mp-border-hi);
    border-radius: 10px;
    padding: 5px 10px;
    font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.1em;
    color: var(--mp-brand);
    font-family: var(--mp-font-mono);
    display: flex; align-items: center; gap: 5px;
}
.mpc-readonly-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: var(--mp-brand);
    animation: mpBlink 2s ease-in-out infinite;
}
@keyframes mpBlink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}

/* ── Copy toast ── */
.mpc-toast {
    position: absolute; bottom: 90px; left: 50%;
    transform: translateX(-50%) translateY(10px);
    background: rgba(16,185,129,0.95);
    backdrop-filter: blur(12px);
    border-radius: 20px; padding: 6px 16px;
    font-size: 11px; font-weight: 700;
    color: #fff; font-family: var(--mp-font-body);
    z-index: 900; pointer-events: none;
    opacity: 0; transition: all 0.25s;
    white-space: nowrap;
}
.mpc-toast.show {
    opacity: 1; transform: translateX(-50%) translateY(0);
}

/* ── Spinner ── */
.mp-spinner {
    width: 12px; height: 12px; border-radius: 50%;
    border: 2px solid rgba(16,185,129,0.3);
    border-top-color: var(--mp-brand);
    animation: mpSpin 0.7s linear infinite;
    flex-shrink: 0;
}
@keyframes mpSpin { to { transform: rotate(360deg); } }

/* ── General animations ── */
@keyframes mpFadeUp {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

{{-- ════════════════════════════════════════════
     COMPONENT MARKUP
═════════════════════════════════════════════ --}}
<div
    class="mpc-root"
    x-data="MapPicker({
        isReadonly: {{ $readonly ? 'true' : 'false' }},
        height: '{{ $height }}'
    })"
    x-init="init()"
    wire:ignore.self
>
    {{-- ── Toolbar (hidden in readonly) ── --}}
    @if(!$readonly)
    <div class="mpc-toolbar">

        {{-- Search ── --}}
        <div class="mpc-search-wrap" x-ref="searchWrap">
            <svg class="mpc-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                class="mpc-search-input"
                type="text"
                placeholder="Search address or place…"
                x-model="searchQuery"
                @input.debounce.400ms="runSearch()"
                @keydown.enter.prevent="selectFirst()"
                @keydown.escape="closeSearch()"
                @keydown.arrow-down.prevent="acDown()"
                @keydown.arrow-up.prevent="acUp()"
                autocomplete="off"
                spellcheck="false"
            >
            <button class="mpc-search-clear" x-show="searchQuery" @click="clearSearch()" tabindex="-1">✕</button>

            {{-- Autocomplete dropdown ── --}}
            <div class="mpc-autocomplete" x-show="showAc" x-cloak @click.outside="closeSearch()">
                <div class="mpc-ac-loading" x-show="acLoading">
                    <div class="mp-spinner"></div>
                    Searching…
                </div>
                <template x-if="!acLoading && acResults.length === 0 && searchQuery.length > 2">
                    <div class="mpc-ac-empty">No results found for "<span x-text="searchQuery"></span>"</div>
                </template>
                <template x-for="(result, i) in acResults" :key="result.place_id">
                    <div
                        class="mpc-ac-item"
                        :class="acIndex === i ? 'bg-[rgba(16,185,129,0.12)]' : ''"
                        @click="selectResult(result)"
                        @mouseenter="acIndex = i"
                    >
                        <svg class="mpc-ac-item-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        </svg>
                        <div>
                            <div class="mpc-ac-item-name" x-text="result._displayName"></div>
                            <div class="mpc-ac-item-sub" x-text="result._subName"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- Map style toggle ── --}}
        <div class="mpc-style-group">
            <button class="mpc-style-btn" :class="mapStyle==='dark' ? 'active' : ''" @click="setStyle('dark')">
                🌙 Dark
            </button>
            <button class="mpc-style-btn" :class="mapStyle==='satellite' ? 'active' : ''" @click="setStyle('satellite')">
                🛰 Sat
            </button>
            <button class="mpc-style-btn" :class="mapStyle==='map' ? 'active' : ''" @click="setStyle('map')">
                🗺 Map
            </button>
        </div>

        {{-- GPS button ── --}}
        <button
            class="mpc-gps-btn"
            :class="gpsScanning ? 'scanning' : ''"
            @click="getLocation()"
            :disabled="gpsScanning"
        >
            <svg class="w-3.5 h-3.5" :class="gpsScanning ? 'animate-pulse' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                      d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm8.94 3A8.994 8.994 0 0013 3.06V1h-2v2.06A8.994 8.994 0 003.06 11H1v2h2.06A8.994 8.994 0 0011 20.94V23h2v-2.06A8.994 8.994 0 0020.94 13H23v-2h-2.06z"/>
            </svg>
            <span x-text="gpsScanning ? 'Locating…' : 'My GPS'"></span>
        </button>

    </div>
    @endif

    {{-- ── Map container ── --}}
    <div class="mpc-map-wrap" :style="`height: {{ $height }}`">

        {{-- Leaflet canvas ── --}}
        <div x-ref="mapContainer" style="width:100%;height:100%;position:relative;z-index:10;"></div>

        {{-- Read-only badge ── --}}
        @if($readonly)
        <div class="mpc-readonly-badge">
            <div class="mpc-readonly-dot"></div>
            View only
        </div>
        @endif

        {{-- Crosshair mode overlay ── --}}
        @if(!$readonly)
        <div class="mpc-crosshair" x-show="crosshairMode">
            <div class="mpc-crosshair-inner">
                <div class="mpc-crosshair-h"></div>
                <div class="mpc-crosshair-v"></div>
                <div class="mpc-crosshair-dot"></div>
                <div class="mpc-crosshair-corner tl"></div>
                <div class="mpc-crosshair-corner tr"></div>
                <div class="mpc-crosshair-corner bl"></div>
                <div class="mpc-crosshair-corner br"></div>
            </div>
        </div>
        {{-- Scan line when GPS active ── --}}
        <div class="mpc-scan-line" x-show="gpsScanning"></div>
        @endif

        {{-- ── Top-right overlay controls ── --}}
        @if(!$readonly)
        <div class="mpc-map-controls">
            <button
                class="mpc-crosshair-toggle"
                :class="crosshairMode ? 'active' : ''"
                @click="crosshairMode = !crosshairMode"
                title="Toggle precision crosshair"
            >
                <svg style="width:11px;height:11px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3" stroke-width="2"/>
                    <path stroke-linecap="round" stroke-width="2" d="M12 2v4M12 18v4M2 12h4M18 12h4"/>
                </svg>
                Crosshair
            </button>
            <button
                class="mpc-crosshair-toggle"
                @click="resetToDefault()"
                title="Reset pin to default"
            >
                <svg style="width:11px;height:11px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Reset
            </button>
        </div>
        @endif

        {{-- ── Coordinate HUD (bottom) ── --}}
        <div class="mpc-coord-bar">
            <div class="mpc-coord-row">
                {{-- Latitude ── --}}
                <div class="mpc-coord-card">
                    <span class="mpc-coord-label">LAT</span>
                    <span class="mpc-coord-value" x-text="displayLat"></span>
                    @if(!$readonly)
                    <button class="mpc-coord-copy" @click="copyCoord('lat')" title="Copy latitude">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                    @endif
                </div>

                {{-- Longitude ── --}}
                <div class="mpc-coord-card">
                    <span class="mpc-coord-label">LNG</span>
                    <span class="mpc-coord-value" x-text="displayLng"></span>
                    @if(!$readonly)
                    <button class="mpc-coord-copy" @click="copyCoord('lng')" title="Copy longitude">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </button>
                    @endif
                </div>

                {{-- Accuracy badge (when GPS used) ── --}}
                <div class="mpc-accuracy-badge" x-show="gpsAccuracy !== null">
                    <div class="mpc-accuracy-dot"></div>
                    ±<span x-text="gpsAccuracy ? Math.round(gpsAccuracy) + 'm' : ''"></span>
                </div>

                {{-- Copy-all ── --}}
                @if(!$readonly)
                <button
                    class="mpc-coord-copy"
                    style="margin-left:auto;padding:6px 10px;border:1px solid var(--mp-border);border-radius:8px;font-size:10px;font-weight:600;color:var(--mp-muted);font-family:var(--mp-font-body);"
                    @click="copyCoord('all')"
                    title="Copy both coordinates"
                >
                    Copy all
                </button>
                @endif
            </div>

            {{-- Address row ── --}}
            <div class="mpc-address-row">
                <div class="mpc-address-loading" x-show="reverseLoading">
                    <div class="mp-spinner"></div>
                    Looking up address…
                </div>
                <div class="mpc-address-tag" x-show="!reverseLoading && resolvedAddress" style="display:none;">
                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    </svg>
                    <span class="mpc-address-text" x-text="resolvedAddress"></span>
                </div>
            </div>
        </div>

        {{-- Copy toast ── --}}
        <div class="mpc-toast" :class="toastVisible ? 'show' : ''" x-text="toastMsg"></div>

    </div>{{-- /mpc-map-wrap --}}
</div>

{{-- ════════════════════════════════════════════
     ALPINE.JS — MapPicker controller
═════════════════════════════════════════════ --}}
<script>
function MapPicker({ isReadonly, height }) {
    return {
        /* ─── Config ─── */
        isReadonly,
        defaultLat: 10.900977766937142,
        defaultLng: 123.07055771888716,

        /* ─── Map state ─── */
        map: null,
        marker: null,
        tileLayer: null,
        userMarker: null,
        accuracyCircle: null,
        mapStyle: 'dark',

        /* ─── Coordinate display ─── */
        displayLat: '—',
        displayLng: '—',

        /* ─── Search ─── */
        searchQuery: '',
        acResults: [],
        acIndex: -1,
        showAc: false,
        acLoading: false,
        _acAbort: null,

        /* ─── Reverse geocode ─── */
        resolvedAddress: '',
        reverseLoading: false,
        _revTimer: null,

        /* ─── GPS ─── */
        gpsScanning: false,
        gpsAccuracy: null,

        /* ─── UI ─── */
        crosshairMode: false,
        toastVisible: false,
        toastMsg: '',
        _toastTimer: null,

        /* ─────────────────────────────────── */
        init() {
            const check = setInterval(() => {
                if (typeof L !== 'undefined') {
                    clearInterval(check);
                    this.$nextTick(() => this.initMap());
                }
            }, 80);

            /* Watch Livewire changes (e.g. parent sets coords programmatically) */
            if (!this.isReadonly) {
                this.$watch('$wire.latitude',  v => this._onWireCoordChange());
                this.$watch('$wire.longitude', v => this._onWireCoordChange());
            }
        },

        /* ─── Map init ─── */
        initMap() {
            const el = this.$refs.mapContainer;
            if (!el) return;

            /* Fix Leaflet default icons */
            delete L.Icon.Default.prototype._getIconUrl;
            L.Icon.Default.mergeOptions({
                iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
                iconUrl:       'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                shadowUrl:     'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
            });

            /* Read current wire values */
            const wireLat = parseFloat($wire.get('latitude'));
            const wireLng = parseFloat($wire.get('longitude'));
            const lat = isNaN(wireLat) ? this.defaultLat : wireLat;
            const lng = isNaN(wireLng) ? this.defaultLng : wireLng;

            /* Create map */
            this.map = L.map(el, {
                center: [lat, lng], zoom: 14,
                zoomControl: true,
                scrollWheelZoom: true,
                tap: true,
            });

            /* Tile layer */
            this.tileLayer = L.tileLayer('', { maxZoom: 19 }).addTo(this.map);
            this.applyTileUrl();

            /* Custom pin marker */
            this.marker = L.marker([lat, lng], {
                draggable: !this.isReadonly,
                icon: this.buildPinIcon(),
                zIndexOffset: 100,
            }).addTo(this.map);

            /* Update coord display */
            this.syncDisplay(lat, lng);

            /* Trigger reverse geocode for initial point */
            this.scheduleReverseGeocode(lat, lng);

            /* Invalidate size after layout */
            setTimeout(() => this.map.invalidateSize(), 300);

            if (this.isReadonly) return;

            /* ── Interactive events ── */
            this.marker.on('dragend', (e) => {
                const p = e.target.getLatLng();
                this.setCoords(p.lat, p.lng);
            });

            this.map.on('click', (e) => {
                this.setCoords(e.latlng.lat, e.latlng.lng, true /* animate drop */);
            });
        },

        /* ─── Tile URL ─── */
        applyTileUrl() {
            const urls = {
                dark:      'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
                satellite: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
                map:       'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png',
            };
            this.tileLayer?.setUrl(urls[this.mapStyle] || urls.dark);
        },

        setStyle(style) {
            this.mapStyle = style;
            this.applyTileUrl();
        },

        /* ─── Custom SVG pin icon ─── */
        buildPinIcon(dropping = false) {
            return L.divIcon({
                className: 'mp-custom-pin',
                html: `<div style="position:relative;width:26px;height:36px;">
                    <div class="mp-pin-pulse" style="position:absolute;top:0;left:3px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);"></div>
                    <div class="mp-pin-body${dropping ? ' mp-pin-dropping' : ''}" style="position:absolute;top:0;left:3px;"></div>
                    <div class="mp-pin-shadow" style="position:absolute;bottom:0;left:9px;"></div>
                </div>`,
                iconSize:   [26, 36],
                iconAnchor: [13, 34],
                popupAnchor:[0, -36],
            });
        },

        /* ─── Set coordinates (main setter) ─── */
        setCoords(lat, lng, animated = false) {
            lat = parseFloat(lat);
            lng = parseFloat(lng);
            if (isNaN(lat) || isNaN(lng)) return;

            /* Animate pin drop */
            if (animated) {
                this.marker.setIcon(this.buildPinIcon(true));
                setTimeout(() => this.marker.setIcon(this.buildPinIcon(false)), 500);
            }

            this.marker.setLatLng([lat, lng]);
            this.syncDisplay(lat, lng);

            /* Push to Livewire (no full render) */
            $wire.set('latitude',  lat.toFixed(6), false);
            $wire.set('longitude', lng.toFixed(6), false);

            /* Schedule reverse geocode */
            this.scheduleReverseGeocode(lat, lng);
        },

        /* ─── Sync display values ─── */
        syncDisplay(lat, lng) {
            this.displayLat = parseFloat(lat).toFixed(6);
            this.displayLng = parseFloat(lng).toFixed(6);
        },

        /* ─── Watch wire coord changes from outside ─── */
        _onWireCoordChange() {
            const lat = parseFloat($wire.latitude);
            const lng = parseFloat($wire.longitude);
            if (!isNaN(lat) && !isNaN(lng) && this.marker) {
                this.marker.setLatLng([lat, lng]);
                this.map?.setView([lat, lng]);
                this.syncDisplay(lat, lng);
                this.scheduleReverseGeocode(lat, lng);
            }
        },

        /* ─── GPS ─── */
        getLocation() {
            if (!navigator.geolocation) {
                this.showToast('Geolocation not supported');
                return;
            }
            this.gpsScanning = true;
            this.gpsAccuracy = null;

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.gpsScanning = false;
                    this.gpsAccuracy = pos.coords.accuracy;

                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;

                    /* Fly to location */
                    this.map?.flyTo([lat, lng], 16, { duration: 1.2 });

                    /* Set pin */
                    setTimeout(() => this.setCoords(lat, lng, true), 600);

                    /* Draw accuracy circle */
                    if (this.accuracyCircle) this.map.removeLayer(this.accuracyCircle);
                    this.accuracyCircle = L.circle([lat, lng], {
                        radius: pos.coords.accuracy,
                        color: '#10b981', fillColor: '#10b981',
                        fillOpacity: 0.07, weight: 1.5,
                        dashArray: '4 4', opacity: 0.5,
                    }).addTo(this.map);

                    /* User dot marker */
                    if (this.userMarker) this.map.removeLayer(this.userMarker);
                    const pulse = `<div style="position:relative;width:48px;height:48px;">
                        <div style="position:absolute;top:50%;left:50%;width:28px;height:28px;border-radius:50%;
                             background:rgba(16,185,129,0.15);border:1px solid rgba(16,185,129,0.4);
                             transform:translate(-50%,-50%);animation:mpUserPing 2s ease-out infinite;"></div>
                        <div style="position:absolute;top:50%;left:50%;width:12px;height:12px;border-radius:50%;
                             background:#10b981;border:2px solid #fff;
                             transform:translate(-50%,-50%);
                             box-shadow:0 0 0 3px rgba(16,185,129,0.25);"></div>
                    </div>`;
                    this.userMarker = L.marker([lat, lng], {
                        icon: L.divIcon({ className: '', html: pulse, iconSize:[48,48], iconAnchor:[24,24] }),
                        zIndexOffset: 500,
                    }).addTo(this.map);

                    this.showToast(`± ${Math.round(pos.coords.accuracy)}m accuracy`);
                },
                (err) => {
                    this.gpsScanning = false;
                    this.showToast('Location permission denied');
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        },

        /* ─── Reset to default ─── */
        resetToDefault() {
            const lat = this.defaultLat, lng = this.defaultLng;
            this.map?.flyTo([lat, lng], 14, { duration: 1 });
            setTimeout(() => this.setCoords(lat, lng, true), 800);
            if (this.accuracyCircle) { this.map.removeLayer(this.accuracyCircle); this.accuracyCircle = null; }
            if (this.userMarker)     { this.map.removeLayer(this.userMarker);     this.userMarker = null; }
            this.gpsAccuracy = null;
        },

        /* ─── Address search (Nominatim) ─── */
        async runSearch() {
            const q = this.searchQuery.trim();
            if (q.length < 3) { this.closeSearch(); return; }

            this.showAc    = true;
            this.acLoading = true;
            this.acResults = [];
            this.acIndex   = -1;

            /* Cancel previous request */
            if (this._acAbort) this._acAbort.abort();
            this._acAbort = new AbortController();

            try {
                const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=6&addressdetails=1`;
                const res  = await fetch(url, {
                    signal: this._acAbort.signal,
                    headers: { 'Accept-Language': 'en' }
                });
                const data = await res.json();

                this.acResults = data.map(r => {
                    const parts = r.display_name.split(', ');
                    return {
                        ...r,
                        _displayName: parts.slice(0, 2).join(', '),
                        _subName:     parts.slice(2).join(', '),
                    };
                });
            } catch(e) {
                if (e.name !== 'AbortError') this.acResults = [];
            }

            this.acLoading = false;
        },

        selectResult(result) {
            const lat = parseFloat(result.lat);
            const lng = parseFloat(result.lon);
            this.searchQuery = result._displayName;
            this.closeSearch();
            this.map?.flyTo([lat, lng], 16, { duration: 1.2 });
            setTimeout(() => this.setCoords(lat, lng, true), 700);
        },

        selectFirst() {
            if (this.acResults.length > 0) this.selectResult(this.acResults[this.acIndex >= 0 ? this.acIndex : 0]);
        },

        clearSearch() {
            this.searchQuery = '';
            this.acResults = [];
            this.closeSearch();
        },

        closeSearch() { this.showAc = false; this.acIndex = -1; },

        acDown() {
            if (!this.showAc) return;
            this.acIndex = Math.min(this.acIndex + 1, this.acResults.length - 1);
        },
        acUp() {
            if (!this.showAc) return;
            this.acIndex = Math.max(this.acIndex - 1, -1);
        },

        /* ─── Reverse geocode ─── */
        scheduleReverseGeocode(lat, lng) {
            clearTimeout(this._revTimer);
            this.reverseLoading = true;
            this.resolvedAddress = '';

            this._revTimer = setTimeout(async () => {
                try {
                    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=17`;
                    const res  = await fetch(url, { headers: { 'Accept-Language': 'en' } });
                    const data = await res.json();

                    if (data?.display_name) {
                        const parts = data.display_name.split(', ');
                        this.resolvedAddress = parts.slice(0, 4).join(', ');
                    } else {
                        this.resolvedAddress = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
                    }
                } catch(e) {
                    this.resolvedAddress = `${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
                }
                this.reverseLoading = false;
            }, 900); /* Debounce 900ms to respect Nominatim rate limits */
        },

        /* ─── Copy coordinates ─── */
        async copyCoord(which) {
            let text = '';
            if (which === 'lat')  text = this.displayLat;
            if (which === 'lng')  text = this.displayLng;
            if (which === 'all')  text = `${this.displayLat}, ${this.displayLng}`;
            try {
                await navigator.clipboard.writeText(text);
                this.showToast('Copied: ' + text);
            } catch(e) {
                this.showToast('Copy not supported');
            }
        },

        /* ─── Toast ─── */
        showToast(msg) {
            this.toastMsg     = msg;
            this.toastVisible = true;
            clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toastVisible = false; }, 2200);
        },
    };
}
</script>