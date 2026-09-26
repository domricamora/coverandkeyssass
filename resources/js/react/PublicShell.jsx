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
        const text = flash?.error ?? flash?.success ?? (errors && Object.values(errors)[0]);
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
