import { Head, router, useForm, usePage } from '@inertiajs/react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { BTN, FIELD, LABEL, csrf } from '../../widgets/ui';
import { TripStatus } from './TripCard';

const CATEGORIES = ['cleanliness', 'location', 'service', 'value', 'amenities'];

/** One trip: status, price, payments (pay now), cancel, review, links to invoice / folio / host. */
export default function Trip({ trip, payments, providers, can, tabs, urls }) {
    const { errors } = usePage().props;
    const p = trip.property;
    const review = useForm({ rating: 5, title: '', comment: '', ...Object.fromEntries(CATEGORIES.map((c) => [`rating_${c}`, ''])) });

    return (
        <>
            <Head title={`Trip ${trip.reference}`} />
            <p className="mb-2 text-[13px]"><a href={urls.trips} className="text-fg-3 hover:text-fg">← All trips</a></p>
            <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">Trip {trip.reference}</p>
            <h1 className="mb-2 font-display text-[30px] font-medium tracking-tight">{p?.url ? <a href={p.url} className="hover:text-brand">{p.name}</a> : p?.name}</h1>
            <p className="mb-6 flex flex-wrap items-center gap-2 text-[14px] text-fg-2">
                <TripStatus status={trip.status} label={trip.statusLabel} /> {trip.dates} · {trip.nights} {trip.nights === 1 ? 'night' : 'nights'} · {trip.guests}
            </p>
            <AccountTabs tabs={tabs} />

            {(errors.booking || errors.payment) && <p role="alert" className="mb-6 border-l-2 border-bad bg-bad-bg px-4 py-3 text-[14px] text-bad">{errors.booking ?? errors.payment}</p>}

            <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
                <div className="space-y-8">
                    {['pending', 'held'].includes(trip.status) && (
                        <section className="border-l-2 border-warn bg-warn-bg px-4 py-3 text-[14px] text-fg-2">
                            <p className="font-medium text-fg">Not confirmed yet</p>
                            <p className="mt-1">{providers.length ? 'Pay now to confirm straight away, or wait for the host to confirm.' : 'The host confirms your request, usually within a few hours.'} The rooms are held for you meanwhile.</p>
                        </section>
                    )}

                    {providers.length > 0 && (
                        <section className="space-y-3">
                            <h2 className="text-[16px] font-semibold">Pay {trip.total}</h2>
                            <div className="flex flex-wrap gap-2">
                                {providers.map(([key, label]) => (
                                    // Plain POST: the payment endpoint redirects off-site to the provider.
                                    <form key={key} method="post" action={urls.pay}>
                                        <input type="hidden" name="_token" value={csrf()} />
                                        <input type="hidden" name="provider" value={key} />
                                        <button type="submit" className="inline-flex h-11 items-center border border-line-strong bg-surface px-4 text-[14px] font-medium hover:border-brand">{label}</button>
                                    </form>
                                ))}
                            </div>
                        </section>
                    )}

                    {payments.length > 0 && (
                        <section>
                            <h2 className="mb-3 text-[16px] font-semibold">Payments</h2>
                            <ul className="divide-y divide-line border-y border-line text-[14px]">
                                {payments.map((pay) => (
                                    <li key={pay.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5">
                                        <span className="text-fg-2">{pay.date} · {pay.method}{pay.failure && <span className="block text-[12px] text-bad">{pay.failure}</span>}</span>
                                        <span className="flex items-center gap-3"><span className="text-[12px] capitalize text-fg-3">{pay.status}</span><span className="tabular-nums">{pay.amount}</span></span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}

                    {can.review && (
                        <section className="space-y-4">
                            <h2 className="text-[16px] font-semibold">How was your stay?</h2>
                            <form onSubmit={(e) => { e.preventDefault(); review.post(urls.review, { preserveScroll: true }); }} className="space-y-4">
                                <div className="flex gap-1" role="radiogroup" aria-label="Overall rating">
                                    {[1, 2, 3, 4, 5].map((n) => (
                                        <button key={n} type="button" role="radio" aria-checked={review.data.rating === n} aria-label={`${n} star${n > 1 ? 's' : ''}`}
                                            onClick={() => review.setData('rating', n)} className={`text-[28px] leading-none ${n <= review.data.rating ? 'text-coral' : 'text-line-strong'}`}>★</button>
                                    ))}
                                </div>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
                                    {CATEGORIES.map((c) => (
                                        <label key={c} className="block">
                                            <span className={LABEL}>{c[0].toUpperCase() + c.slice(1)}</span>
                                            <select className={FIELD} value={review.data[`rating_${c}`]} onChange={(e) => review.setData(`rating_${c}`, e.target.value)}>
                                                <option value="">—</option>
                                                {[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{n}</option>)}
                                            </select>
                                        </label>
                                    ))}
                                </div>
                                <label className="block"><span className={LABEL}>Title (optional)</span><input className={FIELD} value={review.data.title} onChange={(e) => review.setData('title', e.target.value)} /></label>
                                <label className="block">
                                    <span className={LABEL}>Your review</span>
                                    <textarea rows={4} required className={`${FIELD} h-auto py-2.5`} value={review.data.comment} onChange={(e) => review.setData('comment', e.target.value)} />
                                </label>
                                {(review.errors.comment || review.errors.rating) && <p className="text-[13px] text-bad">{review.errors.comment ?? review.errors.rating}</p>}
                                <button type="submit" className={`${BTN} sm:w-auto`} disabled={review.processing}>Publish review</button>
                            </form>
                        </section>
                    )}
                </div>

                <aside className="border border-line bg-surface lg:sticky lg:top-6">
                    {p?.cover && <img src={p.cover} alt="" className="aspect-[16/9] w-full object-cover" />}
                    <div className="space-y-4 p-5">
                        {p?.where && <p className="text-[13px] text-fg-3">{p.where}</p>}
                        <dl className="space-y-1.5 text-[14px] tabular-nums">
                            {trip.rooms.map((r) => <div key={r.id} className="flex justify-between text-fg-2"><dt>{r.name}</dt><dd>{r.total}</dd></div>)}
                            {trip.discount && <div className="flex justify-between text-ok"><dt>{trip.discount.label}</dt><dd>−{trip.discount.amount}</dd></div>}
                            <div className="flex justify-between border-t border-line pt-2 text-[16px] font-semibold"><dt>Total</dt><dd>{trip.total}</dd></div>
                        </dl>
                        {p?.times && <p className="text-[13px] text-fg-3">{p.times}</p>}
                        <ul className="space-y-1.5 border-t border-line pt-4 text-[14px]">
                            <li><a href={urls.message} className="text-brand hover:underline">Message the property</a></li>
                            <li><a href={urls.invoice} className="text-brand hover:underline">Invoice</a></li>
                            <li><a href={urls.folio} className="text-brand hover:underline">Folio</a></li>
                        </ul>
                        {can.cancel && (
                            <div className="border-t border-line pt-4">
                                <button type="button" className="text-[14px] font-medium text-bad hover:underline"
                                    onClick={() => window.confirm('Cancel this booking?') && router.post(urls.cancel, {}, { preserveScroll: true })}>
                                    Cancel booking
                                </button>
                                {p?.policy && <p className="mt-1 text-[12px] text-fg-3">{p.policy}</p>}
                            </div>
                        )}
                    </div>
                </aside>
            </div>
        </>
    );
}

Trip.layout = (page) => <PublicShell>{page}</PublicShell>;
