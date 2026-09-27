import { Link, useForm } from '@inertiajs/react';
import { Action, Facts, Main, Page, Panel, Split, Stack, Status, Table, Td, submit } from '../../../react/kit';

/** One business: members, host verification, suspend / activate / delete, modules. */
export default function AdminTenantShow({ tenant, members, urls }) {
    const verify = useForm({ verified: !tenant.verified, note: '' });

    return (
        <Page
            eyebrow="Super Admin"
            title={tenant.name}
            subtitle={`${tenant.type} · ${tenant.slug}`}
            back={{ href: urls.back, label: 'Businesses' }}
            actions={(
                <>
                    <Link href={urls.modules} className="btn-ghost btn-sm">Modules</Link>
                    {tenant.status === 'active'
                        ? <Action href={urls.suspend} confirm="Suspend this business? Its members lose access immediately.">Suspend</Action>
                        : <Action href={urls.activate} className="btn-primary btn-sm">Activate</Action>}
                    <Action href={urls.destroy} method="delete" confirm="Delete this business? Its members lose access immediately." className="btn-ghost btn-sm text-bad">Delete</Action>
                </>
            )}
        >
            <Split>
                <Panel title="Members" aside={`${members.length}`}>
                    <Table head={['Member', 'Account']} empty="No members.">
                        {members.map((m) => (
                            <tr key={m.id}>
                                <Td><Main title={m.name} sub={m.email} /></Td>
                                <Td><Status value={m.status} /></Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
                <Stack>
                    <Panel title="Business">
                        <Facts rows={[['Status', <Status value={tenant.status} />], ['Members', tenant.members], ['Created', tenant.created], ['Verified host', tenant.verified ?? 'No'], tenant.verificationNote && ['Checked', tenant.verificationNote]]} />
                    </Panel>
                    <Panel title="Host verification" pad>
                        <form onSubmit={submit(verify, urls.verify)} className="space-y-3">
                            {!tenant.verified && (
                                <input className="field" placeholder="What was checked: permit, ID…" aria-label="Verification note" value={verify.data.note} onChange={(e) => verify.setData('note', e.target.value)} />
                            )}
                            <button type="submit" className={tenant.verified ? 'btn-ghost btn-sm' : 'btn-primary btn-sm'} disabled={verify.processing}>
                                {tenant.verified ? 'Remove verification' : 'Verify host'}
                            </button>
                        </form>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
