import { router } from '@inertiajs/react';
import { Action, Page, Panel, Split, Stack, Status, Table, Td } from '../../react/kit';

/** One campaign: message, recipients, send now or schedule. */
export default function Campaign({ campaign: c, can, urls }) {
    return (
        <Page title={c.name} subtitle={c.summary} back={{ href: urls.index, label: 'Marketing' }} actions={<Status value={c.status} />}>
            <Split side="320px">
                <Stack>
                    <Panel title="Message" pad>
                        {c.subject && <p className="font-medium text-fg">{c.subject}</p>}
                        <p className="whitespace-pre-line text-[13px] text-fg-2">{c.body}</p>
                    </Panel>
                    <Panel title="Recipients" aside={c.recipients.length}>
                        <Table head={['Guest', 'Address', 'Coupon', 'Sent']} empty="Not sent yet.">
                            {c.recipients.map((r) => (
                                <tr key={r.id}>
                                    <Td>{r.name}</Td>
                                    <Td muted>{r.address}</Td>
                                    <Td className="font-mono text-[12px]">{r.coupon}</Td>
                                    <Td muted>{r.at}</Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                </Stack>
                {can.send && (
                    <Stack>
                        <Panel title="Send" pad>
                            <Action href={urls.send} className="btn-primary w-full" confirm={`Send to ${c.audience} guest(s) now?`}>Send now</Action>
                            {can.schedule && (
                                <form className="space-y-2" onSubmit={(e) => { e.preventDefault(); router.post(urls.schedule, { scheduled_at: e.currentTarget.at.value }, { preserveScroll: true }); }}>
                                    <label className="label" htmlFor="at">Or send at</label>
                                    <input id="at" name="at" type="datetime-local" required className="field" />
                                    <button className="btn-ghost w-full">Schedule</button>
                                </form>
                            )}
                        </Panel>
                    </Stack>
                )}
            </Split>
        </Page>
    );
}
