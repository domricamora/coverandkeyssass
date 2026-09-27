import { Link, useForm } from '@inertiajs/react';
import { Action, Form, Input, Main, Page, Panel, Row, Select, Stats, Table, Td } from '../../react/kit';

/** Commission rates: one global rate, plus per-listing and dated promotional rates. */
export default function AdminCommissions({ totals, globalRate, globalSet, defaultRate, rates, urls }) {
    const global = useForm({ kind: 'global', rate: globalRate });
    const rate = useForm({ kind: 'listing', rate: '', listing_type: 'property', listing_slug: '', name: '', starts_on: '', ends_on: '' });
    const promo = rate.data.kind === 'promotional';

    return (
        <Page eyebrow="Super Admin" title="Commissions" subtitle="What the platform keeps from online payments. New rates apply to payments from now on." actions={<Link href={urls.payouts} className="btn-ghost btn-sm">Payouts</Link>}>
            <Stats items={totals} />

            <div className="grid items-start gap-8 xl:grid-cols-[360px_minmax(0,1fr)]">
                <Panel title="Global rate" pad>
                    <Form form={global} url={urls.store} button="Save global rate">
                        <Input form={global} name="rate" type="number" step="0.01" min="0" max="100" label="Rate (%)" hint={globalSet ? 'Set by an admin.' : `Using the default (${defaultRate}%) until saved.`} required />
                    </Form>
                </Panel>

                <Panel title="Add a listing or promotional rate" pad>
                    <Form form={rate} url={urls.store} reset button="Add rate">
                        <Row cols={4}>
                            <Select form={rate} name="kind" label="Kind" options={[['listing', 'Listing rate'], ['promotional', 'Promotional (dated)']]} />
                            <Input form={rate} name="rate" type="number" step="0.01" min="0" max="100" label="Rate (%)" required />
                            <Select form={rate} name="listing_type" label="Listing type" options={[['property', 'Property'], ['restaurant', 'Restaurant']]} />
                            <Input form={rate} name="listing_slug" label="Listing slug" hint={promo ? 'Blank = all listings' : undefined} />
                        </Row>
                        <Row cols={3}>
                            <Input form={rate} name="name" label="Name (optional)" />
                            {promo && <Input form={rate} name="starts_on" type="date" label="Starts" required />}
                            {promo && <Input form={rate} name="ends_on" type="date" label="Ends" required />}
                        </Row>
                    </Form>
                </Panel>
            </div>

            <Panel title="Listing and promotional rates">
                <Table head={['Kind', 'Applies to', { label: 'Rate', right: true }, 'Window', { label: '', right: true }]} empty="Only the global rate applies.">
                    {rates.map((r) => (
                        <tr key={r.id}>
                            <Td><Main title={r.kind === 'listing' ? 'Listing' : 'Promotional'} sub={r.name} /></Td>
                            <Td muted>{r.appliesTo}</Td>
                            <Td right>{r.rate}</Td>
                            <Td muted className="whitespace-nowrap">{r.window}</Td>
                            <Td right><Action href={r.destroy} method="delete" confirm="Remove this rate?" className="btn-ghost btn-sm text-bad">Remove</Action></Td>
                        </tr>
                    ))}
                </Table>
            </Panel>
        </Page>
    );
}
