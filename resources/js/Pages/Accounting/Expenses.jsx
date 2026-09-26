import { useForm } from '@inertiajs/react';
import { Form, Input, Page, Pager, Panel, Row, Select, Split, Table, Tabs, Td } from '../../react/kit';
import { label, money } from '../../react/ui';

/** Bills outside purchasing (utilities, rent, fuel): list and booking form. */
export default function Expenses({ expenses, accounts, today, can, tabs, urls }) {
    const form = useForm({ vendor: '', ledger_account_id: accounts[0]?.[0] ?? '', expense_date: today, amount: '', tax_amount: '', paid_from: 'bank', reference: '' });

    return (
        <Page title="Expenses" subtitle="Utilities, rent, fuel and other bills outside purchasing">
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Panel>
                    <Table head={['Date', 'Vendor', 'Account', 'Paid from', { label: 'VAT', right: true }, { label: 'Amount', right: true }]} empty="No expenses yet.">
                        {expenses.data.map((e) => (
                            <tr key={e.id}>
                                <Td muted className="whitespace-nowrap">{e.date}</Td>
                                <Td><span className="text-fg">{e.vendor}</span>{e.reference && <span className="block text-[12px] text-fg-3">{e.reference}</span>}</Td>
                                <Td muted>{e.account}</Td>
                                <Td muted>{label(e.paidFrom)}</Td>
                                <Td right>{money(e.tax)}</Td>
                                <Td right>{money(e.amount)}</Td>
                            </tr>
                        ))}
                    </Table>
                    <Pager page={expenses} />
                </Panel>
                {can.manage && (
                    <Panel title="Book an expense" pad>
                        <Form form={form} url={urls.store} reset button="Book expense">
                            <Input form={form} name="vendor" label="Vendor" placeholder="Meralco" required />
                            <Select form={form} name="ledger_account_id" label="Account" options={accounts} />
                            <Input form={form} name="expense_date" type="date" label="Date" required />
                            <Row>
                                <Input form={form} name="amount" type="number" step="0.01" min="0.01" label="Gross ₱" required />
                                <Input form={form} name="tax_amount" type="number" step="0.01" min="0" label="VAT ₱" />
                            </Row>
                            <Select form={form} name="paid_from" label="Paid from" options={[['bank', 'Bank'], ['cash', 'Cash'], ['payable', 'Not yet paid (payable)']]} />
                            <Input form={form} name="reference" label="OR / invoice no." />
                        </Form>
                    </Panel>
                )}
            </Split>
        </Page>
    );
}
