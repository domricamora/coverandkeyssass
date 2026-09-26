import { useForm } from '@inertiajs/react';
import { Badge, Form, Input, Page, Panel, Row, Select, Split, Stack, Stats, Status, Table, Td, TextArea } from '../../react/kit';

/** One guest: spend, stays, orders and table visits, communication log, profile and notes. */
export default function Guest({ contact: c, bookings, history, channels, can, urls }) {
    const profile = useForm(c.fields);
    const note = useForm({ body: '' });
    const log = useForm({ channel: channels[0], direction: 'outbound', subject: '', body: '' });

    const Rows = ({ rows, empty }) => (
        <Table head={['Reference', 'Where', 'When', 'Status', { label: 'Total', right: true }]} empty={empty}>
            {rows.map((r) => (
                <tr key={r.ref}>
                    <Td className="font-mono text-[12px]">{r.ref}</Td>
                    <Td>{r.place}</Td>
                    <Td muted>{r.when}</Td>
                    <Td><Status value={r.status} /></Td>
                    <Td right>{r.total ?? ''}</Td>
                </tr>
            ))}
        </Table>
    );

    return (
        <Page
            title={c.name}
            subtitle={c.summary}
            back={{ href: urls.index, label: 'Guests' }}
            actions={
                <span className="flex flex-wrap gap-1.5">
                    {c.vip && <Badge tone="warn">VIP</Badge>}
                    {c.segments.map((s) => <Badge key={s} tone="info">{s}</Badge>)}
                    {c.tags.map((t) => <Badge key={t}>{t}</Badge>)}
                </span>
            }
        >
            <Stats items={c.stats} />
            <Split side="360px">
                <Stack>
                    <Panel title="Stays"><Rows rows={bookings} empty="No stays." /></Panel>
                    <Panel title="Orders & table visits"><Rows rows={history} empty="No orders or table visits." /></Panel>
                    <Panel title="Communication">
                        <ul className="divide-y divide-line text-[13px]">
                            {c.interactions.length === 0 && <li className="px-5 py-4 text-fg-3">Nothing logged yet.</li>}
                            {c.interactions.map((i) => (
                                <li key={i.id} className="px-5 py-3">
                                    <p className="text-[12px] text-fg-3">{i.head}</p>
                                    {i.subject && <p className="mt-0.5 font-medium text-fg">{i.subject}</p>}
                                    {i.body && <p className="mt-0.5 whitespace-pre-line text-fg-2">{i.body}</p>}
                                </li>
                            ))}
                        </ul>
                        {can.manage && (
                            <div className="border-t border-line p-5">
                                <Form form={log} url={urls.interaction} reset button="Log">
                                    <Row>
                                        <Select form={log} name="channel" label="Channel" options={channels} />
                                        <Select form={log} name="direction" label="Direction" options={[['outbound', 'We contacted them'], ['inbound', 'They contacted us']]} />
                                    </Row>
                                    <Input form={log} name="subject" label="Subject" />
                                    <TextArea form={log} name="body" label="What was said" rows={2} />
                                </Form>
                            </div>
                        )}
                    </Panel>
                </Stack>

                <Stack>
                    {can.manage && (
                        <Panel title="Profile" pad>
                            <Form form={profile} url={urls.update} method="patch">
                                <Input form={profile} name="name" label="Name" required />
                                <Input form={profile} name="email" type="email" label="Email" />
                                <Input form={profile} name="phone" label="Phone" />
                                <Input form={profile} name="tags" label="Tags" hint="Comma separated" />
                                <Input form={profile} name="is_vip" type="checkbox" label="VIP" />
                                <Input form={profile} name="marketing_consent" type="checkbox" label={`Agreed to marketing${c.consent_at ? ` (${c.consent_at})` : ''}`} />
                            </Form>
                        </Panel>
                    )}
                    <Panel title="Notes">
                        <ul className="divide-y divide-line text-[13px]">
                            {c.notes.length === 0 && <li className="px-5 py-4 text-fg-3">No notes.</li>}
                            {c.notes.map((n) => (
                                <li key={n.id} className="px-5 py-3">
                                    <p className="whitespace-pre-line text-fg-2">{n.body}</p>
                                    <p className="mt-1 text-[12px] text-fg-3">{n.by}</p>
                                </li>
                            ))}
                        </ul>
                        {can.manage && (
                            <div className="border-t border-line p-5">
                                <Form form={note} url={urls.note} reset button="Add note">
                                    <TextArea form={note} name="body" rows={2} placeholder="Allergies, preferences, anniversaries…" aria-label="Note" required />
                                </Form>
                            </div>
                        )}
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
