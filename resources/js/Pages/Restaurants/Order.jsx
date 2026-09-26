import { router } from '@inertiajs/react';
import { Action, Facts, Page, Panel, Split, Status, Tabs } from '../../react/kit';
import { label } from '../../react/ui';

/** One online order: lines + totals, customer, delivery, driver and status actions. */
export default function Order({ order: o, drivers, tabs, urls }) {
    return (
        <Page title={`Order ${o.reference}`} subtitle={o.summary} back={{ href: urls.index, label: 'Orders' }} actions={<Status value={o.status} />}>
            <Tabs tabs={tabs} />
            <Split>
                <Panel title="Items">
                    <ul className="divide-y divide-line text-[13px]">
                        {o.lines.map((l) => (
                            <li key={l.id} className="flex gap-3 px-5 py-3">
                                <span className="w-8 font-semibold tabular-nums">{l.qty}×</span>
                                <span className="min-w-0 flex-1">
                                    <span className="text-fg">{l.name}</span>
                                    {l.mods && <span className="block text-[12px] text-fg-3">{l.mods}</span>}
                                    {l.notes && <span className="block text-[12px] text-coral-deep">“{l.notes}”</span>}
                                </span>
                                <span className="tabular-nums">{l.total}</span>
                            </li>
                        ))}
                    </ul>
                    <div className="border-t border-line"><Facts rows={[...o.totals, ['Total', <strong key="t">{o.total}</strong>]]} /></div>
                </Panel>

                <Panel title="Customer">
                    <Facts rows={[
                        ['Name', o.customer],
                        ['Phone', o.phone],
                        o.address && ['Deliver to', <>{o.address}{o.zone && ` (${o.zone})`}{o.map && <a href={o.map} target="_blank" rel="noopener" className="block text-brand hover:underline">Open pin in maps</a>}</>],
                        o.scheduled && ['Scheduled', o.scheduled],
                        o.eta && ['ETA', o.eta],
                        o.delivery && ['Driver', `${o.driver ?? 'not assigned'}${o.driver_phone ? ` · ${o.driver_phone}` : ''}`],
                        o.notes && ['Notes', o.notes],
                        o.cancelled && ['Cancelled', o.cancelled],
                    ]} />
                    {o.can_assign && (
                        <div className="border-t border-line px-5 py-4">
                            <label className="label">Driver</label>
                            <select className="field" value={o.driver_id ?? ''} onChange={(e) => router.post(o.assign, { driver_id: e.target.value || null }, { preserveScroll: true })}>
                                <option value="">No driver</option>
                                {drivers.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                            </select>
                        </div>
                    )}
                    {o.next.length > 0 && (
                        <div className="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                            {o.next.map((to) => (
                                <Action key={to} href={o.transition} data={{ status: to }} className={['cancelled', 'refunded'].includes(to) ? 'btn-danger btn-sm' : 'btn-primary btn-sm'}>{label(to)}</Action>
                            ))}
                        </div>
                    )}
                </Panel>
            </Split>
        </Page>
    );
}
