import { usePage } from '@inertiajs/react';
import { Action, Filter, Main, Page, Pager, Panel, Search, Status, Table, Td } from '../../react/kit';

/** Every account on the platform: hosts (members of a business), customers and admins. */
export default function AdminUsers({ users, filters }) {
    const here = usePage().url.split('?')[0];

    return (
        <Page
            eyebrow="Super Admin"
            title="Users"
            subtitle="Suspending a user signs them out everywhere at once."
            actions={(
                <>
                    <Search url={here} value={filters.q} params={filters} placeholder="Name or email" />
                    <Filter name="type" value={filters.type} url={here} params={filters} placeholder="Everyone" options={[['hosts', 'Hosts'], ['customers', 'Customers']]} />
                </>
            )}
        >
            <Panel>
                <Table head={['User', 'Roles', 'Status', { label: '', right: true }]} empty="No users found.">
                    {users.data.map((u) => (
                        <tr key={u.id}>
                            <Td><Main title={u.name} sub={u.email} /></Td>
                            <Td muted>{u.roles || '—'}</Td>
                            <Td><Status value={u.status} /></Td>
                            <Td right>
                                {u.protected ? <span className="text-[12px] text-fg-3">Protected</span>
                                    : u.status === 'active' ? <Action href={u.suspend} confirm={`Suspend ${u.name}? They are signed out at once.`} className="btn-ghost btn-sm text-bad">Suspend</Action>
                                        : <Action href={u.activate}>Activate</Action>}
                            </Td>
                        </tr>
                    ))}
                </Table>
                <Pager page={users} />
            </Panel>
        </Page>
    );
}
