import { Link, useForm } from '@inertiajs/react';
import { Badge, Form, Input, Main, Page, Pager, Panel, Search, Split, Stack, Table, Td } from '../../react/kit';
import { cx } from '../../react/ui';

/** Guest CRM: segments, search, the guest list and add-a-guest. */
export default function Guests({ contacts, segments, filters, tags, can, urls }) {
    const form = useForm({ name: '', email: '', phone: '' });
    const chip = (active) => cx('pill h-8 border px-3 text-[12px]', active ? 'border-brand bg-brand text-white' : 'border-line bg-surface text-fg-2 hover:border-line-strong');

    return (
        <Page
            title="Guests"
            subtitle="Everyone who stayed, ate or booked a table with you."
            actions={<Search url={urls.self} value={filters.q} params={{ segment: filters.segment ?? undefined }} placeholder="Name, email or phone" />}
        >
            <div className="flex flex-wrap gap-2">
                <Link href={urls.self} className={chip(!filters.segment && !filters.tag)}>All</Link>
                {segments.map((s) => (
                    <Link key={s.key} href={`${urls.self}?segment=${s.key}`} className={chip(filters.segment === s.key)}>{s.label} <span className="tabular-nums opacity-70">{s.count}</span></Link>
                ))}
            </div>

            <Split side="340px">
                <Panel>
                    <Table head={['Guest', { label: 'Stays', right: true }, { label: 'Orders', right: true }, { label: 'Spend', right: true }, 'Last seen']} empty="No guests match.">
                        {contacts.data.map((c) => (
                            <tr key={c.id}>
                                <Td>
                                    <Main href={c.href} title={<>{c.name} {c.vip && <Badge tone="warn">VIP</Badge>}</>} sub={c.reach} />
                                    {c.tags.length > 0 && <span className="mt-1 flex flex-wrap gap-1">{c.tags.map((t) => <Badge key={t}>{t}</Badge>)}</span>}
                                </Td>
                                <Td right>{c.stays}</Td>
                                <Td right>{c.orders}</Td>
                                <Td right>{c.spend}</Td>
                                <Td muted className="whitespace-nowrap">{c.seen}</Td>
                            </tr>
                        ))}
                    </Table>
                    <Pager page={contacts} />
                </Panel>

                <Stack>
                    {can.manage && (
                        <Panel title="Add a guest" pad>
                            <Form form={form} url={urls.store} button="Add">
                                <Input form={form} name="name" label="Full name" required />
                                <Input form={form} name="email" type="email" label="Email" />
                                <Input form={form} name="phone" label="Phone" />
                            </Form>
                        </Panel>
                    )}
                    <Panel title="Tags">
                        <div className="flex flex-wrap gap-1.5 p-5">
                            {tags.length === 0 && <span className="text-[13px] text-fg-3">No tags yet.</span>}
                            {tags.map((t) => <Link key={t.id} href={`${urls.self}?tag=${t.id}`} className={chip(String(filters.tag) === String(t.id))}>{t.name} · {t.count}</Link>)}
                        </div>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
