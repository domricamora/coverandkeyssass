/**
 * Shared bits for the public widgets. Utility classes only: the public
 * CSS already owns `.btn`, `.card`, `.form-input`, so widgets never use them.
 * Inputs are 16px on phones (no iOS zoom) and 44px tall (touch targets).
 */
export const cx = (...c) => c.filter(Boolean).join(' ');

export const BTN = 'inline-flex h-11 w-full items-center justify-center gap-2 bg-brand px-5 text-[15px] font-medium text-white transition-[transform,background-color] duration-150 hover:bg-brand-deep active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50';
export const BTN_GHOST = 'inline-flex h-11 items-center justify-center gap-2 border border-line bg-surface px-4 text-[14px] font-medium text-fg transition-[transform,border-color] duration-150 hover:border-line-strong active:scale-[0.98] disabled:opacity-50';
export const FIELD = 'block h-11 w-full border border-line-strong bg-surface px-3 text-base text-fg focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20 sm:text-[14px]';
export const LABEL = 'mb-1.5 block text-[12px] font-medium text-fg-2';

export function money(amount, currency = 'PHP', digits = 0) {
    try {
        return new Intl.NumberFormat('en-PH', { style: 'currency', currency, maximumFractionDigits: digits, minimumFractionDigits: digits }).format(amount ?? 0);
    } catch {
        return `${currency} ${Number(amount ?? 0).toFixed(digits)}`;
    }
}

export const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** fetch JSON with CSRF; throws { status, message, errors } on failure. */
export async function json(url, { method = 'GET', body, signal } = {}) {
    const res = await fetch(url, {
        method,
        signal,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const errors = data.errors ?? {};
        throw { status: res.status, errors, message: Object.values(errors)[0]?.[0] ?? data.message ?? 'Something went wrong. Please try again.' };
    }
    return data;
}

/** Plain form POST (full navigation) for endpoints that redirect. */
export function postForm(url, fields) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    Object.entries({ _token: csrf(), ...fields }).forEach(([k, v]) => {
        if (v === undefined || v === null || v === '') return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = k;
        input.value = v;
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
}

/** Sign-in-then-return link: `root` is url('/') from the server, `url` the absolute step URL. */
export const continueUrl = (root, url) => `${root}/continue?to=${encodeURIComponent(url.startsWith(root) ? url.slice(root.length) || '/' : '/')}`;

export function Stepper({ value, min = 1, max = 20, onChange, label }) {
    return (
        <div className="flex h-11 items-center border border-line-strong bg-surface" role="group" aria-label={label}>
            <button type="button" className="h-full w-11 text-lg text-fg-2 hover:bg-soft disabled:opacity-40" disabled={value <= min} onClick={() => onChange(value - 1)} aria-label={`Fewer ${label}`}>−</button>
            <span className="flex-1 text-center text-[15px] tabular-nums text-fg" aria-live="polite">{value}</span>
            <button type="button" className="h-full w-11 text-lg text-fg-2 hover:bg-soft disabled:opacity-40" disabled={value >= max} onClick={() => onChange(value + 1)} aria-label={`More ${label}`}>+</button>
        </div>
    );
}
