import { Head } from '@inertiajs/react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { TripStatus } from './TripCard';

/** Food orders: newest first. */
export default function Orders({ orders, tabs, urls }) {
    return (
        <>
            <Head title="Orders" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Orders</h1>
            <AccountTabs tabs={tabs} />
            {orders.data.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">
                    No orders yet. <a href={urls.browse} className="text-brand hover:underline">Browse restaurants</a>
                </div>
            ) : (
                <div className="space-y-2">
                    {orders.data.map((o) => (
                        <a key={o.reference} href={o.href} className="flex flex-wrap items-center justify-between gap-3 border border-line bg-surface px-4 py-3 hover:border-line-strong">
                            <span className="min-w-0">
                                <span className="block text-[15px] font-medium text-fg">{o.restaurant}</span>
                                <span className="block text-[13px] text-fg-3">{o.when} · {o.how} · {o.reference}</span>
                            </span>
                            <span className="flex items-center gap-3"><TripStatus status={o.status} label={o.statusLabel} /><span className="text-[14px] tabular-nums">{o.total}</span></span>
                        </a>
                    ))}
                </div>
            )}
            <Pager page={orders} />
        </>
    );
}

Orders.layout = (page) => <PublicShell>{page}</PublicShell>;
