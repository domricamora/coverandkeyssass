import { useForm } from '@inertiajs/react';
import { Action, Form, Input, Page, Panel, Row, Select, Split, Stack, Status, Table, Tabs, Td } from '../../react/kit';
import { label, money } from '../../react/ui';

/** One invoice: lines and totals, issue / void, record payments. */
export default function Invoice({ invoice: inv, today, can, tabs, urls }) {
    const pay = useForm({ amount: inv.balance, method: 'bank', paid_on: today, reference: '' });

    return (
        <Page
            title={`${inv.number} · ${inv.customer}`}
            subtitle={<>Issued {inv.issued} · due {inv.due} · <Status value={inv.status} /></>}
            back={{ href: urls.index, label: 'All invoices' }}
            actions={
                <>
                    {can.issue && <Action href={urls.issue} className="btn-primary">Issue</Action>}
                    {can.void && <Action href={urls.void} className="btn-ghost" confirm="Void this invoice?">Void</Action>}
                </>
            }
        >
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Panel title="Lines">
                    <Table head={['Description', { label: 'Qty', right: true }, { label: 'Unit', right: true }, { label: 'Amount', right: true }]}>
                        {inv.lines.map((l) => (
                            <tr key={l.id}>
                                <Td className="text-fg">{l.description}</Td>
                                <Td right>{l.quantity}</Td>
                                <Td right>{money(l.unit)}</Td>
                                <Td right>{money(l.total)}</Td>
                            </tr>
                        ))}
                    </Table>
                    <dl className="space-y-1.5 border-t border-line px-5 py-4 text-[13px] tabular-nums">
                        <div className="flex justify-between text-fg-2"><dt>Subtotal</dt><dd>{money(inv.subtotal)}</dd></div>
                        <div className="flex justify-between text-fg-2"><dt>VAT {inv.taxRate}%</dt><dd>{money(inv.tax)}</dd></div>
                        <div className="flex justify-between font-semibold"><dt>Total</dt><dd>{money(inv.total)}</dd></div>
                        <div className="flex justify-between text-fg-2"><dt>Paid</dt><dd>{money(inv.paid)}</dd></div>
                        <div className="flex justify-between border-t border-line pt-2 text-[15px] font-semibold"><dt>Balance</dt><dd>{money(inv.balance)}</dd></div>
                    </dl>
                    {inv.notes && <p className="border-t border-line px-5 py-3 text-[13px] text-fg-2">{inv.notes}</p>}
                </Panel>
                <Stack>
                    {can.pay && (
                        <Panel title="Record payment" pad>
                            <Form form={pay} url={urls.pay} button="Record payment">
                                <Row>
                                    <Input form={pay} name="amount" type="number" step="0.01" min="0.01" label="Amount" required />
                                    <Select form={pay} name="method" label="Method" options={[['bank', 'Bank'], ['cash', 'Cash']]} />
                                </Row>
                                <Input form={pay} name="paid_on" type="date" label="Paid on" required />
                                <Input form={pay} name="reference" label="Reference" />
                            </Form>
                        </Panel>
                    )}
                    <Panel title="Payments">
                        <ul className="divide-y divide-line text-[13px]">
                            {inv.payments.length === 0 && <li className="px-5 py-4 text-fg-3">None yet.</li>}
                            {inv.payments.map((p) => (
                                <li key={p.id} className="flex justify-between gap-3 px-5 py-2.5">
                                    <span className="text-fg-2">{p.date} · {label(p.method)}{p.reference && ` · ${p.reference}`}</span>
                                    <span className="tabular-nums">{money(p.amount)}</span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
