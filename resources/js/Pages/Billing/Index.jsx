import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Action, Badge, Page, Panel, Status, Table, Td } from '../../react/kit';
import { cx } from '../../react/ui';

/** Business billing: subscription, modules (subscribe / add / remove), usage limits and invoices. */
export default function Billing({ business, subscription: sub, modules, usage, invoices, intervals, can, urls }) {
    const pick = useForm({ modules: modules.filter((m) => m.checked).map((m) => m.id), interval: 'monthly', coupon: '' });
    const [interval, setInterval] = useState(sub?.interval ?? 'monthly');
    const [coupon, setCoupon] = useState('');
    const toggle = (id) => pick.setData('modules', pick.data.modules.includes(id) ? pick.data.modules.filter((x) => x !== id) : [...pick.data.modules, id]);

    return (
        <Page title="Billing" subtitle={`Pay only for the modules ${business} uses. Prices are VAT-inclusive.`}>
            {sub && (
                <Panel title="Your subscription" aside={<Status value={sub.status} />} pad>
                    <p className="text-[15px] font-medium text-fg">{sub.summary}</p>
                    <p className="text-[13px] text-fg-3">
                        Current period {sub.period}. {sub.cancelAtEnd ? 'Ends then; it will not renew.' : 'Renews automatically.'}
                        {sub.coupon && <> Coupon <span className="font-medium text-fg-2">{sub.coupon}</span>.</>}
                    </p>
                    {can.manage && (
                        <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4">
                            <select className="field w-auto" value={interval} onChange={(e) => setInterval(e.target.value)} aria-label="Billing interval">
                                {intervals.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                            <Action href={urls.interval} data={{ interval }} disabled={interval === sub.interval}>Switch at renewal</Action>
                            <span className="flex-1" />
                            {!sub.coupon && (
                                <form className="flex gap-2" onSubmit={(e) => { e.preventDefault(); router.post(urls.coupon, { coupon }, { preserveScroll: true }); }}>
                                    <input className="field w-40" value={coupon} onChange={(e) => setCoupon(e.target.value)} placeholder="Coupon code" aria-label="Coupon code" required />
                                    <button type="submit" className="btn-ghost btn-sm">Apply</button>
                                </form>
                            )}
                            {sub.cancelAtEnd
                                ? <Action href={urls.cancel} data={{ resume: 1 }} className="btn-primary btn-sm">Keep subscription</Action>
                                : <Action href={urls.cancel} confirm="End the subscription at the close of this period?">Cancel subscription</Action>}
                        </div>
                    )}
                </Panel>
            )}

            <Panel title="Modules" pad>
                {!sub && <p className="text-[13px] text-fg-3">Choose your modules. Modules with a free trial are charged only from the day the trial ends. Required modules are added for you.</p>}
                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {modules.map((m) => {
                        const chosen = sub ? m.on : pick.data.modules.includes(m.id);
                        const body = (
                            <>
                                <span className="flex items-start justify-between gap-2">
                                    <span className="text-[14px] font-medium text-fg">{m.name}</span>
                                    {!sub && <input type="checkbox" className="mt-0.5 h-4 w-4 accent-[var(--primary)] text-brand" checked={chosen} onChange={() => toggle(m.id)} />}
                                </span>
                                <span className="mt-0.5 block text-[12px] text-fg-3">{m.price}</span>
                                {m.badge && <span className="mt-2 block"><Badge tone={m.badge[0]}>{m.badge[1]}</Badge></span>}
                            </>
                        );
                        return sub ? (
                            <div key={m.id} className={cx('border p-4', chosen ? 'border-ok' : 'border-line')}>
                                {body}
                                {can.manage && (
                                    <div className="mt-3">
                                        {m.on
                                            ? <Action href={m.remove} method="delete" confirm={`Remove ${m.name}? Its screens switch off now; no credit is given for the rest of the period.`}>Remove</Action>
                                            : <Action href={urls.add} data={{ module_id: m.id }} className="btn-primary btn-sm">Add</Action>}
                                    </div>
                                )}
                            </div>
                        ) : (
                            <label key={m.id} className={cx('block cursor-pointer border p-4', chosen ? 'border-brand bg-brand-soft' : 'border-line hover:border-line-strong')}>{body}</label>
                        );
                    })}
                </div>
                {!sub && can.manage && (
                    <form className="flex flex-wrap items-center gap-2 border-t border-line pt-4" onSubmit={(e) => { e.preventDefault(); pick.post(urls.subscribe, { preserveScroll: true }); }}>
                        <select className="field w-auto" value={pick.data.interval} onChange={(e) => pick.setData('interval', e.target.value)} aria-label="Billing interval">
                            {intervals.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                        </select>
                        <input className="field w-48" value={pick.data.coupon} onChange={(e) => pick.setData('coupon', e.target.value)} placeholder="Coupon code (optional)" aria-label="Coupon code" />
                        <button type="submit" className="btn-primary" disabled={pick.processing || pick.data.modules.length === 0}>Subscribe</button>
                        {Object.values(pick.errors)[0] && <p className="basis-full text-[12px] text-bad">{Object.values(pick.errors)[0]}</p>}
                    </form>
                )}
            </Panel>

            <div className="grid gap-8 2xl:grid-cols-2">
                <Panel title="Usage">
                    <Table head={['Resource', { label: 'Used', right: true }, 'Plan limit']}>
                        {usage.map((u) => (
                            <tr key={u.label}>
                                <Td className="text-fg">{u.label}</Td>
                                <Td right>{u.used}</Td>
                                <Td muted>{u.limit ?? 'Unlimited'} {u.atLimit && <Badge tone="warn">At limit</Badge>}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
                <Panel title="Invoices">
                    <Table head={['Invoice', 'Period', { label: 'Total', right: true }, 'Status']} empty="No invoices yet.">
                        {invoices.map((i) => (
                            <tr key={i.number}>
                                <Td><a href={i.href} className="font-medium text-fg hover:text-brand">{i.number}</a></Td>
                                <Td muted>{i.period}</Td>
                                <Td right>{i.total}</Td>
                                <Td><Status value={i.status} /></Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
            </div>
        </Page>
    );
}
