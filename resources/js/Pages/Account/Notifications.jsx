import { Head, router } from '@inertiajs/react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';

/** Booking, order and message updates for the guest. */
export default function Notifications({ notifications, unread, tabs, urls }) {
    return (
        <>
            <Head title="Notifications" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h1 className="font-display text-[30px] font-medium tracking-tight">Notifications</h1>
                <span className="flex items-center gap-4 text-[14px]">
                    {unread > 0 && <button type="button" className="font-medium text-brand hover:underline" onClick={() => router.post(urls.read, {}, { preserveScroll: true })}>Mark all as read</button>}
                    <a href={urls.settings} className="text-fg-3 hover:text-fg">Settings</a>
                </span>
            </div>
            <AccountTabs tabs={tabs} />
            {notifications.data.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">No notifications yet. Booking, order and message updates show up here.</div>
            ) : (
                <ul className="divide-y divide-line border border-line bg-surface">
                    {notifications.data.map((n) => (
                        <li key={n.id} className={`flex items-start gap-3 px-5 py-3.5 ${n.unread ? 'bg-brand-soft/40' : ''}`}>
                            <span className={`mt-2 h-1.5 w-1.5 shrink-0 ${n.unread ? 'bg-coral' : 'bg-transparent'}`} aria-hidden="true" />
                            <span className="min-w-0 flex-1">
                                {n.href
                                    ? <a href={n.href} className={`block text-[14px] hover:text-brand ${n.unread ? 'font-medium text-fg' : 'text-fg-2'}`}>{n.message}</a>
                                    : <span className={`block text-[14px] ${n.unread ? 'font-medium text-fg' : 'text-fg-2'}`}>{n.message}</span>}
                                <span className="text-[12px] text-fg-3">{n.when}{n.unread && ' · new'}</span>
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <Pager page={notifications} />
        </>
    );
}

Notifications.layout = (page) => <PublicShell>{page}</PublicShell>;
