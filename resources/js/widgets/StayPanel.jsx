import { useEffect, useMemo, useRef, useState } from 'react';
import { BTN, FIELD, Gallery, LABEL, Stepper, cx, json, money } from './ui';

/**
 * Property page booking panel: dates, guests, rooms → live quote for every
 * room type (free rooms, full-stay price), then "Reserve" to the review step.
 * Signed-out guests sign in first and come back to the same selection.
 *
 * props: { quoteUrl, reviewUrl, root, signedIn, currency, fromPrice, roomTypes: [{id, name, sleeps}],
 *          dates: {check_in, check_out, guests} | null, today }
 */
const addDay = (iso, n = 1) => {
    const d = new Date(`${iso}T00:00:00`);
    d.setDate(d.getDate() + n);
    const pad = (x) => String(x).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`; // local date, no UTC shift
};
const nice = (iso) => new Date(`${iso}T00:00:00`).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

export default function StayPanel({ quoteUrl, reviewUrl, root, signedIn, currency = 'PHP', fromPrice, roomTypes, dates, today }) {
    const [checkIn, setCheckIn] = useState(dates?.check_in ?? '');
    const [checkOut, setCheckOut] = useState(dates?.check_out ?? '');
    const [guests, setGuests] = useState(dates?.guests ?? 2);
    const [rooms, setRooms] = useState(1);
    const [pick, setPick] = useState(null);
    const [quote, setQuote] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [gallery, setGallery] = useState(null); // room type whose photos are open
    const abort = useRef(null);

    const ready = checkIn && checkOut && checkOut > checkIn;

    useEffect(() => {
        if (!ready) { setQuote(null); return; }
        abort.current?.abort();
        const ctrl = new AbortController();
        abort.current = ctrl;
        setLoading(true);
        setError(null);
        const t = setTimeout(() => {
            json(`${quoteUrl}?${new URLSearchParams({ check_in: checkIn, check_out: checkOut, guests })}`, { signal: ctrl.signal })
                .then((q) => { setQuote(q); setLoading(false); })
                .catch((e) => { if (e.name !== 'AbortError') { setError(e.message); setQuote(null); setLoading(false); } });
            // Keep dates in the address bar so a reload or shared link keeps the stay.
            const url = new URL(window.location.href);
            Object.entries({ check_in: checkIn, check_out: checkOut, guests }).forEach(([k, v]) => url.searchParams.set(k, v));
            window.history.replaceState(null, '', url);
        }, 250);
        return () => { clearTimeout(t); ctrl.abort(); };
    }, [checkIn, checkOut, guests, ready, quoteUrl]);

    const options = quote?.options ?? [];
    const bookable = (o) => o.fits && o.total !== null && o.free >= rooms;
    const cheapest = useMemo(() => options.filter(bookable).sort((a, b) => a.total - b.total)[0], [options, rooms]); // eslint-disable-line react-hooks/exhaustive-deps
    const selected = options.find((o) => o.room_type_id === pick && bookable(o)) ?? cheapest;
    const total = selected ? selected.total * rooms : null;

    const reserve = () => {
        const url = `${reviewUrl}?${new URLSearchParams({ check_in: checkIn, check_out: checkOut, guests, room_type_id: selected.room_type_id, quantity: rooms })}`;
        window.location.href = url; // guests fill in their details on the review step
    };

    return (
        <div className="space-y-4 text-fg">
            <div aria-live="polite">
                {total !== null ? (
                    <p className="font-display text-[26px] font-medium leading-tight tabular-nums">
                        {money(total, currency)} <span className="text-[14px] font-normal text-fg-3">total for {quote.nights} {quote.nights === 1 ? 'night' : 'nights'}</span>
                    </p>
                ) : (
                    <p className="font-display text-[26px] font-medium leading-tight">
                        {fromPrice} <span className="text-[14px] font-normal text-fg-3">/ night</span>
                    </p>
                )}
                {quote?.free_cancel_until && <p className="mt-1 text-[13px] font-medium text-ok">Free cancellation until {quote.free_cancel_until}</p>}
            </div>

            <fieldset className="grid grid-cols-2 gap-3">
                <legend className="sr-only">Your stay</legend>
                <label className="block">
                    <span className={LABEL}>Check-in</span>
                    <input
                        type="date" className={FIELD} min={today} value={checkIn}
                        onChange={(e) => { const v = e.target.value; setCheckIn(v); if (!checkOut || checkOut <= v) setCheckOut(addDay(v)); }}
                    />
                </label>
                <label className="block">
                    <span className={LABEL}>Check-out</span>
                    <input type="date" className={FIELD} min={checkIn ? addDay(checkIn) : addDay(today)} value={checkOut} onChange={(e) => setCheckOut(e.target.value)} />
                </label>
                <div>
                    <span className={LABEL}>Guests</span>
                    <Stepper value={guests} min={1} max={30} onChange={setGuests} label="guests" />
                </div>
                <div>
                    <span className={LABEL}>Rooms</span>
                    <Stepper value={rooms} min={1} max={10} onChange={setRooms} label="rooms" />
                </div>
            </fieldset>

            {error && <p role="alert" className="border-l-2 border-bad bg-bad-bg px-3 py-2 text-[13px] text-bad">{error}</p>}

            {!ready && !error && <p className="text-[13px] text-fg-3">Add your dates to see prices and free rooms.</p>}

            {ready && loading && !quote && (
                <div className="space-y-2" aria-hidden="true">
                    {[0, 1].map((i) => <div key={i} className="h-[62px] animate-pulse bg-soft" />)}
                </div>
            )}

            {quote && (
                <fieldset className={cx('space-y-2 transition-opacity', loading && 'opacity-60')}>
                    <legend className={LABEL}>Room</legend>
                    {options.map((o) => {
                        const ok = bookable(o);
                        const on = selected?.room_type_id === o.room_type_id;
                        const why = !o.fits ? `Sleeps ${o.sleeps}` : o.total === null ? o.note : o.free === 0 ? 'Sold out' : o.free < rooms ? `Only ${o.free} left` : null;
                        return (
                            <label
                                key={o.room_type_id}
                                className={cx(
                                    'flex min-h-[56px] cursor-pointer items-center gap-3 border px-3 py-2.5 transition-colors',
                                    on ? 'border-brand bg-brand-soft' : 'border-line hover:border-line-strong',
                                    !ok && 'cursor-not-allowed opacity-55',
                                )}
                            >
                                <input type="radio" name="room" className="h-4 w-4 accent-[var(--primary)] text-brand" checked={on} disabled={!ok} onChange={() => setPick(o.room_type_id)} />
                                {o.photos?.length > 0 && (
                                    <button
                                        type="button"
                                        onClick={(e) => { e.preventDefault(); setGallery(o); }}
                                        className="relative h-12 w-16 shrink-0 overflow-hidden"
                                        aria-label={`See ${o.photos.length} photos of ${o.name}`}
                                    >
                                        <img src={o.photos[0]} alt="" loading="lazy" className="h-full w-full object-cover" />
                                        {o.photos.length > 1 && <span className="absolute bottom-0 right-0 bg-black/60 px-1 text-[10px] text-white">{o.photos.length}</span>}
                                    </button>
                                )}
                                <span className="min-w-0 flex-1">
                                    <span className="block text-[14px] font-medium">{o.name}</span>
                                    <span className="block text-[12px] text-fg-3">
                                        Sleeps {o.sleeps}
                                        {ok && o.free <= 2 && <span className="font-medium text-coral-deep"> · Only {o.free} left</span>}
                                        {why && <span> · {why}</span>}
                                    </span>
                                </span>
                                {o.total !== null && (
                                    <span className="text-right tabular-nums">
                                        <span className="block text-[14px] font-semibold">{money(o.total * rooms, quote.currency)}</span>
                                        <span className="block text-[12px] text-fg-3">{money(o.per_night, quote.currency)} / night</span>
                                    </span>
                                )}
                            </label>
                        );
                    })}
                    {options.length > 0 && !cheapest && <p className="text-[13px] text-bad">Nothing is free for all of these nights. Try other dates or fewer rooms.</p>}
                </fieldset>
            )}

            {selected && (
                <dl className="space-y-1 border-t border-line pt-3 text-[13px] tabular-nums">
                    <div className="flex justify-between text-fg-2"><dt>{nice(checkIn)} to {nice(checkOut)}</dt><dd>{quote.nights} {quote.nights === 1 ? 'night' : 'nights'}</dd></div>
                    <div className="flex justify-between text-fg-2"><dt>{rooms} × {selected.name}</dt><dd>{money(selected.per_night, quote.currency)} avg / night</dd></div>
                    <div className="flex justify-between pt-1 text-[15px] font-semibold"><dt>Total</dt><dd>{money(total, quote.currency)}</dd></div>
                </dl>
            )}

            <button type="button" className={BTN} disabled={!selected || loading} onClick={reserve}>
                Reserve
            </button>
            <p className="text-center text-[12px] text-fg-3">You won't be charged yet. You review everything on the next step.</p>
            {gallery && <Gallery photos={gallery.photos} title={gallery.name} onClose={() => setGallery(null)} />}
        </div>
    );
}
