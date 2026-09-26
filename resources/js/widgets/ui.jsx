import { useState } from 'react';

/**
 * Shared bits for the public widgets. Utility classes only: the public
 * CSS already owns `.btn`, `.card`, `.form-input`, so widgets never use them.
 * Inputs are 16px on phones (no iOS zoom) and 44px tall (touch targets).
 */
export const cx = (...c) => c.filter(Boolean).join(' ');

export const BTN = 'inline-flex h-11 w-full items-center justify-center gap-2 bg-brand px-5 text-[15px] font-medium text-white transition-[transform,background-color] duration-150 hover:bg-brand-deep active:scale-[0.98] disabled:pointer-events-none disabled:opacity-50';
export const BTN_GHOST = 'inline-flex h-11 items-center justify-center gap-2 border border-line bg-surface px-4 text-[14px] font-medium text-fg transition-[transform,border-color] duration-150 hover:border-line-strong active:scale-[0.98] disabled:opacity-50';
export const FIELD = 'block h-11 w-full border border-line-strong bg-surface px-3 text-base text-fg focus:border-brand focus:outline-none focus:ring-1 focus:ring-brand sm:text-[14px]';
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

/**
 * Guest details for checkout without registration (GuestCheckoutController).
 * `view` renders name / email / phone (or "Booking as …" when signed in);
 * call `await ensure()` before submitting: true once the guest is signed in.
 * A new email is signed in straight away; an existing one shows a password
 * field and "Email me a sign-in link" (returns to `returnTo`).
 *
 * urls: { identify, password, link }
 */
export function useGuest({ user, urls, returnTo, phone: initialPhone = '', stacked = false }) {
    const [me, setMe] = useState(user ?? null);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [phone, setPhone] = useState(initialPhone);
    const [mode, setMode] = useState('details'); // details | existing | sent
    const [password, setPassword] = useState('');
    const [error, setError] = useState(null);

    const signedIn = (r) => {
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', r.csrf);
        setMe({ name: r.name, email });
        setMode('details');
        return true;
    };

    const ensure = async () => {
        if (me) return true;
        setError(null);
        try {
            if (mode === 'existing') {
                return signedIn(await json(urls.password, { method: 'POST', body: { email, password } }));
            }
            const r = await json(urls.identify, { method: 'POST', body: { name, email } });
            if (r.status === 'signed_in') return signedIn(r);
            setMode('existing');
            return false;
        } catch (e) {
            setError(e.message);
            return false;
        }
    };

    const sendLink = async () => {
        setError(null);
        try {
            await json(urls.link, { method: 'POST', body: { email, to: returnTo } });
            setMode('sent');
        } catch (e) {
            setError(e.message);
        }
    };

    const view = me ? (
        <div className="space-y-4">
            <p className="text-[14px] text-fg-2">Booking as <span className="font-medium text-fg">{me.name}</span>{me.email && <> · {me.email}</>}</p>
            <label className="block">
                <span className={LABEL}>Mobile number</span>
                <input type="tel" autoComplete="tel" className={FIELD} value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="0917 000 0000" />
            </label>
        </div>
    ) : (
        <div className="space-y-4">
            <div className={cx('grid gap-4', !stacked && 'sm:grid-cols-2')}>
                <label className="block">
                    <span className={LABEL}>Full name</span>
                    <input autoComplete="name" required className={FIELD} value={name} onChange={(e) => setName(e.target.value)} disabled={mode !== 'details'} />
                </label>
                <label className="block">
                    <span className={LABEL}>Email <span className="font-normal text-fg-3">(confirmation goes here)</span></span>
                    <input type="email" autoComplete="email" required className={FIELD} value={email} onChange={(e) => { setEmail(e.target.value); setMode('details'); }} />
                </label>
                <label className={cx('block', !stacked && 'sm:col-span-2')}>
                    <span className={LABEL}>Mobile number</span>
                    <input type="tel" autoComplete="tel" className={FIELD} value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="0917 000 0000" />
                </label>
            </div>
            {mode === 'existing' && (
                <div className="space-y-3 border-l-2 border-brand bg-brand-soft px-4 py-3">
                    <p className="text-[14px] text-fg">Welcome back. This email already has an account.</p>
                    <label className="block">
                        <span className={LABEL}>Password</span>
                        <input type="password" autoComplete="current-password" autoFocus className={FIELD} value={password} onChange={(e) => setPassword(e.target.value)} />
                    </label>
                    <button type="button" onClick={sendLink} className="text-[13px] font-medium text-brand hover:underline">Forgot it? Email me a sign-in link</button>
                </div>
            )}
            {mode === 'sent' && <p role="status" className="border-l-2 border-ok bg-ok-bg px-4 py-3 text-[14px] text-ok">Check {email}: the sign-in link brings you back here.</p>}
            {!user && mode === 'details' && <p className="text-[12px] text-fg-3">No account needed. We create one for your bookings; set a password any time.</p>}
            {error && <p role="alert" className="text-[13px] text-bad">{error}</p>}
        </div>
    );

    return { view, ensure, phone, signedIn: !!me, waiting: mode === 'sent' };
}
