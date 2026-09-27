import { useForm } from '@inertiajs/react';
import { Form, Input, Page, Panel } from '../../react/kit';

/** Platform-wide settings: support contacts, site announcement, default commission. */
export default function AdminSettings({ fields, urls }) {
    const form = useForm(Object.fromEntries(fields.map((f) => [f.key, f.value])));

    return (
        <Page eyebrow="Super Admin" title="Platform settings" subtitle="Changes apply across the public site at once.">
            <Panel pad className="max-w-2xl">
                <Form form={form} url={urls.save} method="put" button="Save settings">
                    {fields.map((f) => <Input key={f.key} form={form} name={f.key} label={f.label} hint={f.help} />)}
                </Form>
            </Panel>
        </Page>
    );
}
