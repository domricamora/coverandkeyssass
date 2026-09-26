import { useForm } from '@inertiajs/react';
import { Action, Form, Input, Main, Page, Panel, Row, Select, Split, Status, Table, Td } from '../../react/kit';

/** Promo codes guests and staff can apply to a stay. */
export default function Promotions({ promotions, properties, urls }) {
    const form = useForm({ code: '', name: '', property_id: '', type: 'percent', value: '', starts_on: '', ends_on: '', min_nights: 1, max_uses: '' });

    return (
        <Page title="Promotions" subtitle="Promo codes guests and staff can apply to a booking." back={{ href: urls.bookings, label: 'Bookings' }}>
            <Split side="400px">
                <Panel title="Promo codes">
                    <Table head={['Code', 'Discount', 'Valid', 'Used', 'Status', '']} empty="No promo codes yet.">
                        {promotions.map((p) => (
                            <tr key={p.id}>
                                <Td><Main title={p.code} sub={p.name} /></Td>
                                <Td>{p.discount}</Td>
                                <Td muted className="whitespace-nowrap">{p.valid}</Td>
                                <Td muted>{p.used}</Td>
                                <Td><Status value={p.active ? 'active' : 'inactive'} /></Td>
                                <Td right><Action href={p.toggle} method="patch">{p.active ? 'Deactivate' : 'Activate'}</Action></Td>
                            </tr>
                        ))}
                    </Table>
                </Panel>

                <Panel title="New promo code" pad>
                    <Form form={form} url={urls.store} reset button="Create promo code">
                        <Row>
                            <Input form={form} name="code" label="Code" placeholder="SUMMER10" required />
                            <Select form={form} name="property_id" label="Property" placeholder="All properties" options={properties} />
                        </Row>
                        <Input form={form} name="name" label="Name" required />
                        <Row>
                            <Select form={form} name="type" label="Type" options={[['percent', '% off'], ['fixed', 'Fixed off']]} />
                            <Input form={form} name="value" type="number" step="0.01" min="0" label="Value" required />
                        </Row>
                        <Row>
                            <Input form={form} name="starts_on" type="date" label="Starts" />
                            <Input form={form} name="ends_on" type="date" label="Ends" />
                        </Row>
                        <Row>
                            <Input form={form} name="min_nights" type="number" min="1" label="Min nights" />
                            <Input form={form} name="max_uses" type="number" min="1" label="Max uses" />
                        </Row>
                    </Form>
                </Panel>
            </Split>
        </Page>
    );
}
