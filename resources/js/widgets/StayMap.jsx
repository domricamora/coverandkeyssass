import { useEffect, useRef, useState } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import './map.css';

/**
 * "Show on map" for stay results: OpenStreetMap tiles, one price pin per
 * stay (exact coordinates when the host set them, else the destination's),
 * a small card on tap. Collapsed by default so the list stays first.
 *
 * props: { points: [{id, name, price, url, image, where, lat, lng, exact}] }
 */
const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export default function StayMap({ points = [] }) {
    const [open, setOpen] = useState(false);
    const box = useRef(null);
    const map = useRef(null);

    useEffect(() => {
        if (!open || !box.current || map.current) return;
        const m = L.map(box.current, { scrollWheelZoom: false, attributionControl: true });
        map.current = m;
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(m);

        const markers = points.map((p) => L.marker([p.lat, p.lng], {
            title: p.name,
            icon: L.divIcon({ className: 'stay-pin', html: `<span>${esc(p.price)}</span>`, iconSize: null }),
        }).bindPopup(
            `<a class="stay-pop" href="${esc(p.url)}">${p.image ? `<img src="${esc(p.image)}" alt="" />` : ''}<strong>${esc(p.name)}</strong><span>${esc(p.where)}${p.exact ? '' : ' (area)'}</span><em>${esc(p.price)} / night</em></a>`,
            { closeButton: false, maxWidth: 240 },
        ).addTo(m));

        if (markers.length) m.fitBounds(L.featureGroup(markers).getBounds().pad(0.2), { maxZoom: 13 });
        else m.setView([12.3, 122.5], 5); // the Philippines

        return () => { m.remove(); map.current = null; };
    }, [open, points]);

    if (points.length === 0) return null;

    return (
        <div className="mb-4">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                className="inline-flex h-10 items-center gap-2 border border-line-strong bg-surface px-4 text-[14px] font-medium text-fg hover:border-brand"
            >
                <svg width="16" height="16" fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24" aria-hidden="true"><path strokeLinecap="round" strokeLinejoin="round" d="M9 20l-6-2V4l6 2m0 14l6-2m-6 2V6m6 12l6 2V6l-6-2m0 14V4M9 6l6-2" /></svg>
                {open ? 'Hide map' : 'Show on map'}
            </button>
            {open && <div ref={box} className="mt-3 h-[360px] w-full border border-line sm:h-[440px]" role="region" aria-label="Map of stays" />}
        </div>
    );
}
