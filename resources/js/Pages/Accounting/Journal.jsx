import { Fragment } from 'react';
import { Filter, Page, Pager, Panel, Table, Tabs, Td } from '../../react/kit';

const num = (v) => (v ? Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '');

/** Every posting, newest first; filter by account. */
export default function Journal({ entries, accounts, account, tabs, urls }) {
    return (
        <Page title="Journal" subtitle="Every posting, newest first" actions={<Filter name="account" value={account} url={urls.self} placeholder="All accounts" options={accounts} />}>
            <Tabs tabs={tabs} />
            <Panel>
                <Table head={['Date', 'Entry', 'Account', { label: 'Debit', right: true }, { label: 'Credit', right: true }]} empty="Nothing posted yet.">
                    {entries.data.map((e) => (
                        <Fragment key={e.id}>
                            {e.lines.map((l, i) => (
                                <tr key={l.id}>
                                    <Td muted className="whitespace-nowrap">{i === 0 ? e.date : ''}</Td>
                                    <Td>{i === 0 && <><span className="text-fg">{e.memo}</span>{e.reference && <span className="block text-[12px] text-fg-3">{e.reference}</span>}</>}</Td>
                                    <Td muted className={l.credit > 0 ? 'pl-10' : undefined}>{l.account}</Td>
                                    <Td right>{num(l.debit)}</Td>
                                    <Td right>{num(l.credit)}</Td>
                                </tr>
                            ))}
                        </Fragment>
                    ))}
                </Table>
                <Pager page={entries} />
            </Panel>
        </Page>
    );
}
