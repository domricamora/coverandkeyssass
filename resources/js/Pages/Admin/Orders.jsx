import { usePage } from '@inertiajs/react';
import { Filter, Main, Page, Pager, Panel, Search, Status, Table, Td } from '../../react/kit';

/** Online, room service and POS food orders across every restaurant. */
export default function AdminOrders({ orders, statuses, filters }) {
    const here = usePage().url.split('?')[0];

    return (
        <Page
            eyebrow="Super Admin"
            title="Food orders"
            subtitle="Online, room service and POS orders across restaurants."
            actions={(
                <>
                    <Search url={here} value={filters.q} params={filters} placeholder="Reference or customer" />
                    <Filter name="status" value={filters.status} url={here} params={filters} placeholder="Any status" options={statuses} />
                </>
            )}
        >
            <Panel>
                <Table head={['Reference', 'Restaurant', 'Customer', 'Type / payment', { label: 'Total', right: true }, 'Status']} empty="No orders.">
                    {orders.data.map((o) => (
                        <tr key={o.id}>
                            <Td><Main title={o.reference} sub={o.created} /></Td>
                            <Td muted>{o.restaurant}</Td>
                            <Td muted>{o.customer}</Td>
                            <Td><Main title={o.type} sub={o.payment} /></Td>
                            <Td right>{o.total}</Td>
                            <Td><Status value={o.status} /></Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={orders} />
            </Panel>
        </Page>
    );
}
