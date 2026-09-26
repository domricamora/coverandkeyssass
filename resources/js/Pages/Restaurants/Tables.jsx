import { useForm } from '@inertiajs/react';
import { Action, Form, Input, Page, Panel, Row, Select, Split, Stack, Status, Table, Tabs, Td } from '../../react/kit';

/** Floor plan: dining areas and the tables guests are seated at. */
export default function Tables({ restaurant, tabs, areas, tables, can, urls }) {
    const area = useForm({ name: '' });
    const table = useForm({ label: '', seats: 4, dining_area_id: '', status: 'active' });

    return (
        <Page title={restaurant.name} subtitle="Dining areas and the tables guests are seated at.">
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Panel title="Tables" aside={`${tables.length} total`}>
                    <Table head={['Table', { label: 'Seats', right: true }, 'Area', 'Status', '']} empty="No tables yet.">
                        {tables.map((t) => (
                            <tr key={t.id}>
                                <Td className="font-medium text-fg">{t.fields.label}</Td>
                                <Td right>{t.fields.seats}</Td>
                                <Td muted>{t.area ?? '—'}</Td>
                                <Td><Status value={t.fields.status} /></Td>
                                <Td right>
                                    {can.manage && (
                                        <span className="inline-flex gap-1.5">
                                            <Action href={t.update} method="patch" data={{ ...t.fields, status: t.fields.status === 'active' ? 'inactive' : 'active' }}>{t.fields.status === 'active' ? 'Deactivate' : 'Activate'}</Action>
                                            <Action href={t.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this table?">Remove</Action>
                                        </span>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>

                <Stack>
                    <Panel title="Dining areas">
                        <ul className="divide-y divide-line text-[13px]">
                            {areas.length === 0 && <li className="px-5 py-4 text-fg-3">No areas yet.</li>}
                            {areas.map((a) => (
                                <li key={a.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
                                    <span><strong className="font-medium text-fg">{a.name}</strong> <span className="text-fg-3">· {a.tables} table(s)</span></span>
                                    {can.manage && <Action href={a.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this area? Its tables stay, unassigned.">Remove</Action>}
                                </li>
                            ))}
                        </ul>
                        {can.manage && (
                            <form className="flex gap-2 border-t border-line p-5" onSubmit={(e) => { e.preventDefault(); area.post(urls.addArea, { preserveScroll: true, onSuccess: () => area.reset() }); }}>
                                <Input form={area} name="name" placeholder="Terrace" aria-label="Area name" required className="flex-1" />
                                <button className="btn-ghost" disabled={area.processing}>Add area</button>
                            </form>
                        )}
                    </Panel>

                    {can.manage && (
                        <Panel title="Add table" pad>
                            <Form form={table} url={urls.addTable} reset button="Add table">
                                <Row>
                                    <Input form={table} name="label" label="Label" placeholder="T1" required />
                                    <Input form={table} name="seats" type="number" min="1" label="Seats" required />
                                </Row>
                                <Select form={table} name="dining_area_id" label="Dining area" placeholder="No area" options={areas.map((a) => [a.id, a.name])} />
                            </Form>
                        </Panel>
                    )}
                </Stack>
            </Split>
        </Page>
    );
}
