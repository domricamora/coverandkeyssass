import { Link, router } from '@inertiajs/react';
import { Main, Page, Pager, Panel, Status, Table, Td } from '../../react/kit';

/** Host payout queue: transfer the money first, then mark it paid with the transfer reference. */
export default function AdminPayouts({ payouts, urls }) {
    const settle = (p, status) => {
        const value = window.prompt(status === 'paid' ? `Transfer reference for payout #${p.id} (${p.amount}):` : `Why reject payout #${p.id}? The amount goes back to the host's wallet.`, '');
        if (value === null || (status === 'paid' && !value)) return;
        router.patch(p.update, status === 'paid' ? { status, reference: value } : { status, note: value || null }, { preserveScroll: true });
    };

    return (
        <Page
            eyebrow="Super Admin"
            title="Payouts"
            subtitle="Transfer the amount to the host's account first, then mark it paid with the transfer reference."
            actions={<Link href={urls.commissions} className="btn-ghost btn-sm">Commissions</Link>}
        >
            <Panel>
                <Table head={['Payout', 'Business', { label: 'Amount', right: true }, 'Send to', 'Status', { label: '', right: true }]} empty="No payout requests.">
                    {payouts.data.map((p) => (
                        <tr key={p.id}>
                            <Td><Main title={`#${p.id}`} sub={p.date} /></Td>
                            <Td muted>{p.business}</Td>
                            <Td right>{p.amount}</Td>
                            <Td muted>{p.destination}</Td>
                            <Td><Status value={p.status} />{p.reference && <span className="mt-0.5 block text-[12px] text-fg-3">Ref {p.reference}</span>}</Td>
                            <Td right>
                                {p.update && (
                                    <span className="inline-flex gap-1">
                                        <button type="button" className="btn-primary btn-sm" onClick={() => settle(p, 'paid')}>Mark paid</button>
                                        <button type="button" className="btn-ghost btn-sm text-bad" onClick={() => settle(p, 'rejected')}>Reject</button>
                                    </span>
                                )}
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={payouts} />
            </Panel>
        </Page>
    );
}
