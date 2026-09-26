import { useForm } from '@inertiajs/react';
import { Action, Page, Panel, Status, Table, Tabs, Td } from '../../react/kit';

/** One purchase order: lines, mark ordered / cancel, and receiving (partial allowed). */
export default function PurchaseOrder({ po, can, tabs, urls }) {
    const form = useForm({ received: Object.fromEntries(po.lines.map((l) => [l.id, l.outstanding])) });

    return (
        <Page
            title={po.reference}
            subtitle={po.summary}
            back={{ href: urls.index, label: 'Purchase orders' }}
            actions={
                <>
                    <Status value={po.status} />
                    {can.order && <Action href={urls.order} className="btn-primary">Mark ordered</Action>}
                    {can.cancel && <Action href={urls.cancel} className="btn-ghost" confirm="Cancel this purchase order?">Cancel</Action>}
                </>
            }
        >
            <Tabs tabs={tabs} />
            <form onSubmit={(e) => { e.preventDefault(); form.post(urls.receive, { preserveScroll: true }); }}>
                <Panel title="Lines" aside={`Total ${po.total}`}>
                    <Table head={['Item', { label: 'Ordered', right: true }, { label: 'Received', right: true }, { label: 'Unit cost', right: true }, { label: 'Line', right: true }, ...(can.receive ? ['Receive now'] : [])]}>
                        {po.lines.map((l) => (
                            <tr key={l.id}>
                                <Td className="text-fg">{l.item}</Td>
                                <Td right>{l.ordered}</Td>
                                <Td right>{l.received}</Td>
                                <Td right muted>{l.cost}</Td>
                                <Td right>{l.line}</Td>
                                {can.receive && (
                                    <Td>
                                        <input
                                            type="number" step="0.001" min="0" max={l.outstanding}
                                            className="field w-28" aria-label={`Receive ${l.item}`}
                                            value={form.data.received[l.id]}
                                            onChange={(e) => form.setData('received', { ...form.data.received, [l.id]: e.target.value })}
                                        />
                                    </Td>
                                )}
                            </tr>
                        ))}
                    </Table>
                    {can.receive && (
                        <div className="flex justify-end border-t border-line px-5 py-3">
                            <button className="btn-primary" disabled={form.processing}>Receive</button>
                        </div>
                    )}
                </Panel>
            </form>
        </Page>
    );
}
