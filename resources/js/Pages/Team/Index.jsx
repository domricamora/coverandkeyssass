import { router, useForm } from '@inertiajs/react';
import { Action, Badge, Form, Input, Page, Panel, Row, Select, Table, Td } from '../../react/kit';

/** People with access to the business: add by email, change role, remove. */
export default function Team({ business, members, roles, urls }) {
    const add = useForm({ name: '', email: '', role: 'staff' });

    return (
        <Page title="Team" subtitle={`People with access to ${business}`}>
            <Panel title="Members" aside={`${members.length} people`}>
                <Table head={['Name', 'Email', 'Role', '']} empty="No members yet. Invite your first teammate below.">
                    {members.map((m) => (
                        <tr key={m.id}>
                            <Td className="font-medium text-fg">{m.name} {m.me && <Badge>you</Badge>}</Td>
                            <Td muted>{m.email}</Td>
                            <Td>
                                <select
                                    className="field h-8 w-auto"
                                    value={m.role ?? ''}
                                    aria-label={`Role for ${m.name}`}
                                    onChange={(e) => router.patch(m.urls.role, { role: e.target.value }, { preserveScroll: true })}
                                >
                                    {m.role === null && <option value="">No role</option>}
                                    {roles.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                                </select>
                            </Td>
                            <Td right>
                                {!m.me && <Action href={m.urls.remove} method="delete" className="btn-ghost btn-sm text-bad" confirm={`Remove ${m.name} from ${business}?`}>Remove</Action>}
                            </Td>
                        </tr>
                    ))}
                </Table>
            </Panel>

            <Panel title="Add a team member" pad>
                <Form form={add} url={urls.add} reset button="Add member">
                    <Row cols={3}>
                        <Input form={add} name="name" label="Name" placeholder="Juan Dela Cruz" required />
                        <Input form={add} name="email" type="email" label="Email" placeholder="juan@example.com" required />
                        <Select form={add} name="role" label="Role" options={roles} />
                    </Row>
                </Form>
                <p className="text-[12px] text-fg-3">New members get an account with a generated password; they set their own through "Forgot password".</p>
            </Panel>
        </Page>
    );
}
