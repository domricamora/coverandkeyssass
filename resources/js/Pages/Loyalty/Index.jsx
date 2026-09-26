import { useForm } from '@inertiajs/react';
import { Action, Badge, Form, Input, Main, Page, Pager, Panel, Row, Search, Select, Split, Stack, Stats, Table, Td } from '../../react/kit';

/** Loyalty programme: members, tiers, rewards, gift cards and store credit. */
export default function Loyalty({ program, tiers, members, rewards, promotions, giftCards, outstanding, contacts, q, can, urls }) {
    const settings = useForm(program);
    const reward = useForm({ name: '', points_cost: '', kind: 'credit', credit_amount: '', promotion_id: '' });
    const card = useForm({ amount: '', paid_via: 'cash', crm_contact_id: '', expires_on: '' });

    return (
        <Page
            title="Loyalty"
            subtitle={`Programme ${program.enabled ? 'on' : 'off'} · ₱${program.pesos_per_point} spending = 1 point`}
            actions={<Search url={urls.self} value={q} placeholder="Member or referral code" />}
        >
            <Stats items={[...tiers, ['Credit outstanding', outstanding]]} />
            <Split side="360px">
                <Stack>
                    <Panel title="Members">
                        <Table head={['Member', 'Tier', { label: 'Points', right: true }, { label: 'Lifetime', right: true }, 'Referral code']} empty="No members yet. Guests join automatically when they earn.">
                            {members.data.map((m) => (
                                <tr key={m.id}>
                                    <Td><Main href={m.href} title={m.name} sub={m.email} /></Td>
                                    <Td><Badge tone="warn">{m.tier}</Badge></Td>
                                    <Td right>{m.points}</Td>
                                    <Td right muted>{m.lifetime}</Td>
                                    <Td className="font-mono text-[12px]">{m.code}</Td>
                                </tr>
                            ))}
                        </Table>
                        <Pager page={members} />
                    </Panel>
                    <Panel title="Gift cards & store credit" aside="latest 20">
                        <Table head={['Code', 'Guest', { label: 'Balance', right: true }, '', '']} empty="No cards yet.">
                            {giftCards.map((c) => (
                                <tr key={c.id}>
                                    <Td><span className="font-mono text-[12px]">{c.code}</span> <Badge>{c.kind}</Badge></Td>
                                    <Td muted>{c.guest}</Td>
                                    <Td right>{c.balance}</Td>
                                    <Td muted>{c.note}</Td>
                                    <Td right>{can.manage && c.void && <Action href={c.void} className="btn-ghost btn-sm" confirm="Void this card?">Void</Action>}</Td>
                                </tr>
                            ))}
                        </Table>
                    </Panel>
                </Stack>

                <Stack>
                    {can.manage && (
                        <Panel title="Programme" pad>
                            <Form form={settings} url={urls.program} method="patch">
                                <Input form={settings} name="enabled" type="checkbox" label="Guests earn points" />
                                <Row>
                                    <Input form={settings} name="pesos_per_point" type="number" min="1" label="₱ per point" />
                                    <Input form={settings} name="referral_points" type="number" min="0" label="Referral bonus" />
                                </Row>
                            </Form>
                        </Panel>
                    )}
                    <Panel title="Rewards">
                        <ul className="divide-y divide-line text-[13px]">
                            {rewards.length === 0 && <li className="px-5 py-4 text-fg-3">No rewards yet.</li>}
                            {rewards.map((r) => (
                                <li key={r.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
                                    <span className="text-fg-2">{r.text} {!r.active && <Badge>retired</Badge>}</span>
                                    {can.manage && <Action href={r.toggle}>{r.active ? 'Retire' : 'Restore'}</Action>}
                                </li>
                            ))}
                        </ul>
                        {can.manage && (
                            <div className="border-t border-line p-5">
                                <Form form={reward} url={urls.addReward} reset button="Add reward">
                                    <Input form={reward} name="name" label="Reward" placeholder="₱500 dining credit" required />
                                    <Row>
                                        <Input form={reward} name="points_cost" type="number" min="1" label="Points" required />
                                        <Select form={reward} name="kind" label="Kind" options={[['credit', 'Store credit'], ['coupon', 'Coupon']]} />
                                    </Row>
                                    {reward.data.kind === 'credit'
                                        ? <Input form={reward} name="credit_amount" type="number" step="0.01" min="1" label="Credit (₱)" />
                                        : <Select form={reward} name="promotion_id" label="Coupon from" placeholder="Pick a promotion" options={promotions} />}
                                </Form>
                            </div>
                        )}
                    </Panel>
                    {can.manage && (
                        <Panel title="Sell a gift card" pad>
                            <Form form={card} url={urls.sellCard} reset button="Issue card">
                                <Row>
                                    <Input form={card} name="amount" type="number" step="0.01" min="1" label="Value (₱)" required />
                                    <Select form={card} name="paid_via" label="Paid via" options={[['cash', 'Cash'], ['bank', 'Card / bank']]} />
                                </Row>
                                <Select form={card} name="crm_contact_id" label="For guest" placeholder="Bearer (no guest)" options={contacts} />
                                <Input form={card} name="expires_on" type="date" label="Expires on" />
                            </Form>
                        </Panel>
                    )}
                </Stack>
            </Split>
        </Page>
    );
}
