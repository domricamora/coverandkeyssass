import { router, useForm } from '@inertiajs/react';
import { Badge, Filter, Form, Input, Main, Page, Pager, Panel, Row, Select, Split, Status, Table, Td, TextArea } from '../../react/kit';
import { label } from '../../react/ui';

/** Maintenance tickets: filters, the list by priority, and a new-ticket form. */
export default function Maintenance({ tickets, filters, properties, rooms, statuses, priorities, categories, canWork, urls }) {
    const form = useForm({ property_id: properties[0]?.id ?? '', room_id: '', title: '', description: '', category: 'other', priority: 'normal' });
    const cat = (c) => (['hvac', 'it'].includes(c) ? c.toUpperCase() : label(c));

    return (
        <Page
            title="Maintenance"
            subtitle="Repair tickets across your properties."
            actions={
                <>
                    <Filter name="status" value={filters.status} url={urls.self} params={filters} options={[['active', 'Active'], ...statuses]} />
                    <Filter name="priority" value={filters.priority} url={urls.self} params={filters} placeholder="Any priority" options={priorities} />
                    <label className="flex h-9 items-center gap-2 text-[13px] text-fg-2">
                        <input type="checkbox" className="h-4 w-4 accent-[var(--primary)]" checked={!!filters.mine} onChange={(e) => router.get(urls.self, { ...filters, mine: e.target.checked ? 1 : undefined }, { preserveState: true })} />
                        Mine
                    </label>
                </>
            }
        >
            <Split>
                <Panel title="Tickets" aside={`${tickets.total} total`}>
                    <Table head={['Ticket', 'Where', 'Priority', 'Status', 'Assigned']} empty="No tickets.">
                        {tickets.data.map((t) => (
                            <tr key={t.reference}>
                                <Td><Main href={t.href} title={t.reference} sub={t.title} /></Td>
                                <Td muted>{t.where} {t.out_of_order && <Badge tone="bad">OOO</Badge>}</Td>
                                <Td><Status value={t.priority} /></Td>
                                <Td><Status value={t.status} /></Td>
                                <Td muted>{t.assignee ?? '—'}</Td>
                            </tr>
                        ))}
                    </Table>
                    <Pager page={tickets} />
                </Panel>

                {canWork && (
                    <Panel title="New ticket" pad>
                        <Form form={form} url={urls.store} button="Open ticket">
                            <Select form={form} name="property_id" label="Property" options={properties.map((p) => [p.id, p.name])} onChange={(e) => form.setData({ ...form.data, property_id: e.target.value, room_id: '' })} />
                            <Select form={form} name="room_id" label="Room" placeholder="Common area / no room" options={rooms.filter((r) => String(r.property_id) === String(form.data.property_id)).map((r) => [r.id, `Room ${r.room_number}`])} />
                            <Input form={form} name="title" label="Title" placeholder="Pool pump noisy" required />
                            <TextArea form={form} name="description" label="Details" />
                            <Row>
                                <Select form={form} name="category" label="Category" options={categories.map((c) => [c, cat(c)])} />
                                <Select form={form} name="priority" label="Priority" options={priorities} />
                            </Row>
                        </Form>
                    </Panel>
                )}
            </Split>
        </Page>
    );
}
