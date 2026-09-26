import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PublicShell from '../../react/PublicShell';
import { BTN, FIELD, LABEL, Stepper, cx, json, money, postForm, useGuest } from '../../widgets/ui';

/**
 * Food order checkout: edit quantities, pick pickup / delivery / room
 * service, time, contact, promo and payment, then place the order. Prices
 * come from the server quote; the delivery fee preview mirrors
 * DeliveryZone::feeFor and is charged from the server again on submit.
 */
export default function Checkout({ restaurant, lines, quote, error, promoCode, onlinePayments, zones, stays, minSchedule, old, guest, urls }) {
    const { errors } = usePage().props;
    const [mode, setMode] = useState(old.fulfillment ?? 'pickup');
    const [zoneId, setZoneId] = useState(Number(old.delivery_zone_id) || zones[0]?.id || null);
    const [address, setAddress] = useState(old.delivery_address ?? '');
    const [coords, setCoords] = useState(old.delivery_lat ? { lat: old.delivery_lat, lng: old.delivery_lng } : null);
    const [locating, setLocating] = useState(false);
    const [stay, setStay] = useState(old.room_stay ?? stays[0]?.value ?? '');
    const [when, setWhen] = useState(old.scheduled_for ? 'later' : 'asap');
    const [scheduled, setScheduled] = useState(old.scheduled_for ?? '');
    const { guestUrls } = usePage().props;
    const who = useGuest({ user: guest, urls: guestUrls, returnTo: '/cart', phone: old.customer_phone ?? '' });
    const [notes, setNotes] = useState(old.notes ?? '');
    const [code, setCode] = useState(promoCode);
    const [pay, setPay] = useState(old.payment_method ?? (onlinePayments ? 'online' : 'cash'));
    const [busy, setBusy] = useState(false);

    const setQty = async (key, qty) => {
        await json(`${urls.cartBase}/${key}`, { method: 'PATCH', body: { quantity: qty } }).catch(() => {});
        router.reload({ only: ['lines', 'quote', 'error'], preserveScroll: true });
    };
    const applyPromo = () => router.get(urls.self, code.trim() ? { promo_code: code.trim() } : {}, { preserveState: true, preserveScroll: true, only: ['quote', 'error', 'promoCode'] });

    const locate = () => {
        setLocating(true);
        navigator.geolocation?.getCurrentPosition(
            (p) => { setCoords({ lat: p.coords.latitude.toFixed(7), lng: p.coords.longitude.toFixed(7) }); setLocating(false); },
            () => setLocating(false),
        );
    };

    if (!restaurant || lines.length === 0) {
        return (
            <div className="mx-auto max-w-md py-16 text-center">
                <Head title="Your cart" />
                <h1 className="font-display text-[28px] font-medium">Your cart is empty</h1>
                <p className="mt-2 text-[14px] text-fg-3">Add dishes from a restaurant menu and they will show up here.</p>
                <a href={urls.browse} className={cx(BTN, 'mt-6 w-auto')}>Browse restaurants</a>
            </div>
        );
    }

    const zone = mode === 'delivery' ? zones.find((z) => z.id === zoneId) : null;
    const taxable = quote.subtotal - quote.discount;
    const fee = zone ? (zone.free_over !== null && taxable >= zone.free_over ? 0 : zone.fee) : 0;
    const total = quote.total + fee;
    const room = mode === 'room_service';
    const paymentOptions = [
        onlinePayments && ['online', 'Pay now', 'Card, GCash or Maya'],
        ['cash', 'Cash', mode === 'delivery' ? 'Pay the rider' : room ? 'Pay when it arrives' : 'Pay at pickup'],
        room && ['room_charge', 'Charge to my room', 'Settle at check-out'],
    ].filter(Boolean);
    const payment = paymentOptions.some(([v]) => v === pay) ? pay : paymentOptions[0][0];

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        if (!(await who.ensure())) { setBusy(false); return; }
        postForm(urls.checkout, {
            fulfillment: mode, payment_method: payment, customer_phone: who.phone, notes, promo_code: quote.promo?.code,
            scheduled_for: when === 'later' ? scheduled : '',
            ...(mode === 'delivery' ? { delivery_zone_id: zoneId, delivery_address: address, delivery_lat: coords?.lat, delivery_lng: coords?.lng } : {}),
            ...(room ? { room_stay: stay } : {}),
        });
    };

    const modes = [['pickup', 'Pickup'], zones.length > 0 && ['delivery', 'Delivery'], stays.length > 0 && ['room_service', 'Room service']].filter(Boolean);
    const fieldError = (...keys) => keys.map((k) => errors?.[k]).find(Boolean);

    return (
        <>
            <Head title="Checkout" />
            <p className="mb-2 text-[13px]"><a href={restaurant.url} className="text-fg-3 hover:text-fg">← Back to {restaurant.name}</a></p>
            <h1 className="mb-8 font-display text-[30px] font-medium leading-tight tracking-tight">Checkout</h1>

            <form onSubmit={submit} className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_400px]">
                <div className="space-y-8">
                    {(error || fieldError('cart', 'modifiers')) && <p role="alert" className="border-l-2 border-bad bg-bad-bg px-3 py-2 text-[13px] text-bad">{error ?? fieldError('cart', 'modifiers')}</p>}

                    <section className="space-y-3">
                        <h2 className="text-[16px] font-semibold">How do you want it?</h2>
                        {modes.length === 1 && <p className="text-[14px] text-fg-2">Pickup at {restaurant.name}.</p>}
                        <div role="radiogroup" aria-label="Fulfillment" className={cx('flex border border-line-strong', modes.length === 1 && 'hidden')}>
                            {modes.map(([v, label]) => (
                                <button key={v} type="button" role="radio" aria-checked={mode === v} onClick={() => setMode(v)}
                                    className={cx('h-11 flex-1 border-r border-line-strong text-[14px] font-medium last:border-r-0', mode === v ? 'bg-brand text-white' : 'bg-surface text-fg-2 hover:bg-soft')}>
                                    {label}
                                </button>
                            ))}
                        </div>
                        {fieldError('fulfillment') && <p className="text-[13px] text-bad">{fieldError('fulfillment')}</p>}

                        {mode === 'delivery' && (
                            <div className="space-y-3">
                                <label className="block">
                                    <span className={LABEL}>Delivery area</span>
                                    <select className={FIELD} value={zoneId ?? ''} onChange={(e) => setZoneId(Number(e.target.value))}>
                                        {zones.map((z) => <option key={z.id} value={z.id}>{z.name} · {z.terms}</option>)}
                                    </select>
                                </label>
                                <label className="block">
                                    <span className={LABEL}>Address or landmark</span>
                                    <textarea rows={2} className={cx(FIELD, 'h-auto py-2.5')} value={address} onChange={(e) => setAddress(e.target.value)} autoComplete="street-address" />
                                </label>
                                {zone?.radius && (
                                    <button type="button" onClick={locate} className="h-10 border border-line-strong bg-surface px-4 text-[13px] font-medium hover:border-brand">
                                        {coords ? 'Location shared ✓' : locating ? 'Locating…' : 'Share my location (needed for this area)'}
                                    </button>
                                )}
                                {fieldError('delivery_address', 'delivery_zone_id', 'delivery_lat') && <p className="text-[13px] text-bad">{fieldError('delivery_address', 'delivery_zone_id', 'delivery_lat')}</p>}
                            </div>
                        )}

                        {room && (
                            <label className="block">
                                <span className={LABEL}>Deliver to</span>
                                <select className={FIELD} value={stay} onChange={(e) => setStay(e.target.value)}>
                                    {stays.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </select>
                                {fieldError('room_stay', 'booking_id', 'room_id') && <p className="mt-1 text-[13px] text-bad">{fieldError('room_stay', 'booking_id', 'room_id')}</p>}
                            </label>
                        )}
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-[16px] font-semibold">When</h2>
                        <div className="flex flex-wrap gap-2">
                            {[['asap', `As soon as possible (about ${restaurant.prep_minutes} min)`], ['later', 'Schedule for later']].map(([v, label]) => (
                                <label key={v} className={cx('flex h-11 cursor-pointer items-center gap-2 border px-3 text-[14px]', when === v ? 'border-brand bg-brand-soft' : 'border-line')}>
                                    <input type="radio" name="when" className="accent-[var(--primary)] text-brand" checked={when === v} onChange={() => setWhen(v)} /> {label}
                                </label>
                            ))}
                        </div>
                        {when === 'later' && <input type="datetime-local" className={cx(FIELD, 'max-w-xs')} min={minSchedule} value={scheduled} onChange={(e) => setScheduled(e.target.value)} aria-label="Scheduled time" required />}
                        {fieldError('scheduled_for') && <p className="text-[13px] text-bad">{fieldError('scheduled_for')}</p>}
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-[16px] font-semibold">Your details</h2>
                        {who.view}
                        {fieldError('customer_phone') && <p className="text-[13px] text-bad">{fieldError('customer_phone')}</p>}
                        <label className="block">
                            <span className={LABEL}>Notes for the restaurant <span className="font-normal text-fg-3">(optional)</span></span>
                            <input className={FIELD} value={notes} maxLength={1000} onChange={(e) => setNotes(e.target.value)} placeholder="Gate code, cutlery" />
                        </label>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-[16px] font-semibold">Payment</h2>
                        <div className="grid gap-2 sm:grid-cols-3">
                            {paymentOptions.map(([v, label, hint]) => (
                                <label key={v} className={cx('flex cursor-pointer items-start gap-2 border px-3 py-3', payment === v ? 'border-brand bg-brand-soft' : 'border-line hover:border-line-strong')}>
                                    <input type="radio" name="pay" className="mt-1 accent-[var(--primary)] text-brand" checked={payment === v} onChange={() => setPay(v)} />
                                    <span><span className="block text-[14px] font-medium">{label}</span><span className="block text-[12px] text-fg-3">{hint}</span></span>
                                </label>
                            ))}
                        </div>
                        {fieldError('payment_method') && <p className="text-[13px] text-bad">{fieldError('payment_method')}</p>}
                    </section>
                </div>

                <aside className="border border-line bg-surface lg:sticky lg:top-6">
                    <div className="border-b border-line px-5 py-4">
                        <p className="text-[13px] text-fg-3">Your order from</p>
                        <p className="font-display text-[19px] font-medium">{restaurant.name}</p>
                    </div>
                    <ul className="divide-y divide-line px-5">
                        {lines.map((l) => (
                            <li key={l.key} className="flex items-center gap-3 py-3">
                                <div className="min-w-0 flex-1">
                                    <p className="text-[14px] font-medium">{l.name}</p>
                                    {l.mods && <p className="text-[12px] text-fg-3">{l.mods}</p>}
                                    {l.notes && <p className="text-[12px] text-fg-3">“{l.notes}”</p>}
                                </div>
                                <div className="w-28 shrink-0"><Stepper value={l.qty} min={0} max={50} onChange={(q) => setQty(l.key, q)} label={`${l.name} quantity`} /></div>
                                <span className="w-20 shrink-0 text-right text-[14px] tabular-nums">{money(l.total, 'PHP', 2)}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="space-y-4 border-t border-line px-5 py-4">
                        <div>
                            <div className="flex gap-2">
                                <input className={FIELD} value={code} onChange={(e) => setCode(e.target.value)} placeholder="Promo code" aria-label="Promo code" autoCapitalize="characters" />
                                <button type="button" onClick={applyPromo} className="h-11 shrink-0 border border-line-strong bg-surface px-4 text-[14px] font-medium hover:border-brand">Apply</button>
                            </div>
                            {quote.promo && <p className="mt-1 text-[13px] text-ok">{quote.promo.code} applied: {quote.promo.label}</p>}
                            {fieldError('promo_code') && <p className="mt-1 text-[13px] text-bad">{fieldError('promo_code')}</p>}
                        </div>
                        <dl className="space-y-1.5 text-[14px] tabular-nums">
                            <div className="flex justify-between text-fg-2"><dt>Subtotal</dt><dd>{money(quote.subtotal, 'PHP', 2)}</dd></div>
                            {quote.discount > 0 && <div className="flex justify-between text-ok"><dt>Discount</dt><dd>−{money(quote.discount, 'PHP', 2)}</dd></div>}
                            <div className="flex justify-between text-fg-2"><dt>Tax ({restaurant.tax_rate}% {restaurant.tax_inclusive ? 'included' : 'added'})</dt><dd>{money(quote.tax, 'PHP', 2)}</dd></div>
                            {zone && <div className="flex justify-between text-fg-2"><dt>Delivery</dt><dd>{fee > 0 ? money(fee, 'PHP', 2) : 'Free'}</dd></div>}
                            <div className="flex justify-between border-t border-line pt-2 text-[16px] font-semibold"><dt>Total</dt><dd aria-live="polite">{money(total, 'PHP', 2)}</dd></div>
                        </dl>
                        <button type="submit" className={BTN} disabled={busy}>
                            {busy ? 'Placing order…' : `Place order · ${money(total, 'PHP', 2)}`}
                        </button>
                    </div>
                </aside>
            </form>
        </>
    );
}

Checkout.layout = (page) => <PublicShell>{page}</PublicShell>;
