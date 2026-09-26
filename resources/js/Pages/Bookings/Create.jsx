import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Field, Input, Page, Panel, Row, Select, Table, Td, submit } from '../../react/kit';

/** Manual reservation, hold or walk-in: pick property + dates, see free rooms, fill guest details. */
export default function CreateBooking({ properties, property, dates, roomTypes, urls }) {
    const [pick, setPick] = useState({ property: property ?? '', ...dates });
    const form = useForm({
        property: property ?? '',
        source: 'manual',
        check_in: dates.check_in ?? '',
        check_out: dates.check_out ?? '',
        rooms: roomTypes.map((t) => ({ room_type_id: t.id, quantity: 0 })),
        guest_name: '', guest_email: '', guest_phone: '',
        adults: 1, children: 0, group_name: '', hold_hours: '', promo_code: '', special_requests: '',
    });
    const setQty = (i, v) => form.setData('rooms', form.data.rooms.map((r, k) => (k === i ? { ...r, quantity: v } : r)));

    return (
        <Page title="New booking" subtitle="Manual reservation, hold or walk-in. Availability is re-checked under lock when you save." back={{ href: urls.index, label: 'Bookings' }}>
            <form
                className="flex flex-wrap items-end gap-3 border border-line bg-surface p-5"
                onSubmit={(e) => { e.preventDefault(); router.get(urls.self, pick); }}
            >
                <Field label="Property" className="w-full sm:w-64">
                    <select className="field" required value={pick.property} onChange={(e) => setPick({ ...pick, property: e.target.value })}>
                        <option value="">Choose…</option>
                        {properties.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                    </select>
                </Field>
                <Field label="Check-in"><input type="date" className="field" value={pick.check_in ?? ''} onChange={(e) => setPick({ ...pick, check_in: e.target.value })} /></Field>
                <Field label="Check-out"><input type="date" className="field" value={pick.check_out ?? ''} onChange={(e) => setPick({ ...pick, check_out: e.target.value })} /></Field>
                <button className="btn-ghost" type="submit">Check availability</button>
            </form>

            {property && (
                <form onSubmit={submit(form, urls.store)} className="space-y-8">
                    <Panel title="Stay" pad>
                        <Row cols={3}>
                            <Select form={form} name="source" label="Type" options={[['manual', 'Reservation'], ['walk_in', 'Walk-in (checks in now)']]} />
                            <Input form={form} name="check_in" type="date" label="Check-in" required />
                            <Input form={form} name="check_out" type="date" label="Check-out" required />
                        </Row>
                    </Panel>

                    <Panel title="Rooms">
                        <Table head={['Room type', 'Sleeps', 'Rate', 'Free', 'Quantity']} empty="This property has no active room types yet.">
                            {roomTypes.map((t, i) => (
                                <tr key={t.id}>
                                    <Td className="font-medium text-fg">{t.name}</Td>
                                    <Td muted>{t.sleeps}</Td>
                                    <Td muted>{t.rate}</Td>
                                    <Td muted>{t.free ?? '—'}</Td>
                                    <Td><input type="number" min="0" max="50" className="field w-24" aria-label={`Quantity of ${t.name}`} value={form.data.rooms[i]?.quantity ?? 0} onChange={(e) => setQty(i, e.target.value)} /></Td>
                                </tr>
                            ))}
                        </Table>
                        {form.errors.rooms && <p className="px-5 pb-4 text-[12px] text-bad">{form.errors.rooms}</p>}
                    </Panel>

                    <Panel title="Guest" pad>
                        <Row cols={3}>
                            <Input form={form} name="guest_name" label="Guest name" required />
                            <Input form={form} name="guest_email" type="email" label="Email" />
                            <Input form={form} name="guest_phone" label="Phone" />
                        </Row>
                        <Row cols={4}>
                            <Input form={form} name="adults" type="number" min="1" label="Adults" />
                            <Input form={form} name="children" type="number" min="0" label="Children" />
                            <Input form={form} name="group_name" label="Group name" placeholder="Optional" />
                            <Input form={form} name="hold_hours" type="number" min="1" max="168" label="Hold only (hours)" placeholder="Confirm now" />
                        </Row>
                        <Row>
                            <Input form={form} name="promo_code" label="Promo code" />
                            <Input form={form} name="special_requests" label="Special requests" />
                        </Row>
                    </Panel>

                    <div className="flex justify-end">
                        <button type="submit" className="btn-primary" disabled={form.processing}>Create booking</button>
                    </div>
                </form>
            )}
        </Page>
    );
}
