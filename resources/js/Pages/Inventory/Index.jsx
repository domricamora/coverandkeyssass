import { router, useForm } from '@inertiajs/react';
import { Badge, Filter, Form, Input, Main, Page, Panel, Row, Select, Split, Stack, Table, Tabs, Td } from '../../react/kit';
import { cx } from '../../react/ui';

/** Stock list with low-stock filter, new item, and the category / location / supplier lists. */
export default function Inventory({ items, lowCount, filters, lists, units, tabs, can, urls }) {
    const item = useForm({ sku: '', name: '', unit: units[0], inventory_category_id: '', reorder_level: '', cost_per_unit: '' });

    return (
        <Page
            title="Inventory"
            subtitle={`${items.length} item(s)`}
            actions={
                <>
                    <Filter name="category" value={filters.category} url={urls.self} params={filters} placeholder="All categories" options={lists.categories} />
                    <button
                        className={cx(lowCount ? 'btn-coral' : 'btn-ghost')}
                        onClick={() => router.get(urls.self, { ...filters, low: filters.low ? undefined : 1 }, { preserveState: true })}
                    >
                        {filters.low ? 'Show all' : `${lowCount} low on stock`}
                    </button>
                </>
            }
        >
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Panel>
                    <Table head={['Item', 'Category', { label: 'On hand', right: true }, { label: 'Reorder at', right: true }, { label: 'Avg cost', right: true }]} empty="No items yet.">
                        {items.map((i) => (
                            <tr key={i.id} className={cx(!i.active && 'opacity-55')}>
                                <Td><Main href={i.href} title={i.name} sub={i.sku} /></Td>
                                <Td muted>{i.category ?? '—'}</Td>
                                <Td right>{i.low ? <Badge tone="bad">{i.on_hand}</Badge> : i.on_hand}</Td>
                                <Td right muted>{i.reorder ?? '—'}</Td>
                                <Td right muted>{i.cost}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>

                <Stack>
                    {can.manage && (
                        <Panel title="New item" pad>
                            <Form form={item} url={urls.addItem} button="Add item">
                                <Row>
                                    <Input form={item} name="sku" label="SKU" required />
                                    <Select form={item} name="unit" label="Unit" options={units.map((u) => [u, u])} />
                                </Row>
                                <Input form={item} name="name" label="Name" placeholder="Burger bun" required />
                                <Select form={item} name="inventory_category_id" label="Category" placeholder="No category" options={lists.categories} />
                                <Row>
                                    <Input form={item} name="reorder_level" type="number" step="0.001" min="0" label="Reorder level" />
                                    <Input form={item} name="cost_per_unit" type="number" step="0.0001" min="0" label="Cost / unit" />
                                </Row>
                            </Form>
                        </Panel>
                    )}
                    <NameList title="Categories" rows={lists.categories.map(([, n]) => n)} url={urls.addCategory} placeholder="Meat" allowed={can.manage} />
                    <NameList title="Locations" rows={lists.locations} url={urls.addLocation} placeholder="Main store" allowed={can.manage} />
                    <NameList title="Suppliers" rows={lists.suppliers} url={urls.addSupplier} placeholder="Island Meats Co." allowed={can.buy} />
                </Stack>
            </Split>
        </Page>
    );
}

function NameList({ title, rows, url, placeholder, allowed }) {
    const form = useForm({ name: '' });
    return (
        <Panel title={title} aside={rows.length}>
            <p className="px-5 py-3 text-[13px] text-fg-2">{rows.length ? rows.join(' · ') : <span className="text-fg-3">None yet.</span>}</p>
            {allowed && (
                <form className="flex gap-2 border-t border-line px-5 py-3" onSubmit={(e) => { e.preventDefault(); form.post(url, { preserveScroll: true, onSuccess: () => form.reset() }); }}>
                    <Input form={form} name="name" placeholder={placeholder} aria-label={`${title} name`} required className="flex-1" />
                    <button className="btn-ghost" disabled={form.processing}>Add</button>
                </form>
            )}
        </Panel>
    );
}

