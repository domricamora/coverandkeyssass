import { useForm } from '@inertiajs/react';
import { Input, Main, Page, Pager, Panel, Row, Split, Status, Table, Tabs, Td, submit } from '../../react/kit';
import { money } from '../../react/ui';

/** Customer invoices on terms (corporate accounts, events): list and a draft form with lines. */
export default function Invoices({ invoices, today, due, can, tabs, urls }) {
    const form = useForm({ customer_name: '', customer_email: '', issue_date: today, due_date: due, tax_rate: 12, lines: [{ description: '', quantity: 1, unit_price: '' }] });
    const setLine = (i, k, v) => form.setData('lines', form.data.lines.map((l, j) => (j === i ? { ...l, [k]: v } : l)));
    const subtotal = form.data.lines.reduce((s, l) => s + (Number(l.quantity) || 0) * (Number(l.unit_price) || 0), 0);

    return (
        <Page title="Invoices" subtitle="Corporate accounts, events and anything billed on terms">
            <Tabs tabs={tabs} />
            <Split side="400px">
                <Panel>
                    <Table head={['Invoice', 'Due', 'Status', { label: 'Total', right: true }, { label: 'Balance', right: true }]} empty="No invoices yet.">
                        {invoices.data.map((i) => (
                            <tr key={i.id}>
                                <Td><Main href={i.href} title={i.number} sub={i.customer} /></Td>
                                <Td muted className="whitespace-nowrap">{i.due}</Td>
                                <Td><Status value={i.status} /></Td>
                                <Td right>{money(i.total)}</Td>
                                <Td right>{money(i.balance)}</Td>
                            </tr>
                        ))}
                    </Table>
                    <Pager page={invoices} />
                </Panel>
                {can.manage && (
                    <Panel title="New invoice" pad>
                        <form onSubmit={submit(form, urls.store)} className="space-y-4">
                            <Input form={form} name="customer_name" label="Customer" required />
                            <Input form={form} name="customer_email" type="email" label="Email" />
                            <Row>
                                <Input form={form} name="issue_date" type="date" label="Issue date" required />
                                <Input form={form} name="due_date" type="date" label="Due date" required />
                            </Row>
                            <Input form={form} name="tax_rate" type="number" step="0.01" min="0" max="50" label="VAT %" />
                            <div className="space-y-2">
                                <span className="label">Lines</span>
                                {form.data.lines.map((l, i) => (
                                    <div key={i} className="grid grid-cols-[minmax(0,1fr)_64px_96px] gap-2">
                                        <input className="field" placeholder="Description" value={l.description} onChange={(e) => setLine(i, 'description', e.target.value)} aria-label={`Line ${i + 1} description`} />
                                        <input className="field" type="number" step="0.01" min="0" value={l.quantity} onChange={(e) => setLine(i, 'quantity', e.target.value)} aria-label={`Line ${i + 1} quantity`} />
                                        <input className="field" type="number" step="0.01" min="0" placeholder="₱" value={l.unit_price} onChange={(e) => setLine(i, 'unit_price', e.target.value)} aria-label={`Line ${i + 1} unit price`} />
                                    </div>
                                ))}
                                {form.errors.lines && <p className="text-[12px] text-bad">{form.errors.lines}</p>}
                                <div className="flex items-center justify-between">
                                    <button type="button" className="btn-ghost btn-sm" onClick={() => form.setData('lines', [...form.data.lines, { description: '', quantity: 1, unit_price: '' }])}>+ Line</button>
                                    <span className="text-[13px] tabular-nums text-fg-2">Subtotal {money(subtotal)}</span>
                                </div>
                            </div>
                            <button type="submit" className="btn-primary" disabled={form.processing}>Save draft</button>
                        </form>
                    </Panel>
                )}
            </Split>
        </Page>
    );
}
