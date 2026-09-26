import { useEffect, useRef, useState } from 'react';
import { BTN, FIELD, LABEL, Stepper, cx, json, money } from './ui';

/**
 * Restaurant menu with ordering: category chips, an item sheet that
 * enforces the modifier rules, and a cart bar + drawer that edits the
 * session cart over JSON. Read-only menu when the restaurant is not
 * taking online orders.
 *
 * props: { ordering, slug, currency, addUrl, summaryUrl, cartBase, checkoutUrl,
 *          categories: [{id, name, items: [{id, name, description, price, photo, available,
 *                        groups: [{id, name, min, max, rule, options: [{id, name, price}]}]}]}] }
 */
export default function MenuOrder({ ordering, slug, currency = 'PHP', addUrl, summaryUrl, cartBase, checkoutUrl, categories }) {
    const [cart, setCart] = useState(null);
    const [item, setItem] = useState(null);
    const [drawer, setDrawer] = useState(false);
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        if (ordering) json(summaryUrl).then(setCart).catch(() => {});
    }, [ordering, summaryUrl]);

    const added = (summary, name) => {
        setCart(summary);
        setItem(null);
        setNotice(summary.replaced ? 'Your cart from another restaurant was replaced. Carts hold one restaurant at a time.' : `${name} added to your cart.`);
    };

    const setQty = async (key, qty) => {
        try {
            setCart(await json(`${cartBase}/${key}`, { method: 'PATCH', body: { quantity: qty } }));
        } catch (e) {
            setNotice(e.message);
        }
    };

    const here = cart?.restaurant?.slug === slug;

    return (
        <div className="text-fg">
            <div className="flex items-baseline justify-between gap-4">
                <h2 className="font-display text-[22px] font-medium tracking-tight">Menu</h2>
                {!ordering && <span className="text-[13px] text-fg-3">Not taking online orders right now</span>}
            </div>

            {categories.length > 1 && (
                <nav aria-label="Menu sections" className="sticky top-0 z-10 -mx-1 mt-3 flex gap-2 overflow-x-auto bg-canvas/95 px-1 py-2 backdrop-blur">
                    {categories.map((c) => (
                        <a key={c.id} href={`#menu-${c.id}`} className="shrink-0 border border-line bg-surface px-3 py-1.5 text-[13px] font-medium text-fg-2 hover:border-brand hover:text-fg">
                            {c.name}
                        </a>
                    ))}
                </nav>
            )}

            <p aria-live="polite" className={cx('text-[13px]', notice ? 'mt-3 border-l-2 border-brand bg-brand-soft px-3 py-2 text-fg-2' : 'sr-only')}>{notice}</p>

            {categories.map((c) => (
                <section key={c.id} id={`menu-${c.id}`} className="scroll-mt-16 pt-6">
                    {c.banner ? (
                        <div className="relative mb-2 h-32 overflow-hidden sm:h-40">
                            <img src={c.banner} alt="" loading="lazy" className="h-full w-full object-cover" />
                            <h3 className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-4 pb-3 pt-8 text-[18px] font-semibold text-white">{c.name}</h3>
                        </div>
                    ) : <h3 className="mb-2 text-[16px] font-semibold">{c.name}</h3>}
                    <ul className="divide-y divide-line border-y border-line">
                        {c.items.map((i) => (
                            <li key={i.id} className={cx('flex gap-4 py-4', !i.available && 'opacity-55')}>
                                <div className="min-w-0 flex-1">
                                    <p className="text-[15px] font-medium">{i.name}</p>
                                    {i.description && <p className="mt-0.5 line-clamp-2 text-[13px] text-fg-3">{i.description}</p>}
                                    <p className="mt-1.5 text-[14px] tabular-nums text-fg-2">{i.available ? money(i.price, currency, 2) : 'Sold out'}</p>
                                    {!ordering && i.groups.map((g) => (
                                        <p key={g.id} className="mt-1 text-[12px] text-fg-3">{g.name}: {g.options.map((o) => o.name + (o.price > 0 ? ` +${money(o.price, currency)}` : '')).join(', ')}</p>
                                    ))}
                                </div>
                                {i.photo && <img src={i.photo} alt="" loading="lazy" className="h-20 w-20 shrink-0 object-cover sm:h-24 sm:w-24" />}
                                {ordering && i.available && (
                                    <button type="button" onClick={() => setItem(i)} className="h-10 shrink-0 self-center border border-line-strong bg-surface px-4 text-[14px] font-medium hover:border-brand hover:text-brand" aria-label={`Add ${i.name}`}>
                                        Add
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            ))}

            {item && <ItemSheet item={item} currency={currency} addUrl={addUrl} onClose={() => setItem(null)} onAdded={added} />}

            {cart?.count > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 px-4 py-3 backdrop-blur sm:px-10">
                    <div className="mx-auto flex max-w-3xl items-center gap-3">
                        <button type="button" onClick={() => setDrawer(true)} className="min-w-0 flex-1 text-left">
                            <span className="block text-[14px] font-medium">{cart.count} {cart.count === 1 ? 'item' : 'items'} · {money(cart.subtotal, currency, 2)}</span>
                            <span className="block truncate text-[12px] text-fg-3">{here ? 'View your order' : `Cart from ${cart.restaurant.name}`}</span>
                        </button>
                        <a href={checkoutUrl} className={cx(BTN, 'w-auto')}>Checkout</a>
                    </div>
                </div>
            )}

            {drawer && cart && <CartDrawer cart={cart} currency={currency} checkoutUrl={checkoutUrl} onQty={setQty} onClose={() => setDrawer(false)} />}
        </div>
    );
}

/** Native <dialog>: focus trap, Esc to close and a backdrop for free. */
function Sheet({ title, onClose, children }) {
    const ref = useRef(null);
    useEffect(() => { ref.current?.showModal(); }, []);
    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => e.target === ref.current && onClose()}
            aria-label={title}
            className="m-0 mt-auto max-h-[90dvh] w-full max-w-none bg-surface p-0 text-fg backdrop:bg-black/40 sm:m-auto sm:max-w-lg"
        >
            <div className="flex items-center justify-between border-b border-line px-5 py-4">
                <h2 className="text-[17px] font-semibold">{title}</h2>
                <button type="button" onClick={onClose} className="h-9 w-9 text-xl text-fg-3 hover:text-fg" aria-label="Close">×</button>
            </div>
            {children}
        </dialog>
    );
}

function ItemSheet({ item, currency, addUrl, onClose, onAdded }) {
    const groups = item.groups.filter((g) => g.options.length);
    const [chosen, setChosen] = useState({}); // groupId → [optionId]
    const [qty, setQty] = useState(1);
    const [notes, setNotes] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    const picked = (g) => chosen[g.id] ?? [];
    const toggle = (g, id) => setChosen((c) => {
        const cur = c[g.id] ?? [];
        if (g.max === 1) return { ...c, [g.id]: [id] };
        if (cur.includes(id)) return { ...c, [g.id]: cur.filter((x) => x !== id) };
        return g.max && cur.length >= g.max ? c : { ...c, [g.id]: [...cur, id] };
    });
    const missing = groups.find((g) => picked(g).length < g.min);
    const optionIds = groups.flatMap(picked);
    const unit = item.price + groups.flatMap((g) => g.options.filter((o) => picked(g).includes(o.id))).reduce((s, o) => s + o.price, 0);

    const add = async () => {
        setBusy(true);
        setError(null);
        try {
            onAdded(await json(addUrl, { method: 'POST', body: { item_id: item.id, options: optionIds, quantity: qty, notes } }), item.name);
        } catch (e) {
            setError(e.message);
            setBusy(false);
        }
    };

    return (
        <Sheet title={item.name} onClose={onClose}>
            <div className="max-h-[60dvh] space-y-5 overflow-y-auto px-5 py-4">
                {item.photo && <img src={item.photo} alt="" className="aspect-[16/9] w-full object-cover" />}
                {item.description && <p className="text-[14px] text-fg-2">{item.description}</p>}
                {groups.map((g) => (
                    <fieldset key={g.id}>
                        <legend className="mb-2 flex w-full items-baseline justify-between gap-3">
                            <span className="text-[15px] font-semibold">{g.name}</span>
                            <span className={cx('text-[12px]', g.min > 0 && picked(g).length < g.min ? 'font-medium text-coral-deep' : 'text-fg-3')}>{g.rule}</span>
                        </legend>
                        <div className="divide-y divide-line border border-line">
                            {g.options.map((o) => {
                                const on = picked(g).includes(o.id);
                                const full = !on && g.max > 1 && picked(g).length >= g.max;
                                return (
                                    <label key={o.id} className={cx('flex min-h-[48px] cursor-pointer items-center gap-3 px-3', full && 'cursor-not-allowed opacity-50')}>
                                        <input type={g.max === 1 ? 'radio' : 'checkbox'} name={`g${g.id}`} className="h-4 w-4 accent-[var(--primary)] text-brand" checked={on} disabled={full} onChange={() => toggle(g, o.id)} />
                                        <span className="flex-1 text-[14px]">{o.name}</span>
                                        {o.price > 0 && <span className="text-[13px] tabular-nums text-fg-3">+{money(o.price, currency, 2)}</span>}
                                    </label>
                                );
                            })}
                        </div>
                    </fieldset>
                ))}
                <label className="block">
                    <span className={LABEL}>Notes for the kitchen <span className="font-normal text-fg-3">(optional)</span></span>
                    <input className={FIELD} maxLength={255} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="No onions, extra spicy" />
                </label>
                {error && <p role="alert" className="text-[13px] text-bad">{error}</p>}
            </div>
            <div className="flex items-center gap-3 border-t border-line px-5 py-4">
                <div className="w-36 shrink-0"><Stepper value={qty} min={1} max={50} onChange={setQty} label="quantity" /></div>
                <button type="button" className={BTN} disabled={busy || !!missing} onClick={add}>
                    {missing ? `Choose ${missing.name.toLowerCase()}` : `Add · ${money(unit * qty, currency, 2)}`}
                </button>
            </div>
        </Sheet>
    );
}

function CartDrawer({ cart, currency, checkoutUrl, onQty, onClose }) {
    return (
        <Sheet title={cart.restaurant ? `Your order from ${cart.restaurant.name}` : 'Your order'} onClose={onClose}>
            <ul className="max-h-[55dvh] divide-y divide-line overflow-y-auto px-5">
                {cart.lines.map((l) => (
                    <li key={l.key} className="flex items-center gap-3 py-3">
                        <div className="min-w-0 flex-1">
                            <p className="text-[14px] font-medium">{l.name}</p>
                            {l.mods && <p className="text-[12px] text-fg-3">{l.mods}</p>}
                            {l.notes && <p className="text-[12px] text-fg-3">“{l.notes}”</p>}
                        </div>
                        <div className="w-32 shrink-0"><Stepper value={l.qty} min={0} max={50} onChange={(q) => onQty(l.key, q)} label={`${l.name} quantity`} /></div>
                        <span className="w-20 shrink-0 text-right text-[14px] tabular-nums">{money(l.total, currency, 2)}</span>
                    </li>
                ))}
                {cart.lines.length === 0 && <li className="py-6 text-center text-[14px] text-fg-3">Your cart is empty.</li>}
            </ul>
            {cart.error && <p role="alert" className="px-5 text-[13px] text-bad">{cart.error}</p>}
            <div className="flex items-center gap-4 border-t border-line px-5 py-4">
                <p className="flex-1 text-[15px] font-semibold tabular-nums">Subtotal {money(cart.subtotal, currency, 2)}</p>
                {cart.count > 0 && <a href={checkoutUrl} className={cx(BTN, 'w-auto')}>Checkout</a>}
            </div>
        </Sheet>
    );
}
