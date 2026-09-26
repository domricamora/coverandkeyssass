import { Action, Empty, Page, Pager, Panel } from '../../react/kit';
import { cx } from '../../react/ui';

/** Notification centre: orders, assigned tasks and tickets, stock and subscription alerts. */
export default function Notifications({ notifications, unread, urls }) {
    return (
        <Page
            title="Notifications"
            subtitle={unread ? `${unread} unread` : 'New orders, assigned tasks and tickets, stock and subscription alerts.'}
            actions={
                <>
                    {unread > 0 && <Action href={urls.read}>Mark all as read</Action>}
                    <a href={urls.settings} className="btn-ghost btn-sm">Settings</a>
                </>
            }
        >
            {notifications.data.length === 0 ? (
                <Empty title="No notifications yet" body="Assigned tasks, new orders and alerts show up here." />
            ) : (
                <Panel>
                    <ul className="divide-y divide-line">
                        {notifications.data.map((n) => (
                            <li key={n.id} className={cx('flex items-start gap-3 px-5 py-3.5', n.unread && 'bg-brand-soft/40')}>
                                <span className={cx('mt-1.5 h-2 w-2 shrink-0 rounded-full', n.unread ? 'bg-coral' : 'bg-transparent')} aria-hidden="true" />
                                <span className="min-w-0 flex-1">
                                    {n.href
                                        ? <a href={n.href} className={cx('block text-[14px] hover:text-brand', n.unread ? 'font-medium text-fg' : 'text-fg-2')}>{n.message}</a>
                                        : <span className={cx('block text-[14px]', n.unread ? 'font-medium text-fg' : 'text-fg-2')}>{n.message}</span>}
                                    <span className="text-[12px] text-fg-3">{n.when}{n.unread && ' · unread'}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                    <Pager page={notifications} />
                </Panel>
            )}
        </Page>
    );
}
