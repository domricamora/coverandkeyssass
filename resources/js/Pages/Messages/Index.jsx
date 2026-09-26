import { Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Action, Badge, Input, Page, Pager, Panel, Status, TextArea } from '../../react/kit';
import { cx } from '../../react/ui';

/** Business inbox: guest conversations and staff threads, list + open conversation side by side. */
export default function Messages({ threads, status, members, thread, urls }) {
    const [composing, setComposing] = useState(false);

    return (
        <Page
            title="Messages"
            subtitle="Guests writing about your stays, restaurants, orders and tables, plus staff conversations"
            actions={
                <>
                    <nav className="flex border border-line" aria-label="Filter">
                        {[['open', 'Open'], ['closed', 'Closed'], ['all', 'All']].map(([k, l]) => (
                            <Link key={k} href={`${urls.self}?status=${k}`} preserveState className={cx('px-3 py-1.5 text-[13px]', status === k ? 'bg-brand text-white' : 'text-fg-2 hover:bg-soft')}>{l}</Link>
                        ))}
                    </nav>
                    {members.length > 0 && <button type="button" className="btn-primary btn-sm" onClick={() => setComposing(true)}>Message colleagues</button>}
                </>
            }
        >
            <div className="grid items-start gap-6 lg:grid-cols-[340px_minmax(0,1fr)]">
                <Panel className={cx(thread && 'hidden lg:block')}>
                    <ul className="divide-y divide-line">
                        {threads.data.length === 0 && <li className="px-5 py-8 text-center text-[13px] text-fg-3">No conversations.</li>}
                        {threads.data.map((t) => (
                            <li key={t.id}>
                                <Link href={t.href} preserveScroll className={cx('flex items-start gap-3 px-5 py-3 hover:bg-soft', t.active && 'bg-brand-soft')}>
                                    <span className="min-w-0 flex-1">
                                        <span className={cx('block truncate text-[14px]', t.unread ? 'font-semibold text-fg' : 'text-fg')}>{t.subject}</span>
                                        <span className="block truncate text-[12px] text-fg-3">{t.who} · {t.when}</span>
                                    </span>
                                    {t.unread > 0 && <Badge tone="warn">{t.unread} new</Badge>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                    <Pager page={threads} />
                </Panel>

                {thread ? <Conversation key={thread.id} thread={thread} back={`${urls.self}?status=${status}`} /> : (
                    <div className="hidden border border-dashed border-line px-6 py-16 text-center text-[14px] text-fg-3 lg:block">Pick a conversation to read and reply.</div>
                )}
            </div>

            {composing && <Compose members={members} url={urls.staff} onClose={() => setComposing(false)} />}
        </Page>
    );
}

function Conversation({ thread, back }) {
    const reply = useForm({ body: '', files: [] });
    const end = useRef(null);
    useEffect(() => { end.current?.scrollIntoView({ block: 'nearest' }); }, [thread.messages.length]);

    const send = (e) => {
        e.preventDefault();
        reply.post(thread.urls.reply, { preserveScroll: true, forceFormData: true, onSuccess: () => reply.reset() });
    };

    return (
        <Panel>
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div className="min-w-0">
                    <Link href={back} className="mb-1 inline-block text-[12px] text-fg-3 hover:text-fg lg:hidden">← Inbox</Link>
                    <h2 className="text-[16px] font-semibold text-fg">{thread.subject}</h2>
                    <p className="text-[12px] text-fg-3">{thread.meta}</p>
                </div>
                <span className="flex items-center gap-2">
                    <Status value={thread.status} />
                    <Action href={thread.urls.status} data={{ open: thread.status === 'open' ? 0 : 1 }}>{thread.status === 'open' ? 'Close' : 'Reopen'}</Action>
                </span>
            </div>

            <div className="max-h-[60dvh] space-y-3 overflow-y-auto px-5 py-4">
                {thread.messages.map((m) => (
                    <div key={m.id} className={cx('max-w-[80%] px-4 py-3', m.mine ? 'ml-auto bg-brand-soft' : 'border border-line bg-surface')}>
                        <p className="mb-1 text-[11px] text-fg-3">{m.author} · {m.side} · {m.when}</p>
                        <p className="whitespace-pre-line text-[14px] text-fg">{m.body}</p>
                        {m.files.map((f) => <a key={f.id} href={f.url} target="_blank" rel="noopener" className="mt-1.5 block text-[13px] text-brand hover:underline">📎 {f.name}</a>)}
                    </div>
                ))}
                <div ref={end} />
            </div>

            {thread.canReply ? (
                <form onSubmit={send} className="space-y-3 border-t border-line px-5 py-4">
                    <TextArea form={reply} name="body" rows={3} placeholder="Write a reply…" aria-label="Reply" required />
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" className="text-[12px] text-fg-3" onChange={(e) => reply.setData('files', [...e.target.files])} aria-label="Attach files" />
                        <button type="submit" className="btn-primary" disabled={reply.processing}>Send</button>
                    </div>
                    {(reply.errors.files || reply.errors['files.0']) && <p className="text-[12px] text-bad">{reply.errors.files ?? reply.errors['files.0']}</p>}
                </form>
            ) : thread.status === 'closed' && <p className="border-t border-line px-5 py-4 text-[13px] text-fg-3">This conversation is closed. Reopen it to reply.</p>}
        </Panel>
    );
}

function Compose({ members, url, onClose }) {
    const form = useForm({ participants: [], subject: '', body: '' });
    const toggle = (id) => form.setData('participants', form.data.participants.includes(id) ? form.data.participants.filter((x) => x !== id) : [...form.data.participants, id]);
    const ref = useRef(null);
    useEffect(() => { ref.current?.showModal(); }, []);

    return (
        <dialog ref={ref} onClose={onClose} className="w-full max-w-lg bg-surface p-0 text-fg backdrop:bg-black/40">
            <form onSubmit={(e) => { e.preventDefault(); form.post(url); }} className="space-y-4 p-5">
                <div className="flex items-center justify-between">
                    <h2 className="text-[16px] font-semibold">Message colleagues</h2>
                    <button type="button" onClick={onClose} className="text-xl text-fg-3 hover:text-fg" aria-label="Close">×</button>
                </div>
                <fieldset>
                    <legend className="label">To</legend>
                    <div className="flex max-h-40 flex-wrap gap-1.5 overflow-y-auto">
                        {members.map(([id, name]) => (
                            <button key={id} type="button" onClick={() => toggle(id)} aria-pressed={form.data.participants.includes(id)}
                                className={cx('border px-2.5 py-1 text-[13px]', form.data.participants.includes(id) ? 'border-brand bg-brand text-white' : 'border-line text-fg-2 hover:border-line-strong')}>
                                {name}
                            </button>
                        ))}
                    </div>
                    {form.errors.participants && <p className="mt-1 text-[12px] text-bad">{form.errors.participants}</p>}
                </fieldset>
                <Input form={form} name="subject" label="Subject" required />
                <TextArea form={form} name="body" label="Message" rows={4} required />
                <button type="submit" className="btn-primary" disabled={form.processing || form.data.participants.length === 0}>Send</button>
            </form>
        </dialog>
    );
}
