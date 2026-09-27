import { usePage } from '@inertiajs/react';
import { Filter, Main, Page, Pager, Panel, Search, Status, Table, Td } from '../../react/kit';

/** Every stay booked on the platform, across businesses. */
export default function AdminBookings({ bookings, statuses, filters }) {
    const here = usePage().url.split('?')[0];

    return (
        <Page
            eyebrow="Super Admin"
            title="Bookings"
            subtitle="Every stay booked on the platform."
            actions={(
                <>
                    <Search url={here} value={filters.q} params={filters} placeholder="Reference, guest or email" />
                    <Filter name="status" value={filters.status} url={here} params={filters} placeholder="Any status" options={statuses} />
                </>
            )}
        >
            <Panel>
                <Table head={['Reference', 'Business / property', 'Guest', 'Dates', { label: 'Total', right: true }, 'Status']} empty="No bookings.">
                    {bookings.data.map((b) => (
                        <tr key={b.id}>
                            <Td><Main title={b.reference} sub={b.created} /></Td>
                            <Td><Main title={b.business} sub={b.property} /></Td>
                            <Td><Main title={b.guest} sub={b.email} /></Td>
                            <Td muted className="whitespace-nowrap">{b.dates}</Td>
                            <Td right>{b.total}</Td>
                            <Td><Status value={b.status} /></Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={bookings} />
            </Panel>
        </Page>
    );
}
