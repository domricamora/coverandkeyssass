import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Main, Page, Panel, Table, Tabs, Td } from '../../react/kit';
import { money } from '../../react/ui';

/** What we owe suppliers (pay from here) and what customers owe us (open invoices). */
export default function Payables({ suppliers, otherPayables, receivables, today, can, tabs }) {
    return (
        <Page title="Payables & receivables">
            <Tabs tabs={tabs} />
            <div className="grid gap-8 2xl:grid-cols-2">
                <Panel title="We owe (suppliers)">
                    <Table head={['Supplier', { label: 'Owed', right: true }, '']} empty="No suppliers.">
                        {suppliers.map((s) => <SupplierRow key={s.id} s={s} today={today} canPay={can.manage} />)}
                        {otherPayables !== 0 && (
                            <tr><Td muted>Other payables (repairs, bills)</Td><Td right>{money(otherPayables)}</Td><Td /></tr>
                        )}
                    </Table>
                </Panel>
                <Panel title="Owed to us (open invoices)">
                    <Table head={['Invoice', 'Due', { label: 'Balance', right: true }]} empty="Nothing outstanding.">
                        {receivables.map((i) => (
                            <tr key={i.id}>
                                <Td><Main href={i.href} title={i.number} sub={i.customer} /></Td>
                                <Td muted>{i.due} {i.overdueDays !== null && <Badge tone="bad">{i.overdueDays}d overdue</Badge>}</Td>
                                <Td right>{money(i.balance)}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
            </div>
        </Page>
    );
}

function SupplierRow({ s, today, canPay }) {
    const [amount, setAmount] = useState(s.owed);
    const [method, setMethod] = useState('bank');
    const pay = (e) => { e.preventDefault(); router.post(s.pay, { amount, method, paid_on: today }, { preserveScroll: true }); };

    return (
        <tr>
            <Td className="font-medium text-fg">{s.name}</Td>
            <Td right>{money(s.owed)}</Td>
            <Td right>
                {canPay && s.owed > 0 && (
                    <form onSubmit={pay} className="flex justify-end gap-1">
                        <input type="number" step="0.01" min="0.01" max={s.owed} className="field h-8 w-28" value={amount} onChange={(e) => setAmount(e.target.value)} aria-label={`Amount to ${s.name}`} />
                        <select className="field h-8 w-24" value={method} onChange={(e) => setMethod(e.target.value)} aria-label="Method"><option value="bank">Bank</option><option value="cash">Cash</option></select>
                        <button type="submit" className="btn-primary btn-sm">Pay</button>
                    </form>
                )}
            </Td>
        </tr>
    );
}
