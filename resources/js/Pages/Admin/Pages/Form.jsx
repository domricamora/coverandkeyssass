import { useForm } from '@inertiajs/react';
import { Form, Input, Page, Panel, Row, TextArea } from '../../../react/kit';

/** Create or edit a CMS page. The body is Markdown (no HTML). */
export default function AdminPageForm({ page, urls }) {
    const form = useForm(page ?? { title: '', slug: '', meta_description: '', body: '', is_published: false, in_footer: false });

    return (
        <Page
            eyebrow="Super Admin"
            title={page ? `Edit ${page.title}` : 'New page'}
            back={{ href: urls.back, label: 'CMS pages' }}
            actions={urls.view && <a href={urls.view} className="btn-ghost btn-sm">View page</a>}
        >
            <Panel pad className="max-w-4xl">
                <Form form={form} url={urls.save} method={page ? 'put' : 'post'} button="Save page">
                    <Row>
                        <Input form={form} name="title" label="Title" required />
                        <Input form={form} name="slug" label="Address" hint={`/pages/${form.data.slug || 'your-page'}`} pattern="[A-Za-z0-9_-]+" required />
                    </Row>
                    <Input form={form} name="meta_description" label="Meta description" maxLength={300} hint="Shown by search engines, up to 300 characters." />
                    <TextArea form={form} name="body" label="Body" rows={18} className="[&_textarea]:font-mono" hint="Markdown: # heading, **bold**, [link](https://…), - lists. HTML is not allowed." required />
                    <div className="flex flex-wrap gap-6">
                        <Input form={form} name="is_published" type="checkbox" label="Published" />
                        <Input form={form} name="in_footer" type="checkbox" label="Link in the site footer" />
                    </div>
                </Form>
            </Panel>
        </Page>
    );
}
