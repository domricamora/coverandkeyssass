import { Link, useForm } from '@inertiajs/react';
import { Action, Badge, Form, Input, Page, Panel, Row, Select, Split, Stack, Status, Tabs } from '../../react/kit';

const List = ({ items, empty, render }) => (
    <ul className="divide-y divide-line text-[13px]">
        {items.length === 0 && <li className="px-5 py-4 text-fg-3">{empty}</li>}
        {items.map(render)}
    </ul>
);

/** Self-service for staff: shifts, assigned rooms and tickets, clock in / out, leave. */
export default function MyWork({ employee, shifts, tasks, tickets, leave, types, today, tabs, urls }) {
    const request = useForm({ type: types[0], starts_on: '', ends_on: '', reason: '' });

    return (
        <Page
            title="My work"
            subtitle={employee ? `${employee.name} · ${employee.no}` : 'Your account is not linked to an employee profile yet. Ask a manager.'}
            actions={employee && (
                <Action href={urls.clock} data={{ action: employee.since ? 'out' : 'in' }} className={employee.since ? 'btn-ghost' : 'btn-primary'}>
                    {employee.since ? `Clock out (in since ${employee.since})` : 'Clock in'}
                </Action>
            )}
        >
            <Tabs tabs={tabs} />
            <div className="grid gap-8 lg:grid-cols-3">
                <Panel title="Shifts" aside="next 7 days">
                    <List items={shifts} empty="No shifts." render={(s) => <li key={s.id} className="px-5 py-2.5 text-fg-2">{s.text}</li>} />
                </Panel>
                <Panel title="Housekeeping" aside={tasks.length > 0 && <Link href={urls.board} className="text-brand hover:underline">Open board</Link>}>
                    <List items={tasks} empty="No rooms assigned." render={(t) => <li key={t.id} className="flex items-center justify-between gap-2 px-5 py-2.5 text-fg-2">{t.text}{t.high && <Badge tone="warn">High</Badge>}</li>} />
                </Panel>
                <Panel title="Maintenance">
                    <List items={tickets} empty="No tickets assigned." render={(t) => (
                        <li key={t.id} className="px-5 py-2.5 text-fg-2"><Link href={t.href} className="font-mono text-[12px] text-brand hover:underline">{t.reference}</Link> · {t.text}</li>
                    )} />
                </Panel>
            </div>

            {employee && (
                <Split side="360px">
                    <Panel title="My leave">
                        <List items={leave} empty="No requests." render={(l) => (
                            <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5">
                                <span className="text-fg-2">{l.text}{l.note && <span className="text-fg-3"> · {l.note}</span>}</span>
                                <span className="flex items-center gap-2">
                                    <Status value={l.status} />
                                    {l.cancel && <Action href={l.cancel} confirm="Withdraw this request?">Withdraw</Action>}
                                </span>
                            </li>
                        )} />
                    </Panel>
                    <Stack>
                        <Panel title="Request leave" pad>
                            <Form form={request} url={urls.leave} reset button="Send request">
                                <Select form={request} name="type" label="Type" options={types} />
                                <Row>
                                    <Input form={request} name="starts_on" type="date" min={today} label="From" required />
                                    <Input form={request} name="ends_on" type="date" min={request.data.starts_on || today} label="To" required />
                                </Row>
                                <Input form={request} name="reason" label="Reason (optional)" />
                            </Form>
                        </Panel>
                    </Stack>
                </Split>
            )}
        </Page>
    );
}
