import { Link, usePage } from '@inertiajs/react';
import { Badge, Main, Page, Pager, Panel, Search, Status, Table, Td } from '../../../react/kit';

/** Every business (tenant) on the platform. */
export default function AdminTenants({ tenants, filters, urls }) {
    const here = usePage().url.split('?')[0];

    return (
        <Page
            eyebrow="Super Admin"
            title="Businesses"
            subtitle="Hotels, resorts, restaurants and every other business using the platform."
            actions={(
                <>
                    <Search url={here} value={filters.q} params={filters} placeholder="Search businesses" />
                    <Link href={urls.create} className="btn-primary">New business</Link>
                </>
            )}
        >
            <Panel>
                <Table head={['Business', 'Status', { label: 'Members', right: true }, { label: '', right: true }]} empty="No businesses found.">
                    {tenants.data.map((t) => (
                        <tr key={t.id}>
                            <Td><Main href={t.href} title={t.name} sub={t.type} /></Td>
                            <Td>
                                <span className="flex flex-wrap gap-1.5"><Status value={t.status} />{t.verified && <Badge tone="brand">Verified host</Badge>}</span>
                            </Td>
                            <Td right>{t.members}</Td>
                            <Td right><Link href={t.href} className="btn-ghost btn-sm">Manage</Link></Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={tenants} />
            </Panel>
        </Page>
    );
}
