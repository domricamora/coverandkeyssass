import { router, useForm } from '@inertiajs/react';
import { Fragment } from 'react';
import { Form, Input, Page, Panel, Row, Select, Split, Stack, Table, Td } from '../../react/kit';
import { cx, label, money } from '../../react/ui';

const GROUPS = [['charge', 'Charges'], ['payment', 'Payments'], ['refund', 'Refunds']];

/** A booking's folio: ledger + totals, and desk forms for charges, payments and refunds. */
export default function Folio({ booking, entries, totals, categories, methods, can, urls }) {
    const charge = useForm({ category: categories[0], description: '', unit_amount: '', quantity: 1 });
    const payment = useForm({ method: methods[0], amount: totals.balance > 0 ? totals.balance : '', reference: '' });
    const refund = useForm({ method: methods[0], amount: '', reason: '' });

    const voidEntry = (e) => {
        const reason = window.prompt('Why void this line?');
        if (reason) router.post(e.void, { reason }, { preserveScroll: true });
    };

    return (
        <Page
            title={`Folio · ${booking.reference}`}
            subtitle={booking.summary}
            back={{ href: urls.booking, label: 'Booking' }}
            actions={<a href={urls.print} target="_blank" rel="noopener" className="btn-ghost">Print</a>}
        >
            <Split>
                <Stack>
                    <Panel title="Ledger">
                        <Table head={['Date', 'Description', 'Category', { label: 'Amount', right: true }, ...(can.void ? [''] : [])]} empty="No lines yet.">
                            {GROUPS.map(([type, heading]) => {
                                const rows = entries.filter((e) => e.type === type);
                                if (!rows.length) return null;
                                return (
                                    <Fragment key={type}>
                                        <tr className="bg-soft"><td colSpan={can.void ? 5 : 4} className="px-5 py-2 text-[11px] font-semibold uppercase tracking-[0.08em] text-fg-3">{heading}</td></tr>
                                        {rows.map((e) => (
                                            <tr key={e.id} className={cx(e.voided && 'opacity-50')}>
                                                <Td muted className="whitespace-nowrap">{e.date}</Td>
                                                <Td>
                                                    <span className={cx(e.voided && 'line-through')}>{e.description}</span>
                                                    {e.quantity !== 1 && <span className="text-fg-3"> ({e.quantity} × {money(e.unit)})</span>}
                                                    {e.reference && <span className="block text-[12px] text-fg-3">Ref {e.reference}</span>}
                                                    {e.voided && <span className="block text-[12px] text-fg-3">Void: {e.void_reason}</span>}
                                                </Td>
                                                <Td muted>{e.category}</Td>
                                                <Td right className={cx(e.voided && 'line-through')}>{type === 'charge' ? '' : '−'}{money(e.amount)}</Td>
                                                {can.void && <Td right>{e.voidable && <button className="btn-ghost btn-sm" onClick={() => voidEntry(e)}>Void</button>}</Td>}
                                            </tr>
                                        ))}
                                    </Fragment>
                                );
                            })}
                        </Table>
                    </Panel>

                    <Panel title="Totals">
                        <dl className="divide-y divide-line text-[13px] tabular-nums">
                            {Object.entries(totals.by_category ?? {}).map(([c, v]) => <Line key={c} k={label(c)} v={money(v)} />)}
                            <Line k="Total charges" v={money(totals.charges)} strong />
                            <Line k="Payments" v={`−${money(totals.payments)}`} />
                            {totals.refunds > 0 && <Line k="Refunds" v={`+${money(totals.refunds)}`} />}
                            <Line k={`Balance ${totals.balance < 0 ? '(credit)' : 'due'}`} v={money(Math.abs(totals.balance))} strong />
                        </dl>
                    </Panel>
                </Stack>

                {can.manage && (
                    <Stack>
                        <Panel title="Post a charge" pad>
                            <Form form={charge} url={urls.charge} reset button="Post charge">
                                <Select form={charge} name="category" label="Category" options={categories} />
                                <Input form={charge} name="description" label="Description" placeholder="Airport transfer" required />
                                <Row>
                                    <Input form={charge} name="unit_amount" type="number" step="0.01" min="0.01" label="Amount (₱)" required />
                                    <Input form={charge} name="quantity" type="number" step="0.01" min="0.01" label="Quantity" />
                                </Row>
                            </Form>
                        </Panel>
                        <Panel title="Record a payment" pad>
                            <Form form={payment} url={urls.payment} button="Record payment">
                                <Row>
                                    <Select form={payment} name="method" label="Method" options={methods} />
                                    <Input form={payment} name="amount" type="number" step="0.01" min="0.01" label="Amount (₱)" required />
                                </Row>
                                <Input form={payment} name="reference" label="Reference / gift card code" />
                            </Form>
                        </Panel>
                        <Panel title="Refund at the desk" pad>
                            <Form form={refund} url={urls.refund} reset button="Record refund">
                                <Row>
                                    <Select form={refund} name="method" label="Method" options={methods.filter((m) => m !== 'gift_card')} />
                                    <Input form={refund} name="amount" type="number" step="0.01" min="0.01" label="Amount (₱)" required />
                                </Row>
                                <Input form={refund} name="reason" label="Reason" />
                            </Form>
                        </Panel>
                    </Stack>
                )}
            </Split>
        </Page>
    );
}

const Line = ({ k, v, strong }) => (
    <div className={cx('flex justify-between px-5 py-2.5', strong && 'font-semibold text-fg')}>
        <dt className={strong ? '' : 'text-fg-3'}>{k}</dt>
        <dd>{v}</dd>
    </div>
);
