import { useForm } from '@inertiajs/react';
import { Input, Main, Page, Pager, Panel, Select, Split, Status, Table, Tabs, Td, submit } from '../../react/kit';

/** Purchase orders list and a draft form with any number of lines. */
export default function PurchaseOrders({ orders, suppliers, locations, items, tabs, can, urls }) {
    const blank = { inventory_item_id: items[0]?.[0] ?? '', quantity: '', unit_cost: '' };
    const form = useForm({ supplier_id: suppliers[0]?.[0] ?? '', stock_location_id: locations[0]?.[0] ?? '', expected_on: '', lines: [blank] });
    const setLine = (i, k, v) => form.setData('lines', form.data.lines.map((l, n) => (n === i ? { ...l, [k]: v } : l)));

    return (
        <Page title="Purchase orders" subtitle="Order from suppliers; receiving books stock at the ordered cost.">
            <Tabs tabs={tabs} />
            <Split side="400px">
                <Panel>
                    <Table head={['PO', 'Supplier', 'Deliver to', 'Status', { label: 'Total', right: true }]} empty="No purchase orders yet.">
                        {orders.data.map((po) => (
                            <tr key={po.reference}>
                                <Td><Main href={po.href} title={po.reference} sub={po.when} /></Td>
                                <Td>{po.supplier}</Td>
                                <Td muted>{po.location}</Td>
                                <Td><Status value={po.status} /></Td>
                                <Td right>{po.total}</Td>
                            </tr>
                        ))}
                    </Table>
                    <Pager page={orders} />
                </Panel>

                {can.buy && suppliers.length > 0 && locations.length > 0 && (
                    <Panel title="New purchase order" pad>
                        <form onSubmit={submit(form, urls.store)} className="space-y-4">
                            <Select form={form} name="supplier_id" label="Supplier" options={suppliers} />
                            <Select form={form} name="stock_location_id" label="Deliver to" options={locations} />
                            <Input form={form} name="expected_on" type="date" label="Expected on" />
                            <div className="space-y-2">
                                <span className="label">Lines</span>
                                {form.data.lines.map((l, i) => (
                                    <div key={i} className="grid grid-cols-[1fr_72px_84px] gap-1.5">
                                        <select className="field" aria-label="Item" value={l.inventory_item_id} onChange={(e) => setLine(i, 'inventory_item_id', e.target.value)}>
                                            {items.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                                        </select>
                                        <input className="field" type="number" step="0.001" min="0" placeholder="Qty" aria-label="Quantity" value={l.quantity} onChange={(e) => setLine(i, 'quantity', e.target.value)} />
                                        <input className="field" type="number" step="0.0001" min="0" placeholder="₱/unit" aria-label="Unit cost" value={l.unit_cost} onChange={(e) => setLine(i, 'unit_cost', e.target.value)} />
                                    </div>
                                ))}
                                {form.errors.lines && <p className="text-[12px] text-bad">{form.errors.lines}</p>}
                                <button type="button" className="btn-ghost btn-sm" onClick={() => form.setData('lines', [...form.data.lines, blank])}>+ Line</button>
                            </div>
                            <button type="submit" className="btn-primary" disabled={form.processing}>Save draft</button>
                        </form>
                    </Panel>
                )}
            </Split>
        </Page>
    );
}
