import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Light shell for guest-facing React pages (booking review, checkout):
 * brand, the signed-in guest, and flash / validation messages. No
 * dashboard sidebar; the marketplace itself stays server-rendered.
 */
export default function PublicShell({ children }) {
    const { app, auth, flash, errors } = usePage().props;
    const [notice, setNotice] = useState(null);

    useEffect(() => {
        const text = flash?.error ?? flash?.warning ?? flash?.success ?? (errors && Object.values(errors)[0]);
        setNotice(text ? { text, bad: !flash?.success || !!flash?.error } : null);
    }, [flash, errors]);

    return (
        <div className="min-h-dvh bg-canvas text-fg">
            <header className="border-b border-line bg-surface">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-5 sm:px-10">
                    <a href={app.home} className="font-display text-[17px] font-medium tracking-tight">{app.name}</a>
                    {auth && <span className="text-[13px] text-fg-3">Signed in as <span className="text-fg-2">{auth.name}</span></span>}
                </div>
            </header>
            {notice && (
                <div role="alert" className={`border-b px-5 py-3 text-center text-[14px] ${notice.bad ? 'border-line bg-bad-bg text-bad' : 'border-line bg-ok-bg text-ok'}`}>
                    {notice.text}
                </div>
            )}
            <main className="mx-auto max-w-6xl px-5 py-8 sm:px-10 sm:py-12">{children}</main>
        </div>
    );
}

/** Guest account sub-navigation (Trips, Tables, Orders, …); some tabs are still server pages, so plain links. */
export function AccountTabs({ tabs }) {
    return (
        <nav aria-label="Account" className="-mx-5 mb-8 flex gap-1 overflow-x-auto border-b border-line px-5 sm:mx-0 sm:px-0">
            {tabs.map((t) => (
                <a
                    key={t.href}
                    href={t.href}
                    aria-current={t.active ? 'page' : undefined}
                    className={`-mb-px whitespace-nowrap border-b-2 px-3 py-2.5 text-[14px] ${t.active ? 'border-brand font-medium text-fg' : 'border-transparent text-fg-3 hover:text-fg'}`}
                >
                    {t.label}
                </a>
            ))}
        </nav>
    );
}
