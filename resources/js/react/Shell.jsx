import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Icon, cx } from './ui';

/**
 * Full-screen dashboard shell: fixed sidebar rail (drawer under lg), slim
 * top bar, and a main area that uses the whole viewport width.
 */
export default function Shell({ children }) {
    const { props, url } = usePage();
    const [nav, setNav] = useState(false);

    useEffect(() => setNav(false), [url]);

    return (
        <div className="flex h-full min-h-0">
            <a href="#main" className="sr-only focus:not-sr-only focus:absolute focus:left-3 focus:top-3 focus:z-50 focus:bg-surface focus:px-3 focus:py-2">
                Skip to content
            </a>

            <aside
                id="dash-nav"
                className={cx(
                    'fixed inset-y-0 left-0 z-30 flex w-64 flex-col border-r border-line bg-surface transition-transform duration-200 lg:static lg:translate-x-0',
                    nav ? 'translate-x-0' : '-translate-x-full',
                )}
                style={{ transitionTimingFunction: 'var(--ease-out)' }}
            >
                <a href={props.app.home} className="flex h-14 shrink-0 items-center gap-2.5 border-b border-line px-5">
                    <Mark />
                    <span className="font-display text-[15px] font-medium tracking-tight">{props.app.name}</span>
                </a>
                {props.business && (
                    <p className="px-5 pb-1 pt-4 text-[11px] font-medium uppercase tracking-[0.1em] text-brand">{props.business}</p>
                )}
                <nav className="flex-1 overflow-y-auto px-3 pb-6" aria-label="Dashboard">
                    {props.nav.map((group, i) => (
                        <div key={group.label ?? i} className="mt-3 first:mt-1">
                            {group.label && <p className="px-2 pb-1 pt-2 text-[11px] font-medium uppercase tracking-[0.1em] text-fg-4">{group.label}</p>}
                            {group.items.map((item) => (
                                <NavLink key={item.href} item={item} />
                            ))}
                        </div>
                    ))}
                </nav>
            </aside>

            {nav && <div className="fixed inset-0 z-20 bg-black/30 lg:hidden" onClick={() => setNav(false)} />}

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="flex h-14 shrink-0 items-center gap-3 border-b border-line bg-surface px-4 lg:px-8">
                    <button
                        type="button"
                        className="btn-ghost btn-sm lg:hidden"
                        aria-controls="dash-nav"
                        aria-expanded={nav}
                        onClick={() => setNav(!nav)}
                    >
                        <Icon d="M4 7h16M4 12h16M4 17h16" className="h-4 w-4" /> Menu
                    </button>
                    <div className="flex-1" />
                    <UserMenu />
                </header>

                <main id="main" className="min-h-0 flex-1 overflow-y-auto">
                    {children}
                </main>
            </div>

            <Toasts />
        </div>
    );
}

function NavLink({ item }) {
    const className = cx(
        'group relative flex h-9 items-center gap-3 px-2 text-[13px] transition-colors duration-150',
        item.active ? 'bg-soft font-medium text-fg' : 'text-fg-2 hover:bg-soft hover:text-fg',
    );
    const body = (
        <>
            {item.active && <span className="absolute inset-y-1.5 left-0 w-[2px] bg-brand" aria-hidden="true" />}
            <Icon d={item.icon} className={cx('h-[17px] w-[17px] shrink-0', item.active ? 'text-fg' : 'text-fg-4 group-hover:text-fg-2')} />
            <span className="truncate">{item.label}</span>
            {item.badge ? <span className="ml-auto bg-ink px-1.5 text-[11px] leading-5 text-white">{item.badge}</span> : null}
        </>
    );

    // Inertia screens swap in place; the Blade screens get a normal page load.
    return item.spa ? (
        <Link href={item.href} className={className} aria-current={item.active ? 'page' : undefined}>{body}</Link>
    ) : (
        <a href={item.href} className={className} aria-current={item.active ? 'page' : undefined}>{body}</a>
    );
}

function UserMenu() {
    const { auth, links } = usePage().props;
    const [open, setOpen] = useState(false);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!auth) return null;

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                aria-expanded={open}
                className="focus-ring flex h-9 items-center gap-2.5 pl-1 pr-2 hover:bg-soft"
            >
                <span className="flex h-7 w-7 items-center justify-center bg-ink text-xs font-medium text-white">{auth.name.slice(0, 1).toUpperCase()}</span>
                <span className="hidden text-[13px] sm:block">{auth.name}</span>
            </button>
            {open && (
                <>
                    <div className="fixed inset-0 z-30" onClick={() => setOpen(false)} />
                    <div className="absolute right-0 top-11 z-40 w-56 border border-line bg-surface py-1 shadow-[0_12px_32px_-12px_rgba(11,12,14,.3)]">
                        <p className="border-b border-line px-3 pb-2 pt-1.5 text-xs text-fg-3">{auth.email}</p>
                        {links.trips && <MenuLink href={links.trips}>My trips</MenuLink>}
                        {links.businesses && <MenuLink href={links.businesses}>Businesses</MenuLink>}
                        <MenuLink href={links.profile}>Profile</MenuLink>
                        {links.admin && <MenuLink href={links.admin}>Platform admin</MenuLink>}
                        <form method="POST" action={links.logout} className="border-t border-line pt-1">
                            <input type="hidden" name="_token" value={csrf} />
                            <button type="submit" className="block w-full px-3 py-2 text-left text-[13px] text-bad hover:bg-soft">Sign out</button>
                        </form>
                    </div>
                </>
            )}
        </div>
    );
}

const MenuLink = ({ href, children }) => (
    <a href={href} className="block px-3 py-2 text-[13px] text-fg-2 hover:bg-soft hover:text-fg">{children}</a>
);

/** Flash + first validation error, announced politely, gone after 5s. */
function Toasts() {
    const { flash, errors } = usePage().props;
    const [items, setItems] = useState([]);

    useEffect(() => {
        const next = [];
        if (flash?.success) next.push({ tone: 'ok', text: flash.success });
        if (flash?.error) next.push({ tone: 'bad', text: flash.error });
        if (flash?.warning) next.push({ tone: 'bad', text: flash.warning });
        const firstError = errors && Object.values(errors)[0];
        if (firstError) next.push({ tone: 'bad', text: firstError });
        if (!next.length) return;
        setItems(next);
        const t = setTimeout(() => setItems([]), 5000);
        return () => clearTimeout(t);
    }, [flash, errors]);

    return (
        <div aria-live="polite" className="pointer-events-none fixed bottom-5 right-5 z-50 flex w-[min(380px,calc(100vw-40px))] flex-col gap-2">
            {items.map((item, i) => (
                <div
                    key={i + item.text}
                    className={cx(
                        'pointer-events-auto border-l-2 bg-ink px-4 py-3 text-[13px] text-white shadow-[0_16px_40px_-16px_rgba(11,12,14,.6)]',
                        item.tone === 'ok' ? 'border-coral' : 'border-[#e0786e]',
                    )}
                >
                    {item.text}
                </div>
            ))}
        </div>
    );
}

function Mark() {
    return (
        <svg viewBox="0 0 32 32" width="20" height="20" fill="none" aria-hidden="true">
            <path d="M16 3.2a12.8 12.8 0 1 1-9.05 21.85" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" />
            <circle cx="16" cy="16" r="8.4" stroke="currentColor" strokeWidth="1.1" opacity="0.45" />
            <circle cx="16" cy="13.6" r="3" fill="currentColor" />
            <path d="M16 16.4 14.7 23h2.6L16 16.4Z" fill="currentColor" />
        </svg>
    );
}
