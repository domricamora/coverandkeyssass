import { Head, useForm } from '@inertiajs/react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { BTN, FIELD, LABEL } from '../../widgets/ui';

/** Start a conversation with a host (about a stay, order or table) or with support. */
export default function MessageNew({ title, subject, support, tabs, urls }) {
    const form = useForm({ subject, body: '', files: [] });

    return (
        <>
            <Head title={title} />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">{title}</h1>
            <AccountTabs tabs={tabs} />
            <form onSubmit={(e) => { e.preventDefault(); form.post(urls.store, { forceFormData: true }); }} className="max-w-2xl space-y-4">
                <label className="block">
                    <span className={LABEL}>Subject</span>
                    <input required className={FIELD} value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                    {form.errors.subject && <span className="mt-1 block text-[13px] text-bad">{form.errors.subject}</span>}
                </label>
                <label className="block">
                    <span className={LABEL}>Message</span>
                    <textarea rows={6} required className={`${FIELD} h-auto py-2.5`} value={form.data.body} onChange={(e) => form.setData('body', e.target.value)}
                        placeholder={support ? 'Tell us what happened and we will get back to you' : 'Ask about arrival, dietary needs, anything'} />
                    {form.errors.body && <span className="mt-1 block text-[13px] text-bad">{form.errors.body}</span>}
                </label>
                <label className="block">
                    <span className={LABEL}>Photos or PDFs <span className="font-normal text-fg-3">(optional, up to 3)</span></span>
                    <input type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" className="text-[13px] text-fg-2" onChange={(e) => form.setData('files', [...e.target.files])} />
                    {form.errors['files.0'] && <span className="mt-1 block text-[13px] text-bad">{form.errors['files.0']}</span>}
                </label>
                <div className="flex items-center gap-4">
                    <button type="submit" disabled={form.processing} className={`${BTN} w-auto`}>Send message</button>
                    <a href={urls.inbox} className="text-[14px] text-fg-3 hover:text-fg">Cancel</a>
                </div>
            </form>
        </>
    );
}

MessageNew.layout = (page) => <PublicShell>{page}</PublicShell>;
