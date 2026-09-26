import { Head } from '@inertiajs/react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import TripCard from './TripCard';

/** Trips: upcoming / past. */
export default function Trips({ trips, upcoming, tabs, urls }) {
    return (
        <>
            <Head title="Trips" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Trips</h1>
            <AccountTabs tabs={tabs} />
            <div className="mb-5 flex border border-line-strong sm:w-fit">
                <a href={urls.upcoming} className={`flex-1 px-4 py-2 text-center text-[14px] ${upcoming ? 'bg-brand text-white' : 'text-fg-2 hover:bg-soft'}`}>Upcoming</a>
                <a href={urls.past} className={`flex-1 border-l border-line-strong px-4 py-2 text-center text-[14px] ${!upcoming ? 'bg-brand text-white' : 'text-fg-2 hover:bg-soft'}`}>Past and cancelled</a>
            </div>
            {trips.data.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">
                    {upcoming ? <>No upcoming trips. <a href={urls.explore} className="text-brand hover:underline">Explore stays</a></> : 'No past trips yet.'}
                </div>
            ) : (
                <div className="space-y-2">{trips.data.map((t) => <TripCard key={t.reference} trip={t} />)}</div>
            )}
            <Pager page={trips} />
        </>
    );
}

Trips.layout = (page) => <PublicShell>{page}</PublicShell>;
