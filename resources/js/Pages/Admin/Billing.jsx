import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Action, Badge, Chips, Input, Main, Page, Pager, Panel, Row, Select, Status, Table, Td, submit } from '../../react/kit';

/** SaaS billing: invoices (confirm bank transfers, void), live subscriptions and coupons. */
export default function AdminBilling({ summary, status, invoices, subscriptions, coupons, durations, symbol, urls }) {
    const here = usePage().url.split('?')[0];
    const coupon = useForm({ code: '', name: '', percent_off: '', amount_off: '', duration: Object.keys(durations)[0], duration_cycles: '', max_redemptions: '', expires_at: '' });

    const markPaid = (i) => {
        const reference = window.prompt(`Bank transfer reference for ${i.number} (${i.total}):`);
        if (reference) router.post(i.paid, { reference }, { preserveScroll: true });
    };

    return (
        <Page eyebrow="Super Admin" title="Subscriptions & invoices" subtitle={`${summary.live} live subscriptions · MRR ${summary.mrr}`}>
            <Panel title="Invoices" aside={<Chips url={here} name="status" value={status} options={[['open', 'Open'], ['paid', 'Paid'], ['void', 'Void'], ['all', 'All']]} />}>
                <Table head={['Invoice', 'Business', { label: 'Total', right: true }, 'Due', 'Status', { label: '', right: true }]} empty="No invoices.">
                    {invoices.data.map((i) => (
                        <tr key={i.id}>
                            <Td className="font-medium text-fg">{i.number}</Td>
                            <Td muted>{i.business}</Td>
                            <Td right>{i.total}</Td>
                            <Td muted>{i.due}</Td>
                            <Td><Status value={i.status} />{i.reference && <span className="mt-0.5 block text-[12px] text-fg-3">Ref {i.reference}</span>}</Td>
                            <Td right>
                                {i.open && (
                                    <span className="inline-flex gap-1">
                                        <button type="button" className="btn-primary btn-sm" onClick={() => markPaid(i)}>Mark paid</button>
                                        <Action href={i.void} confirm={`Void ${i.number}?`} className="btn-ghost btn-sm text-bad">Void</Action>
                                    </span>
                                )}
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={invoices} />
            </Panel>

            <Panel title="Subscriptions" aside={`${subscriptions.length}`}>
                <Table head={['Business', 'Modules', 'Interval', 'Period ends', 'Status']} empty="No subscriptions yet.">
                    {subscriptions.map((s) => (
                        <tr key={s.id}>
                            <Td>{s.href ? <Link href={s.href} className="font-medium text-fg hover:text-brand">{s.business}</Link> : s.business}</Td>
                            <Td muted className="max-w-sm">{s.modules}</Td>
                            <Td muted>{s.interval}</Td>
                            <Td muted className="whitespace-nowrap">{s.ends}</Td>
                            <Td><Status value={s.status} /></Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Coupons">
                <form onSubmit={submit(coupon, urls.coupons, { reset: true })} className="space-y-4 border-b border-line p-5">
                    <Row cols={4}>
                        <Input form={coupon} name="code" label="Code" placeholder="WELCOME20" required />
                        <Input form={coupon} name="name" label="Name" required />
                        <Input form={coupon} name="percent_off" type="number" min="1" max="100" label="% off" />
                        <Input form={coupon} name="amount_off" type="number" min="1" step="0.01" label={`or ${symbol} off`} />
                    </Row>
                    <Row cols={4}>
                        <Select form={coupon} name="duration" label="Applies to" options={Object.entries(durations)} />
                        <Input form={coupon} name="duration_cycles" type="number" min="1" max="36" label="Invoices (if several)" />
                        <Input form={coupon} name="max_redemptions" type="number" min="1" label="Max businesses" />
                        <Input form={coupon} name="expires_at" type="date" label="Expires on" />
                    </Row>
                    <button type="submit" className="btn-primary" disabled={coupon.processing}>Create coupon</button>
                </form>
                <Table head={['Coupon', 'Discount', 'Usage', { label: '', right: true }]} empty="No coupons yet.">
                    {coupons.map((c) => (
                        <tr key={c.id}>
                            <Td><Main title={c.code} sub={c.name} /></Td>
                            <Td muted>{c.label}</Td>
                            <Td muted>{c.used}{!c.active && <span className="ml-2"><Badge>Off</Badge></span>}</Td>
                            <Td right><Action href={c.toggle}>{c.active ? 'Disable' : 'Enable'}</Action></Td>
                        </tr>
                    ))}
                </Table>
            </Panel>
        </Page>
    );
}
