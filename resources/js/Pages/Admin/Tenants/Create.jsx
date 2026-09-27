import { useForm } from '@inertiajs/react';
import { Form, Input, Page, Panel, Select } from '../../../react/kit';

/** Create a business for an existing user, who becomes its owner. */
export default function AdminTenantCreate({ types, urls }) {
    const form = useForm({ name: '', business_type: Object.keys(types)[0], owner_email: '' });

    return (
        <Page eyebrow="Super Admin" title="New business" subtitle="The owner must already have an account; they get the owner role." back={{ href: urls.back, label: 'Businesses' }}>
            <Panel pad className="max-w-xl">
                <Form form={form} url={urls.store} button="Create business">
                    <Input form={form} name="name" label="Business name" required />
                    <Select form={form} name="business_type" label="Business type" options={Object.entries(types)} />
                    <Input form={form} name="owner_email" type="email" label="Owner email (existing user)" required />
                </Form>
            </Panel>
        </Page>
    );
}
