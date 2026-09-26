import { useEffect, useState } from 'react';

/**
 * Recently viewed stays (this browser only). On a stay page pass `record`
 * (the stay) to remember it and show nothing; on the home / search pages
 * it shows the last few stays, newest first, and hides when there are none.
 *
 * props: { record?: {slug, name, url, image, price, where}, title? }
 */
const KEY = 'ck.recent-stays';
const MAX = 8;

const read = () => {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch { return []; }
};

export default function RecentlyViewed({ record, title = 'Recently viewed' }) {
    const [items, setItems] = useState([]);

    useEffect(() => {
        if (record) {
            try { localStorage.setItem(KEY, JSON.stringify([record, ...read().filter((s) => s.slug !== record.slug)].slice(0, MAX))); } catch { /* private mode / storage off */ }
            return;
        }
        setItems(read());
    }, [record]);

    if (record || items.length === 0) return null;

    return (
        <section aria-label={title} className="pt-10 text-fg">
            <div className="mb-3 flex items-baseline justify-between gap-4">
                <h2 className="font-display text-[20px] font-medium tracking-tight">{title}</h2>
                <button type="button" className="text-[13px] text-fg-3 hover:text-fg" onClick={() => { try { localStorage.removeItem(KEY); } catch { /* ignore */ } setItems([]); }}>Clear</button>
            </div>
            <div className="-mx-1 flex snap-x gap-3 overflow-x-auto px-1 pb-2">
                {items.map((s) => (
                    <a key={s.slug} href={s.url} className="group w-56 shrink-0 snap-start">
                        {s.image ? <img src={s.image} alt="" loading="lazy" className="aspect-[4/3] w-full object-cover" /> : <span className="block aspect-[4/3] w-full bg-soft" />}
                        <span className="mt-2 block truncate text-[14px] font-medium group-hover:text-brand">{s.name}</span>
                        <span className="block truncate text-[12px] text-fg-3">{[s.where, s.price && `${s.price} / night`].filter(Boolean).join(' · ')}</span>
                    </a>
                ))}
            </div>
        </section>
    );
}
