import { router, useForm } from '@inertiajs/react';
import { Action, Empty, Form, Input, Page, Panel, Row, Select, Split, Stack, Table, Td, Tabs } from '../../react/kit';
import { label } from '../../react/ui';

/** Inventory workbench: room types with rooms and rate periods, plus availability blocks. */
export default function Inventory({ property, tabs, roomTypes, blocks, roomStatuses, reasons, can, urls }) {
    const type = useForm({ name: '', max_guests: 2, base_price: '', weekend_price: '', min_stay_nights: 1, status: 'active' });
    const block = useForm({ room_type_id: roomTypes[0]?.id ?? '', room_id: '', start_date: '', end_date: '', reason: reasons[0], note: '' });
    const roomsOfType = roomTypes.find((t) => String(t.id) === String(block.data.room_type_id))?.rooms ?? [];

    return (
        <Page title={property.name} subtitle="Room types, physical rooms, rate periods and availability blocks.">
            <Tabs tabs={tabs} />
            <Split side="380px">
                <Stack>
                    {roomTypes.length === 0 && <Empty title="No room types yet" body="Add the first room type, then its rooms and seasonal rates." />}
                    {roomTypes.map((t) => <RoomType key={t.id} type={t} statuses={roomStatuses} can={can} />)}

                    <Panel title="Availability blocks" aside="Take a type or single room off the market">
                        <Table head={['Target', 'Range', 'Reason', 'Note', '']} empty="Nothing blocked. All sellable rooms are open.">
                            {blocks.map((b) => (
                                <tr key={b.id}>
                                    <Td muted>{b.target}</Td>
                                    <Td muted className="whitespace-nowrap">{b.range}</Td>
                                    <Td>{label(b.reason)}</Td>
                                    <Td muted>{b.note ?? '—'}</Td>
                                    <Td right>{can.edit && <Action href={b.destroy} method="delete" className="btn-danger btn-sm">Remove</Action>}</Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                </Stack>

                {can.edit && (
                    <Stack>
                        <Panel title="Add room type" pad>
                            <Form form={type} url={urls.addType} reset button="Add room type">
                                <Input form={type} name="name" label="Name" placeholder="Deluxe Room" required />
                                <Row>
                                    <Input form={type} name="max_guests" type="number" min="1" label="Max guests" required />
                                    <Input form={type} name="min_stay_nights" type="number" min="1" label="Min stay" />
                                </Row>
                                <Row>
                                    <Input form={type} name="base_price" type="number" step="0.01" min="0" label="Nightly rate" required />
                                    <Input form={type} name="weekend_price" type="number" step="0.01" min="0" label="Weekend rate" />
                                </Row>
                                <Select form={type} name="status" label="Status" options={['active', 'draft']} />
                            </Form>
                        </Panel>

                        {roomTypes.length > 0 && (
                            <Panel title="Block dates" pad>
                                <Form form={block} url={urls.addBlock} reset button="Add block">
                                    <Select form={block} name="room_type_id" label="Room type" options={roomTypes.map((t) => [t.id, t.name])} onChange={(e) => block.setData({ ...block.data, room_type_id: e.target.value, room_id: '' })} />
                                    <Select form={block} name="room_id" label="Room" placeholder="Whole type" options={roomsOfType.map((r) => [r.id, r.label])} />
                                    <Row>
                                        <Input form={block} name="start_date" type="date" label="From" required />
                                        <Input form={block} name="end_date" type="date" label="To" required />
                                    </Row>
                                    <Select form={block} name="reason" label="Reason" options={reasons} />
                                    <Input form={block} name="note" label="Note" placeholder="Poolside renovation" />
                                </Form>
                            </Panel>
                        )}
                    </Stack>
                )}
            </Split>
        </Page>
    );
}

function RoomType({ type: t, statuses, can }) {
    const room = useForm({ room_number: '', floor: '' });
    const rate = useForm({ name: '', start_date: '', end_date: '', nightly_price: '' });

    return (
        <Panel
            title={t.name}
            aside={can.edit && <Action href={t.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this room type?">Remove type</Action>}
        >
            <p className="border-b border-line px-5 py-2.5 text-[13px] text-fg-3">{t.summary}</p>

            <Table head={['Room', 'Floor', 'Status', '']} empty="No rooms yet for this type.">
                {t.rooms.map((r) => (
                    <tr key={r.id}>
                        <Td className="font-medium text-fg">{r.label}</Td>
                        <Td muted>{r.floor ?? '—'}</Td>
                        <Td>
                            <select
                                className="field w-40"
                                aria-label={`Status of ${r.label}`}
                                value={r.status}
                                disabled={!can.edit}
                                onChange={(e) => router.patch(r.update, { status: e.target.value }, { preserveScroll: true })}
                            >
                                {statuses.map((s) => <option key={s} value={s}>{label(s)}</option>)}
                            </select>
                        </Td>
                        <Td right>{can.edit && <Action href={r.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this room from inventory?">Remove</Action>}</Td>
                    </tr>
                ))}
            </Table>
            {can.edit && (
                <form className="flex flex-wrap items-end gap-3 border-t border-line px-5 py-4" onSubmit={(e) => { e.preventDefault(); room.post(t.addRoom, { preserveScroll: true, onSuccess: () => room.reset() }); }}>
                    <Input form={room} name="room_number" label="Room number" placeholder="101" required className="w-32" />
                    <Input form={room} name="floor" type="number" label="Floor" className="w-24" />
                    <button className="btn-ghost" disabled={room.processing}>Add room</button>
                </form>
            )}

            <h3 className="border-t border-line px-5 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-fg-3">Seasonal rates</h3>
            <Table head={['Range', 'Nightly', 'Weekend', 'Min stay', '']} empty="No rate periods. The base rate applies every night.">
                {t.rates.map((r) => (
                    <tr key={r.id}>
                        <Td muted className="whitespace-nowrap">{r.range}</Td>
                        <Td>{r.nightly}</Td>
                        <Td muted>{r.weekend ?? '—'}</Td>
                        <Td muted>{r.min}</Td>
                        <Td right>{can.edit && <Action href={r.destroy} method="delete" className="btn-danger btn-sm">Remove</Action>}</Td>
                    </tr>
                ))}
            </Table>
            {can.edit && (
                <form className="grid gap-3 border-t border-line px-5 py-4 sm:grid-cols-[1fr_1fr_1fr_1fr_auto] sm:items-end" onSubmit={(e) => { e.preventDefault(); rate.post(t.addRate, { preserveScroll: true, onSuccess: () => rate.reset() }); }}>
                    <Input form={rate} name="name" label="Label" placeholder="High season" />
                    <Input form={rate} name="start_date" type="date" label="From" required />
                    <Input form={rate} name="end_date" type="date" label="To" required />
                    <Input form={rate} name="nightly_price" type="number" step="0.01" min="0" label="Nightly" required />
                    <button className="btn-ghost" disabled={rate.processing}>Add rate</button>
                </form>
            )}
        </Panel>
    );
}
