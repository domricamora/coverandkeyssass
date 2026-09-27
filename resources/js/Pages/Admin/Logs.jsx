import { router, usePage } from '@inertiajs/react';
import { Main, Page, Pager, Panel, Table, Td } from '../../react/kit';

/** Audit log: every privileged action, who did it and from where. */
export default function AdminLogs({ logs, prefixes, filters }) {
    const here = usePage().url.split('?')[0];

    const apply = (e) => {
        e.preventDefault();
        const data = Object.fromEntries([...new FormData(e.currentTarget)].filter(([, v]) => v));
        router.get(here, data, { preserveState: true });
    };

    return (
        <Page eyebrow="Super Admin" title="Audit log" subtitle="Every privileged action, with who did it and where from.">
            <form onSubmit={apply} className="flex flex-wrap items-end gap-2">
                <select name="action" defaultValue={filters.action ?? ''} aria-label="Area" className="field w-auto min-w-40">
                    <option value="">Any area</option>
                    {prefixes.map((p) => <option key={p} value={p}>{p}</option>)}
                </select>
                <input name="user" defaultValue={filters.user ?? ''} placeholder="User email" aria-label="User email" className="field w-52" />
                <input name="tenant" type="number" defaultValue={filters.tenant ?? ''} placeholder="Business ID" aria-label="Business ID" className="field w-32" />
                <input name="from" type="date" defaultValue={filters.from ?? ''} aria-label="From date" className="field w-auto" />
                <button type="submit" className="btn-ghost">Filter</button>
            </form>
            <Panel>
                <Table head={['When', 'Action', 'Who', 'Business', 'Details']} empty="No entries.">
                    {logs.data.map((l) => (
                        <tr key={l.id}>
                            <Td muted className="whitespace-nowrap text-[12px]">{l.when}</Td>
                            <Td><span className="font-mono text-[12px] text-coral-deep">{l.action}</span></Td>
                            <Td><Main title={l.who} sub={l.ip} /></Td>
                            <Td muted>{l.business ?? '—'}</Td>
                            <Td><span className="block max-w-md break-all font-mono text-[11px] text-fg-3">{l.details}</span></Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={logs} />
            </Panel>
        </Page>
    );
}
