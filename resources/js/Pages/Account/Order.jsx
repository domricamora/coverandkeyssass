import { Head, router, useForm, usePage } from '@inertiajs/react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { BTN, FIELD, LABEL, csrf } from '../../widgets/ui';
import { TripStatus } from './TripCard';

/** 1–5 star picker; optional pickers can be cleared by clicking the chosen star again. */
const Stars = ({ value, onChange, label, optional }) => (
    <div className="flex gap-0.5" role="radiogroup" aria-label={label}>
        {[1, 2, 3, 4, 5].map((n) => (
            <button key={n} type="button" role="radio" aria-checked={value === n} aria-label={`${n} star${n > 1 ? 's' : ''}`}
                onClick={() => onChange(optional && value === n ? '' : n)} className={`text-[22px] leading-none ${n <= (value || 0) ? 'text-coral' : 'text-line-strong'}`}>★</button>
        ))}
    </div>
);

/** One food order: live status, lines and totals, pay now, cancel while pending, review when completed. */
export default function Order({ order, providers, can, tabs, urls }) {
    const { errors } = usePage().props;
    const dishes = [...new Map(order.lines.filter((l) => l.menuItemId).map((l) => [l.menuItemId, l.name])).entries()];
    const review = useForm({ rating: 5, rating_food: '', rating_service: '', rating_value: '', comment: '', items: {} });

    return (
        <>
            <Head title={`Order ${order.reference}`} />
            <p className="mb-2 text-[13px]"><a href={urls.orders} className="text-fg-3 hover:text-fg">← All orders</a></p>
            <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">Order {order.reference}</p>
            <h1 className="mb-2 font-display text-[30px] font-medium tracking-tight">{order.restaurant}</h1>
            <p className="mb-6 text-[14px] text-fg-2">{order.meta}</p>
            <AccountTabs tabs={tabs} />

            {(errors.order || errors.payment) && <p role="alert" className="mb-6 border-l-2 border-bad bg-bad-bg px-4 py-3 text-[14px] text-bad">{errors.order ?? errors.payment}</p>}

            <div className="grid items-start gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
                <div className="space-y-8">
                    <section className="space-y-2 border border-line bg-surface p-5">
                        <p className="flex flex-wrap items-center gap-2 text-[15px]"><TripStatus status={order.status} label={order.statusLabel} /> <span className="text-fg-3">Payment: {order.payment}</span></p>
                        {order.eta && <p className="text-[16px] font-semibold text-fg">{order.eta}</p>}
                        {order.details.map((d) => <p key={d} className="text-[14px] text-fg-2">{d}</p>)}
                        <div className="flex flex-wrap gap-2 pt-2">
                            {providers.map(([key, label]) => (
                                // Plain POST: the payment endpoint redirects off-site to the provider.
                                <form key={key} method="post" action={urls.pay}>
                                    <input type="hidden" name="_token" value={csrf()} />
                                    <input type="hidden" name="provider" value={key} />
                                    <button type="submit" className="inline-flex h-10 items-center bg-brand px-4 text-[14px] font-medium text-white hover:bg-brand-deep">Pay {order.total} · {label}</button>
                                </form>
                            ))}
                            {can.cancel && (
                                <button type="button" className="inline-flex h-10 items-center border border-line-strong px-4 text-[14px] text-bad hover:border-bad"
                                    onClick={() => window.confirm('Cancel this order?') && router.post(urls.cancel, {}, { preserveScroll: true })}>Cancel order</button>
                            )}
                            <a href={urls.message} className="inline-flex h-10 items-center border border-line-strong px-4 text-[14px] text-fg-2 hover:border-brand">Message the restaurant</a>
                        </div>
                    </section>

                    {can.review && (
                        <section className="space-y-4">
                            <h2 className="text-[16px] font-semibold">How was your meal?</h2>
                            <form onSubmit={(e) => { e.preventDefault(); review.post(urls.review, { preserveScroll: true }); }} className="space-y-4">
                                <div className="grid gap-3 sm:grid-cols-4">
                                    {[['rating', 'Overall'], ['rating_food', 'Food'], ['rating_service', 'Service'], ['rating_value', 'Value']].map(([k, l]) => (
                                        <div key={k}><span className={LABEL}>{l}</span><Stars value={review.data[k]} label={l} optional={k !== 'rating'} onChange={(v) => review.setData(k, v)} /></div>
                                    ))}
                                </div>
                                {dishes.length > 0 && (
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        {dishes.map(([id, name]) => (
                                            <div key={id}><span className={LABEL}>{name}</span><Stars value={review.data.items[id] ?? ''} label={name} optional onChange={(v) => review.setData('items', { ...review.data.items, [id]: v })} /></div>
                                        ))}
                                    </div>
                                )}
                                <label className="block">
                                    <span className={LABEL}>Your review</span>
                                    <textarea rows={3} required className={`${FIELD} h-auto py-2.5`} value={review.data.comment} onChange={(e) => review.setData('comment', e.target.value)} placeholder="Tell others what you liked" />
                                </label>
                                {(review.errors.comment || review.errors.rating) && <p className="text-[13px] text-bad">{review.errors.comment ?? review.errors.rating}</p>}
                                <button type="submit" className={`${BTN} sm:w-auto`} disabled={review.processing}>Publish review</button>
                            </form>
                        </section>
                    )}
                </div>

                <aside className="border border-line bg-surface">
                    <ul className="divide-y divide-line px-5">
                        {order.lines.map((l) => (
                            <li key={l.id} className="flex justify-between gap-3 py-3 text-[14px]">
                                <span className="min-w-0">
                                    <span className="text-fg">{l.qty} × {l.name}</span>
                                    {l.mods && <span className="block text-[12px] text-fg-3">{l.mods}</span>}
                                    {l.notes && <span className="block text-[12px] text-fg-3">“{l.notes}”</span>}
                                </span>
                                <span className="shrink-0 tabular-nums">{l.total}</span>
                            </li>
                        ))}
                    </ul>
                    <dl className="space-y-1.5 border-t border-line px-5 py-4 text-[14px] tabular-nums">
                        {order.totals.map(([k, v]) => <div key={k} className="flex justify-between text-fg-2"><dt>{k}</dt><dd>{v}</dd></div>)}
                        <div className="flex justify-between border-t border-line pt-2 text-[16px] font-semibold"><dt>Total</dt><dd>{order.total}</dd></div>
                    </dl>
                </aside>
            </div>
        </>
    );
}

Order.layout = (page) => <PublicShell>{page}</PublicShell>;
