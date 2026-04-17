import './bootstrap';
import 'preline';

// Leaflet 2.0.0 Integration
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix marker icon paths for Vite bundling
import iconUrl from 'leaflet/dist/images/marker-icon.png';
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png';
import shadowUrl from 'leaflet/dist/images/marker-shadow.png';

L.Icon.Default.mergeOptions({
    iconUrl,
    iconRetinaUrl,
    shadowUrl,
});

// Make Leaflet globally available for Alpine components
window.L = L;