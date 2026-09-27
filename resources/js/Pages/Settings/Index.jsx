import { Link, useForm } from '@inertiajs/react';
import { Facts, Form, Input, Page, Panel, Split } from '../../react/kit';

/** Business profile (name) plus shortcuts to the other settings. */
export default function Settings({ company, platformSymbol, shortcuts, urls }) {
    const form = useForm({ name: company.name, currency_symbol: company.currency_symbol });

    return (
        <Page title="Business settings" subtitle={`${company.name} · ${company.slug}`}>
            <Split side="340px">
                <Panel title="Business profile">
                    <div className="p-5">
                        <Form form={form} url={urls.update} method="patch" button="Save changes">
                            <Input form={form} name="name" label="Business name" required />
                            <Input form={form} name="currency_symbol" label="Currency symbol" maxLength={4} className="max-w-[200px]" placeholder={platformSymbol}
                                hint={`Shown before every amount, e.g. ${form.data.currency_symbol || platformSymbol}1,250.00. Empty = the platform default (${platformSymbol}).`} />
                        </Form>
                    </div>
                    <div className="border-t border-line"><Facts rows={[['Type', company.type], ['Status', company.status]]} /></div>
                </Panel>
                <Panel title="More settings">
                    <ul className="divide-y divide-line text-[14px]">
                        {[['Team and roles', shortcuts.team], ['Billing and modules', shortcuts.billing], ['Notification settings', shortcuts.notifications, true], ['Your profile and password', shortcuts.profile, true]].map(([label, href, external]) => (
                            <li key={href}>
                                {external
                                    ? <a href={href} className="flex justify-between px-5 py-3 text-fg hover:bg-soft">{label}<span aria-hidden="true" className="text-fg-3">→</span></a>
                                    : <Link href={href} className="flex justify-between px-5 py-3 text-fg hover:bg-soft">{label}<span aria-hidden="true" className="text-fg-3">→</span></Link>}
                            </li>
                        ))}
                    </ul>
                </Panel>
            </Split>
        </Page>
    );
}
