import { router } from '@inertiajs/react';
import { Badge, Page, Panel, Table, Tabs, Td } from '../../react/kit';
import { label } from '../../react/ui';

const num = (v) => (v ? Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');

/** Trial balance as of a date. */
export default function TrialBalance({ asOf, tb, tabs, urls }) {
    const balanced = Math.abs(tb.debits - tb.credits) < 0.01;

    return (
        <Page
            title="Trial balance"
            subtitle={`As of ${asOf}`}
            actions={<input type="date" className="field w-auto" value={asOf} aria-label="As of" onChange={(e) => router.get(urls.self, { as_of: e.target.value }, { preserveState: true })} />}
        >
            <Tabs tabs={tabs} />
            <Panel title="Accounts" aside={<Badge tone={balanced ? 'ok' : 'bad'}>{balanced ? 'balanced' : 'out of balance'}</Badge>}>
                <Table head={['Account', 'Type', { label: 'Debit', right: true }, { label: 'Credit', right: true }]} empty="Nothing posted yet.">
                    {tb.rows.map((r) => (
                        <tr key={r.code}>
                            <Td className="text-fg">{r.code} · {r.name}</Td>
                            <Td muted>{label(r.type)}</Td>
                            <Td right>{num(r.debit)}</Td>
                            <Td right>{num(r.credit)}</Td>
                        </tr>
                    ))}
                    {tb.rows.length > 0 && (
                        <tr className="font-semibold">
                            <Td colSpan={2}>Totals</Td>
                            <Td right>{num(tb.debits)}</Td>
                            <Td right>{num(tb.credits)}</Td>
                        </tr>
                    )}
                </Table>
            </Panel>
        </Page>
    );
}
