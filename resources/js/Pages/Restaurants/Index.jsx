import { Link } from '@inertiajs/react';
import { Main, Page, Pager, Panel, Status, Table, Td } from '../../react/kit';

/** Restaurants: profiles with menu size, tables and shortcuts. */
export default function Restaurants({ restaurants, can, urls }) {
    return (
        <Page
            title="Restaurants"
            subtitle="Profiles, menus, modifiers, tables, reservations and orders."
            actions={can.create && <Link href={urls.create} className="btn-primary">New restaurant</Link>}
        >
            <Panel>
                <Table head={['Restaurant', 'Status', { label: 'Menu items', right: true }, { label: 'Tables', right: true }, '']} empty="No restaurants yet. Create your first listing.">
                    {restaurants.data.map((r) => (
                        <tr key={r.urls.show}>
                            <Td><Main href={r.urls.show} title={r.name} sub={r.where ?? '—'} /></Td>
                            <Td><Status value={r.status} /></Td>
                            <Td right>{r.items}</Td>
                            <Td right>{r.tables}</Td>
                            <Td right>
                                <span className="inline-flex gap-1.5">
                                    <Link href={r.urls.menu} className="btn-ghost btn-sm">Menu</Link>
                                    <Link href={r.urls.orders} className="btn-ghost btn-sm">Orders</Link>
                                    <Link href={r.urls.reservations} className="btn-ghost btn-sm">Reservations</Link>
                                </span>
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={restaurants} />
            </Panel>
        </Page>
    );
}
