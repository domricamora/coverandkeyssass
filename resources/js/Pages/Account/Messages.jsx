import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { Pager } from '../../react/kit';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { FIELD } from '../../widgets/ui';

/** Guest inbox: conversations with hosts and support; list and open conversation side by side. */
export default function Messages({ threads, thread, tabs, urls }) {
    return (
        <>
            <Head title={thread ? thread.subject : 'Messages'} />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <h1 className="font-display text-[30px] font-medium tracking-tight">Messages</h1>
                <a href={urls.support} className="text-[14px] font-medium text-brand hover:underline">Contact support</a>
            </div>
            <AccountTabs tabs={tabs} />
            <div className="grid items-start gap-6 lg:grid-cols-[320px_minmax(0,1fr)]">
                <div className={thread ? 'hidden lg:block' : ''}>
                    {threads.data.length === 0 ? (
                        <div className="border border-dashed border-line px-6 py-10 text-center text-[14px] text-fg-3">No conversations yet. Message a host from your trip or order page.</div>
                    ) : (
                        <ul className="divide-y divide-line border border-line bg-surface">
                            {threads.data.map((t) => (
                                <li key={t.id}>
                                    <a href={t.href} className={`flex items-start gap-3 px-4 py-3 transition-colors duration-150 hover:bg-soft ${t.active ? 'bg-brand-soft' : ''}`}>
                                        <span className="min-w-0 flex-1">
                                            <span className={`block truncate text-[14px] ${t.unread ? 'font-semibold' : ''} text-fg`}>{t.subject}</span>
                                            <span className="block truncate text-[12px] text-fg-3">{t.who} · {t.when}{t.closed && ' · closed'}</span>
                                        </span>
                                        {t.unread > 0 && <span className="shrink-0 bg-coral px-1.5 text-[11px] font-medium text-white">{t.unread}</span>}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Pager page={threads} />
                </div>
                {thread ? <Conversation key={thread.id} thread={thread} inbox={urls.inbox} /> : (
                    <div className="hidden border border-dashed border-line px-6 py-16 text-center text-[14px] text-fg-3 lg:block">Pick a conversation to read it.</div>
                )}
            </div>
        </>
    );
}

function Conversation({ thread, inbox }) {
    const reply = useForm({ body: '', files: [] });
    const end = useRef(null);
    useEffect(() => { end.current?.scrollIntoView({ block: 'nearest' }); }, [thread.messages.length]);

    return (
        <section className="border border-line bg-surface">
            <header className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div className="min-w-0">
                    <a href={inbox} className="mb-1 inline-block text-[12px] text-fg-3 hover:text-fg lg:hidden">All messages</a>
                    <h2 className="text-[16px] font-semibold">{thread.subject}</h2>
                    <p className="text-[12px] text-fg-3">{thread.who}</p>
                </div>
                {thread.status === 'open' && (
                    <button type="button" className="h-9 border border-line-strong px-3 text-[13px] text-fg-2 transition-transform duration-150 hover:border-brand active:scale-[0.97]"
                        onClick={() => router.post(thread.urls.close, {}, { preserveScroll: true })}>Close conversation</button>
                )}
            </header>
            <div className="max-h-[60dvh] space-y-3 overflow-y-auto px-5 py-4">
                {thread.messages.map((m) => (
                    <div key={m.id} className={`max-w-[85%] px-4 py-3 ${m.mine ? 'ml-auto bg-brand-soft' : 'border border-line'}`}>
                        <p className="mb-1 text-[11px] text-fg-3">{m.author} · {m.when}</p>
                        <p className="whitespace-pre-line text-[14px] text-fg">{m.body}</p>
                        {m.files.map((f) => <a key={f.id} href={f.url} target="_blank" rel="noopener" className="mt-1.5 block text-[13px] text-brand hover:underline">{f.name}</a>)}
                    </div>
                ))}
                <div ref={end} />
            </div>
            {thread.canReply ? (
                <form onSubmit={(e) => { e.preventDefault(); reply.post(thread.urls.reply, { preserveScroll: true, forceFormData: true, onSuccess: () => reply.reset() }); }} className="space-y-3 border-t border-line px-5 py-4">
                    <textarea rows={3} required className={`${FIELD} h-auto py-2.5`} value={reply.data.body} onChange={(e) => reply.setData('body', e.target.value)} placeholder="Write a reply" aria-label="Reply" />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" className="text-[12px] text-fg-3" onChange={(e) => reply.setData('files', [...e.target.files])} aria-label="Attach files" />
                        <button type="submit" disabled={reply.processing} className="h-11 bg-brand px-5 text-[14px] font-medium text-white transition-transform duration-150 hover:bg-brand-deep active:scale-[0.97]">Send</button>
                    </div>
                    {(reply.errors.body || reply.errors['files.0']) && <p className="text-[13px] text-bad">{reply.errors.body ?? reply.errors['files.0']}</p>}
                </form>
            ) : <p className="border-t border-line px-5 py-4 text-[13px] text-fg-3">This conversation is closed.</p>}
        </section>
    );
}

Messages.layout = (page) => <PublicShell>{page}</PublicShell>;
