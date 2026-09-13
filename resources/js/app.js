import './bootstrap';
import 'preline';

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/*
|--------------------------------------------------------------------------
| Alpine plugins
|--------------------------------------------------------------------------
|
| Livewire v4 bundles Alpine core but does NOT bundle Collapse (or any
| other Alpine plugin). We import Collapse here — as an ES module value,
| not via a CDN <script> — and register it on `alpine:init`.
|
| WHY THIS IS DETERMINISTIC:
|
|   • app.js is loaded via <script type="module"> from @vite([...]) in the
|     layout's <head>. By spec, ES modules are deferred, and they execute
|     in document order before any subsequent <script> in <body>.
|
|   • @livewireScripts sits at the end of <body>. When it runs, it boots
|     Alpine and fires `alpine:init` exactly once.
|
|   • The listener below is attached during module evaluation — i.e. BEFORE
|     Livewire boots. It is guaranteed to be in place when `alpine:init`
|     fires.
|
|   • `collapse` is a value, not a window global. There is no window to
|     poll, no CDN to reach, no `onload` race to lose.
|
| Previous approach used a deferred CDN <script> with an `alpine:init`
| listener and an `onload` fallback — that pattern was racy because Alpine
| could boot before the CDN asset arrived (jsDelivr latency, DNS failure,
| blocked network), leaving `window.AlpineCollapse` undefined at boot.
|
*/

import collapse from '@alpinejs/collapse';

document.addEventListener('alpine:init', () => {
    window.Alpine.plugin(collapse);
});