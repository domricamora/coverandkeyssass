import { useEffect, useMemo, useState } from 'react';
import { BTN, FIELD, LABEL, Stepper, cx, json, postForm, useGuest } from './ui';

/**
 * Table booking: 14-day date strip, party size, live time slots, then
 * contact + requests and "Book table" (posts the existing reserve
 * endpoint). Signed-out guests sign in and land back here with the same
 * date, time and party prefilled.
 *
 * props: { slotsUrl, reserveUrl, pageUrl, root, signedIn, today, prefill: {date, time, party, phone, requests}, error }
 */
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

export default function TableBooking({ slotsUrl, reserveUrl, pageUrl, root, user, guestUrls, today, prefill = {}, error }) {
    const days = useMemo(() => Array.from({ length: 14 }, (_, i) => {
        const d = new Date(`${today}T00:00:00`);
        d.setDate(d.getDate() + i);
        return { value: iso(d), dow: i === 0 ? 'Today' : d.toLocaleDateString('en-US', { weekday: 'short' }), day: d.getDate(), month: d.toLocaleDateString('en-US', { month: 'short' }) };
    }), [today]);

    const [date, setDate] = useState(prefill.date && prefill.date >= today ? prefill.date : today);
    const [party, setParty] = useState(Number(prefill.party) || 2);
    const [time, setTime] = useState(prefill.time ?? null);
    const [slots, setSlots] = useState(null);
    const [requests, setRequests] = useState(prefill.requests ?? '');
    const [busy, setBusy] = useState(false);
    const back = `${pageUrl.slice(root.length) || '/'}?${new URLSearchParams({ date, time: time ?? '', party })}#book`;
    const who = useGuest({ user, urls: guestUrls, returnTo: back, phone: prefill.phone ?? '', stacked: true });

    useEffect(() => {
        const ctrl = new AbortController();
        setSlots(null);
        json(`${slotsUrl}?date=${date}`, { signal: ctrl.signal })
            .then((r) => { setSlots(r.slots); setTime((t) => (r.slots.includes(t) ? t : null)); })
            .catch((e) => { if (e.name !== 'AbortError') setSlots([]); });
        return () => ctrl.abort();
    }, [date, slotsUrl]);

    const book = async () => {
        setBusy(true);
        if (!(await who.ensure())) { setBusy(false); return; }
        postForm(reserveUrl, { date, time, party_size: party, guest_phone: who.phone, special_requests: requests });
    };

    return (
        <div id="book" className="scroll-mt-6 space-y-4 text-fg">
            <h3 className="text-[17px] font-semibold">Book a table</h3>
            {error && <p role="alert" className="border-l-2 border-bad bg-bad-bg px-3 py-2 text-[13px] text-bad">{error}</p>}

            <div>
                <span className={LABEL}>Date</span>
                <div className="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1" role="radiogroup" aria-label="Date">
                    {days.map((d) => (
                        <button
                            key={d.value} type="button" role="radio" aria-checked={date === d.value} onClick={() => setDate(d.value)}
                            className={cx('flex h-16 w-14 shrink-0 flex-col items-center justify-center border text-center', date === d.value ? 'border-brand bg-brand text-white' : 'border-line bg-surface hover:border-line-strong')}
                        >
                            <span className={cx('text-[11px]', date === d.value ? 'text-white/80' : 'text-fg-3')}>{d.dow}</span>
                            <span className="text-[17px] font-semibold tabular-nums leading-tight">{d.day}</span>
                            <span className={cx('text-[11px]', date === d.value ? 'text-white/80' : 'text-fg-3')}>{d.month}</span>
                        </button>
                    ))}
                </div>
            </div>

            <div>
                <span className={LABEL}>Guests</span>
                <Stepper value={party} min={1} max={50} onChange={setParty} label="guests" />
            </div>

            <div>
                <span className={LABEL}>Time</span>
                {slots === null && <div className="grid grid-cols-4 gap-1.5" aria-hidden="true">{[0, 1, 2, 3, 4, 5, 6, 7].map((i) => <div key={i} className="h-10 animate-pulse bg-soft" />)}</div>}
                {slots?.length === 0 && <p className="text-[13px] text-fg-3">Closed or no free times that day. Try another date.</p>}
                {slots?.length > 0 && (
                    <div className="grid grid-cols-4 gap-1.5" role="radiogroup" aria-label="Time">
                        {slots.map((s) => (
                            <button key={s} type="button" role="radio" aria-checked={time === s} onClick={() => setTime(s)}
                                className={cx('h-10 border text-[14px] tabular-nums', time === s ? 'border-brand bg-brand-soft font-semibold text-brand-deep' : 'border-line bg-surface hover:border-line-strong')}>
                                {s}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {time && (
                <div className="space-y-3">
                    {who.view}
                    <label className="block">
                        <span className={LABEL}>Requests <span className="font-normal text-fg-3">(optional)</span></span>
                        <textarea rows={2} className={cx(FIELD, 'h-auto py-2.5')} value={requests} onChange={(e) => setRequests(e.target.value)} placeholder="Birthday, high chair, window seat" />
                    </label>
                </div>
            )}

            <button type="button" className={BTN} disabled={!time || busy} onClick={book}>
                {!time ? 'Pick a time' : busy ? 'Booking…' : `Book table for ${party} at ${time}`}
            </button>
            <p className="text-center text-[12px] text-fg-3">The restaurant confirms your request.</p>
        </div>
    );
}
