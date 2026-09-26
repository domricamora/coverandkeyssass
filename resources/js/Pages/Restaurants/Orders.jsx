import { Link, router, useForm } from '@inertiajs/react';
import { Action, Form, Input, Main, Page, Panel, Row, Select, Split, Status, Table, Tabs, Td } from '../../react/kit';
import { label } from '../../react/ui';

/** Online order queue by stage, finished orders and order promo codes. */
export default function Orders({ restaurant, tabs, stages, open, recent, drivers, promotions, can, urls }) {
    const promo = useForm({ code: '', name: '', type: 'percent', value: '', min_subtotal: '', max_uses: '' });

    return (
        <Page
            title={restaurant.name}
            subtitle={<>Online ordering is {restaurant.ordering ? 'on' : 'off'}. {!restaurant.ordering && <Link href={urls.profile} className="text-brand hover:underline">Turn it on in the profile.</Link>}</>}
        >
            <Tabs tabs={tabs} />

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {Object.entries(stages).map(([status, name]) => {
                    const list = open.filter((o) => o.status === status);
                    return (
                        <Panel key={status} title={name} aside={list.length}>
                            <ul className="divide-y divide-line">
                                {list.length === 0 && <li className="px-5 py-4 text-[13px] text-fg-3">—</li>}
                                {list.map((o) => <OrderCard key={o.reference} order={o} drivers={drivers} />)}
                            </ul>
                        </Panel>
                    );
                })}
            </div>

            <Split side="360px">
                <Panel title="Finished orders">
                    <Table head={['Order', 'Customer', 'Status', { label: 'Total', right: true }]} empty="No finished orders yet.">
                        {recent.map((o) => (
                            <tr key={o.reference}>
                                <Td><Main href={o.href} title={o.reference} sub={o.at} /></Td>
                                <Td muted>{o.customer}</Td>
                                <Td><Status value={o.status} /></Td>
                                <Td right>{o.total}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>

                <Panel title="Order promo codes">
                    <ul className="divide-y divide-line text-[13px]">
                        {promotions.length === 0 && <li className="px-5 py-4 text-fg-3">No order promo codes yet.</li>}
                        {promotions.map((p) => (
                            <li key={p.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
                                <span><strong className="font-medium text-fg">{p.code}</strong> <span className="text-fg-3">· {p.terms}</span></span>
                                {can.promotions && p.toggle && <Action href={p.toggle}>{p.active ? 'Deactivate' : 'Reactivate'}</Action>}
                            </li>
                        ))}
                    </ul>
                    {can.promotions && (
                        <div className="border-t border-line p-5">
                            <Form form={promo} url={urls.addPromotion} reset button="Create code">
                                <Row>
                                    <Input form={promo} name="code" label="Code" placeholder="LUNCH10" required />
                                    <Input form={promo} name="name" label="Name" placeholder="Lunch promo" required />
                                </Row>
                                <Row>
                                    <Select form={promo} name="type" label="Type" options={[['percent', '% off'], ['fixed', '₱ off']]} />
                                    <Input form={promo} name="value" type="number" step="0.01" min="0.01" label="Value" required />
                                </Row>
                                <Row>
                                    <Input form={promo} name="min_subtotal" type="number" step="0.01" min="0" label="Min order" />
                                    <Input form={promo} name="max_uses" type="number" min="1" label="Max uses" />
                                </Row>
                            </Form>
                        </div>
                    )}
                </Panel>
            </Split>
        </Page>
    );
}

export function OrderCard({ order: o, drivers }) {
    return (
        <li className="space-y-2 px-5 py-3.5 text-[13px]">
            <p><Link href={o.href} className="font-semibold text-fg hover:text-brand">{o.reference}</Link> <span className="text-fg-2">· {o.customer} · {o.items} item(s) · {o.total}</span></p>
            <p className="text-[12px] text-fg-3">{o.meta}{o.scheduled && <> · <strong className="text-fg">Scheduled {o.scheduled}</strong></>}{o.eta && ` · ETA ${o.eta}`}</p>
            {o.can_assign && (
                <select
                    className="field"
                    aria-label={`Driver for ${o.reference}`}
                    value={o.driver_id ?? ''}
                    onChange={(e) => router.post(o.assign, { driver_id: e.target.value || null }, { preserveScroll: true })}
                >
                    <option value="">{o.driver ? 'Clear driver' : 'Assign driver…'}</option>
                    {drivers.map(([id, name]) => <option key={id} value={id}>{name}</option>)}
                </select>
            )}
            {o.next.length > 0 && (
                <span className="flex flex-wrap gap-1.5">
                    {o.next.map((to) => (
                        <Action key={to} href={o.transition} data={{ status: to }} className={['cancelled', 'refunded'].includes(to) ? 'btn-danger btn-sm' : 'btn-primary btn-sm'}>{label(to)}</Action>
                    ))}
                </span>
            )}
        </li>
    );
}
