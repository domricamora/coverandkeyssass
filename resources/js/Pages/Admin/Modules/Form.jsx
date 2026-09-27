import { useForm } from '@inertiajs/react';
import { Form, Input, Page, Panel, Row, Select, TextArea } from '../../../react/kit';

/** Create or edit a module. The slug is fixed once created (code refers to it). */
export default function AdminModuleForm({ module, urls }) {
    const form = useForm(module ?? { name: '', slug: '', description: '', category: '', icon: '', trial_days: 0, sort_order: 0, is_core: false });

    return (
        <Page eyebrow="Super Admin" title={module ? `Edit ${module.name}` : 'New module'} subtitle={module?.slug} back={{ href: urls.back, label: 'Modules' }}>
            <Panel pad className="max-w-2xl">
                <Form form={form} url={urls.save} method={module ? 'patch' : 'post'} button={module ? 'Save changes' : 'Create module'}>
                    <Row>
                        <Input form={form} name="name" label="Module name" required />
                        {module
                            ? <Select form={form} name="status" label="Status" options={['active', 'inactive']} />
                            : <Input form={form} name="slug" label="Slug" hint="Lowercase, used in code. Cannot change later." required />}
                    </Row>
                    <TextArea form={form} name="description" label="Description" />
                    <Row>
                        <Input form={form} name="category" label="Category" required />
                        <Input form={form} name="icon" label="Icon (optional)" />
                    </Row>
                    <Row>
                        <Input form={form} name="trial_days" type="number" min="0" label="Trial days" />
                        <Input form={form} name="sort_order" type="number" label="Sort order" />
                    </Row>
                    <Input form={form} name="is_core" type="checkbox" label="Core module (cannot be deleted, on for every business)" />
                </Form>
            </Panel>
        </Page>
    );
}
