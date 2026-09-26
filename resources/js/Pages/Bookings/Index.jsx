import { Link, router } from '@inertiajs/react';
import { Filter, Main, Page, Pager, Panel, Search, Status, Table, Td } from '../../react/kit';
import { label } from '../../react/ui';

/** Reservations, walk-ins and group stays with search, status and date filters. */
export default function Bookings({ bookings, filters, statuses, can, urls }) {
    const date = (name) => (
        <input
            type="date"
            aria-label={name === 'from' ? 'Staying from' : 'Staying to'}
            className="field w-auto"
            value={filters[name] ?? ''}
            onChange={(e) => router.get(urls.self, { ...filters, [name]: e.target.value || undefined }, { preserveState: true })}
        />
    );

    return (
        <Page
            title="Bookings"
            subtitle="Reservations, walk-ins and group stays across your properties."
            actions={
                <>
                    <Link href={urls.frontdesk} className="btn-ghost">Tape chart</Link>
                    {can.promotions && <Link href={urls.promotions} className="btn-ghost">Promotions</Link>}
                    {can.create && <Link href={urls.create} className="btn-primary">New booking</Link>}
                </>
            }
        >
            <div className="flex flex-wrap items-center gap-2">
                <Search url={urls.self} value={filters.q} params={filters} placeholder="Reference, guest or group" />
                <Filter name="status" value={filters.status} url={urls.self} params={filters} placeholder="Any status" options={statuses} />
                <span className="text-[12px] text-fg-3">Staying</span>
                {date('from')}
                <span className="text-[12px] text-fg-3">to</span>
                {date('to')}
            </div>

            <Panel title="Bookings" aside={`${bookings.total} total`}>
                <Table head={['Reference', 'Guest', 'Property', 'Stay', 'Status', { label: 'Total', right: true }]} empty="No bookings match.">
                    {bookings.data.map((b) => (
                        <tr key={b.reference}>
                            <Td><Main href={b.href} title={b.reference} sub={label(b.source)} /></Td>
                            <Td><Main title={b.guest} sub={b.group} /></Td>
                            <Td muted>{b.property}</Td>
                            <Td muted className="whitespace-nowrap">{b.stay}</Td>
                            <Td><Status value={b.status} /></Td>
                            <Td right>{b.total}</Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={bookings} />
            </Panel>
        </Page>
    );
}
