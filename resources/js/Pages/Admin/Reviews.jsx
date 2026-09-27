import { Link, router, usePage } from '@inertiajs/react';
import { Badge, Page, Pager, Panel, Status, Table, Td } from '../../react/kit';
import { cx } from '../../react/ui';

/** Review moderation: reported reviews first; keep (publish) or hide. */
export default function AdminReviews({ reviews, flagged, filter }) {
    const here = usePage().url.split('?')[0];
    const tabs = { '': 'All', flagged: `Reported (${flagged})`, rejected: 'Hidden' };

    const moderate = (r, publish) => {
        const note = window.prompt(publish ? 'Note (optional):' : 'Why hide this review? (optional)', '');
        if (note === null) return;
        router.post(r.moderate, { publish: publish ? 1 : 0, note: note || null }, { preserveScroll: true });
    };

    return (
        <Page
            eyebrow="Super Admin"
            title="Reviews"
            subtitle="Verified reviews go live at once. Hide the ones that break the rules."
            actions={(
                <div className="flex flex-wrap gap-1">
                    {Object.entries(tabs).map(([k, text]) => (
                        <Link key={k} href={here} data={k === 'flagged' ? { flagged: 1 } : k ? { status: k } : {}} preserveState aria-current={filter === k ? 'true' : undefined}
                            className={cx('h-8 border px-3 text-[12px] leading-[30px]', filter === k ? 'border-ink bg-ink text-white' : 'border-line bg-surface text-fg-2 hover:border-line-strong')}>
                            {text}
                        </Link>
                    ))}
                </div>
            )}
        >
            <Panel>
                <Table head={['Review', 'Listing', 'Status', { label: '', right: true }]} empty="Nothing to moderate.">
                    {reviews.data.map((r) => (
                        <tr key={r.id}>
                            <Td className="max-w-xl">
                                <p className="text-fg"><span className="font-medium">{r.author}</span> · <span className="text-coral-deep">{'★'.repeat(r.rating)}</span> · <span className="text-fg-3">{r.date}</span></p>
                                <p className="mt-1 text-fg-2">{r.comment}</p>
                                {r.flag && <p className="mt-1.5"><Badge tone="warn">Reported: {r.flag}</Badge></p>}
                                {r.note && <p className="mt-1 text-[12px] text-fg-3">Note: {r.note}</p>}
                            </Td>
                            <Td muted>{r.listing}</Td>
                            <Td><Status value={r.status} /></Td>
                            <Td right>
                                <span className="inline-flex gap-1">
                                    {(r.status !== 'published' || r.flag) && <button type="button" className="btn-primary btn-sm" onClick={() => moderate(r, true)}>Keep</button>}
                                    {r.status !== 'rejected' && <button type="button" className="btn-ghost btn-sm text-bad" onClick={() => moderate(r, false)}>Hide</button>}
                                </span>
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={reviews} />
            </Panel>
        </Page>
    );
}
