import { Head } from '@inertiajs/react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';

const Stars = ({ n }) => (
    <span className="text-[14px] tracking-tight text-coral" aria-label={`${n} out of 5`}>{'★'.repeat(n)}<span className="text-line-strong">{'★'.repeat(5 - n)}</span></span>
);

/** Reviews the guest has written, with the host's reply when there is one. */
export default function Reviews({ reviews, tabs }) {
    return (
        <>
            <Head title="Your reviews" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Reviews</h1>
            <AccountTabs tabs={tabs} />
            {reviews.data.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">You have not written any reviews yet. You can review a stay after check-out, and a meal or table after your visit.</div>
            ) : (
                <ul className="space-y-3">
                    {reviews.data.map((r) => (
                        <li key={r.id} className="border border-line bg-surface px-5 py-4">
                            <p className="flex flex-wrap items-baseline justify-between gap-2">
                                <span className="text-[15px] font-medium text-fg">{r.listing}</span>
                                <span className="text-[12px] text-fg-3">{r.date} · {r.status}</span>
                            </p>
                            <Stars n={r.rating} />
                            {r.title && <p className="mt-2 text-[14px] font-medium text-fg">{r.title}</p>}
                            <p className="mt-1 max-w-[65ch] text-[14px] leading-relaxed text-fg-2">{r.comment}</p>
                            {r.reply && (
                                <div className="mt-3 border-l-2 border-brand bg-brand-soft/60 px-4 py-2.5 text-[13px] text-fg-2">
                                    <span className="font-medium text-fg">Reply from the host</span>
                                    <p className="mt-0.5">{r.reply}</p>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <Pager page={reviews} />
        </>
    );
}

Reviews.layout = (page) => <PublicShell>{page}</PublicShell>;
