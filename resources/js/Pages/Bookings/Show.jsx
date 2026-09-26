import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Action, Facts, Page, Panel, Status, Table, Td } from '../../react/kit';
import { label } from '../../react/ui';

const ACTIONS = {
    confirmed: ['Confirm', 'btn-primary'],
    checked_in: ['Check in', 'btn-primary'],
    checked_out: ['Check out', 'btn-primary'],
    completed: ['Mark completed', 'btn-ghost'],
    no_show: ['No-show', 'btn-ghost'],
    refunded: ['Mark refunded', 'btn-ghost'],
    held: ['Hold', 'btn-ghost'],
};

/** One booking: status actions, guest, rooms & charges, payments. */
export default function Booking({ booking: b, payments, can, urls }) {
    const [reason, setReason] = useState('');

    return (
        <Page
            title={b.reference}
            subtitle={b.summary}
            back={{ href: urls.index, label: 'Bookings' }}
            actions={
                <>
                    <Status value={b.status} />
                    <Link href={urls.frontdesk} className="btn-ghost">Open at front desk</Link>
                    {can.folio && <Link href={urls.folio} className="btn-ghost">Folio</Link>}
                </>
            }
        >
            {can.update && b.next.length > 0 && (
                <div className="flex flex-wrap items-center gap-2 border border-line bg-surface p-4">
                    {b.next.filter((to) => to !== 'cancelled').map((to) => (
                        <Action key={to} href={urls.transition} data={{ status: to }} className={ACTIONS[to]?.[1] ?? 'btn-ghost'}>{ACTIONS[to]?.[0] ?? label(to)}</Action>
                    ))}
                    {b.next.includes('cancelled') && (
                        <span className="flex gap-2 sm:ml-auto">
                            <input className="field w-56" placeholder="Cancellation reason" aria-label="Cancellation reason" value={reason} onChange={(e) => setReason(e.target.value)} />
                            <button
                                className="btn-danger"
                                onClick={() => window.confirm('Cancel this booking and release its rooms?') && router.post(urls.transition, { status: 'cancelled', reason }, { preserveScroll: true })}
                            >
                                Cancel booking
                            </button>
                        </span>
                    )}
                    {b.hold_expires && <span className="text-[13px] text-fg-3">Hold expires {b.hold_expires}</span>}
                </div>
            )}

            <div className="grid gap-8 lg:grid-cols-2">
                <Panel title="Guest"><Facts rows={b.guest} /></Panel>
                <Panel title="Rooms & charges">
                    <Facts rows={[...b.lines, ['Total', <strong key="t">{b.total}</strong>]]} />
                </Panel>
            </div>

            {payments.length > 0 && (
                <Panel title="Payments">
                    <Table head={['Date', 'Method', 'Status', { label: 'Amount', right: true }]}>
                        {payments.map((p) => (
                            <tr key={p.id}>
                                <Td muted>{p.date}</Td>
                                <Td>{p.method}</Td>
                                <Td><Status value={p.status} />{p.note && <span className="mt-1 block text-[12px] text-fg-3">{p.note}</span>}</Td>
                                <Td right>{p.amount}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
            )}
        </Page>
    );
}
