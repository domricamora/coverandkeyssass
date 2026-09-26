import { Link, useForm } from '@inertiajs/react';
import { Badge, Form, Input, Page, Panel, Split, Stack, Table, Td } from '../../react/kit';
import { cx } from '../../react/ui';

/** One loyalty member: points history, redeem, adjust, credit and gift cards. */
export default function Member({ account: a, transactions, rewards, cards, can, urls }) {
    const redeem = useForm({ reward_id: rewards.find((r) => r.affordable)?.id ?? '' });
    const adjust = useForm({ points: '', reason: '' });

    return (
        <Page
            title={a.name}
            subtitle={a.summary}
            back={{ href: urls.index, label: 'Loyalty' }}
            actions={<><Badge tone="warn">{a.tier}</Badge>{urls.guest && <Link href={urls.guest} className="btn-ghost">Guest profile</Link>}</>}
        >
            <Split side="340px">
                <Panel title="Points history" aside="last 50">
                    <Table head={['When', 'What', { label: 'Points', right: true }, { label: 'Balance', right: true }]} empty="No activity yet.">
                        {transactions.map((t) => (
                            <tr key={t.id}>
                                <Td muted className="whitespace-nowrap">{t.at}</Td>
                                <Td>{t.what} {t.by && <span className="text-[12px] text-fg-3">{t.by}</span>}</Td>
                                <Td right className={cx(t.minus && 'text-bad')}>{t.points}</Td>
                                <Td right>{t.balance}</Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>
                <Stack>
                    {can.manage && (
                        <>
                            <Panel title="Redeem a reward" pad>
                                <Form form={redeem} url={urls.redeem} button="Redeem">
                                    <select className="field" aria-label="Reward" value={redeem.data.reward_id} onChange={(e) => redeem.setData('reward_id', e.target.value)}>
                                        {rewards.map((r) => <option key={r.id} value={r.id} disabled={!r.affordable}>{r.name}</option>)}
                                    </select>
                                </Form>
                            </Panel>
                            <Panel title="Adjust points" pad>
                                <Form form={adjust} url={urls.adjust} reset button="Adjust">
                                    <Input form={adjust} name="points" type="number" label="Points" placeholder="+50 or -20" required />
                                    <Input form={adjust} name="reason" label="Reason" required />
                                </Form>
                            </Panel>
                        </>
                    )}
                    <Panel title="Credit & gift cards">
                        <ul className="divide-y divide-line text-[13px]">
                            {cards.length === 0 && <li className="px-5 py-4 text-fg-3">None.</li>}
                            {cards.map((c) => <li key={c} className="px-5 py-2.5 text-fg-2">{c}</li>)}
                        </ul>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
