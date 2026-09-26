import { router, useForm } from '@inertiajs/react';
import { Action, Form, Input, Main, Page, Panel, Select, Split, Table, Td, Tabs } from '../../react/kit';
import { label } from '../../react/ui';

/** Assign business members to one property's daily operations. */
export default function PropertyStaff({ property, tabs, staff, roles, urls }) {
    const form = useForm({ email: '', role: roles[0] });

    return (
        <Page title={property.name} subtitle="Assign business members to this property's daily operations.">
            <Tabs tabs={tabs} />
            <Split>
                <Panel title="Property staff">
                    <Table head={['Member', 'Property role', 'Assigned', '']} empty="No staff assigned to this property yet.">
                        {staff.map((m) => (
                            <tr key={m.id}>
                                <Td><Main title={m.name} sub={m.email} /></Td>
                                <Td>
                                    <select className="field w-48" aria-label={`Role of ${m.name}`} value={m.role} onChange={(e) => router.patch(m.update, { role: e.target.value }, { preserveScroll: true })}>
                                        {roles.map((r) => <option key={r} value={r}>{label(r)}</option>)}
                                    </select>
                                </Td>
                                <Td muted>{m.since ?? '—'}</Td>
                                <Td right><Action href={m.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this member from the property?">Remove</Action></Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
                <Panel title="Assign a member" pad>
                    <Form form={form} url={urls.store} reset button="Assign">
                        <Input form={form} name="email" type="email" label="Member email" hint="Must already be on the business team." required />
                        <Select form={form} name="role" label="Property role" options={roles} />
                    </Form>
                </Panel>
            </Split>
        </Page>
    );
}
