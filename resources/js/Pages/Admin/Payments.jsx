import { router, usePage } from '@inertiajs/react';
import { Chips, Main, Page, Pager, Panel, Status, Table, Td } from '../../react/kit';

/** Guest payments across businesses; a refund cancels the booking or order first when needed. */
export default function AdminPayments({ payments, filters }) {
    const here = usePage().url.split('?')[0];

    const refund = (p) => {
        const reason = window.prompt(`Cancel and refund payment #${p.id} (${p.amount}) in full? Give a reason:`);
        if (reason) router.post(p.refund, { reason }, { preserveScroll: true });
    };

    return (
        <Page
            eyebrow="Super Admin"
            title="Payments & refunds"
            subtitle="Online payments from guests. A refund cancels the booking or order first when needed, then refunds through the gateway."
            actions={<Chips url={here} name="status" value={filters.status} options={[['', 'All'], ['paid', 'Paid'], ['pending', 'Pending'], ['failed', 'Failed'], ['refunded', 'Refunded']]} />}
        >
            <Panel>
                <Table head={['Payment', 'For', 'Payer', { label: 'Amount', right: true }, 'Status', { label: '', right: true }]} empty="No payments.">
                    {payments.data.map((p) => (
                        <tr key={p.id}>
                            <Td><Main title={`#${p.id}`} sub={`${p.created} · ${p.method ?? '—'}`} /></Td>
                            <Td muted>{p.for ?? '—'}</Td>
                            <Td><Main title={p.payer ?? '—'} sub={p.email} /></Td>
                            <Td right>{p.amount}</Td>
                            <Td><Status value={p.status} />{p.refunded && <span className="mt-0.5 block text-[12px] text-fg-3">{p.refunded}</span>}</Td>
                            <Td right>{p.refund && <button type="button" className="btn-ghost btn-sm text-bad" onClick={() => refund(p)}>Refund</button>}</Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={payments} />
            </Panel>
        </Page>
    );
}
