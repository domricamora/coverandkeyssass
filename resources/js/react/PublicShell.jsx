import logo from '../../images/logo.svg';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Shell for guest-facing React pages (stay review, checkout, account):
 * site navigation, the account menu (business links only for members),
 * flash / validation messages, and a quiet page-enter transition keyed on
 * the page component. Mobile-first: a menu button below `sm`.
 */
export default function PublicShell({ children }) {
    const { app, auth, flash, errors, links = {} } = usePage().props;
    const { component } = usePage();
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        const text = flash?.error ?? flash?.warning ?? flash?.success ?? (errors && Object.values(errors)[0]);
        setNotice(text ? { text, bad: !flash?.success || !!flash?.error || !!flash?.warning } : null);
    }, [flash, errors]);

    const nav = [['Stays', app.stays], ['Restaurants', app.restaurants]].filter(([, href]) => href);

    return (
        <div className="min-h-dvh bg-canvas text-fg">
            <header className="sticky top-0 z-30 border-b border-line bg-surface/95 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-6 px-5 sm:px-10">
                    <a href={app.home} className="flex items-center gap-2.5 font-display text-[17px] font-medium tracking-tight"><img className="brand-logo" src={logo} width="36" height="36" alt="" aria-hidden="true" style={{ borderRadius: 9999, border: '2px solid #c9a13b', padding: 3, boxSizing: 'border-box' }} />{app.name}</a>
                    <nav aria-label="Site" className="hidden items-center gap-5 sm:flex">
                        {nav.map(([label, href]) => <a key={label} href={href} className="text-[14px] text-fg-2 transition-colors duration-150 hover:text-fg">{label}</a>)}
                    </nav>
                    <span className="flex-1" />
                    {auth ? <AccountMenu auth={auth} links={links} nav={nav} /> : app.login && <a href={app.login} className="text-[14px] font-medium text-brand">Sign in</a>}
                </div>
            </header>
            {notice && (
                <div role="alert" className={`border-b px-5 py-3 text-center text-[14px] ${notice.bad ? 'border-line bg-bad-bg text-bad' : 'border-line bg-ok-bg text-ok'}`}>
                    {notice.text}
                </div>
            )}
            <main className="mx-auto max-w-6xl px-5 py-8 sm:px-10 sm:py-12">
                <div key={component} className="page-in">{children}</div>
            </main>
        </div>
    );
}

function AccountMenu({ auth, links, nav }) {
    const [open, setOpen] = useState(false);
    const box = useRef(null);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    useEffect(() => {
        if (!open) return;
        const close = (e) => { if (e.key === 'Escape' || (e.type === 'mousedown' && !box.current?.contains(e.target))) setOpen(false); };
        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);
        return () => { document.removeEventListener('mousedown', close); document.removeEventListener('keydown', close); };
    }, [open]);

    const items = [
        ...nav.map(([label, href]) => ({ label, href, mobileOnly: true })),
        links.trips && { label: 'My trips', href: links.trips },
        links.businesses && { label: 'Businesses', href: links.businesses },
        links.profile && { label: 'Profile', href: links.profile },
        links.admin && { label: 'Platform admin', href: links.admin },
    ].filter(Boolean);

    return (
        <div ref={box} className="relative">
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="flex h-11 items-center gap-2 px-1 text-[14px] text-fg-2 transition-transform duration-150 hover:text-fg active:scale-[0.97]"
            >
                <span className="grid h-8 w-8 place-items-center bg-brand text-[13px] font-medium text-white" aria-hidden="true">{auth.name?.[0]}</span>
                <span className="hidden max-w-40 truncate sm:inline">{auth.name}</span>
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </button>
            {open && (
                <div role="menu" className="menu-in absolute right-0 top-12 z-40 w-60 origin-top-right border border-line bg-surface py-1 shadow-[0_18px_40px_-18px_rgba(16,37,42,.35)]">
                    <p className="border-b border-line px-4 pb-2.5 pt-2 text-[13px] font-medium">{auth.name}<span className="block truncate font-normal text-fg-3">{auth.email}</span></p>
                    {items.map((i) => (
                        <a key={i.label} role="menuitem" href={i.href} className={`block px-4 py-2.5 text-[14px] text-fg-2 hover:bg-soft hover:text-fg ${i.mobileOnly ? 'sm:hidden' : ''}`}>{i.label}</a>
                    ))}
                    {links.logout && (
                        <form method="post" action={links.logout} className="mt-1 border-t border-line pt-1">
                            <input type="hidden" name="_token" value={csrf()} />
                            <button type="submit" role="menuitem" className="block w-full px-4 py-2.5 text-left text-[14px] text-fg-2 hover:bg-soft hover:text-fg">Sign out</button>
                        </form>
                    )}
                </div>
            )}
        </div>
    );
}

/** Guest account sub-navigation (Trips, Tables, Orders, …); scrolls sideways on phones. */
export function AccountTabs({ tabs }) {
    return (
        <nav aria-label="Account" className="-mx-5 mb-8 flex gap-1 overflow-x-auto border-b border-line px-5 [scrollbar-width:none] sm:mx-0 sm:px-0">
            {tabs.map((t) => (
                <a
                    key={t.href}
                    href={t.href}
                    aria-current={t.active ? 'page' : undefined}
                    className={`-mb-px whitespace-nowrap border-b-2 px-3 py-3 text-[14px] transition-colors duration-150 ${t.active ? 'border-brand font-medium text-fg' : 'border-transparent text-fg-3 hover:text-fg'}`}
                >
                    {t.label}
                </a>
            ))}
        </nav>
    );
}
