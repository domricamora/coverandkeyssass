import { Head, router } from '@inertiajs/react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';

/** Saved stays and restaurants, as photo cards; saved stays get price-drop alerts. */
export default function WishList({ items, tabs, urls }) {
    const list = items.data.filter(Boolean);

    return (
        <>
            <Head title="Wish list" />
            <h1 className="mb-2 font-display text-[30px] font-medium tracking-tight">Wish list</h1>
            <p className="mb-6 text-[14px] text-fg-3">We let you know when a saved stay gets cheaper.</p>
            <AccountTabs tabs={tabs} />
            {list.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">
                    Nothing saved yet. Tap the heart on a stay or restaurant to keep it here. <a href={urls.stays} className="text-brand hover:underline">Browse stays</a>
                </div>
            ) : (
                <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {list.map((i) => (
                        <li key={i.id} className="group">
                            <a href={i.url} className="block overflow-hidden">
                                {i.image
                                    ? <img src={i.image} alt="" loading="lazy" className="aspect-[4/3] w-full object-cover transition-transform duration-300 ease-out group-hover:scale-[1.02]" />
                                    : <span className="block aspect-[4/3] w-full bg-soft" />}
                            </a>
                            <div className="mt-2.5 flex items-start justify-between gap-3">
                                <a href={i.url} className="min-w-0">
                                    <span className="block truncate text-[15px] font-medium text-fg hover:text-brand">{i.name}</span>
                                    <span className="block truncate text-[13px] text-fg-3">{[i.kind, i.where].filter(Boolean).join(' · ')}</span>
                                    <span className="block text-[13px] text-fg-2">{i.price}</span>
                                </a>
                                <button
                                    type="button"
                                    onClick={() => router.delete(i.remove, { preserveScroll: true })}
                                    className="h-11 shrink-0 px-2 text-[13px] text-fg-3 transition-colors hover:text-bad"
                                    aria-label={`Remove ${i.name} from your wish list`}
                                >
                                    Remove
                                </button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <Pager page={items} />
        </>
    );
}

WishList.layout = (page) => <PublicShell>{page}</PublicShell>;
