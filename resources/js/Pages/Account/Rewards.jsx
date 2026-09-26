import { Head, useForm } from '@inertiajs/react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { FIELD } from '../../widgets/ui';

/** Loyalty points per business, referral codes and gift-card balances. */
export default function Rewards({ accounts, cards, tabs }) {
    return (
        <>
            <Head title="Rewards" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Rewards</h1>
            <AccountTabs tabs={tabs} />
            {accounts.length === 0 ? (
                <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">Your points show up here after your first stay or order with a business that runs a rewards programme.</div>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2">{accounts.map((a) => <Account key={a.id} a={a} />)}</div>
            )}
            {cards.length > 0 && (
                <section className="mt-10">
                    <h2 className="mb-3 text-[17px] font-semibold">Gift cards and credit</h2>
                    <ul className="divide-y divide-line border border-line bg-surface">
                        {cards.map((c) => (
                            <li key={c.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3 text-[14px]">
                                <span><span className="font-medium text-fg">{c.business}</span> <span className="font-mono text-[13px] text-fg-3">{c.code}</span></span>
                                <span className="tabular-nums">{c.balance} left</span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </>
    );
}

function Account({ a }) {
    const form = useForm({ code: '' });

    return (
        <section className="border border-line bg-surface p-5">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[15px] font-medium text-fg">{a.business}</p>
                    <p className="text-[13px] text-fg-3">{a.tier}{a.next && ` · ${a.next}`}</p>
                </div>
                <p className="text-right"><span className="block font-display text-[28px] font-medium leading-none tabular-nums">{a.points.toLocaleString()}</span><span className="text-[12px] text-fg-3">points</span></p>
            </div>
            <p className="mt-4 text-[13px] text-fg-2">Share your code <span className="font-mono font-medium text-fg">{a.code}</span> and you both get a bonus.</p>
            {a.canRefer && (
                <form onSubmit={(e) => { e.preventDefault(); form.post(a.referral, { preserveScroll: true }); }} className="mt-3 flex gap-2">
                    <input className={FIELD} value={form.data.code} onChange={(e) => form.setData('code', e.target.value)} placeholder="A friend's code" aria-label={`Referral code for ${a.business}`} required />
                    <button type="submit" disabled={form.processing} className="h-11 shrink-0 border border-line-strong bg-surface px-4 text-[14px] font-medium transition-transform duration-150 hover:border-brand active:scale-[0.97]">Apply</button>
                </form>
            )}
            {form.errors.code && <p className="mt-1 text-[13px] text-bad">{form.errors.code}</p>}
            {a.activity.length > 0 && (
                <ul className="mt-4 space-y-1 border-t border-line pt-3 text-[13px]">
                    {a.activity.map((t) => (
                        <li key={t.id} className="flex justify-between gap-3 text-fg-2">
                            <span className="truncate">{t.text}</span>
                            <span className={`tabular-nums ${t.points > 0 ? 'text-ok' : 'text-fg-3'}`}>{t.points > 0 ? '+' : ''}{t.points}</span>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

Rewards.layout = (page) => <PublicShell>{page}</PublicShell>;
