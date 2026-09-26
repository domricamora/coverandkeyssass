import { Link, useForm } from '@inertiajs/react';
import { Action, Form, Input, Page, Panel, Row, Select, Tabs } from '../../react/kit';
import { cx } from '../../react/ui';

/** Weekly roster: one row per employee, shifts and approved leave per day. */
export default function StaffSchedule({ week, days, rows, employees, properties, can, tabs, urls }) {
    const shift = useForm({ employee_id: employees[0]?.[0] ?? '', date: week.start, start: '07:00', end: '15:00', property_id: '', notes: '' });

    return (
        <Page
            title="Schedule"
            subtitle={week.label}
            actions={
                <>
                    <Link href={week.prev} className="btn-ghost btn-sm" aria-label="Previous week">←</Link>
                    <Link href={week.now} className="btn-ghost btn-sm">This week</Link>
                    <Link href={week.next} className="btn-ghost btn-sm" aria-label="Next week">→</Link>
                </>
            }
        >
            <Tabs tabs={tabs} />
            <Panel>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[900px] text-[13px]">
                        <thead className="text-left text-[11px] uppercase tracking-[0.08em] text-fg-3">
                            <tr className="border-b border-line">
                                <th className="sticky left-0 bg-surface px-5 py-2.5 font-medium">Employee</th>
                                {days.map((d) => <th key={d.date} className={cx('px-3 py-2.5 font-medium', d.today && 'text-brand')}>{d.label}</th>)}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-line">
                            {rows.length === 0 && <tr><td colSpan={8} className="px-5 py-10 text-center text-fg-3">Add employees first.</td></tr>}
                            {rows.map((r) => (
                                <tr key={r.id}>
                                    <td className="sticky left-0 min-w-[180px] bg-surface px-5 py-3 align-top">
                                        <span className="block font-medium text-fg">{r.name}</span>
                                        {r.position && <span className="block text-[12px] text-fg-3">{r.position}</span>}
                                    </td>
                                    {r.cells.map((c, i) => (
                                        <td key={i} className={cx('space-y-1 px-3 py-3 align-top', days[i].today && 'bg-brand-soft/40')}>
                                            {c.leave && <span className="pill bg-warn-bg text-warn">{c.leave}</span>}
                                            {c.shifts.map((s) => (
                                                <span key={s.id} className="flex items-center gap-1">
                                                    <span className="pill whitespace-nowrap bg-info-bg text-info">{s.label}</span>
                                                    {s.cancel && <Action href={s.cancel} className="text-fg-3 hover:text-bad" confirm="Cancel this shift?" aria-label="Cancel shift">×</Action>}
                                                </span>
                                            ))}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </Panel>

            {can.manage && employees.length > 0 && (
                <Panel title="Add a shift" pad>
                    <Form form={shift} url={urls.store} button="Add shift">
                        <Row cols={4}>
                            <Select form={shift} name="employee_id" label="Employee" options={employees} />
                            <Input form={shift} name="date" type="date" label="Date" required />
                            <Input form={shift} name="start" type="time" label="Start" required />
                            <Input form={shift} name="end" type="time" label="End" hint="Earlier than start = overnight" required />
                        </Row>
                        <Row>
                            <Select form={shift} name="property_id" label="Property" placeholder="Any property" options={properties} />
                            <Input form={shift} name="notes" label="Notes" />
                        </Row>
                    </Form>
                </Panel>
            )}
        </Page>
    );
}
