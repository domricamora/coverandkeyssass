import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Page, Panel, Split, Stack, Stats, Tabs } from '../../react/kit';
import { cx, money } from '../../react/ui';

const Row = ({ label, value, strong, bad }) => (
    <div className={cx('flex justify-between gap-4 px-5 py-2 text-[13px]', strong && 'font-semibold')}>
        <span className={strong ? 'text-fg' : 'text-fg-2'}>{label}</span>
        <span className={cx('tabular-nums', bad && 'text-bad')}>{money(value || 0)}</span>
    </div>
);

/** Accounting overview: balances, profit & loss for a period, VAT, receivables / payables, commissions. */
export default function AccountingIndex({ from, to, pnl, vat, balances, receivables, payables, overdue, commissions, payouts, tabs, urls }) {
    const [range, setRange] = useState({ from, to });
    const apply = (e) => { e.preventDefault(); router.get(urls.self, range, { preserveState: true }); };

    return (
        <Page
            title="Accounting"
            subtitle="Profit & loss for the chosen period"
            actions={
                <form onSubmit={apply} className="flex flex-wrap items-center gap-2">
                    <input type="date" className="field w-auto" value={range.from} onChange={(e) => setRange({ ...range, from: e.target.value })} aria-label="From" />
                    <input type="date" className="field w-auto" value={range.to} onChange={(e) => setRange({ ...range, to: e.target.value })} aria-label="To" />
                    <button type="submit" className="btn-ghost btn-sm">Apply</button>
                </form>
            }
        >
            <Tabs tabs={tabs} />
            <Stats items={balances.map(([label, value]) => [label, money(value)])} />
            <Split side="360px">
                <Panel title="Profit & loss">
                    <div className="divide-y divide-line">
                        <div className="py-2">
                            <p className="px-5 py-1 text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">Revenue</p>
                            {pnl.revenue.length === 0 && <p className="px-5 py-2 text-[13px] text-fg-3">No revenue in this period.</p>}
                            {pnl.revenue.map((r) => <Row key={r.code} label={`${r.code} · ${r.name}`} value={r.amount} />)}
                            <Row label="Total revenue" value={pnl.total_revenue} strong />
                        </div>
                        <div className="py-2">
                            <p className="px-5 py-1 text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">Expenses</p>
                            {pnl.expenses.map((r) => <Row key={r.code} label={`${r.code} · ${r.name}`} value={r.amount} />)}
                            <Row label="Total expenses" value={pnl.total_expenses} strong />
                        </div>
                        <div className="py-2"><Row label={`Net ${pnl.net < 0 ? 'loss' : 'profit'}`} value={pnl.net} strong bad={pnl.net < 0} /></div>
                    </div>
                </Panel>

                <Stack>
                    <Panel title="VAT">
                        <div className="py-2">
                            <Row label="Output VAT" value={vat.output} />
                            <Row label="Input VAT" value={-vat.input} />
                            <Row label={vat.payable >= 0 ? 'Payable' : 'Refundable'} value={Math.abs(vat.payable)} strong />
                        </div>
                    </Panel>
                    <Panel title="Receivables & payables" aside={<Link href={urls.payables} className="text-brand hover:underline">Details</Link>}>
                        <div className="py-2">
                            <Row label="Invoices open" value={receivables} />
                            {overdue > 0 && <p className="px-5 pb-1"><Badge tone="bad">{overdue} overdue</Badge></p>}
                            <Row label="Accounts payable" value={payables} />
                        </div>
                    </Panel>
                    <Panel title="Commissions & payouts">
                        <div className="py-2">
                            <Row label="Platform commissions" value={commissions} />
                            <Row label="Payouts to bank" value={payouts} />
                        </div>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
