import { Head, Link, router } from '@inertiajs/react';
import { cx, label } from './ui';

/**
 * Building blocks for the everyday dashboard screens (lists, detail pages,
 * forms). Forms use Inertia's useForm and post to the existing Laravel
 * routes; field helpers take that form object and a field name.
 */

export function Page({ title, eyebrow, subtitle, actions, back, children }) {
    return (
        <div className="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
            <Head title={title} />
            <header className="flex flex-wrap items-end gap-x-6 gap-y-4">
                <div className="min-w-0 basis-full md:basis-0 md:flex-1">
                    {back && <Link href={back.href} className="mb-2 inline-block text-[12px] text-fg-3 hover:text-fg">← {back.label}</Link>}
                    {eyebrow && <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">{eyebrow}</p>}
                    <h1 className="mt-1 font-display text-[28px] font-medium tracking-tight text-fg">{title}</h1>
                    {subtitle && <p className="mt-1 text-[13px] text-fg-3">{subtitle}</p>}
                </div>
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </header>
            <div className="mt-6 space-y-8">{children}</div>
        </div>
    );
}

export function Panel({ title, aside, children, className, pad }) {
    return (
        <section className={cx('min-w-0 border border-line bg-surface', className)}>
            {title && (
                <h2 className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">
                    {title}
                    {aside && <span className="font-normal normal-case tracking-normal">{aside}</span>}
                </h2>
            )}
            {pad ? <div className="space-y-4 p-5">{children}</div> : children}
        </section>
    );
}

/** Sub-navigation between the screens of one record: [{ label, href, active }]. */
export function Tabs({ tabs }) {
    if (!tabs?.length) return null;
    return (
        <nav className="-mt-2 flex gap-1 overflow-x-auto border-b border-line" aria-label="Sections">
            {tabs.map((t) => (
                <Link
                    key={t.href}
                    href={t.href}
                    aria-current={t.active ? 'page' : undefined}
                    className={cx('-mb-px whitespace-nowrap border-b-2 px-3 py-2.5 text-[13px]', t.active ? 'border-brand font-medium text-fg' : 'border-transparent text-fg-3 hover:text-fg')}
                >
                    {t.label}
                </Link>
            ))}
        </nav>
    );
}

/** Two columns on wide screens: main content + a narrower side column. */
export const Split = ({ children, side = '360px' }) => (
    <div className="grid items-start gap-8 xl:grid-cols-[minmax(0,1fr)_var(--side)]" style={{ '--side': side }}>{children}</div>
);

/** Side column: stacked panels. */
export const Stack = ({ children }) => <div className="min-w-0 space-y-8">{children}</div>;

/** head: labels, or { label, right } for numeric columns. Empty rows show `empty`. */
export function Table({ head, rows, empty = 'Nothing here yet.', children }) {
    const body = rows ?? children;
    const has = Array.isArray(body) ? body.length > 0 : !!body;
    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[680px] text-[13px]">
                <thead className="text-left text-[11px] uppercase tracking-[0.08em] text-fg-3">
                    <tr className="border-b border-line">
                        {head.map((h, i) => (
                            <th key={i} className={cx('whitespace-nowrap px-5 py-2.5 font-medium', h?.right && 'text-right')}>{h?.label ?? h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-line">
                    {has ? body : (
                        <tr><td colSpan={head.length} className="px-5 py-10 text-center text-fg-3">{empty}</td></tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

export const Td = ({ children, right, muted, className, ...rest }) => (
    <td className={cx('px-5 py-3 align-top', right && 'whitespace-nowrap text-right tabular-nums', muted && 'text-fg-2', className)} {...rest}>{children}</td>
);

/** Primary cell: linked title + muted second line. */
export const Main = ({ href, title, sub }) => (
    <>
        {href ? <Link href={href} className="font-medium text-fg hover:text-brand">{title}</Link> : <span className="font-medium text-fg">{title}</span>}
        {sub && <span className="mt-0.5 block text-[12px] text-fg-3">{sub}</span>}
    </>
);

/** Laravel paginator (`->paginate()` serialised: data, links, last_page). */
export function Pager({ page }) {
    if (!page?.links || page.last_page <= 1) return null;
    return (
        <nav className="flex flex-wrap items-center gap-1 border-t border-line px-5 py-3" aria-label="Pages">
            {page.links.map((l, i) => (
                <Link
                    key={i}
                    href={l.url ?? '#'}
                    preserveScroll
                    className={cx('h-8 min-w-8 border px-2 text-center text-[12px] leading-8', l.active ? 'border-brand bg-brand text-white' : 'border-line text-fg-2 hover:border-line-strong', !l.url && 'pointer-events-none opacity-40')}
                    dangerouslySetInnerHTML={{ __html: l.label }}
                />
            ))}
        </nav>
    );
}

const TONES = {
    ok: 'bg-ok-bg text-ok',
    warn: 'bg-warn-bg text-warn',
    bad: 'bg-bad-bg text-bad',
    info: 'bg-info-bg text-info',
    brand: 'bg-brand-soft text-brand-deep',
    muted: 'bg-soft text-fg-3',
};

export const Badge = ({ tone = 'muted', children }) => <span className={cx('pill whitespace-nowrap', TONES[tone])}>{children}</span>;

/** Common status words → tone, so pages just pass the status. */
const STATUS_TONE = {
    active: 'ok', published: 'ok', paid: 'ok', completed: 'ok', approved: 'ok', resolved: 'ok', sent: 'ok', posted: 'ok', delivered: 'ok', checked_in: 'ok', succeeded: 'ok', clean: 'ok', inspected: 'ok',
    confirmed: 'info', open: 'info', in_progress: 'info', scheduled: 'info', accepted: 'info', preparing: 'info', ready: 'info', issued: 'info', processing: 'info',
    pending: 'warn', held: 'warn', on_hold: 'warn', low: 'warn', high: 'warn', partial: 'warn', partially_paid: 'warn', dirty: 'warn', submitted: 'warn', requested: 'warn',
    trial: 'brand', trialing: 'brand', draft: 'muted',
    cancelled: 'bad', failed: 'bad', rejected: 'bad', overdue: 'bad', suspended: 'bad', expired: 'bad', voided: 'bad', void: 'bad', urgent: 'bad', no_show: 'bad', out_of_stock: 'bad', declined: 'bad', past_due: 'bad',
    closed: 'muted', archived: 'muted', inactive: 'muted', hidden: 'muted', refunded: 'muted', checked_out: 'muted', normal: 'muted', unpublished: 'muted',
};
export const Status = ({ value, children }) => <Badge tone={STATUS_TONE[value] ?? 'muted'}>{children ?? label(value)}</Badge>;

/** items: [term, value, note?] */
export function Stats({ items }) {
    return (
        <dl className={cx('grid grid-cols-2 border-l border-t border-line bg-surface', items.length > 4 ? 'md:grid-cols-3 xl:grid-cols-6' : 'md:grid-cols-4')}>
            {items.map(([term, value, note]) => (
                <div key={term} className="border-b border-r border-line px-5 py-4">
                    <dt className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">{term}</dt>
                    <dd className="mt-1.5 font-display text-[24px] font-medium leading-none tabular-nums text-fg">{value}</dd>
                    {note && <dd className="mt-1.5 text-[12px] text-fg-3">{note}</dd>}
                </div>
            ))}
        </dl>
    );
}

export const Empty = ({ title, body, children }) => (
    <div className="border border-dashed border-line-strong bg-surface px-6 py-14 text-center">
        <p className="font-display text-[18px] text-fg">{title}</p>
        {body && <p className="mx-auto mt-1.5 max-w-md text-[13px] text-fg-3">{body}</p>}
        {children && <div className="mt-5 flex justify-center gap-2">{children}</div>}
    </div>
);

/** Label/value rows for detail side panels: [[label, value], …] (falsy rows skipped). */
export const Facts = ({ rows }) => (
    <dl className="divide-y divide-line text-[13px]">
        {rows.filter(Boolean).map(([k, v]) => (
            <div key={k} className="flex justify-between gap-4 px-5 py-2.5">
                <dt className="shrink-0 text-fg-3">{k}</dt>
                <dd className="min-w-0 text-right text-fg">{v ?? '—'}</dd>
            </div>
        ))}
    </dl>
);

// ---- Forms --------------------------------------------------------------

export function Field({ label: text, error, hint, children, className }) {
    return (
        <label className={cx('block', className)}>
            {text && <span className="label">{text}</span>}
            {children}
            {hint && !error && <span className="mt-1 block text-[12px] text-fg-3">{hint}</span>}
            {error && <span className="mt-1 block text-[12px] text-bad">{error}</span>}
        </label>
    );
}

const opts = (options) => options.map((o) => (Array.isArray(o) ? o : [o, label(o)]));

export function Input({ form, name, label: text, hint, type = 'text', className, ...rest }) {
    const set = (v) => form.setData(name, v);
    if (type === 'checkbox') {
        return (
            <label className={cx('flex items-center gap-2 text-[13px] text-fg', className)}>
                <input type="checkbox" className="h-4 w-4 accent-[var(--primary)] text-brand" checked={!!form.data[name]} onChange={(e) => set(e.target.checked)} {...rest} />
                {text}
            </label>
        );
    }
    return (
        <Field label={text} error={form.errors[name]} hint={hint} className={className}>
            {type === 'file' ? (
                <input type="file" className="field h-auto py-1.5" onChange={(e) => set(rest.multiple ? [...e.target.files] : e.target.files[0])} {...rest} />
            ) : (
                <input type={type} className="field" value={form.data[name] ?? ''} onChange={(e) => set(e.target.value)} {...rest} />
            )}
        </Field>
    );
}

export function TextArea({ form, name, label: text, hint, rows = 3, className, ...rest }) {
    return (
        <Field label={text} error={form.errors[name]} hint={hint} className={className}>
            <textarea rows={rows} className="field h-auto py-2" value={form.data[name] ?? ''} onChange={(e) => form.setData(name, e.target.value)} {...rest} />
        </Field>
    );
}

/** options: [[value, label], …] or plain strings (labelled with `label()`). */
export function Select({ form, name, label: text, options, placeholder, hint, className, ...rest }) {
    return (
        <Field label={text} error={form.errors[name]} hint={hint} className={className}>
            <select className="field" value={form.data[name] ?? ''} onChange={(e) => form.setData(name, e.target.value)} {...rest}>
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {opts(options).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
            </select>
        </Field>
    );
}

/** GET filter: a change re-queries `url` with the other `params` kept. */
export function Filter({ name, value, options, placeholder, url, params = {} }) {
    return (
        <select
            aria-label={placeholder ?? label(name)}
            className="field w-auto min-w-36"
            value={value ?? ''}
            onChange={(e) => router.get(url, { ...params, [name]: e.target.value || undefined }, { preserveState: true, preserveScroll: true })}
        >
            {placeholder !== undefined && <option value="">{placeholder}</option>}
            {opts(options).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
        </select>
    );
}

/** Search box for list pages (submits on Enter). */
export function Search({ url, value, params = {}, placeholder = 'Search' }) {
    return (
        <form onSubmit={(e) => { e.preventDefault(); router.get(url, { ...params, q: e.currentTarget.q.value || undefined }, { preserveState: true }); }}>
            <input name="q" type="search" defaultValue={value ?? ''} placeholder={placeholder} aria-label={placeholder} className="field w-56" />
        </form>
    );
}

/** One-click request button (POST by default) with an optional confirm prompt. */
export function Action({ href, data = {}, method = 'post', confirm, className = 'btn-ghost btn-sm', children, ...rest }) {
    return (
        <button
            type="button"
            className={className}
            onClick={() => (!confirm || window.confirm(confirm)) && router[method](href, data, { preserveScroll: true })}
            {...rest}
        >
            {children}
        </button>
    );
}

/** `onSubmit={submit(form, url)}`; `reset: true` clears the form after success. */
export const submit = (form, url, { method = 'post', reset, ...options } = {}) => (e) => {
    e.preventDefault();
    form.submit(method, url, { preserveScroll: true, onSuccess: () => reset && form.reset(), ...options });
};

/** Form wrapper with a submit button. */
export function Form({ form, url, method, reset, button = 'Save', className, children, danger }) {
    return (
        <form onSubmit={submit(form, url, { method, reset })} className={cx('space-y-4', className)}>
            {children}
            <button type="submit" className={danger ? 'btn-danger' : 'btn-primary'} disabled={form.processing}>{button}</button>
        </form>
    );
}

/** Responsive field row. */
export const Row = ({ children, cols = 2 }) => <div className={cx('grid gap-4', cols === 3 ? 'sm:grid-cols-3' : cols === 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-2')}>{children}</div>;

export const when = (date, o = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }) => (date ? new Date(date).toLocaleString('en-US', o) : '—');
export const day = (date) => when(date, { month: 'short', day: 'numeric', year: 'numeric' });
