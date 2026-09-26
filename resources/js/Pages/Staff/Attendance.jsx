import { router } from '@inertiajs/react';
import { Action, Badge, Page, Panel, Split, Table, Tabs, Td } from '../../react/kit';

/** Attendance for one day, plus clocking in / out on someone's behalf. */
export default function StaffAttendance({ date, label, onClock, records, employees, can, tabs, urls }) {
    return (
        <Page
            title="Attendance"
            subtitle={`${label} · ${onClock} on the clock now`}
            actions={<input type="date" className="field w-auto" value={date} aria-label="Date" onChange={(e) => router.get(urls.self, { date: e.target.value }, { preserveState: true })} />}
        >
            <Tabs tabs={tabs} />
            <Split side="340px">
                <Panel title="Clock-ins">
                    <Table head={['Employee', 'Shift', 'In', 'Out', { label: 'Worked', right: true }, 'Late']} empty="No clock-ins on this day.">
                        {records.map((a) => (
                            <tr key={a.id}>
                                <Td className="font-medium text-fg">{a.name}</Td>
                                <Td muted>{a.shift}</Td>
                                <Td>{a.in}</Td>
                                <Td muted>{a.out ?? '—'}</Td>
                                <Td right>{a.worked}</Td>
                                <Td>{a.late ? <Badge tone="warn">{a.late} min</Badge> : '—'}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>

                {can.manage && (
                    <Panel title="Clock for someone">
                        <ul className="divide-y divide-line text-[13px]">
                            {employees.map((e) => (
                                <li key={e.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
                                    <span className="min-w-0">
                                        <span className="block truncate text-fg">{e.name}</span>
                                        {e.since && <span className="text-[12px] text-ok">in since {e.since}</span>}
                                    </span>
                                    <Action href={e.clock} data={{ action: e.since ? 'out' : 'in' }}>Clock {e.since ? 'out' : 'in'}</Action>
                                </li>
                            ))}
                        </ul>
                    </Panel>
                )}
            </Split>
        </Page>
    );
}
