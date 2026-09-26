import { useForm } from '@inertiajs/react';
import { Action, Badge, Facts, Form, Input, Page, Panel, Select, Split, Stack, Status, TextArea, when } from '../../react/kit';
import { cx, label, money } from '../../react/ui';

/** One maintenance ticket: notes, files, workflow buttons, assignment and cost. */
export default function MaintenanceTicket({ ticket: t, members, can, urls }) {
    const open = t.status !== 'closed';
    const note = useForm({ body: '' });
    const file = useForm({ file: null });
    const assign = useForm({ assigned_to: t.assigned_to ?? '' });
    const cost = useForm({ cost: t.cost ?? '' });

    return (
        <Page
            title={`${t.reference} · ${t.title}`}
            back={{ href: urls.index, label: 'All tickets' }}
            subtitle={`${t.where} · ${label(t.category)} · ${label(t.priority)} priority · reported by ${t.reporter ?? '—'} ${t.reported}`}
        >
            <Split>
                <Stack>
                    <Panel title="Notes" pad>
                        {t.description && <p className="whitespace-pre-line text-[13px] text-fg-2">{t.description}</p>}
                        <ul className="space-y-3">
                            {t.notes.map((n) => (
                                <li key={n.id} className="text-[13px]">
                                    <p className="text-[12px] text-fg-3"><strong className="font-medium text-fg">{n.author}</strong> · {when(n.at)}</p>
                                    <p className={cx('mt-0.5 whitespace-pre-line', n.system ? 'italic text-fg-3' : 'text-fg-2')}>{n.body}</p>
                                </li>
                            ))}
                        </ul>
                        {can.work && open && (
                            <Form form={note} url={urls.note} reset button="Add note">
                                <TextArea form={note} name="body" rows={2} placeholder="Add a note" aria-label="Note" required />
                            </Form>
                        )}
                    </Panel>

                    <Panel title="Attachments" pad>
                        <ul className="space-y-1 text-[13px]">
                            {t.files.length === 0 && <li className="text-fg-3">No files.</li>}
                            {t.files.map((f) => (
                                <li key={f.id}><a href={f.href} target="_blank" rel="noopener" className="text-brand hover:underline">{f.name}</a> <span className="text-fg-3">({f.kind})</span></li>
                            ))}
                        </ul>
                        {can.work && open && (
                            <Form form={file} url={urls.attach} reset button="Upload">
                                <Input form={file} name="file" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" aria-label="Attachment" required />
                            </Form>
                        )}
                    </Panel>
                </Stack>

                <Stack>
                    <Panel title="Status" aside={<span className="flex gap-1.5"><Status value={t.status} />{t.out_of_order && <Badge tone="bad">Room out of order</Badge>}</span>}>
                        <Facts rows={[
                            ['Assigned', t.assignee ?? 'nobody'],
                            ['Cost', t.cost !== null ? money(t.cost) : '—'],
                            t.started_at && ['Started', when(t.started_at)],
                            t.resolved_at && ['Resolved', when(t.resolved_at)],
                        ]} />
                        {can.work && t.next.length > 0 && (
                            <div className="flex flex-wrap gap-2 border-t border-line px-5 py-4">
                                {t.next.map((n) => (
                                    <Action key={n.status} href={urls.transition} data={{ status: n.status }} className={n.status === 'resolved' ? 'btn-primary btn-sm' : 'btn-ghost btn-sm'}>{n.label}</Action>
                                ))}
                            </div>
                        )}
                    </Panel>

                    {can.manage && open && (
                        <>
                            <Panel title="Assign" pad>
                                <Form form={assign} url={urls.assign}>
                                    <Select form={assign} name="assigned_to" aria-label="Assign to" placeholder="Nobody" options={members.map((m) => [m.id, m.name])} />
                                </Form>
                            </Panel>
                            <Panel title="Cost" pad>
                                <Form form={cost} url={urls.cost} button="Save cost">
                                    <Input form={cost} name="cost" type="number" step="0.01" min="0" aria-label="Cost" required />
                                </Form>
                            </Panel>
                        </>
                    )}
                </Stack>
            </Split>
        </Page>
    );
}
