import { useForm } from '@inertiajs/react';
import { Form, Input, Page, Pager, Panel, Row, Select, Stats, Status, Table, Td } from '../../react/kit';
import { cx, label, money } from '../../react/ui';

/** Host wallet: online earnings after commission, payout requests, commissions and the ledger. */
export default function Wallet({ currency, balances, transactions, commissions, payouts, methods, minPayout, can, urls }) {
    const payout = useForm({ amount: '', method: methods[0], account_name: '', account_number: '' });
    const m = (v) => money(v, currency);

    return (
        <Page title="Wallet" subtitle="Online booking and food-order earnings after platform commission. Earnings unlock when the guest checks out or the order is completed.">
            <Stats items={[['Available to withdraw', m(balances.available)], ['Pending (stays not finished)', m(balances.pending)], ['Payouts in progress', m(balances.inProgress)]]} />

            {can.request && (
                <Panel title="Request a payout" pad>
                    <Form form={payout} url={urls.payout} reset button="Request payout">
                        <Row cols={4}>
                            <Input form={payout} name="amount" type="number" step="0.01" min={minPayout} label="Amount" hint={`Minimum ${m(minPayout)}`} required />
                            <Select form={payout} name="method" label="Method" options={methods} />
                            <Input form={payout} name="account_name" label="Account name" required />
                            <Input form={payout} name="account_number" label="Account number" required />
                        </Row>
                    </Form>
                </Panel>
            )}

            <div className="grid gap-8 2xl:grid-cols-2">
                <Panel title="Recent commissions">
                    <Table head={['Source', { label: 'Gross', right: true }, { label: 'Fee', right: true }, { label: 'You get', right: true }, 'Status']} empty="No online payments yet.">
                        {commissions.map((c) => (
                            <tr key={c.id}>
                                <Td className="text-fg">{c.source}</Td>
                                <Td right>{m(c.gross)}</Td>
                                <Td right>{m(c.fee)} <span className="text-fg-3">({c.rate}%)</span></Td>
                                <Td right>{m(c.net)}</Td>
                                <Td><Status value={c.status} /></Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
                <Panel title="Payouts">
                    <Table head={['Requested', { label: 'Amount', right: true }, 'To', 'Status']} empty="No payouts yet.">
                        {payouts.map((p) => (
                            <tr key={p.id}>
                                <Td muted>{p.date}</Td>
                                <Td right>{m(p.amount)}</Td>
                                <Td muted>{p.to}</Td>
                                <Td><Status value={p.status} />{p.reference && <span className="mt-0.5 block text-[12px] text-fg-3">Ref {p.reference}</span>}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
            </div>

            <Panel title="Wallet activity">
                <Table head={['Date', 'Description', 'Balance', { label: 'Amount', right: true }, { label: 'Balance after', right: true }]} empty="No wallet activity yet.">
                    {transactions.data.map((t) => (
                        <tr key={t.id}>
                            <Td muted className="whitespace-nowrap">{t.date}</Td>
                            <Td className="text-fg">{t.description}</Td>
                            <Td muted>{label(t.bucket)}</Td>
                            <Td right className={cx(t.amount < 0 && 'text-bad')}>{t.amount >= 0 ? '+' : '−'}{m(Math.abs(t.amount))}</Td>
                            <Td right>{m(t.after)}</Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={transactions} />
            </Panel>
        </Page>
    );
}
