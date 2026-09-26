import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Drawer, cx, label } from '../../react/ui';

/**
 * Housekeeping & ops, mobile-first: room board by cleaning status, the task
 * queue with the next action for each task, maintenance tickets and who is
 * on duty today. Every action posts to the existing housekeeping /
 * maintenance endpoints, which return here.
 */
const HK = {
    dirty: ['Dirty', 'border-warn bg-warn-bg text-warn'],
    cleaning: ['Cleaning', 'border-info bg-info-bg text-info'],
    clean: ['Clean', 'border-ok bg-ok-bg text-ok'],
    inspected: ['Inspected', 'border-brand bg-brand-soft text-brand-deep'],
    maintenance: ['Maintenance', 'border-coral bg-coral-soft text-coral-deep'],
    out_of_order: ['Out of order', 'border-bad bg-bad-bg text-bad'],
};

export default function Housekeeping(props) {
    const { properties, property, mine, today, can, counts, rooms, tasks, members, tickets, onDuty, urls, types } = props;
    const [room, setRoom] = useState(null);
    const [creating, setCreating] = useState(false);
    const post = (url, data = {}, opts = {}) => router.post(url, data, { preserveScroll: true, ...opts });
    const visit = (params) => router.get(urls.self, { property, ...params }, { preserveScroll: true });

    const queue = mine ? tasks.filter((t) => t.mine) : tasks;

    return (
        <div className="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
            <Head title="Housekeeping" />

            <header className="flex flex-wrap items-end gap-x-6 gap-y-4">
                <div className="min-w-0 basis-full md:basis-0 md:flex-1">
                    <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">Housekeeping & ops</p>
                    <h1 className="mt-1 font-display text-[28px] font-medium tracking-tight text-fg">{mine ? 'My tasks' : 'Rooms & tasks'}</h1>
                </div>
                {properties.length > 1 && (
                    <label className="w-full sm:w-56">
                        <span className="label">Property</span>
                        <select className="field" value={property ?? ''} onChange={(e) => router.get(urls.self, { property: e.target.value, mine: mine ? 1 : undefined })}>
                            {properties.map((p) => <option key={p.slug} value={p.slug}>{p.name}</option>)}
                        </select>
                    </label>
                )}
                <div className="flex border border-line bg-surface" role="tablist" aria-label="View">
                    <button role="tab" aria-selected={!mine} className={cx('h-9 px-4 text-[13px]', !mine ? 'bg-brand text-white' : 'text-fg-2')} onClick={() => visit({})}>All</button>
                    <button role="tab" aria-selected={mine} className={cx('h-9 px-4 text-[13px]', mine ? 'bg-brand text-white' : 'text-fg-2')} onClick={() => visit({ mine: 1 })}>My tasks</button>
                </div>
                {can.manage && <button className="btn-primary" onClick={() => setCreating(true)}>New task</button>}
            </header>

            <div className="mt-6 flex flex-wrap gap-2">
                {Object.entries(HK).map(([key, [name, style]]) => (
                    <span key={key} className={cx('pill border px-3 py-1.5 text-[12px]', style)}>{name} <strong className="font-semibold tabular-nums">{counts[key] ?? 0}</strong></span>
                ))}
            </div>

            <div className="mt-6 grid gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)_340px]">
                {/* Task queue first on phones: that's what attendants open the app for. */}
                <section className="order-1 min-w-0 border border-line bg-surface xl:order-2">
                    <h2 className="flex items-center justify-between border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">
                        Task queue <span className="pill bg-soft text-fg-2">{queue.length}</span>
                    </h2>
                    {queue.length === 0 ? (
                        <p className="px-5 py-10 text-center text-[13px] text-fg-3">{mine ? 'Nothing assigned to you right now.' : 'All rooms are done. Nice work.'}</p>
                    ) : (
                        <ul className="divide-y divide-line">
                            {queue.map((t) => <TaskRow key={t.id} task={t} can={can} members={members} post={post} />)}
                        </ul>
                    )}
                </section>

                <section className="order-2 min-w-0 border border-line bg-surface xl:order-1">
                    <h2 className="border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">Rooms</h2>
                    <div className="grid grid-cols-3 gap-2 p-4 sm:grid-cols-4 lg:grid-cols-5 xl:grid-cols-4 2xl:grid-cols-5">
                        {rooms.map((r) => {
                            const [name, style] = HK[r.hk] ?? ['—', 'border-line bg-surface text-fg-3'];
                            return (
                                <button key={r.id} onClick={() => setRoom(r)} className={cx('focus-ring flex aspect-square flex-col justify-between border p-2.5 text-left transition-transform active:scale-[0.97]', style)}>
                                    <span className="font-display text-[20px] font-medium leading-none text-fg">{r.number}</span>
                                    <span className="text-[11px] font-medium leading-tight">{name}{r.tasks ? ` · ${r.tasks} task${r.tasks > 1 ? 's' : ''}` : ''}</span>
                                </button>
                            );
                        })}
                    </div>
                    {rooms.length === 0 && <p className="px-5 pb-8 text-center text-[13px] text-fg-3">No rooms at this property yet.</p>}
                </section>

                <aside className="order-3 min-w-0 space-y-8">
                    <section className="border border-line bg-surface">
                        <h2 className="flex items-center justify-between border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">
                            Maintenance <a className="text-[12px] normal-case tracking-normal text-brand hover:underline" href={urls.maintenance}>All tickets</a>
                        </h2>
                        {tickets.length === 0 ? (
                            <p className="px-5 py-8 text-center text-[13px] text-fg-3">No open tickets.</p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {tickets.map((m) => (
                                    <li key={m.reference} className="px-5 py-3">
                                        <div className="flex items-start gap-2">
                                            <a href={m.url} className="min-w-0 flex-1 text-[14px] font-medium text-fg hover:text-brand">{m.title}</a>
                                            <span className={cx('pill', m.priority === 'urgent' || m.priority === 'high' ? 'bg-bad-bg text-bad' : 'bg-soft text-fg-3')}>{label(m.priority)}</span>
                                        </div>
                                        <p className="mt-0.5 text-xs text-fg-3">{m.room ? `Room ${m.room} · ` : ''}{label(m.status)}{m.blocks ? ' · room out of order' : ''}</p>
                                        {can.maintenance && m.next.length > 0 && (
                                            <div className="mt-2 flex flex-wrap gap-1.5">
                                                {m.next.filter((s) => s !== 'closed').map((s) => (
                                                    <button key={s} className="btn-ghost btn-sm" onClick={() => post(m.transition, { status: s })}>{{ in_progress: 'Start work', on_hold: 'On hold', resolved: 'Resolved' }[s] ?? label(s)}</button>
                                                ))}
                                            </div>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section className="border border-line bg-surface">
                        <h2 className="border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">On duty today</h2>
                        {onDuty.length === 0 ? (
                            <p className="px-5 py-8 text-center text-[13px] text-fg-3">No shifts scheduled today.</p>
                        ) : (
                            <ul className="divide-y divide-line">
                                {onDuty.map((s, i) => (
                                    <li key={i} className="flex items-center gap-3 px-5 py-2.5 text-[13px]">
                                        <span className={cx('h-2 w-2 shrink-0', s.in ? 'bg-ok' : 'bg-line-strong')} title={s.in ? 'Clocked in' : 'Not clocked in'} />
                                        <span className="min-w-0 flex-1"><span className="block truncate text-fg">{s.name}</span><span className="block truncate text-xs text-fg-3">{s.position}</span></span>
                                        <span className="whitespace-nowrap text-xs tabular-nums text-fg-3">{s.from}–{s.to}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </aside>
            </div>

            <Drawer open={!!room} onClose={() => setRoom(null)} title={room ? `Room ${room.number}` : 'Room'} width="max-w-[460px]">
                {room && <RoomPanel room={room} tasks={tasks.filter((t) => t.room_id === room.id)} can={can} post={post} onClose={() => setRoom(null)} />}
            </Drawer>

            <Drawer open={creating} onClose={() => setCreating(false)} title="New task" width="max-w-[460px]">
                {creating && <NewTask rooms={rooms} types={types} members={members} today={today} url={urls.task} post={post} onClose={() => setCreating(false)} />}
            </Drawer>
        </div>
    );
}

function TaskRow({ task: t, can, members, post }) {
    const high = t.priority === 'high';
    const awaiting = t.status === 'completed';

    return (
        <li className={cx('px-5 py-3.5', high && 'border-l-2 border-l-coral')}>
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span className="font-display text-[20px] font-medium leading-none text-fg">{t.room}</span>
                <span className="text-[14px] text-fg">{t.type}</span>
                {high && <span className="pill bg-coral-soft text-coral-deep">High</span>}
                <span className={cx('pill', awaiting ? 'bg-info-bg text-info' : t.status === 'in_progress' ? 'bg-brand-soft text-brand-deep' : 'bg-soft text-fg-3')}>{awaiting ? 'Awaiting inspection' : label(t.status)}</span>
            </div>
            <p className="mt-1 text-xs text-fg-3">{t.assignee ? t.assignee.name : 'Unassigned'} · due {t.due}{t.notes ? ` · ${t.notes}` : ''}</p>
            <div className="mt-2.5 flex flex-wrap items-center gap-2">
                {can.work && t.status === 'pending' && <button className="btn-primary btn-sm" onClick={() => post(t.urls.start)}>Start cleaning</button>}
                {can.work && t.status === 'in_progress' && <button className="btn-primary btn-sm" onClick={() => post(t.urls.complete)}>Done — room clean</button>}
                {can.manage && awaiting && (
                    <>
                        <button className="btn-primary btn-sm" onClick={() => post(t.urls.inspect, { passed: 1 })}>Pass</button>
                        <button className="btn-danger btn-sm" onClick={() => post(t.urls.inspect, { passed: 0 })}>Fail · re-clean</button>
                    </>
                )}
                {can.manage && t.status === 'pending' && (
                    <select className="field h-8 w-40 text-xs" value={t.assignee?.id ?? ''} onChange={(e) => post(t.urls.assign, { assigned_to: e.target.value || null })} aria-label="Assign to">
                        <option value="">Unassigned</option>
                        {members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                    </select>
                )}
                {can.manage && !awaiting && <button className="btn-ghost btn-sm ml-auto" onClick={() => window.confirm('Cancel this task?') && post(t.urls.cancel)}>Cancel</button>}
            </div>
        </li>
    );
}

function RoomPanel({ room, tasks, can, post, onClose }) {
    const [issue, setIssue] = useState({ title: '', description: '', priority: 'normal', out_of_order: false });
    const [name] = HK[room.hk] ?? ['—'];

    return (
        <>
            <div className="flex items-start gap-3 border-b border-line px-6 py-5">
                <div className="min-w-0 flex-1">
                    <h2 className="font-display text-[24px] font-medium text-fg">Room {room.number}</h2>
                    <p className="text-xs text-fg-3">{room.type} · {name}</p>
                </div>
                <button className="btn-ghost btn-sm" onClick={onClose}>Close</button>
            </div>
            <div className="flex-1 space-y-6 overflow-y-auto px-6 py-5">
                {can.manage && (
                    <section>
                        <h3 className="label">Set status</h3>
                        <div className="grid grid-cols-2 gap-2">
                            {Object.entries(HK).map(([key, [text, style]]) => (
                                <button key={key} disabled={room.hk === key} onClick={() => post(room.urls.status, { housekeeping_status: key }, { onSuccess: onClose })}
                                    className={cx('btn justify-start', room.hk === key ? style : 'border-line bg-surface text-fg')}>{text}</button>
                            ))}
                        </div>
                    </section>
                )}

                {tasks.length > 0 && (
                    <section>
                        <h3 className="label">Open tasks</h3>
                        <ul className="divide-y divide-line border border-line text-[13px]">
                            {tasks.map((t) => <li key={t.id} className="px-4 py-2.5">{t.type} · {label(t.status)} · {t.assignee?.name ?? 'Unassigned'}</li>)}
                        </ul>
                    </section>
                )}

                {can.work && (
                    <form className="space-y-3" onSubmit={(e) => { e.preventDefault(); post(room.urls.issue, issue, { onSuccess: onClose }); }}>
                        <h3 className="label">Report an issue</h3>
                        <input className="field" required maxLength={160} placeholder="e.g. Aircon leaking" value={issue.title} onChange={(e) => setIssue({ ...issue, title: e.target.value })} />
                        <textarea className="field h-20 py-2" maxLength={2000} placeholder="Details for maintenance (optional)" value={issue.description} onChange={(e) => setIssue({ ...issue, description: e.target.value })} />
                        <div className="flex flex-wrap items-center gap-3">
                            <select className="field w-36" value={issue.priority} onChange={(e) => setIssue({ ...issue, priority: e.target.value })} aria-label="Priority">
                                {['low', 'normal', 'high', 'urgent'].map((p) => <option key={p} value={p}>{label(p)}</option>)}
                            </select>
                            <label className="flex items-center gap-2 text-[13px] text-fg-2">
                                <input type="checkbox" checked={issue.out_of_order} onChange={(e) => setIssue({ ...issue, out_of_order: e.target.checked })} /> Take room out of order
                            </label>
                        </div>
                        <button className="btn-coral w-full">Send to maintenance</button>
                    </form>
                )}
            </div>
        </>
    );
}

function NewTask({ rooms, types, members, today, url, post, onClose }) {
    const [task, setTask] = useState({ room_id: rooms[0]?.id ?? '', type: types[0]?.value ?? '', due_on: today, priority: 'normal', assigned_to: '', notes: '' });
    const set = (k) => (e) => setTask({ ...task, [k]: e.target.value });

    return (
        <form className="flex flex-1 flex-col" onSubmit={(e) => { e.preventDefault(); post(url, { ...task, assigned_to: task.assigned_to || null }, { onSuccess: onClose }); }}>
            <div className="flex items-start gap-3 border-b border-line px-6 py-5">
                <h2 className="flex-1 font-display text-[22px] font-medium text-fg">New task</h2>
                <button type="button" className="btn-ghost btn-sm" onClick={onClose}>Close</button>
            </div>
            <div className="grid flex-1 grid-cols-2 content-start gap-4 overflow-y-auto px-6 py-5">
                <label><span className="label">Room</span><select className="field" value={task.room_id} onChange={set('room_id')}>{rooms.map((r) => <option key={r.id} value={r.id}>{r.number}</option>)}</select></label>
                <label><span className="label">Type</span><select className="field" value={task.type} onChange={set('type')}>{types.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}</select></label>
                <label><span className="label">Due</span><input className="field" type="date" value={task.due_on} onChange={set('due_on')} /></label>
                <label><span className="label">Priority</span><select className="field" value={task.priority} onChange={set('priority')}><option value="normal">Normal</option><option value="high">High</option></select></label>
                <label className="col-span-2"><span className="label">Assign to</span><select className="field" value={task.assigned_to} onChange={set('assigned_to')}><option value="">Unassigned</option>{members.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}</select></label>
                <label className="col-span-2"><span className="label">Notes</span><textarea className="field h-20 py-2" maxLength={1000} value={task.notes} onChange={set('notes')} /></label>
            </div>
            <div className="border-t border-line px-6 py-4"><button className="btn-primary w-full">Create task</button></div>
        </form>
    );
}
