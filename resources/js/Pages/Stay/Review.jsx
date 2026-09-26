import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PublicShell from '../../react/PublicShell';
import { BTN, FIELD, LABEL, Stepper, json, money, postForm, useGuest } from '../../widgets/ui';

/** Stay booking, step 2 of 2: review the stay, add details and a promo code, request the booking. */
export default function StayReview({ property, stay, option, cancellation, guest, urls }) {
    const { errors, guestUrls } = usePage().props;
    const who = useGuest({ user: guest, urls: guestUrls, returnTo: urls.here });
    const [adults, setAdults] = useState(Math.max(1, stay.guests));
    const [children, setChildren] = useState(0);
    const [requests, setRequests] = useState('');
    const [code, setCode] = useState('');
    const [promo, setPromo] = useState(null);
    const [promoState, setPromoState] = useState({ busy: false, error: null });
    const [sending, setSending] = useState(false);

    const discount = promo?.discount ?? 0;
    const total = Math.max(0, option.total - discount);

    const applyPromo = async () => {
        if (!code.trim()) return;
        setPromoState({ busy: true, error: null });
        try {
            const q = await json(`${urls.quote}?${new URLSearchParams({ check_in: stay.check_in, check_out: stay.check_out, guests: stay.guests, room_type_id: stay.room_type_id, quantity: stay.quantity, promo_code: code.trim() })}`);
            setPromo(q.promo);
            setPromoState({ busy: false, error: q.promo_error });
        } catch (e) {
            setPromoState({ busy: false, error: e.message });
        }
    };

    const submit = async (e) => {
        e.preventDefault();
        setSending(true);
        if (!(await who.ensure())) { setSending(false); return; }
        postForm(urls.reserve, {
            check_in: stay.check_in, check_out: stay.check_out, room_type_id: stay.room_type_id, quantity: stay.quantity,
            adults, children, guest_phone: who.phone, special_requests: requests, promo_code: promo?.code,
        });
    };

    return (
        <>
            <Head title={`Review your stay at ${property.name}`} />
            <ol className="mb-6 flex items-center gap-3 text-[13px]" aria-label="Booking steps">
                <li><a href={urls.back} className="text-fg-3 hover:text-fg">1. Choose room</a></li>
                <li aria-hidden="true" className="text-fg-4">/</li>
                <li aria-current="step" className="font-medium text-fg">2. Review and request</li>
            </ol>

            <form onSubmit={submit} className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_400px]">
                <div className="space-y-8">
                    <h1 className="font-display text-[30px] font-medium leading-tight tracking-tight">Review and request</h1>

                    <section className="space-y-4">
                        <h2 className="text-[16px] font-semibold">Your details</h2>
                        {who.view}
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div><span className={LABEL}>Adults</span><Stepper value={adults} min={1} max={50} onChange={setAdults} label="adults" /></div>
                            <div><span className={LABEL}>Children</span><Stepper value={children} min={0} max={50} onChange={setChildren} label="children" /></div>
                        </div>
                        {(errors?.adults || errors?.guest_phone) && <p className="text-[13px] text-bad">{errors.adults ?? errors.guest_phone}</p>}
                        <label className="block">
                            <span className={LABEL}>Requests for the host <span className="font-normal text-fg-3">(optional)</span></span>
                            <textarea rows={3} className={`${FIELD} h-auto py-2.5`} value={requests} onChange={(e) => setRequests(e.target.value)} placeholder="Arrival time, bed setup, celebrations" />
                        </label>
                    </section>

                    <section className="space-y-3">
                        <h2 className="text-[16px] font-semibold">Promo code</h2>
                        <div className="flex max-w-md gap-2">
                            <input className={FIELD} value={code} onChange={(e) => { setCode(e.target.value); setPromo(null); setPromoState({ busy: false, error: null }); }} placeholder="SUMMER10" aria-label="Promo code" autoCapitalize="characters" />
                            <button type="button" className="h-11 shrink-0 border border-line-strong bg-surface px-4 text-[14px] font-medium hover:border-brand disabled:opacity-50" disabled={!code.trim() || promoState.busy} onClick={applyPromo}>
                                {promoState.busy ? 'Checking…' : 'Apply'}
                            </button>
                        </div>
                        <p aria-live="polite" className="text-[13px]">
                            {promo && <span className="text-ok">{promo.code} applied: {promo.label}</span>}
                            {promoState.error && <span className="text-bad">{promoState.error}</span>}
                            {errors?.promo_code && <span className="text-bad">{errors.promo_code}</span>}
                        </p>
                    </section>

                    <section className="border-l-2 border-brand bg-brand-soft px-4 py-3 text-[14px] text-fg-2">
                        <p className="font-medium text-fg">What happens next</p>
                        <p className="mt-1">The host confirms your request, usually within a few hours. You won't be charged until then{cancellation ? `. ${cancellation}.` : '.'}</p>
                    </section>
                </div>

                <aside className="border border-line bg-surface lg:sticky lg:top-6">
                    {property.cover && <img src={property.cover} alt="" className="aspect-[16/9] w-full object-cover" />}
                    <div className="space-y-4 p-5">
                        <div>
                            <p className="font-display text-[19px] font-medium leading-snug">{property.name}</p>
                            {property.where && <p className="text-[13px] text-fg-3">{property.where}</p>}
                        </div>
                        <dl className="grid grid-cols-2 gap-3 border-y border-line py-4 text-[13px]">
                            <div><dt className="text-fg-3">Check-in</dt><dd className="font-medium">{stay.from}</dd>{property.check_in_time && <dd className="text-fg-3">from {property.check_in_time}</dd>}</div>
                            <div><dt className="text-fg-3">Check-out</dt><dd className="font-medium">{stay.to}</dd>{property.check_out_time && <dd className="text-fg-3">by {property.check_out_time}</dd>}</div>
                            <div className="col-span-2"><dt className="text-fg-3">Room</dt><dd className="font-medium">{stay.quantity} × {option.name}</dd></div>
                        </dl>
                        <dl className="space-y-1.5 text-[14px] tabular-nums">
                            <div className="flex justify-between text-fg-2"><dt>{money(option.per_night, option.currency)} × {stay.nights} {stay.nights === 1 ? 'night' : 'nights'}{stay.quantity > 1 ? ` × ${stay.quantity}` : ''}</dt><dd>{money(option.total, option.currency)}</dd></div>
                            {discount > 0 && <div className="flex justify-between text-ok"><dt>Promo {promo.code}</dt><dd>−{money(discount, option.currency)}</dd></div>}
                            <div className="flex justify-between border-t border-line pt-2 text-[16px] font-semibold"><dt>Total</dt><dd aria-live="polite">{money(total, option.currency)}</dd></div>
                        </dl>
                        <button type="submit" className={BTN} disabled={sending}>{sending ? 'Sending request…' : 'Request booking'}</button>
                        <a href={urls.back} className="block text-center text-[13px] text-fg-3 hover:text-fg">Change dates or room</a>
                    </div>
                </aside>
            </form>
        </>
    );
}

StayReview.layout = (page) => <PublicShell>{page}</PublicShell>;
