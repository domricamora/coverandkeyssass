import { Link } from '@inertiajs/react';
import { Action, Badge, Main, Page, Panel, Status, Table, Td } from '../../../react/kit';

/** CMS pages (about, terms, privacy, help) served at /pages/{slug}. */
export default function AdminPages({ pages, urls }) {
    return (
        <Page eyebrow="Super Admin" title="CMS pages" subtitle="About, terms, privacy and help, served at /pages/{slug}." actions={<Link href={urls.create} className="btn-primary">New page</Link>}>
            <Panel>
                <Table head={['Page', 'Address', 'Status', 'Updated', { label: '', right: true }]} empty="No pages yet.">
                    {pages.map((p) => (
                        <tr key={p.id}>
                            <Td><Main href={p.edit} title={p.title} /></Td>
                            <Td><a href={p.view} className="text-brand hover:underline">/pages/{p.slug}</a></Td>
                            <Td><span className="flex flex-wrap gap-1.5"><Status value={p.published ? 'published' : 'draft'} />{p.footer && <Badge>Footer</Badge>}</span></Td>
                            <Td muted>{p.updated}</Td>
                            <Td right>
                                <span className="inline-flex gap-1">
                                    <Link href={p.edit} className="btn-ghost btn-sm">Edit</Link>
                                    <Action href={p.destroy} method="delete" confirm={`Delete ${p.title}?`} className="btn-ghost btn-sm text-bad">Delete</Action>
                                </span>
                            </Td>
                        </tr>
                    ))}
                </Table>
            </Panel>
        </Page>
    );
}
