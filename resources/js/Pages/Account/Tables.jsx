import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { FIELD } from '../../widgets/ui';
import { TripStatus } from './TripCard';

/** Table reservations: cancel while allowed, quick review after the visit. */
export default function Tables({ reservations, tabs, urls }) {
    return (
        <>
            <Head title="Table reservations" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Table reservations</h1>
            <AccountTabs tabs={tabs} />
            {reservations.data.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">
                    No table reservations yet. <a href={urls.browse} className="text-brand hover:underline">Find a restaurant</a>
                </div>
            ) : (
                <div className="space-y-2">{reservations.data.map((r) => <Reservation key={r.reference} r={r} />)}</div>
            )}
            <Pager page={reservations} />
        </>
    );
}

function Reservation({ r }) {
    const [reviewing, setReviewing] = useState(false);
    const form = useForm({ rating: 5, comment: '' });

    return (
        <div className="border border-line bg-surface px-4 py-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="min-w-0">
                    <span className="block text-[15px] font-medium text-fg">{r.restaurant}</span>
                    <span className="block text-[13px] text-fg-3">{r.when} · party of {r.party} · {r.reference}</span>
                    {r.requests && <span className="block text-[13px] text-fg-3">“{r.requests}”</span>}
                </span>
                <span className="flex items-center gap-3">
                    <TripStatus status={r.status} label={r.statusLabel} />
                    {r.canReview && !reviewing && <button type="button" className="text-[14px] font-medium text-brand hover:underline" onClick={() => setReviewing(true)}>Review</button>}
                    {r.canCancel && (
                        <button type="button" className="text-[14px] text-bad hover:underline" onClick={() => window.confirm('Cancel this reservation?') && router.post(r.urls.cancel, {}, { preserveScroll: true })}>Cancel</button>
                    )}
                </span>
            </div>
            {reviewing && (
                <form onSubmit={(e) => { e.preventDefault(); form.post(r.urls.review, { preserveScroll: true }); }} className="mt-3 space-y-2 border-t border-line pt-3">
                    <div className="flex gap-0.5" role="radiogroup" aria-label="Rating">
                        {[1, 2, 3, 4, 5].map((n) => (
                            <button key={n} type="button" role="radio" aria-checked={form.data.rating === n} aria-label={`${n} stars`} onClick={() => form.setData('rating', n)}
                                className={`text-[22px] leading-none ${n <= form.data.rating ? 'text-coral' : 'text-line-strong'}`}>★</button>
                        ))}
                    </div>
                    <textarea rows={2} required className={`${FIELD} h-auto py-2`} value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} placeholder="How was your visit?" aria-label="Review" />
                    {form.errors.comment && <p className="text-[13px] text-bad">{form.errors.comment}</p>}
                    <button type="submit" className="inline-flex h-10 items-center bg-brand px-4 text-[14px] font-medium text-white hover:bg-brand-deep" disabled={form.processing}>Publish</button>
                </form>
            )}
        </div>
    );
}

Tables.layout = (page) => <PublicShell>{page}</PublicShell>;
