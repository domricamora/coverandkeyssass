import { Link, useForm } from '@inertiajs/react';
import { Badge, Form, Input, Page, Panel, Row, Select, Split, Stack, Status, Table, Tabs, Td } from '../../react/kit';
import { EmployeeFields } from './Index';

/** Employee profile: upcoming shifts, attendance, leave, and the edit form. */
export default function StaffShow({ employee, shifts, attendances, leave, options, can, tabs, urls }) {
    const form = useForm(employee.form);

    return (
        <Page
            title={employee.name}
            eyebrow={employee.no}
            subtitle={<>{employee.summary}{employee.linked && can.team && <> · <Link href={urls.team} className="text-brand hover:underline">manage access</Link></>}</>}
            back={{ href: urls.index, label: 'All staff' }}
        >
            <Tabs tabs={tabs} />
            <Split side="380px">
                <Stack>
                    <Panel title="Upcoming shifts">
                        <ul className="divide-y divide-line text-[13px]">
                            {shifts.length === 0 && <li className="px-5 py-4 text-fg-3">None scheduled.</li>}
                            {shifts.map((s) => <li key={s.id} className="flex justify-between px-5 py-2.5"><span className="text-fg">{s.day}</span><span className="tabular-nums text-fg-2">{s.label}</span></li>)}
                        </ul>
                    </Panel>
                    <Panel title="Attendance" aside="latest 15">
                        <Table head={['In', 'Out', { label: 'Worked', right: true }, 'Late']} empty="No records.">
                            {attendances.map((a) => (
                                <tr key={a.id}>
                                    <Td>{a.in}</Td>
                                    <Td muted>{a.out ?? '—'}</Td>
                                    <Td right>{a.worked}</Td>
                                    <Td>{a.late ? <Badge tone="warn">{a.late} min</Badge> : '—'}</Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                    <Panel title="Leave">
                        <ul className="divide-y divide-line text-[13px]">
                            {leave.length === 0 && <li className="px-5 py-4 text-fg-3">No requests.</li>}
                            {leave.map((l) => <li key={l.id} className="flex items-center justify-between gap-3 px-5 py-2.5"><span className="text-fg-2">{l.text}</span><Status value={l.status} /></li>)}
                        </ul>
                    </Panel>
                </Stack>

                {can.manage && (
                    <Panel title="Profile" pad>
                        <Form form={form} url={urls.update} method="patch">
                            <Input form={form} name="name" label="Name" required />
                            <Row>
                                <Input form={form} name="email" type="email" label="Email" />
                                <Input form={form} name="phone" label="Phone" />
                            </Row>
                            <EmployeeFields form={form} options={options} />
                            <Select form={form} name="status" label="Status" options={[['active', 'Active'], ['terminated', 'Terminated']]} />
                        </Form>
                    </Panel>
                )}
            </Split>
        </Page>
    );
}
