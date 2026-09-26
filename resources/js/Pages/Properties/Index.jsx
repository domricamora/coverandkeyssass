import { Link } from '@inertiajs/react';
import { Main, Page, Pager, Panel, Status, Table, Td } from '../../react/kit';

/** Properties: listings with type, status, starting rate and room count. */
export default function Properties({ properties, can, urls }) {
    return (
        <Page
            title="Rooms & rates"
            subtitle="Your properties: listings, room inventory, rates and availability."
            actions={can.create && <Link href={urls.create} className="btn-primary">New property</Link>}
        >
            <Panel>
                <Table head={['Property', 'Type', 'Status', 'From', { label: 'Rooms', right: true }, '']} empty="No properties yet. Create your first listing.">
                    {properties.data.map((p) => (
                        <tr key={p.urls.show}>
                            <Td><Main href={p.urls.show} title={p.name} sub={p.where ?? '—'} /></Td>
                            <Td muted>{p.type ?? '—'}</Td>
                            <Td><Status value={p.status} /></Td>
                            <Td muted className="whitespace-nowrap">{p.from}</Td>
                            <Td right>{p.rooms}</Td>
                            <Td right>
                                <span className="inline-flex gap-1.5">
                                    <Link href={p.urls.inventory} className="btn-ghost btn-sm">Rooms & rates</Link>
                                    <Link href={p.urls.staff} className="btn-ghost btn-sm">Staff</Link>
                                </span>
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={properties} />
            </Panel>
        </Page>
    );
}
