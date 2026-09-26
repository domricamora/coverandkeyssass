import { Link, router } from '@inertiajs/react';
import { Badge, Empty, Page, Pager, Panel } from '../../react/kit';
import { cx } from '../../react/ui';

const FILTERS = [['', 'All'], ['unanswered', 'Unanswered'], ['low', '1–2 stars']];

/** Guest reviews: filter, reply publicly, report abuse to the platform. */
export default function Reviews({ reviews, average, unanswered, filter, can, urls }) {
    const opts = { preserveScroll: true };

    return (
        <Page
            title="Reviews"
            subtitle={`Average ${Number(average).toFixed(1)} ★ · ${unanswered} without a reply`}
            actions={
                <div className="flex border border-line bg-surface" role="tablist" aria-label="Filter">
                    {FILTERS.map(([key, name]) => (
                        <Link key={key} href={key ? `${urls.self}?filter=${key}` : urls.self} role="tab" aria-selected={filter === key} className={cx('h-9 px-4 text-[13px] leading-9', filter === key ? 'bg-brand text-white' : 'text-fg-2')}>{name}</Link>
                    ))}
                </div>
            }
        >
            {reviews.data.length === 0 && <Empty title="No reviews yet" body="Reviews from stays, orders and table visits show up here." />}
            <div className="space-y-4">
                {reviews.data.map((r) => (
                    <Panel key={r.id}>
                        <div className="space-y-2 px-5 py-4 text-[13px] text-fg-2">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p>
                                    <strong className="font-medium text-fg">{r.guest}</strong>{' '}
                                    <span className={cx('font-semibold', r.rating <= 2 ? 'text-bad' : 'text-fg')}>{r.rating} ★</span> · {r.about}{' '}
                                    {r.status !== 'published' && <Badge>{r.status}</Badge>} {r.flagged && <Badge tone="warn">reported</Badge>}
                                </p>
                                {r.categories.length > 0 && <p className="text-[12px] text-fg-3">{r.categories.join(' · ')}</p>}
                            </div>
                            {r.title && <p className="font-medium text-fg">{r.title}</p>}
                            <p className="whitespace-pre-line">{r.comment}</p>
                            {r.dishes && <p className="text-[12px] text-fg-3">Dishes: {r.dishes}</p>}
                            {r.reply && <p className="border-l-2 border-brand bg-soft px-3 py-2"><strong className="font-medium text-fg">Your reply:</strong> {r.reply}</p>}
                        </div>
                        {can.reply && (
                            <div className="flex flex-wrap gap-3 border-t border-line px-5 py-3">
                                <form className="flex min-w-64 flex-1 gap-2" onSubmit={(e) => { e.preventDefault(); const f = e.currentTarget; router.post(r.urls.reply, { host_response: f.reply.value }, { ...opts, onSuccess: () => f.reset() }); }}>
                                    <input name="reply" required className="field" placeholder={r.reply ? 'Edit your reply' : 'Reply publicly'} aria-label="Reply" />
                                    <button className="btn-primary">Reply</button>
                                </form>
                                {!r.flagged && (
                                    <button className="btn-ghost" onClick={() => { const why = window.prompt('Why report this review?'); if (why) router.post(r.urls.flag, { flag_reason: why }, opts); }}>Report</button>
                                )}
                            </div>
                        )}
                    </Panel>
                ))}
            </div>
            <Pager page={reviews} />
        </Page>
    );
}
