import { Link } from '@inertiajs/react';
import { Page, Panel, Status } from '../../react/kit';

/** Platform overview: money, volume, supply and demand across every business. */
export default function AdminDashboard({ stats, recentTenants, recentAudit, urls }) {
    return (
        <Page eyebrow="Super Admin" title="Platform overview" subtitle="All businesses, all time, with the last 30 days alongside.">
            <div className="grid grid-cols-1 border-l border-t border-line bg-surface sm:grid-cols-2 xl:grid-cols-4">
                {stats.map(([term, value, note, href]) => (
                    <Link key={term} href={href} className="group border-b border-r border-line px-5 py-4 transition-colors hover:bg-soft">
                        <p className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">{term}</p>
                        <p className="mt-1.5 font-display text-[24px] font-medium leading-none tabular-nums text-fg">{value}</p>
                        <p className="mt-1.5 text-[12px] text-fg-3 group-hover:text-fg-2">{note}</p>
                    </Link>
                ))}
            </div>

            <div className="grid gap-8 lg:grid-cols-2">
                <Panel title="Latest businesses" aside={<Link href={urls.tenants} className="text-[12px] text-brand hover:underline">All businesses</Link>}>
                    <ul className="divide-y divide-line">
                        {recentTenants.length ? recentTenants.map((t) => (
                            <li key={t.href} className="flex items-center justify-between gap-3 px-5 py-3 text-[13px]">
                                <span className="min-w-0">
                                    <Link href={t.href} className="font-medium text-fg hover:text-brand">{t.name}</Link>
                                    <span className="ml-2 text-fg-3">{t.type}</span>
                                </span>
                                <Status value={t.status} />
                            </li>
                        )) : <li className="px-5 py-10 text-center text-[13px] text-fg-3">No businesses yet.</li>}
                    </ul>
                </Panel>
                <Panel title="Recent activity" aside={<Link href={urls.logs} className="text-[12px] text-brand hover:underline">Audit log</Link>}>
                    <ul className="divide-y divide-line">
                        {recentAudit.length ? recentAudit.map((a) => (
                            <li key={a.id} className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 px-5 py-3 text-[13px]">
                                <span className="font-mono text-[12px] text-coral-deep">{a.action}</span>
                                <span className="text-[12px] text-fg-3">{a.who} · {a.when}</span>
                            </li>
                        )) : <li className="px-5 py-10 text-center text-[13px] text-fg-3">No activity recorded yet.</li>}
                    </ul>
                </Panel>
            </div>
        </Page>
    );
}
