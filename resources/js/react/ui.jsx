import { useEffect } from 'react';

/** Stroke icon from a path string (the sidebar paths come from DashboardNav). */
export function Icon({ d, className = 'h-[18px] w-[18px]' }) {
    return (
        <svg className={className} fill="none" stroke="currentColor" strokeWidth="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d={d} />
        </svg>
    );
}

export const cx = (...classes) => classes.filter(Boolean).join(' ');

/** The gold-rimmed logo on its teal plate (styles in css/motion.css). src = shared `app.logo` (an asset() URL, so it works under any base path). */
export const BrandMark = ({ src, size = 36 }) => (
    <span className="brand-plate" style={{ width: size, height: size }} aria-hidden="true">
        <img className="brand-logo" src={src} alt="" width={size} height={size} />
    </span>
);

// Money shows a symbol, never an ISO code: the business's own symbol, else the platform default (shared prop `moneySymbol`).
let symbol = '₱';
export const setMoneySymbol = (s) => { if (s) symbol = s; };

/** money(1250) → "₱1,250.00". The second argument (an ISO code from older call sites) is ignored on purpose. */
export function money(amount, _currency, digits = 2) {
    const n = Number(amount ?? 0);
    return `${n < 0 ? '-' : ''}${symbol}${Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: digits, maximumFractionDigits: digits })}`;
}

/** 'YYYY-MM-DD' → local Date (no UTC shift). */
export function parseDay(value) {
    const [y, m, d] = value.split('-').map(Number);
    return new Date(y, m - 1, d);
}

export function isoDay(date) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function addDays(value, days) {
    const date = parseDay(value);
    date.setDate(date.getDate() + days);
    return isoDay(date);
}

export const daysBetween = (a, b) => Math.round((parseDay(b) - parseDay(a)) / 86400000);

export const fmtDay = (value, options = { month: 'short', day: 'numeric' }) => parseDay(value).toLocaleDateString('en-US', options);

export const label = (value) => (value ?? '').replaceAll('_', ' ').replace(/^\w/, (c) => c.toUpperCase());

const STATUS = {
    pending: 'bg-warn-bg text-warn',
    held: 'bg-warn-bg text-warn',
    confirmed: 'bg-info-bg text-info',
    checked_in: 'bg-ok-bg text-ok',
    checked_out: 'bg-soft text-fg-3',
    completed: 'bg-soft text-fg-3',
    cancelled: 'bg-bad-bg text-bad',
    no_show: 'bg-bad-bg text-bad',
    refunded: 'bg-soft text-fg-3',
};

export function StatusPill({ status }) {
    return <span className={cx('pill', STATUS[status] ?? 'bg-soft text-fg-3')}>{label(status)}</span>;
}

/** Right-hand sheet. Esc and the backdrop close it. */
export function Drawer({ open, onClose, title, children, width = 'max-w-[520px]' }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    return (
        <div className={cx('fixed inset-0 z-40', !open && 'pointer-events-none')} aria-hidden={!open}>
            <div
                className={cx('absolute inset-0 transition-opacity duration-200', open ? 'opacity-100' : 'opacity-0')}
                style={{ backgroundColor: 'rgba(11,12,14,.38)' }}
                onClick={onClose}
            />
            <aside
                role="dialog"
                aria-modal="true"
                aria-label={title}
                className={cx(
                    'absolute inset-y-0 right-0 flex w-full flex-col border-l border-line bg-surface shadow-[0_0_48px_-12px_rgba(11,12,14,.35)] transition-transform duration-[240ms]',
                    width,
                    open ? 'translate-x-0' : 'translate-x-full',
                )}
                style={{ transitionTimingFunction: 'var(--ease-out)' }}
            >
                {children}
            </aside>
        </div>
    );
}
