import { Link, useForm } from '@inertiajs/react';
import { Badge, Filter, Form, Input, Main, Page, Panel, Row, Select, Split, Stack, Table, Tabs, Td } from '../../react/kit';

/** Staff: employees, departments and positions. */
export default function StaffIndex({ employees, active, departments, positions, department, options, can, tabs, urls }) {
    const hire = useForm({ name: '', email: '', phone: '', department_id: '', position_id: '', employment_type: 'full_time', hire_date: '', property_id: '', user_id: '' });
    const dept = useForm({ name: '' });
    const pos = useForm({ name: '', department_id: '', hourly_rate: '' });

    return (
        <Page
            title="Staff"
            subtitle={`${active} active employees · ${departments.length} departments`}
            actions={departments.length > 0 && <Filter name="department" value={department} url={urls.self} placeholder="All departments" options={departments.map((d) => [d.id, d.name])} />}
        >
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Panel title="Employees" aside={`${employees.length} shown`}>
                    <Table head={['Employee', 'Department', 'Position', 'Type', 'Login']} empty="No employees yet.">
                        {employees.map((e) => (
                            <tr key={e.id} className={e.terminated ? 'opacity-55' : undefined}>
                                <Td><Main href={e.href} title={e.name} sub={e.no} /> {e.terminated && <Badge>terminated</Badge>}</Td>
                                <Td muted>{e.department ?? '—'}</Td>
                                <Td muted>{e.position ?? '—'}</Td>
                                <Td muted className="whitespace-nowrap">{e.type}</Td>
                                <Td muted>{e.account ?? '—'}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>

                <Stack>
                    {can.manage && (
                        <Panel title="Add employee" pad>
                            <Form form={hire} url={urls.store} button="Add employee">
                                <Input form={hire} name="name" label="Full name" required />
                                <Row>
                                    <Input form={hire} name="email" type="email" label="Email" />
                                    <Input form={hire} name="phone" label="Phone" />
                                </Row>
                                <EmployeeFields form={hire} options={options} />
                            </Form>
                        </Panel>
                    )}
                    <Panel title="Departments">
                        <ul className="divide-y divide-line text-[13px]">
                            {departments.length === 0 && <li className="px-5 py-4 text-fg-3">None yet.</li>}
                            {departments.map((d) => (
                                <li key={d.id} className="flex justify-between px-5 py-2.5">
                                    <Link href={`${urls.self}?department=${d.id}`} className="text-fg hover:text-brand">{d.name}</Link>
                                    <span className="tabular-nums text-fg-3">{d.count}</span>
                                </li>
                            ))}
                        </ul>
                        {can.manage && (
                            <div className="border-t border-line p-5">
                                <Form form={dept} url={urls.departments} reset button="Add department">
                                    <Input form={dept} name="name" label="Department" placeholder="Housekeeping" required />
                                </Form>
                            </div>
                        )}
                    </Panel>
                    <Panel title="Positions">
                        <ul className="divide-y divide-line text-[13px]">
                            {positions.length === 0 && <li className="px-5 py-4 text-fg-3">None yet.</li>}
                            {positions.map((p) => <li key={p.id} className="px-5 py-2.5 text-fg-2">{p.text}</li>)}
                        </ul>
                        {can.manage && (
                            <div className="border-t border-line p-5">
                                <Form form={pos} url={urls.positions} reset button="Add position">
                                    <Input form={pos} name="name" label="Position" placeholder="Room Attendant" required />
                                    <Row>
                                        <Select form={pos} name="department_id" label="Department" placeholder="None" options={options.departments} />
                                        <Input form={pos} name="hourly_rate" type="number" step="0.01" min="0" label="₱ per hour" />
                                    </Row>
                                </Form>
                            </div>
                        )}
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}

/** Assignment fields shared by "Add employee" and the profile form. */
export function EmployeeFields({ form, options }) {
    return (
        <>
            <Row>
                <Select form={form} name="department_id" label="Department" placeholder="None" options={options.departments} />
                <Select form={form} name="position_id" label="Position" placeholder="None" options={options.positions} />
            </Row>
            <Row>
                <Select form={form} name="employment_type" label="Type" options={options.types} />
                <Input form={form} name="hire_date" type="date" label="Hire date" />
            </Row>
            <Row>
                <Select form={form} name="property_id" label="Home property" placeholder="Any property" options={options.properties} />
                <Select form={form} name="user_id" label="Login account" placeholder="No login account" options={options.members} />
            </Row>
        </>
    );
}
