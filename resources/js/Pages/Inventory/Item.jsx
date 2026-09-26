import { useForm } from '@inertiajs/react';
import { Badge, Form, Input, Page, Panel, Select, Split, Stack, Table, Tabs, Td } from '../../react/kit';
import { cx } from '../../react/ui';

const ACTIONS = [['receive', 'Receive (delivery)'], ['issue', 'Issue (to a department)'], ['waste', 'Waste / spoilage'], ['count', 'Stock count (set to)'], ['transfer', 'Transfer']];

/** One stock item: levels by location, movement history, stock movements and item settings. */
export default function InventoryItem({ item, movements, locations, categories, tabs, can, urls }) {
    const move = useForm({ action: 'receive', location_id: locations[0]?.[0] ?? '', to_location_id: locations[1]?.[0] ?? '', quantity: '', unit_cost: '', notes: '' });
    const edit = useForm(item.fields);

    return (
        <Page title={item.name} eyebrow={item.sku} subtitle={item.summary} back={{ href: urls.index, label: 'All items' }}>
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Stack>
                    <Panel title="By location">
                        <ul className="divide-y divide-line text-[13px]">
                            {item.levels.length === 0 && <li className="px-5 py-4 text-fg-3">No stock anywhere yet.</li>}
                            {item.levels.map((l) => (
                                <li key={l.location} className="flex justify-between px-5 py-2.5">
                                    <span className="text-fg-2">{l.location}</span>
                                    <span className="font-medium tabular-nums text-fg">{l.qty} {l.negative && <Badge tone="bad">negative, count it</Badge>}</span>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                    <Panel title="Movements" aside="last 50">
                        <Table head={['When', 'Movement', 'Location', { label: 'Qty', right: true }, { label: 'Balance', right: true }, 'By / ref']} empty="No movements yet.">
                            {movements.map((m) => (
                                <tr key={m.id}>
                                    <Td muted className="whitespace-nowrap">{m.at}</Td>
                                    <Td>{m.type}{m.notes && <span className="block text-[12px] text-fg-3">{m.notes}</span>}</Td>
                                    <Td muted>{m.location}</Td>
                                    <Td right className={cx(m.out && 'text-bad')}>{m.qty}</Td>
                                    <Td right>{m.balance}</Td>
                                    <Td muted>{m.by}</Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                </Stack>

                {can.manage && (
                    <Stack>
                        <Panel title="Stock movement" pad>
                            <Form form={move} url={urls.move} reset button="Save movement">
                                <Select form={move} name="action" label="Movement" options={ACTIONS} />
                                <Select form={move} name="location_id" label={move.data.action === 'transfer' ? 'From' : 'Location'} options={locations} />
                                {move.data.action === 'transfer' && <Select form={move} name="to_location_id" label="To" options={locations} />}
                                <Input form={move} name="quantity" type="number" step="0.001" min="0" label={`Quantity (${item.unit})`} required />
                                {move.data.action === 'receive' && <Input form={move} name="unit_cost" type="number" step="0.0001" min="0" label="Unit cost (₱)" />}
                                <Input form={move} name="notes" label={move.data.action === 'waste' ? 'Reason' : 'Notes'} required={move.data.action === 'waste'} />
                            </Form>
                        </Panel>
                        <Panel title="Item" pad>
                            <Form form={edit} url={urls.update} method="patch">
                                <Input form={edit} name="name" label="Name" required />
                                <Select form={edit} name="inventory_category_id" label="Category" placeholder="No category" options={categories} />
                                <Input form={edit} name="reorder_level" type="number" step="0.001" min="0" label="Reorder level" required />
                                <Input form={edit} name="is_active" type="checkbox" label="Active" />
                            </Form>
                        </Panel>
                    </Stack>
                )}
            </Split>
        </Page>
    );
}
