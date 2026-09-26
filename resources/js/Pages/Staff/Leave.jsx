import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Page, Panel, Status, Table, Tabs, Td } from '../../react/kit';

/** Leave requests: decide pending ones, see recent decisions. */
export default function StaffLeave({ pending, recent, can, tabs }) {
    return (
        <Page title="Leave" subtitle={`${pending.length} request(s) waiting`}>
            <Tabs tabs={tabs} />
            <Panel title="Pending">
                <ul className="divide-y divide-line text-[13px]">
                    {pending.length === 0 && <li className="px-5 py-6 text-fg-3">Nothing to decide.</li>}
                    {pending.map((l) => <PendingRow key={l.id} leave={l} canApprove={can.approve} />)}
                </ul>
            </Panel>
            <Panel title="Recent decisions">
                <Table head={['Employee', 'Leave', 'Dates', 'Decision', 'Note']} empty="No decisions yet.">
                    {recent.map((l) => (
                        <tr key={l.id}>
                            <Td className="font-medium text-fg">{l.name}</Td>
                            <Td muted>{l.type}</Td>
                            <Td muted>{l.dates}</Td>
                            <Td><Status value={l.status} /></Td>
                            <Td muted>{l.note ?? '—'}</Td>
                        </tr>
                    ))}
                </Table>
            </Panel>
        </Page>
    );
}

function PendingRow({ leave: l, canApprove }) {
    const [note, setNote] = useState('');
    const decide = (approve) => router.post(l.decide, { approve: approve ? 1 : 0, note }, { preserveScroll: true });

    return (
        <li className="flex flex-wrap items-start justify-between gap-3 px-5 py-3">
            <div className="min-w-0">
                <p><span className="font-medium text-fg">{l.name}</span> <span className="text-fg-2">· {l.type} · {l.dates} ({l.days} day{l.days === 1 ? '' : 's'})</span></p>
                {l.reason && <p className="mt-0.5 text-[12px] text-fg-3">{l.reason}</p>}
            </div>
            {canApprove && (
                <div className="flex flex-wrap items-center gap-2">
                    <button type="button" className="btn-primary btn-sm" onClick={() => decide(true)}>Approve</button>
                    <input className="field h-8 w-40" value={note} onChange={(e) => setNote(e.target.value)} placeholder="Reason (if rejecting)" aria-label="Rejection reason" />
                    <button type="button" className="btn-danger btn-sm" onClick={() => decide(false)}>Reject</button>
                </div>
            )}
        </li>
    );
}
