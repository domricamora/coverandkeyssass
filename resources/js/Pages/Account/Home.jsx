import { Head } from '@inertiajs/react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import TripCard from './TripCard';

/** Guest account home: counters, upcoming and recent trips. */
export default function AccountHome({ name, upcoming, past, counts, tabs, urls }) {
    return (
        <>
            <Head title="My account" />
            <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">Welcome back</p>
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">{name}</h1>
            <AccountTabs tabs={tabs} />

            <div className="mb-10 grid grid-cols-3 gap-2 sm:gap-3">
                {counts.map(([label, value, href]) => (
                    <a key={label} href={href} className="border border-line bg-surface px-3 py-3 transition-colors duration-150 hover:border-line-strong sm:px-5 sm:py-4">
                        <span className="block text-[12px] leading-tight text-fg-3 sm:text-[13px]">{label}</span>
                        <span className="block font-display text-[26px] font-medium tabular-nums">{value}</span>
                    </a>
                ))}
            </div>

            <h2 className="mb-3 text-[17px] font-semibold">Upcoming trips</h2>
            {upcoming.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center">
                    <p className="font-medium">No upcoming trips</p>
                    <p className="mt-1 text-[14px] text-fg-3">Find a stay and book it; it shows up here.</p>
                    <a href={urls.explore} className="mt-4 inline-flex h-10 items-center bg-brand px-4 text-[14px] font-medium text-white hover:bg-brand-deep">Explore stays</a>
                </div>
            ) : (
                <div className="space-y-2">{upcoming.map((t) => <TripCard key={t.reference} trip={t} />)}</div>
            )}

            {past.length > 0 && (
                <>
                    <h2 className="mb-3 mt-10 text-[17px] font-semibold">Past trips</h2>
                    <div className="space-y-2">{past.map((t) => <TripCard key={t.reference} trip={t} />)}</div>
                    <a href={urls.past} className="mt-3 inline-block text-[14px] text-brand hover:underline">All past trips</a>
                </>
            )}
        </>
    );
}

AccountHome.layout = (page) => <PublicShell>{page}</PublicShell>;
