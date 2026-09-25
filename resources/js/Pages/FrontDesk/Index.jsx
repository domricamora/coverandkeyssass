import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Drawer, StatusPill, addDays, cx, daysBetween, fmtDay, label, money, parseDay } from '../../react/ui';

/**
 * Front desk: today at a glance (arrivals / in house / departures with
 * one-click check-in and check-out), a 14-day room tape chart where a stay
 * can be dragged to another room, and a booking drawer with the folio.
 */
export default function FrontDesk(props) {
    const { today, start, days, properties, property, lists, stats, rooms, stays, selected, can, urls } = props;
    const [tab, setTab] = useState('arrivals');

    const query = (extra = {}) => ({ property, start, ...extra });
    const visit = (params, options = {}) => router.get(urls.self, params, { preserveState: true, preserveScroll: true, ...options });
    const open = (reference) => visit(query({ booking: reference }), { only: ['selected'] });
    const close = () => visit(query(), { only: ['selected'] });
    const transition = (url, status) => router.post(url, { status }, { preserveScroll: true });

    if (!property) {
        return (
            <Page>
                <Head title="Front desk" />
                <Empty title="No property yet" body="Add a property with rooms to start running the front desk." />
            </Page>
        );
    }

    const tabs = [
        ['arrivals', 'Arrivals', lists.arrivals, stats.arrivalsLeft],
        ['inHouse', 'In house', lists.inHouse, null],
        ['departures', 'Departures', lists.departures, stats.departuresLeft],
    ];
    const rows = lists[tab];

    return (
        <Page>
            <Head title="Front desk" />

            <header className="flex flex-wrap items-end gap-x-6 gap-y-4">
                <div className="min-w-0 basis-full md:basis-0 md:flex-1">
                    <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">{fmtDay(today, { weekday: 'long', month: 'long', day: 'numeric' })}</p>
                    <h1 className="mt-1 font-display text-[28px] font-medium tracking-tight text-fg">Front desk</h1>
                </div>
                {properties.length > 1 && (
                    <label className="w-full sm:w-56">
                        <span className="label">Property</span>
                        <select className="field" value={property} onChange={(e) => visit({ property: e.target.value, start })}>
                            {properties.map((p) => <option key={p.slug} value={p.slug}>{p.name}</option>)}
                        </select>
                    </label>
                )}
                {can.create && <a href={urls.create} className="btn-primary">New booking / walk-in</a>}
            </header>

            <dl className="mt-6 grid grid-cols-2 border border-line bg-surface sm:grid-cols-3 xl:grid-cols-6">
                <Stat term="Arrivals" value={stats.arrivals} note={stats.arrivalsLeft ? `${stats.arrivalsLeft} to check in` : 'All checked in'} />
                <Stat term="Departures" value={stats.departures} note={stats.departuresLeft ? `${stats.departuresLeft} to check out` : 'All checked out'} />
                <Stat term="In house" value={stats.inHouse} note="Stays open now" />
                <Stat term="Occupancy" value={`${stats.occupancy}%`} note={`${stats.occupied} of ${stats.sellable} rooms tonight`} />
                <Stat term="Dirty rooms" value={stats.dirty} note={<a className="underline decoration-line-strong underline-offset-2 hover:text-fg" href={urls.housekeeping}>Housekeeping board</a>} tone={stats.dirty ? 'warn' : null} />
                <Stat term="Out of order" value={stats.outOfOrder} note="Not sellable" tone={stats.outOfOrder ? 'bad' : null} />
            </dl>

            <section className="mt-8 border border-line bg-surface" aria-label="Today">
                <div role="tablist" className="flex overflow-x-auto border-b border-line">
                    {tabs.map(([key, name, list, left]) => (
                        <button
                            key={key}
                            role="tab"
                            aria-selected={tab === key}
                            onClick={() => setTab(key)}
                            className={cx(
                                'relative flex h-12 shrink-0 items-center gap-2 px-5 text-[13px] transition-colors',
                                tab === key ? 'font-medium text-fg' : 'text-fg-3 hover:text-fg',
                            )}
                        >
                            {name}
                            <span className={cx('px-1.5 text-[11px] leading-5', tab === key ? 'bg-brand text-white' : 'bg-soft text-fg-3')}>{list.length}</span>
                            {left ? <span className="sr-only">{left} still to do</span> : null}
                            {tab === key && <span className="absolute inset-x-0 bottom-[-1px] h-[2px] bg-brand" />}
                        </button>
                    ))}
                </div>

                {rows.length === 0 ? (
                    <Empty
                        title={{ arrivals: 'No arrivals today', inHouse: 'Nobody in house', departures: 'No departures today' }[tab]}
                        body="New bookings for today show up here automatically."
                    />
                ) : (
                    <ul className="divide-y divide-line">
                        {rows.map((b) => (
                            <GuestRow key={b.reference} booking={b} tab={tab} today={today} can={can} onOpen={() => open(b.reference)} onTransition={(s) => transition(b.transition, s)} />
                        ))}
                    </ul>
                )}
            </section>

            <TapeChart start={start} days={days} today={today} rooms={rooms} stays={stays} canMove={can.update} urls={urls} onOpen={open} onShift={(s) => visit({ property, start: s })} />

            <BookingDrawer booking={selected} rooms={rooms} can={can} props={props} onClose={close} onTransition={(s) => transition(selected.urls.transition, s)} />
        </Page>
    );
}

const Page = ({ children }) => <div className="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">{children}</div>;

function Stat({ term, value, note, tone }) {
    return (
        <div className="border-b border-r border-line px-5 py-4 [&:nth-child(2n)]:border-r-0 sm:[&:nth-child(2n)]:border-r sm:[&:nth-child(3n)]:border-r-0 xl:border-b-0 xl:[&:nth-child(3n)]:border-r xl:last:border-r-0">
            <dt className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">{term}</dt>
            <dd className={cx('mt-1.5 font-display text-[26px] font-medium tabular-nums leading-none', tone === 'warn' ? 'text-warn' : tone === 'bad' ? 'text-bad' : 'text-fg')}>{value}</dd>
            <dd className="mt-1.5 text-xs text-fg-3">{note}</dd>
        </div>
    );
}

function Empty({ title, body }) {
    return (
        <div className="px-6 py-14 text-center">
            <p className="font-display text-lg font-medium text-fg">{title}</p>
            <p className="mt-1 text-[13px] text-fg-3">{body}</p>
        </div>
    );
}

function GuestRow({ booking: b, tab, today, can, onOpen, onTransition }) {
    const owes = b.balance > 0.005;
    let action = null;

    if (can.update) {
        if (tab === 'arrivals' && ['pending', 'held'].includes(b.status)) action = <button className="btn-ghost btn-sm" onClick={() => onTransition('confirmed')}>Confirm</button>;
        if (tab === 'arrivals' && b.status === 'confirmed') action = <button className="btn-primary btn-sm" onClick={() => onTransition('checked_in')}>Check in</button>;
        if (tab !== 'arrivals' && b.status === 'checked_in') {
            action = owes
                ? <button className="btn-coral btn-sm" onClick={onOpen}>Settle {money(b.balance, b.currency)}</button>
                : <button className="btn-primary btn-sm" onClick={() => onTransition('checked_out')}>Check out</button>;
        }
    }

    const late = tab === 'inHouse' && b.check_out < today;

    return (
        <li className="flex flex-wrap items-center gap-x-5 gap-y-2 px-5 py-3.5 transition-colors hover:bg-canvas">
            <button onClick={onOpen} className="focus-ring min-w-0 flex-1 basis-60 text-left">
                <span className="flex items-center gap-2.5">
                    <span className="truncate text-[14px] font-medium text-fg">{b.guest}</span>
                    <StatusPill status={b.status} />
                    {late && <span className="pill bg-bad-bg text-bad">Overdue</span>}
                </span>
                <span className="mt-0.5 block truncate text-xs text-fg-3">
                    <span className="font-mono">{b.reference}</span>
                    {' · '}Room {b.rooms.join(', ') || '—'} · {b.nights} {b.nights === 1 ? 'night' : 'nights'} · {b.guests} {b.guests === 1 ? 'guest' : 'guests'}
                    {tab === 'inHouse' && ` · leaves ${fmtDay(b.check_out)}`}
                    {b.group && ` · ${b.group}`}
                </span>
                {b.note && <span className="mt-1 block truncate text-xs text-coral-deep">“{b.note}”</span>}
            </button>
            <div className="w-28 text-right">
                <p className={cx('text-[13px] tabular-nums', owes ? 'font-medium text-fg' : 'text-fg-3')}>{money(b.balance, b.currency)}</p>
                <p className="text-[11px] text-fg-4">{owes ? 'balance due' : 'settled'}</p>
            </div>
            <div className="flex w-36 justify-end">{action}</div>
        </li>
    );
}

// ---------------------------------------------------------------------------

const BAR = {
    checked_in: 'bg-brand text-white border-brand',
    confirmed: 'bg-brand-soft text-brand-deep border-brand',
    pending: 'bg-coral-soft text-coral-deep border-dashed border-coral',
    held: 'bg-coral-soft text-coral-deep border-dashed border-coral',
    checked_out: 'bg-soft text-fg-3 border-line-strong',
};

const HK = { dirty: 'bg-warn', cleaning: 'bg-info', clean: 'bg-ok', inspected: 'bg-ok', maintenance: 'bg-bad', out_of_order: 'bg-bad' };

function TapeChart({ start, days, today, rooms, stays, canMove, urls, onOpen, onShift }) {
    const [over, setOver] = useState(null);
    const dates = useMemo(() => Array.from({ length: days }, (_, i) => addDays(start, i)), [start, days]);
    const byRoom = useMemo(() => {
        const map = {};
        stays.forEach((s) => (map[s.room] ??= []).push(s));
        return map;
    }, [stays]);

    const drop = (roomId, e) => {
        e.preventDefault();
        setOver(null);
        const stay = JSON.parse(e.dataTransfer.getData('application/json') || 'null');
        if (!stay || stay.room === roomId) return;
        router.post(urls.move, { booking_room: stay.id, room_id: roomId }, { preserveScroll: true });
    };

    const grid = { gridTemplateColumns: `140px repeat(${days}, minmax(56px, 1fr))` };

    return (
        <section className="mt-8 border border-line bg-surface" aria-label="Room chart">
            <div className="flex flex-wrap items-center gap-3 border-b border-line px-5 py-3">
                <h2 className="font-display text-[17px] font-medium text-fg">Room chart</h2>
                <p className="text-xs text-fg-3">{fmtDay(dates[0])} – {fmtDay(dates[dates.length - 1])}{canMove && ' · drag a stay onto another room to move it'}</p>
                <div className="ml-auto flex gap-1.5">
                    <button className="btn-ghost btn-sm" onClick={() => onShift(addDays(start, -7))} aria-label="Previous week">← Week</button>
                    <button className="btn-ghost btn-sm" onClick={() => onShift(addDays(today, -1))}>Today</button>
                    <button className="btn-ghost btn-sm" onClick={() => onShift(addDays(start, 7))} aria-label="Next week">Week →</button>
                </div>
            </div>

            <div className="overflow-x-auto">
                <div className="min-w-[900px]">
                    <div className="grid border-b border-line bg-canvas" style={grid}>
                        <div className="sticky left-0 z-10 bg-canvas px-4 py-2 text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">Room</div>
                        {dates.map((d) => {
                            const day = parseDay(d);
                            const weekend = [5, 6].includes(day.getDay());
                            return (
                                <div key={d} className={cx('border-l border-line px-1 py-2 text-center', d === today && 'bg-brand-soft')}>
                                    <p className={cx('text-[10px] uppercase tracking-wider', weekend ? 'text-coral-deep' : 'text-fg-4')}>{day.toLocaleDateString('en-US', { weekday: 'short' })}</p>
                                    <p className={cx('text-[13px] tabular-nums', d === today ? 'font-semibold text-brand-deep' : 'text-fg-2')}>{day.getDate()}</p>
                                </div>
                            );
                        })}
                    </div>

                    {rooms.length === 0 && <Empty title="No rooms yet" body="Add rooms under Rooms & rates to fill the chart." />}

                    {rooms.map((room) => (
                        <div
                            key={room.id}
                            className={cx('relative grid border-b border-line last:border-b-0', over === room.id && 'bg-brand-soft', !room.sellable && 'bg-[repeating-linear-gradient(135deg,transparent,transparent_6px,var(--soft)_6px,var(--soft)_12px)]')}
                            style={{ ...grid, gridTemplateRows: '44px' }}
                            onDragOver={canMove && room.sellable ? (e) => { e.preventDefault(); setOver(room.id); } : undefined}
                            onDragLeave={() => setOver((o) => (o === room.id ? null : o))}
                            onDrop={canMove && room.sellable ? (e) => drop(room.id, e) : undefined}
                        >
                            <div className="sticky left-0 z-10 flex items-center gap-2.5 border-r border-line bg-surface px-4" style={{ gridColumn: 1, gridRow: 1 }}>
                                <span className={cx('h-2 w-2 shrink-0', HK[room.hk] ?? 'bg-line-strong')} title={`Housekeeping: ${label(room.hk)}`} />
                                <span className="min-w-0">
                                    <span className="block text-[13px] font-medium tabular-nums text-fg">{room.number}</span>
                                    <span className="block truncate text-[11px] text-fg-4">{room.type}</span>
                                </span>
                            </div>
                            {dates.map((d, i) => (
                                <div key={d} className={cx('border-l border-line', d === today && 'bg-brand-soft')} style={{ gridColumn: i + 2, gridRow: 1 }} />
                            ))}
                            {(byRoom[room.id] ?? []).map((s) => {
                                const from = Math.max(0, daysBetween(start, s.first));
                                const to = Math.min(days - 1, daysBetween(start, s.last));
                                const movable = canMove && s.status !== 'checked_out';
                                return (
                                    <button
                                        key={s.id}
                                        draggable={movable}
                                        onDragStart={(e) => {
                                            e.dataTransfer.setData('application/json', JSON.stringify(s));
                                            e.dataTransfer.effectAllowed = 'move';
                                        }}
                                        onClick={() => onOpen(s.reference)}
                                        title={`${s.guest} · ${label(s.status)} · ${fmtDay(s.first)} – ${fmtDay(addDays(s.last, 1))}`}
                                        className={cx(
                                            'focus-ring z-[1] mx-[3px] my-[7px] flex min-w-0 items-center truncate border px-2 text-left text-xs font-medium transition-transform active:scale-[0.98]',
                                            movable && 'cursor-grab active:cursor-grabbing',
                                            BAR[s.status] ?? BAR.checked_out,
                                        )}
                                        style={{ gridColumn: `${from + 2} / ${to + 3}`, gridRow: 1 }}
                                    >
                                        <span className="truncate">{s.guest}</span>
                                    </button>
                                );
                            })}
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex flex-wrap gap-x-5 gap-y-2 border-t border-line px-5 py-3 text-[11px] text-fg-3">
                <Legend className={BAR.checked_in}>In house</Legend>
                <Legend className={BAR.confirmed}>Confirmed</Legend>
                <Legend className={BAR.pending}>Pending / held</Legend>
                <Legend className={BAR.checked_out}>Departed</Legend>
                <span className="flex items-center gap-1.5"><span className="h-2 w-2 bg-warn" />Dirty</span>
                <span className="flex items-center gap-1.5"><span className="h-2 w-2 bg-ok" />Clean</span>
                <span className="flex items-center gap-1.5"><span className="h-2 w-2 bg-bad" />Out of order</span>
            </div>
        </section>
    );
}

const Legend = ({ className, children }) => (
    <span className="flex items-center gap-1.5"><span className={cx('h-3 w-5 border', className)} />{children}</span>
);

// ---------------------------------------------------------------------------

const ACTION_LABEL = { confirmed: 'Confirm', checked_in: 'Check in', checked_out: 'Check out', no_show: 'No-show', cancelled: 'Cancel booking' };

function BookingDrawer({ booking: b, rooms, can, props, onClose, onTransition }) {
    return (
        <Drawer open={!!b} onClose={onClose} title={b ? `Booking ${b.reference}` : 'Booking'}>
            {b && (
                <>
                    <div className="flex items-start gap-3 border-b border-line px-6 py-5">
                        <div className="min-w-0 flex-1">
                            <p className="font-mono text-xs text-fg-3">{b.reference} · {label(b.source)}</p>
                            <h2 className="mt-1 truncate font-display text-[22px] font-medium tracking-tight text-fg">{b.guest}</h2>
                            <div className="mt-2"><StatusPill status={b.status} /></div>
                        </div>
                        <button onClick={onClose} className="btn-ghost btn-sm" aria-label="Close">Close</button>
                    </div>

                    <div className="flex-1 overflow-y-auto">
                        <dl className="grid grid-cols-2 gap-x-6 gap-y-4 px-6 py-5 text-[13px]">
                            <Fact term="Arrive">{fmtDay(b.check_in, { weekday: 'short', month: 'short', day: 'numeric' })}</Fact>
                            <Fact term="Depart">{fmtDay(b.check_out, { weekday: 'short', month: 'short', day: 'numeric' })}</Fact>
                            <Fact term="Stay">{b.nights} {b.nights === 1 ? 'night' : 'nights'}</Fact>
                            <Fact term="Guests">{b.adults} adult{b.adults === 1 ? '' : 's'}{b.children ? `, ${b.children} child${b.children === 1 ? '' : 'ren'}` : ''}</Fact>
                            {b.email && <Fact term="Email"><a className="text-brand hover:underline" href={`mailto:${b.email}`}>{b.email}</a></Fact>}
                            {b.phone && <Fact term="Phone"><a className="text-brand hover:underline" href={`tel:${b.phone}`}>{b.phone}</a></Fact>}
                            {b.group && <Fact term="Group">{b.group}</Fact>}
                        </dl>

                        {b.note && <p className="mx-6 mb-5 border-l-2 border-coral bg-coral-soft px-4 py-3 text-[13px] text-fg-2">{b.note}</p>}

                        {can.update && b.next.length > 0 && (
                            <div className="flex flex-wrap gap-2 border-t border-line px-6 py-4">
                                {b.next.map((s) => {
                                    const destructive = ['cancelled', 'no_show'].includes(s);
                                    return (
                                        <button
                                            key={s}
                                            onClick={() => (!destructive || window.confirm(`Mark this booking ${label(s).toLowerCase()}?`)) && onTransition(s)}
                                            className={destructive ? 'btn-danger' : 'btn-primary'}
                                        >
                                            {ACTION_LABEL[s]}
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        <Section title="Rooms">
                            <ul className="divide-y divide-line border border-line">
                                {b.rooms.map((r) => (
                                    <li key={r.id} className="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                                        <span className="font-medium tabular-nums">{r.number ?? '—'}</span>
                                        <span className="flex-1 truncate text-fg-3">{r.type}</span>
                                        {can.update && ['pending', 'held', 'confirmed', 'checked_in'].includes(b.status) && (
                                            <label className="flex items-center gap-2 text-xs text-fg-3">
                                                Move to
                                                <select
                                                    className="field h-8 w-24"
                                                    value=""
                                                    onChange={(e) => e.target.value && router.post(props.urls.move, { booking_room: r.id, room_id: Number(e.target.value) }, { preserveScroll: true })}
                                                >
                                                    <option value="">Room…</option>
                                                    {rooms.filter((x) => x.sellable && x.number !== r.number).map((x) => <option key={x.id} value={x.id}>{x.number}</option>)}
                                                </select>
                                            </label>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </Section>

                        {b.folio && <Folio booking={b} can={can} props={props} />}
                    </div>

                    <div className="flex flex-wrap gap-2 border-t border-line px-6 py-3">
                        <a className="btn-ghost btn-sm" href={b.urls.folio}>Full folio</a>
                        <a className="btn-ghost btn-sm" href={b.urls.print} target="_blank" rel="noreferrer">Print bill</a>
                        <a className="btn-ghost btn-sm" href={b.urls.booking}>Booking page</a>
                    </div>
                </>
            )}
        </Drawer>
    );
}

const Fact = ({ term, children }) => (
    <div className="min-w-0">
        <dt className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-4">{term}</dt>
        <dd className="mt-0.5 truncate text-fg">{children}</dd>
    </div>
);

const Section = ({ title, children, aside }) => (
    <section className="border-t border-line px-6 py-5">
        <div className="mb-3 flex items-center">
            <h3 className="text-[11px] font-medium uppercase tracking-[0.1em] text-fg-3">{title}</h3>
            <div className="ml-auto">{aside}</div>
        </div>
        {children}
    </section>
);

function Folio({ booking: b, can, props }) {
    const { totals, entries } = b.folio;
    const due = totals.balance;
    const [pay, setPay] = useState({ method: 'cash', amount: '' });
    const [charge, setCharge] = useState(null);
    const [busy, setBusy] = useState(false);

    const post = (url, data, reset) =>
        router.post(url, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onSuccess: reset,
        });

    return (
        <Section title="Folio" aside={<span className={cx('text-[13px] font-medium tabular-nums', due > 0.005 ? 'text-coral-deep' : 'text-ok')}>{due > 0.005 ? `${money(due, b.currency)} due` : 'Settled'}</span>}>
            <table className="w-full text-[13px]">
                <tbody className="divide-y divide-line">
                    {entries.map((e) => (
                        <tr key={e.id} className={cx(e.void && 'text-fg-4 line-through')}>
                            <td className="w-16 py-2 pr-2 align-top text-xs text-fg-4">{fmtDay(e.date)}</td>
                            <td className="py-2 pr-3 align-top text-fg-2">{e.description}</td>
                            <td className={cx('whitespace-nowrap py-2 text-right align-top tabular-nums', e.type === 'payment' && !e.void && 'text-ok')}>
                                {e.type === 'payment' ? '−' : ''}{money(e.amount, b.currency)}
                            </td>
                        </tr>
                    ))}
                    {entries.length === 0 && (
                        <tr><td className="py-3 text-fg-3" colSpan={3}>Nothing posted yet.</td></tr>
                    )}
                </tbody>
                <tfoot className="border-t border-line-strong">
                    <tr><td /><td className="pt-3 text-fg-3">Charges</td><td className="pt-3 text-right tabular-nums">{money(totals.charges, b.currency)}</td></tr>
                    <tr><td /><td className="text-fg-3">Payments</td><td className="text-right tabular-nums text-ok">−{money(totals.payments, b.currency)}</td></tr>
                    {totals.refunds > 0 && <tr><td /><td className="text-fg-3">Refunds</td><td className="text-right tabular-nums">{money(totals.refunds, b.currency)}</td></tr>}
                    <tr><td /><td className="pt-1 font-medium">Balance</td><td className="pt-1 text-right font-medium tabular-nums">{money(due, b.currency)}</td></tr>
                </tfoot>
            </table>

            {can.folio && (
                <>
                    <form
                        className="mt-5 grid grid-cols-2 gap-3 border border-line bg-canvas p-4 sm:grid-cols-[130px_1fr_auto]"
                        onSubmit={(e) => {
                            e.preventDefault();
                            post(b.urls.payment, { ...pay, amount: pay.amount || due }, () => setPay({ method: 'cash', amount: '' }));
                        }}
                    >
                        <label>
                            <span className="label">Method</span>
                            <select className="field" value={pay.method} onChange={(e) => setPay({ ...pay, method: e.target.value })}>
                                {props.paymentMethods.map((m) => <option key={m} value={m}>{label(m)}</option>)}
                            </select>
                        </label>
                        <label>
                            <span className="label">Amount</span>
                            <input className="field tabular-nums" type="number" min="0.01" step="0.01" inputMode="decimal" placeholder={due > 0 ? due.toFixed(2) : '0.00'} value={pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} />
                        </label>
                        <div className="col-span-2 flex items-end sm:col-span-1">
                            <button className="btn-primary w-full" disabled={busy || (!pay.amount && due <= 0)}>Take payment</button>
                        </div>
                    </form>

                    {charge ? (
                        <form
                            className="mt-3 grid grid-cols-2 gap-3 border border-line p-4"
                            onSubmit={(e) => {
                                e.preventDefault();
                                post(b.urls.charge, charge, () => setCharge(null));
                            }}
                        >
                            <label>
                                <span className="label">Category</span>
                                <select className="field" value={charge.category} onChange={(e) => setCharge({ ...charge, category: e.target.value })}>
                                    {props.chargeCategories.map((c) => <option key={c} value={c}>{label(c)}</option>)}
                                </select>
                            </label>
                            <label>
                                <span className="label">Amount</span>
                                <input className="field tabular-nums" type="number" min="0.01" step="0.01" required value={charge.unit_amount} onChange={(e) => setCharge({ ...charge, unit_amount: e.target.value })} />
                            </label>
                            <label className="col-span-2">
                                <span className="label">Description</span>
                                <input className="field" required maxLength={255} placeholder="e.g. Minibar, 2 drinks" value={charge.description} onChange={(e) => setCharge({ ...charge, description: e.target.value })} />
                            </label>
                            <div className="col-span-2 flex justify-end gap-2">
                                <button type="button" className="btn-ghost" onClick={() => setCharge(null)}>Cancel</button>
                                <button className="btn-primary" disabled={busy}>Post charge</button>
                            </div>
                        </form>
                    ) : (
                        <button className="btn-ghost btn-sm mt-3" onClick={() => setCharge({ category: props.chargeCategories[0], description: '', unit_amount: '' })}>+ Add a charge</button>
                    )}
                </>
            )}
        </Section>
    );
}
