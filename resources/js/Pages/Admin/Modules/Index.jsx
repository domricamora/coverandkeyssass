import { Link } from '@inertiajs/react';
import { Action, Badge, Main, Page, Pager, Panel, Status, Table, Td } from '../../../react/kit';

/** The module catalogue: what businesses can switch on, its trial and how many use it. */
export default function AdminModules({ modules, urls }) {
    return (
        <Page eyebrow="Super Admin" title="Modules" subtitle="Core modules are on for every business and cannot be deleted." actions={<Link href={urls.create} className="btn-primary">New module</Link>}>
            <Panel>
                <Table head={['Module', 'Category', 'Status', 'Trial', { label: 'Businesses', right: true }, { label: '', right: true }]} empty="No modules yet.">
                    {modules.data.map((m) => (
                        <tr key={m.id}>
                            <Td><Main href={m.edit} title={m.name} sub={m.slug} /></Td>
                            <Td muted>{m.category}</Td>
                            <Td><span className="flex flex-wrap gap-1.5"><Status value={m.status} />{m.core && <Badge tone="info">Core</Badge>}</span></Td>
                            <Td muted>{m.trial ?? '—'}</Td>
                            <Td right>{m.tenants}</Td>
                            <Td right>
                                <span className="inline-flex gap-1">
                                    <Link href={m.edit} className="btn-ghost btn-sm">Edit</Link>
                                    {!m.core && <Action href={m.destroy} method="delete" confirm={`Delete ${m.name}?`} className="btn-ghost btn-sm text-bad">Delete</Action>}
                                </span>
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={modules} />
            </Panel>
        </Page>
    );
}
