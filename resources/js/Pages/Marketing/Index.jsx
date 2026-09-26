import { Link, useForm } from '@inertiajs/react';
import { Badge, Form, Input, Main, Page, Panel, Select, Split, Stack, Status, Table, Td, TextArea } from '../../react/kit';

/** Campaigns, automated follow-ups and the promotions they draw coupons from. */
export default function Marketing({ campaigns, automations, promotions, audiences, consented, can, urls }) {
    const promoOptions = promotions.map((p) => [p.id, `${p.code} — ${p.label}`]);
    const campaign = useForm({ name: '', channel: 'email', audience: audiences[0]?.[0] ?? 'all', subject: '', body: '', promotion_id: '' });

    return (
        <Page
            title="Marketing"
            subtitle={`${consented} guest(s) opted in. Placeholders: {name} {business} {link} {coupon}`}
            actions={<Link href={urls.guests} className="btn-ghost">Guests</Link>}
        >
            <Split side="380px">
                <Stack>
                    <Panel title="Campaigns">
                        <Table head={['Campaign', 'Channel', 'Status', { label: 'Sent', right: true }]} empty="No campaigns yet.">
                            {campaigns.map((c) => (
                                <tr key={c.id}>
                                    <Td><Main href={c.href} title={c.name} sub={c.when} /></Td>
                                    <Td muted>{c.channel}</Td>
                                    <Td><Status value={c.status} /></Td>
                                    <Td right>{c.sent}</Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                    <Panel title="Automations" aside="Each guest gets each follow-up once">
                        <div className="divide-y divide-line">
                            {automations.map((a) => <Automation key={a.type} automation={a} promotions={promoOptions} can={can} />)}
                        </div>
                    </Panel>
                </Stack>

                <Stack>
                    {can.manage && (
                        <Panel title="New campaign" pad>
                            <Form form={campaign} url={urls.store} button="Save draft">
                                <Input form={campaign} name="name" label="Name" placeholder="Rainy season getaway" required />
                                <Select form={campaign} name="channel" label="Channel" options={[['email', 'Email'], ['sms', 'SMS']]} />
                                <Select form={campaign} name="audience" label="Audience" options={audiences} />
                                {campaign.data.channel === 'email' && <Input form={campaign} name="subject" label="Subject" required />}
                                <TextArea form={campaign} name="body" label="Message" rows={4} placeholder="Hi {name}, …" required />
                                <Select form={campaign} name="promotion_id" label="Personal coupons from" placeholder="No coupon" options={promoOptions} />
                            </Form>
                        </Panel>
                    )}
                    <Panel title="Promotions & coupons">
                        <p className="border-b border-line px-5 py-3 text-[12px] text-fg-3">Shared codes are created on the booking and restaurant order screens. Campaigns issue one personal coupon per guest.</p>
                        <ul className="divide-y divide-line text-[13px]">
                            {promotions.length === 0 && <li className="px-5 py-4 text-fg-3">No promotions yet.</li>}
                            {promotions.map((p) => (
                                <li key={p.id} className="px-5 py-2.5">
                                    <p><strong className="font-medium text-fg">{p.code}</strong> <span className="text-fg-2">· {p.label} · {p.applies_to}</span> {!p.active && <Badge>off</Badge>}</p>
                                    <p className="text-[12px] text-fg-3">{p.usage}</p>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}

function Automation({ automation: a, promotions, can }) {
    const form = useForm(a.fields);
    return (
        <form className="space-y-3 px-5 py-4" onSubmit={(e) => { e.preventDefault(); form.patch(a.update, { preserveScroll: true }); }}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <strong className="text-[14px] font-medium text-fg">{a.label}</strong>
                <span className="text-[12px] text-fg-3">{a.consent ? 'Marketing: opted-in guests only' : 'Service message'}</span>
            </div>
            <fieldset disabled={!can.manage} className="space-y-3">
                <div className="flex flex-wrap items-end gap-3">
                    <Input form={form} name="enabled" type="checkbox" label="On" className="h-9" />
                    <Input form={form} name="delay_hours" type="number" min="0" label="After (hours)" className="w-32" />
                    {a.consent && <Select form={form} name="promotion_id" label="Coupon" placeholder="No coupon" options={promotions} className="min-w-48 flex-1" />}
                </div>
                <Input form={form} name="subject" label="Subject" required />
                <TextArea form={form} name="body" label="Message" rows={2} required />
                {can.manage && <button className="btn-ghost btn-sm" disabled={form.processing}>Save</button>}
            </fieldset>
        </form>
    );
}
