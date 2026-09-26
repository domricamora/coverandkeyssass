import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Drawer, cx, label, money } from '../../react/ui';

/**
 * Restaurant floor: live table plan (free / seated / ready / paid / booked
 * soon), the kitchen rail with bump buttons, today's book, and one drawer
 * for ordering (fast menu with modifiers), paying and closing a ticket.
 */
export default function RestaurantFloor(props) {
    const { restaurants, restaurant, session, closings, areas, counter, kitchen, book, menu, stats, urls, can, methods, ticket } = props;
    const [draft, setDraft] = useState(null); // { tableId, where, lines: [] } for a new ticket

    if (!restaurant) {
        return (
            <Page>
                <Head title="Restaurant floor" />
                <Empty title="No restaurant yet" body="Add a restaurant with tables and a menu to run the floor." />
            </Page>
        );
    }

    const currency = restaurant.currency;
    const visit = (params, options = {}) => router.get(urls.self, { restaurant: restaurant.slug, ...params }, { preserveState: true, preserveScroll: true, ...options });
    const openTicket = (reference) => { setDraft(null); visit({ ticket: reference }, { only: ['ticket'] }); };
    const closeDrawer = () => { setDraft(null); if (ticket) visit({}, { only: ['ticket'] }); };
    const post = (url, data = {}, opts = {}) => router.post(url, data, { preserveScroll: true, ...opts });

    return (
        <Page>
            <Head title="Restaurant floor" />

            <header className="flex flex-wrap items-end gap-x-6 gap-y-4">
                <div className="min-w-0 basis-full md:basis-0 md:flex-1">
                    <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">{new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}</p>
                    <h1 className="mt-1 font-display text-[28px] font-medium tracking-tight text-fg">Restaurant floor</h1>
                </div>
                {restaurants.length > 1 && (
                    <label className="w-full sm:w-56">
                        <span className="label">Restaurant</span>
                        <select className="field" value={restaurant.slug} onChange={(e) => router.get(urls.self, { restaurant: e.target.value })}>
                            {restaurants.map((r) => <option key={r.slug} value={r.slug}>{r.name}</option>)}
                        </select>
                    </label>
                )}
                {session ? (
                    <>
                        <span className="pill h-9 bg-ok-bg px-3 text-[13px] text-ok">Register open since {session.opened_at}</span>
                        {can.manage && (
                            <button
                                className="btn-ghost"
                                onClick={() => {
                                    const counted = window.prompt(`Count the drawer. Expected ${money(session.expected, currency)}. Counted cash (₱):`);
                                    if (counted !== null && counted !== '') post(session.close, { counted_cash: counted });
                                }}
                            >
                                Close day
                            </button>
                        )}
                    </>
                ) : (
                    <button className="btn-coral" onClick={() => post(urls.session, { opening_float: window.prompt('Opening cash float (₱)', '3000') || 0 })}>Open register</button>
                )}
                <button className="btn-primary" onClick={() => setDraft({ tableId: null, where: 'Counter order', lines: [] })}>New counter order</button>
            </header>

            <dl className="mt-6 grid grid-cols-2 border border-line bg-surface lg:grid-cols-4">
                <Stat term="Open tickets" value={stats.open} />
                <Stat term="Sales today" value={money(stats.sales, currency)} />
                <Stat term="Covers seated" value={stats.covers} />
                <Stat term="Bookings to come" value={stats.booked} />
            </dl>
            {closings?.length > 0 && (
                <p className="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[12px] text-fg-3">
                    Past closings:
                    {closings.map((c) => (
                        <a key={c.id} href={c.href} className="hover:text-fg">{c.at} <span className={cx('tabular-nums', c.variance < 0 && 'text-bad')}>({money(c.variance, currency)})</span></a>
                    ))}
                </p>
            )}

            <div className="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="min-w-0 space-y-8">
                    {areas.map((area) => (
                        <section key={area.name} className="border border-line bg-surface">
                            <h2 className="border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">{area.name}</h2>
                            <div className="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-6">
                                {area.tables.map((t) => (
                                    <TableTile key={t.id} table={t} currency={currency}
                                        onClick={() => (t.ticket ? openTicket(t.ticket.reference) : setDraft({ tableId: t.id, where: `Table ${t.label}`, lines: [] }))} />
                                ))}
                            </div>
                        </section>
                    ))}
                    {areas.length === 0 && <Empty title="No tables yet" body="Add dining areas and tables under Restaurants → Tables." />}

                    {counter.length > 0 && (
                        <section className="border border-line bg-surface">
                            <h2 className="border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">Counter & takeaway</h2>
                            <ul className="divide-y divide-line">
                                {counter.map((o) => (
                                    <li key={o.reference}>
                                        <button className="flex w-full items-center gap-4 px-5 py-3 text-left hover:bg-canvas" onClick={() => openTicket(o.reference)}>
                                            <span className="font-mono text-xs text-fg-3">{o.reference}</span>
                                            <span className="flex-1 text-[14px] text-fg">{o.name || 'Counter'}</span>
                                            <span className="pill bg-soft text-fg-2">{label(o.status)}</span>
                                            <span className="text-[14px] tabular-nums">{money(o.total, currency)}</span>
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </div>

                <aside className="min-w-0 space-y-8">
                    <KitchenRail tickets={kitchen} post={post} />
                    <Book book={book} can={can} post={post} />
                </aside>
            </div>

            <Drawer open={!!draft || !!ticket} onClose={closeDrawer} title={draft ? draft.where : ticket?.where ?? 'Ticket'} width="max-w-[640px]">
                {draft && <NewTicket draft={draft} setDraft={setDraft} menu={menu} currency={currency} urls={urls} onClose={closeDrawer} />}
                {!draft && ticket && <TicketPanel ticket={ticket} menu={menu} currency={currency} session={session} can={can} methods={methods} post={post} onClose={closeDrawer} />}
            </Drawer>
        </Page>
    );
}

const Page = ({ children }) => <div className="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">{children}</div>;

function Stat({ term, value }) {
    return (
        <div className="border-b border-r border-line px-5 py-4 [&:nth-child(2n)]:border-r-0 lg:border-b-0 lg:[&:nth-child(2n)]:border-r lg:last:border-r-0">
            <dt className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">{term}</dt>
            <dd className="mt-1.5 font-display text-[26px] font-medium leading-none tabular-nums text-fg">{value}</dd>
        </div>
    );
}

function Empty({ title, body }) {
    return (
        <div className="border border-dashed border-line-strong px-6 py-12 text-center">
            <p className="font-display text-lg font-medium text-fg">{title}</p>
            <p className="mt-1 text-[13px] text-fg-3">{body}</p>
        </div>
    );
}

function TableTile({ table: t, currency, onClick }) {
    const tk = t.ticket;
    const state = tk ? (tk.paid ? 'paid' : tk.status === 'ready' ? 'ready' : 'seated') : t.reservation ? 'booked' : 'free';
    const styles = {
        free: 'border-line bg-surface hover:border-brand',
        seated: 'border-brand bg-brand-soft',
        ready: 'border-coral bg-coral-soft',
        paid: 'border-ok bg-ok-bg',
        booked: 'border-dashed border-coral bg-surface',
    };
    const late = tk && tk.minutes >= 90;

    return (
        <button onClick={onClick} className={cx('focus-ring flex aspect-[4/3] flex-col border p-3 text-left transition-colors', styles[state])}>
            <span className="flex items-baseline justify-between">
                <span className="font-display text-[22px] font-medium leading-none text-fg">{t.label}</span>
                <span className="text-[11px] text-fg-3">{t.seats} seats</span>
            </span>
            <span className="mt-auto text-[12px] leading-snug">
                {state === 'free' && <span className="text-fg-3">Free · tap to seat</span>}
                {state === 'booked' && <span className="text-coral-deep">{t.reservation.time} · {t.reservation.name} ({t.reservation.party})</span>}
                {tk && (
                    <>
                        <span className="block font-medium tabular-nums text-fg">{money(tk.total, currency)}</span>
                        <span className={cx('block', late ? 'font-medium text-bad' : 'text-fg-3')}>
                            {state === 'ready' ? 'Food ready' : state === 'paid' ? 'Paid · close table' : label(tk.status)} · {tk.minutes} min
                        </span>
                    </>
                )}
            </span>
        </button>
    );
}

function KitchenRail({ tickets, post }) {
    return (
        <section className="border border-line bg-surface">
            <h2 className="flex items-center justify-between border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">
                Kitchen <span className="pill bg-soft text-fg-2">{tickets.length}</span>
            </h2>
            {tickets.length === 0 ? (
                <p className="px-5 py-8 text-center text-[13px] text-fg-3">Nothing cooking. Tickets appear here as they're sent.</p>
            ) : (
                <ul className="max-h-[560px] divide-y divide-line overflow-y-auto">
                    {tickets.map((k) => (
                        <li key={k.reference} className="px-5 py-3">
                            <div className="flex items-center gap-2">
                                <span className="text-[14px] font-semibold text-fg">{k.where}</span>
                                <span className={cx('pill', k.minutes >= 25 ? 'bg-bad-bg text-bad' : k.minutes >= 15 ? 'bg-warn-bg text-warn' : 'bg-soft text-fg-3')}>{k.minutes} min</span>
                                <button className={cx('btn-sm ml-auto', k.status === 'accepted' ? 'btn-ghost' : 'btn-primary')} onClick={() => post(k.bump)}>
                                    {k.status === 'accepted' ? 'Start' : 'Ready'}
                                </button>
                            </div>
                            <ul className="mt-2 space-y-1 text-[13px]">
                                {k.items.map((i, n) => (
                                    <li key={n} className="text-fg-2">
                                        <strong className="font-semibold text-fg">{i.qty}×</strong> {i.name}
                                        {i.mods && <span className="block pl-5 text-xs text-fg-3">{i.mods}</span>}
                                        {i.notes && <span className="block pl-5 text-xs text-coral-deep">“{i.notes}”</span>}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function Book({ book, can, post }) {
    return (
        <section className="border border-line bg-surface">
            <h2 className="flex items-center justify-between border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">
                Today's book <span className="pill bg-soft text-fg-2">{book.length}</span>
            </h2>
            {book.length === 0 ? (
                <p className="px-5 py-8 text-center text-[13px] text-fg-3">No reservations today.</p>
            ) : (
                <ul className="divide-y divide-line">
                    {book.map((r) => (
                        <li key={r.reference} className="flex items-center gap-3 px-5 py-3">
                            <span className="w-16 text-[13px] font-semibold tabular-nums text-fg">{r.time}</span>
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-[14px] text-fg">{r.name} · {r.party}</span>
                                {r.note && <span className="block truncate text-xs text-coral-deep">{r.note}</span>}
                            </span>
                            {r.status === 'seated' ? (
                                <span className="pill bg-ok-bg text-ok">Seated</span>
                            ) : can.book ? (
                                <span className="flex gap-1">
                                    <button className="btn-primary btn-sm" onClick={() => post(r.transition, { status: 'seated' })}>Seat</button>
                                    <button className="btn-ghost btn-sm" onClick={() => window.confirm(`Mark ${r.name} as a no-show?`) && post(r.transition, { status: 'no_show' })}>No-show</button>
                                </span>
                            ) : (
                                <span className="pill bg-soft text-fg-3">{label(r.status)}</span>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

// ---------------------------------------------------------------------------

/** Category tabs + item buttons; items with modifiers open a picker first. */
function MenuPicker({ menu, currency, onAdd }) {
    const [cat, setCat] = useState(0);
    const [picking, setPicking] = useState(null); // { item, chosen: {groupId: [optionIds]}, qty, notes }
    const items = menu[cat]?.items ?? [];

    const start = (item) => (item.groups.length ? setPicking({ item, chosen: {}, qty: 1, notes: '' }) : onAdd({ item, options: [], qty: 1, notes: '' }));
    const valid = picking && picking.item.groups.every((g) => (picking.chosen[g.id]?.length ?? 0) >= g.min);
    const toggle = (g, optionId) => setPicking((p) => {
        const cur = p.chosen[g.id] ?? [];
        const next = cur.includes(optionId) ? cur.filter((x) => x !== optionId) : g.max === 1 ? [optionId] : g.max && cur.length >= g.max ? cur : [...cur, optionId];
        return { ...p, chosen: { ...p.chosen, [g.id]: next } };
    });

    if (picking) {
        const extra = picking.item.groups.flatMap((g) => g.options.filter((o) => (picking.chosen[g.id] ?? []).includes(o.id))).reduce((s, o) => s + o.price, 0);
        return (
            <div className="border border-line p-4">
                <div className="flex items-center justify-between">
                    <p className="text-[15px] font-semibold text-fg">{picking.item.name}</p>
                    <button className="btn-ghost btn-sm" onClick={() => setPicking(null)}>Back</button>
                </div>
                {picking.item.groups.map((g) => (
                    <fieldset key={g.id} className="mt-4">
                        <legend className="label">{g.name} {g.min > 0 ? `· pick ${g.min}${g.max && g.max !== g.min ? `–${g.max}` : ''}` : '· optional'}</legend>
                        <div className="flex flex-wrap gap-2">
                            {g.options.map((o) => {
                                const on = (picking.chosen[g.id] ?? []).includes(o.id);
                                return (
                                    <button key={o.id} type="button" onClick={() => toggle(g, o.id)} className={cx('btn btn-sm', on ? 'border-brand bg-brand text-white' : 'border-line bg-surface text-fg')}>
                                        {o.name}{o.price ? ` +${money(o.price, currency)}` : ''}
                                    </button>
                                );
                            })}
                        </div>
                    </fieldset>
                ))}
                <div className="mt-4 grid grid-cols-[90px_1fr] gap-3">
                    <label><span className="label">Qty</span><input className="field" type="number" min="1" max="50" value={picking.qty} onChange={(e) => setPicking({ ...picking, qty: Number(e.target.value) || 1 })} /></label>
                    <label><span className="label">Note for the kitchen</span><input className="field" maxLength={255} value={picking.notes} onChange={(e) => setPicking({ ...picking, notes: e.target.value })} placeholder="e.g. no chilli" /></label>
                </div>
                <button className="btn-primary mt-4 w-full" disabled={!valid} onClick={() => { onAdd({ item: picking.item, options: Object.values(picking.chosen).flat(), qty: picking.qty, notes: picking.notes, extra }); setPicking(null); }}>
                    Add · {money((picking.item.price + extra) * picking.qty, currency)}
                </button>
            </div>
        );
    }

    return (
        <div>
            <div className="flex gap-1 overflow-x-auto border-b border-line" role="tablist">
                {menu.map((c, i) => (
                    <button key={c.name} role="tab" aria-selected={cat === i} onClick={() => setCat(i)}
                        className={cx('relative h-10 shrink-0 px-3 text-[13px]', cat === i ? 'font-medium text-fg' : 'text-fg-3 hover:text-fg')}>
                        {c.name}
                        {cat === i && <span className="absolute inset-x-0 bottom-[-1px] h-[2px] bg-brand" />}
                    </button>
                ))}
            </div>
            <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                {items.map((item) => (
                    <button key={item.id} onClick={() => start(item)} className="focus-ring flex min-h-[72px] flex-col justify-between border border-line bg-surface p-3 text-left transition-colors hover:border-brand active:scale-[0.98]">
                        <span className="text-[13px] font-medium leading-snug text-fg">{item.name}</span>
                        <span className="mt-1 text-xs tabular-nums text-fg-3">{money(item.price, currency)}{item.groups.length ? ' · options' : ''}</span>
                    </button>
                ))}
            </div>
            {menu.length === 0 && <p className="py-6 text-center text-[13px] text-fg-3">No menu items are available.</p>}
        </div>
    );
}

function NewTicket({ draft, setDraft, menu, currency, urls, onClose }) {
    const [busy, setBusy] = useState(false);
    const total = draft.lines.reduce((s, l) => s + (l.item.price + (l.extra ?? 0)) * l.qty, 0);
    const add = (line) => setDraft({ ...draft, lines: [...draft.lines, line] });
    const change = (i, qty) => setDraft({ ...draft, lines: qty < 1 ? draft.lines.filter((_, n) => n !== i) : draft.lines.map((l, n) => (n === i ? { ...l, qty } : l)) });

    const send = () => router.post(urls.open, {
        restaurant_table_id: draft.tableId,
        lines: draft.lines.map((l) => ({ item_id: l.item.id, options: l.options, quantity: l.qty, notes: l.notes || null })),
    }, { onStart: () => setBusy(true), onFinish: () => setBusy(false), onSuccess: () => setDraft(null) });

    return (
        <>
            <DrawerHead title={draft.where} sub="New order" onClose={onClose} />
            <div className="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                <MenuPicker menu={menu} currency={currency} onAdd={add} />
                {draft.lines.length > 0 && (
                    <ul className="divide-y divide-line border border-line">
                        {draft.lines.map((l, i) => (
                            <li key={i} className="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                                <span className="flex items-center">
                                    <button className="h-7 w-7 border border-line" onClick={() => change(i, l.qty - 1)} aria-label="Less">−</button>
                                    <span className="w-8 text-center tabular-nums">{l.qty}</span>
                                    <button className="h-7 w-7 border border-line" onClick={() => change(i, l.qty + 1)} aria-label="More">+</button>
                                </span>
                                <span className="min-w-0 flex-1 truncate text-fg">{l.item.name}{l.notes ? <span className="text-coral-deep"> · {l.notes}</span> : null}</span>
                                <span className="tabular-nums">{money((l.item.price + (l.extra ?? 0)) * l.qty, currency)}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <div className="border-t border-line px-6 py-4">
                <button className="btn-primary w-full" disabled={!draft.lines.length || busy} onClick={send}>
                    Send to kitchen{draft.lines.length ? ` · ${money(total, currency)}` : ''}
                </button>
            </div>
        </>
    );
}

function TicketPanel({ ticket: t, menu, currency, session, can, methods, post, onClose }) {
    const [adding, setAdding] = useState(false);
    const [pay, setPay] = useState({ method: 'cash', amount: '' });

    const payNow = (e) => {
        e.preventDefault();
        const amount = Number(pay.amount) || t.due;
        post(t.urls.pay, pay.method === 'cash' ? { method: 'cash', tendered: amount } : { method: pay.method, amount }, { onSuccess: () => setPay({ method: 'cash', amount: '' }) });
    };

    return (
        <>
            <DrawerHead title={t.where} sub={<span className="font-mono">{t.reference} · {label(t.status)}</span>} onClose={onClose} />
            <div className="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                <ul className="divide-y divide-line border border-line text-[13px]">
                    {t.items.map((i) => (
                        <li key={i.id} className="flex gap-3 px-4 py-2.5">
                            <span className="w-7 font-semibold tabular-nums">{i.qty}×</span>
                            <span className="min-w-0 flex-1">
                                <span className="text-fg">{i.name}</span>
                                {i.mods && <span className="block text-xs text-fg-3">{i.mods}</span>}
                                {i.notes && <span className="block text-xs text-coral-deep">“{i.notes}”</span>}
                            </span>
                            <span className="tabular-nums">{money(i.total, currency)}</span>
                        </li>
                    ))}
                </ul>

                <dl className="space-y-1 text-[13px]">
                    <Row term="Subtotal" value={money(t.subtotal, currency)} />
                    {t.discount > 0 && <Row term="Discount" value={`−${money(t.discount, currency)}`} />}
                    <Row term="VAT (included)" value={money(t.tax, currency)} muted />
                    <Row term="Total" value={money(t.total, currency)} strong />
                    {t.payments.map((p, n) => <Row key={n} term={`Paid · ${label(p.method)}${p.change ? ` (change ${money(p.change, currency)})` : ''}`} value={`−${money(p.amount, currency)}`} muted />)}
                    <Row term="Balance due" value={money(t.due, currency)} strong />
                </dl>

                {!t.paid && (
                    adding ? (
                        <div className="space-y-2">
                            <MenuPicker menu={menu} currency={currency} onAdd={(l) => post(t.urls.lines, { item_id: l.item.id, options: l.options, quantity: l.qty, notes: l.notes || null })} />
                            <button className="btn-ghost btn-sm" onClick={() => setAdding(false)}>Done adding</button>
                        </div>
                    ) : (
                        <button className="btn-ghost w-full" onClick={() => setAdding(true)}>+ Add items</button>
                    )
                )}

                {!t.paid && t.due > 0 && (
                    session ? (
                        <form onSubmit={payNow} className="grid grid-cols-2 gap-3 border border-line bg-canvas p-4 sm:grid-cols-[130px_1fr_auto]">
                            <label><span className="label">Method</span>
                                <select className="field" value={pay.method} onChange={(e) => setPay({ ...pay, method: e.target.value })}>
                                    {methods.map((m) => <option key={m} value={m}>{label(m)}</option>)}
                                </select>
                            </label>
                            <label><span className="label">{pay.method === 'cash' ? 'Cash given' : 'Amount'}</span>
                                <input className="field tabular-nums" type="number" step="0.01" min="0.01" placeholder={t.due.toFixed(2)} value={pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} />
                            </label>
                            <div className="col-span-2 flex items-end sm:col-span-1"><button className="btn-primary w-full">Take payment</button></div>
                        </form>
                    ) : (
                        <p className="border border-dashed border-coral bg-coral-soft px-4 py-3 text-[13px] text-coral-deep">Open the register to take payments.</p>
                    )
                )}

                {session && t.stays.length > 0 && (
                    <form
                        className="flex flex-wrap items-end gap-3 border border-line p-4"
                        onSubmit={(e) => { e.preventDefault(); post(t.urls.room, { room_stay: e.currentTarget.room_stay.value }); }}
                    >
                        <label className="min-w-48 flex-1"><span className="label">Charge to room</span>
                            <select name="room_stay" className="field">{t.stays.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}</select>
                        </label>
                        <button className="btn-ghost">Post to folio</button>
                    </form>
                )}

                {session && can.refund && t.refundable && (
                    <form
                        className="flex flex-wrap items-end gap-3 border border-line p-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            const f = e.currentTarget;
                            if (window.confirm(`Refund ${money(t.total, currency)}?`)) post(t.urls.refund, { method: f.method.value, reason: f.reason.value });
                        }}
                    >
                        <label><span className="label">Refund via</span>
                            <select name="method" className="field">{t.refund_methods.map((m) => <option key={m} value={m}>{label(m)}</option>)}</select>
                        </label>
                        <label className="min-w-40 flex-1"><span className="label">Reason</span><input name="reason" required className="field" /></label>
                        <button className="btn-danger">Refund</button>
                    </form>
                )}
            </div>
            <div className="flex flex-wrap gap-2 border-t border-line px-6 py-3">
                {t.paid && <button className="btn-primary" onClick={() => post(t.urls.close)}>Close ticket & free table</button>}
                {can.discount && !t.paid && (
                    <button className="btn-ghost btn-sm" onClick={() => { const v = window.prompt('Discount in % (e.g. 10)'); if (v) post(t.urls.discount, { type: 'percent', value: v, reason: 'Floor discount' }); }}>Discount</button>
                )}
                <a className="btn-ghost btn-sm" href={t.urls.receipt} target="_blank" rel="noreferrer">Receipt</a>
                {t.voidable && (
                    <button className="btn-danger btn-sm ml-auto" onClick={() => window.confirm('Void this ticket?') && post(t.urls.cancel)}>Void ticket</button>
                )}
            </div>
        </>
    );
}

const DrawerHead = ({ title, sub, onClose }) => (
    <div className="flex items-start gap-3 border-b border-line px-6 py-5">
        <div className="min-w-0 flex-1">
            <h2 className="truncate font-display text-[22px] font-medium tracking-tight text-fg">{title}</h2>
            <p className="mt-0.5 text-xs text-fg-3">{sub}</p>
        </div>
        <button onClick={onClose} className="btn-ghost btn-sm">Close</button>
    </div>
);

const Row = ({ term, value, strong, muted }) => (
    <div className={cx('flex justify-between', strong && 'pt-1 text-[14px] font-semibold text-fg', muted && 'text-fg-3')}>
        <dt>{term}</dt><dd className="tabular-nums">{value}</dd>
    </div>
);
