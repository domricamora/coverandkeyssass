import { Head } from '@inertiajs/react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { TripStatus } from './TripCard';

/** Every online payment the guest made, across businesses. */
export default function Payments({ payments, tabs }) {
    return (
        <>
            <Head title="Payments" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Payments</h1>
            <AccountTabs tabs={tabs} />
            {payments.data.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">No online payments yet. Payments you make for stays and orders show up here.</div>
            ) : (
                <ul className="divide-y divide-line border border-line bg-surface">
                    {payments.data.map((p) => (
                        <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5">
                            <span className="min-w-0">
                                {p.href ? <a href={p.href} className="block text-[14px] font-medium text-fg hover:text-brand">{p.for}</a> : <span className="block text-[14px] font-medium">{p.for}</span>}
                                <span className="text-[12px] text-fg-3">{p.date} · {p.method}</span>
                            </span>
                            <span className="flex items-center gap-3">
                                <TripStatus status={p.status} label={p.status[0].toUpperCase() + p.status.slice(1)} />
                                <span className="text-[14px] tabular-nums">{p.amount}</span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <Pager page={payments} />
        </>
    );
}

Payments.layout = (page) => <PublicShell>{page}</PublicShell>;
