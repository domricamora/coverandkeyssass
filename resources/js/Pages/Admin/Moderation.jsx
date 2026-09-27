import { Link, router, usePage } from '@inertiajs/react';
import { Chips, Main, Page, Pager, Panel, Status, Table, Td } from '../../react/kit';

/** Listings reported by guests. Closing a report closes every open report on the same listing. */
export default function AdminModeration({ status, reports, flaggedReviews, urls }) {
    const here = usePage().url.split('?')[0];

    const act = (r, action) => {
        let note = null;
        if (action === 'suspend') {
            note = window.prompt(`Suspend ${r.listing}? Give the reason (goes in the audit log):`);
            if (!note) return;
        }
        router.post(r.resolve, { action, note }, { preserveScroll: true });
    };

    return (
        <Page
            eyebrow="Super Admin"
            title="Reported content"
            subtitle="Listings reported by guests. Reported reviews are under Reviews."
            actions={(
                <>
                    <Chips url={here} name="status" value={status} options={[['open', 'Open'], ['resolved', 'Resolved'], ['dismissed', 'Dismissed']]} />
                    <Link href={urls.reviews} className="btn-ghost btn-sm">Reported reviews ({flaggedReviews})</Link>
                </>
            )}
        >
            <Panel>
                <Table head={['Listing', 'Reason', 'Reported by', { label: status === 'open' ? '' : 'Outcome', right: status === 'open' }]} empty="Nothing to review.">
                    {reports.data.map((r) => (
                        <tr key={r.id}>
                            <Td>
                                <Main title={r.listing ?? 'Deleted listing'} sub={r.kind} />
                                {r.listingStatus && <span className="mt-1 inline-block"><Status value={r.listingStatus} /></span>}
                            </Td>
                            <Td><Main title={r.reason} sub={r.details} /></Td>
                            <Td><Main title={r.by} sub={r.when} /></Td>
                            <Td right={r.status === 'open'}>
                                {r.status === 'open' ? (
                                    <span className="inline-flex flex-wrap justify-end gap-1">
                                        <button type="button" className="btn-ghost btn-sm text-bad" onClick={() => act(r, 'suspend')}>Suspend listing</button>
                                        <button type="button" className="btn-ghost btn-sm" onClick={() => act(r, 'resolve')}>Resolved</button>
                                        <button type="button" className="btn-ghost btn-sm" onClick={() => act(r, 'dismiss')}>Dismiss</button>
                                    </span>
                                ) : <span className="text-fg-2">{r.outcome}</span>}
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={reports} />
            </Panel>
        </Page>
    );
}
