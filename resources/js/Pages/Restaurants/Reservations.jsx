import { router, useForm } from '@inertiajs/react';
import { Action, Form, Input, Page, Panel, Row, Select, Split, Stack, Status, Tabs, TextArea } from '../../react/kit';
import { addDays, cx } from '../../react/ui';

const ACTIONS = {
    confirmed: ['Confirm', 'btn-primary btn-sm'],
    seated: ['Seat', 'btn-primary btn-sm'],
    completed: ['Complete', 'btn-ghost btn-sm'],
    no_show: ['No-show', 'btn-ghost btn-sm'],
    cancelled: ['Cancel', 'btn-danger btn-sm'],
};
const BLOCK = { confirmed: 'border-info bg-info-bg text-info', seated: 'border-ok bg-ok-bg text-ok', pending: 'border-warn bg-warn-bg text-warn', completed: 'border-line bg-soft text-fg-3' };

/** Reservations book for one day: per-table timeline, the list with actions, and a booking form. */
export default function Reservations({ restaurant, tabs, date, dateLabel, covers, reservations, tables, slots, can, urls }) {
    const go = (d) => router.get(urls.self, { date: d }, { preserveScroll: true });
    const form = useForm({ date, time: slots[0] ?? '19:00', party_size: 2, guest_name: '', guest_phone: '', restaurant_table_id: '', special_requests: '' });

    return (
        <Page
            title={restaurant.name}
            subtitle={`${dateLabel} · ${covers} covers booked`}
            actions={
                <div className="flex items-center gap-1">
                    <button className="btn-ghost btn-sm h-9" onClick={() => go(addDays(date, -1))} aria-label="Previous day">←</button>
                    <input type="date" className="field w-auto" value={date} onChange={(e) => go(e.target.value)} aria-label="Date" />
                    <button className="btn-ghost btn-sm h-9" onClick={() => go(addDays(date, 1))} aria-label="Next day">→</button>
                </div>
            }
        >
            <Tabs tabs={tabs} />

            <Panel title="By table">
                <ul className="divide-y divide-line">
                    {tables.length === 0 && <li className="px-5 py-6 text-[13px] text-fg-3">No active tables. Add some on the Tables tab first.</li>}
                    {tables.map((t) => {
                        const booked = reservations.filter((r) => r.table_id === t.id);
                        return (
                            <li key={t.id} className="flex flex-wrap items-center gap-3 px-5 py-3">
                                <span className="w-24 shrink-0"><strong className="font-medium text-fg">{t.label}</strong> <span className="text-[12px] text-fg-3">{t.seats} seats</span></span>
                                {booked.length === 0 ? <span className="text-[12px] text-fg-3">Free all day</span> : booked.map((r) => (
                                    <span key={r.reference} className={cx('border px-2 py-1 text-[12px]', BLOCK[r.status] ?? 'border-line text-fg-3 line-through')} title={`${r.name} · ${r.status}`}>
                                        {r.span} · {r.party}p · {r.name}
                                    </span>
                                ))}
                            </li>
                        );
                    })}
                </ul>
            </Panel>

            <Split side="360px">
                <Panel title="Bookings" aside={`${reservations.length} on this day`}>
                    <ul className="divide-y divide-line">
                        {reservations.length === 0 && <li className="px-5 py-8 text-center text-[13px] text-fg-3">No reservations on this day.</li>}
                        {reservations.map((r) => (
                            <li key={r.reference} className="flex flex-wrap items-start justify-between gap-3 px-5 py-3.5 text-[13px]">
                                <div className="min-w-0">
                                    <p className="text-fg"><strong className="font-semibold tabular-nums">{r.time}</strong> · {r.name} · party of {r.party} · table {r.table ?? '—'} <Status value={r.status} /></p>
                                    <p className="mt-0.5 text-[12px] text-fg-3">{r.reference} · {r.source}{r.phone ? ` · ${r.phone}` : ''}</p>
                                    {r.note && <p className="mt-1 italic text-fg-2">“{r.note}”</p>}
                                </div>
                                <span className="flex flex-wrap gap-1.5">
                                    {r.next.map((to) => <Action key={to} href={r.transition} data={{ status: to }} className={ACTIONS[to]?.[1] ?? 'btn-ghost btn-sm'}>{ACTIONS[to]?.[0] ?? to}</Action>)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Panel>

                {can.manage && (
                    <Stack>
                        <Panel title="New reservation" pad>
                            <Form form={form} url={urls.store} button="Book table">
                                <Row>
                                    <Input form={form} name="date" type="date" label="Date" required />
                                    <Input form={form} name="time" type="time" step="900" label="Time" required />
                                </Row>
                                <Row>
                                    <Input form={form} name="party_size" type="number" min="1" label="Party size" required />
                                    <Select form={form} name="restaurant_table_id" label="Table" placeholder="Best available" options={tables.map((t) => [t.id, `${t.label} (${t.seats})`])} />
                                </Row>
                                <Input form={form} name="guest_name" label="Guest name" required />
                                <Input form={form} name="guest_phone" label="Phone" />
                                <TextArea form={form} name="special_requests" label="Special requests" rows={2} />
                            </Form>
                            {slots.length > 0 && <p className="text-[12px] text-fg-3">Online slots: {slots[0]}–{slots[slots.length - 1]}</p>}
                        </Panel>
                    </Stack>
                )}
            </Split>
        </Page>
    );
}
