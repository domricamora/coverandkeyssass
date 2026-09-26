import { useForm } from '@inertiajs/react';
import { Input, Page, Panel, Row, Select, Tabs, TextArea, submit } from '../../react/kit';
import MediaManager from '../../react/media';

const BLANK = {
    name: '', tagline: '', description: '', property_type_id: '', location_id: '', address_line: '', city: '', region: '',
    max_guests: 2, bedrooms: 1, beds: 1, bathrooms: 1, base_price: '', weekend_price: '', cleaning_fee: 0, currency: 'PHP',
    check_in_time: '14:00', check_out_time: '11:00',
};
const POLICIES = [['cancellation', 'Cancellation'], ['children', 'Children'], ['pets', 'Pets'], ['noise', 'Noise / quiet hours'], ['smoking', 'Smoking']];

/** Create or edit a property: profile, policies, amenities (one save) and, when editing, photos and videos. */
export default function PropertyForm({ property, title, options, media, tabs, urls }) {
    const editing = !!property;
    const form = useForm(editing ? { ...BLANK, ...clean(property) } : BLANK);
    const toggle = (id) => form.setData('amenities', form.data.amenities.includes(id) ? form.data.amenities.filter((a) => a !== id) : [...form.data.amenities, id]);

    return (
        <Page
            title={editing ? title : 'New property'}
            subtitle={editing ? 'Profile, policies, amenities, photos and videos.' : 'Created as a draft. Publish it when the profile is ready.'}
            back={{ href: urls.back, label: editing ? 'Overview' : 'Rooms & rates' }}
        >
            <Tabs tabs={tabs} />
            <form onSubmit={submit(form, urls.submit, { method: editing ? 'patch' : 'post' })} className="space-y-8">
                <Panel title="Profile" pad>
                    <Row>
                        <Input form={form} name="name" label="Property name" required />
                        <Input form={form} name="tagline" label="Tagline" placeholder="Beachfront suites with sunset views" />
                    </Row>
                    <TextArea form={form} name="description" label="Description" rows={4} />
                    <Row>
                        <Select form={form} name="property_type_id" label="Property type" placeholder="—" options={options.types} />
                        <Select form={form} name="location_id" label="Destination" placeholder="—" options={options.locations} />
                    </Row>
                    <Row cols={3}>
                        <Input form={form} name="address_line" label="Address" />
                        <Input form={form} name="city" label="City" />
                        <Input form={form} name="region" label="Region" />
                    </Row>
                    <Row cols={4}>
                        <Input form={form} name="max_guests" type="number" min="1" label="Max guests" required />
                        <Input form={form} name="bedrooms" type="number" min="0" label="Bedrooms" />
                        <Input form={form} name="beds" type="number" min="0" label="Beds" />
                        <Input form={form} name="bathrooms" type="number" min="0" label="Bathrooms" />
                    </Row>
                    <Row cols={4}>
                        <Input form={form} name="base_price" type="number" step="0.01" min="0" label="Nightly rate" required />
                        <Input form={form} name="weekend_price" type="number" step="0.01" min="0" label="Weekend rate" />
                        <Input form={form} name="cleaning_fee" type="number" step="0.01" min="0" label="Cleaning fee" />
                        <Input form={form} name="currency" maxLength={3} label="Currency" />
                    </Row>
                    <Row>
                        <Input form={form} name="check_in_time" type="time" label="Check-in" />
                        <Input form={form} name="check_out_time" type="time" label="Check-out" />
                    </Row>
                </Panel>

                {editing && (
                    <>
                        <Panel title="Policies" pad>
                            <Row>
                                <Input
                                    form={form}
                                    name="policy_free_cancellation_days"
                                    type="number" min="0" max="60"
                                    label="Free cancellation (days before check-in)"
                                    placeholder="Empty = non-refundable"
                                    hint="Shows a “Free cancellation” badge and lets guests filter for it. 0 = free until the day of arrival."
                                />
                                {POLICIES.map(([k, l]) => <TextArea key={k} form={form} name={`policy_${k}`} label={l} rows={2} />)}
                            </Row>
                        </Panel>

                        <Panel title="Amenities" pad>
                            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                {options.amenities.map(([id, name]) => (
                                    <label key={id} className="flex items-center gap-2 text-[13px] text-fg-2">
                                        <input type="checkbox" className="h-4 w-4 accent-[var(--primary)]" checked={form.data.amenities.includes(id)} onChange={() => toggle(id)} />
                                        {name}
                                    </label>
                                ))}
                            </div>
                        </Panel>
                    </>
                )}

                <div className="flex justify-end">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{editing ? 'Save changes' : 'Create property'}</button>
                </div>
            </form>

            {editing && <MediaManager media={media} />}
        </Page>
    );
}

/** null → '' so inputs stay controlled; times trimmed to HH:MM for <input type=time>. */
function clean(p) {
    const out = Object.fromEntries(Object.entries(p).map(([k, v]) => [k, v ?? '']));
    for (const k of ['check_in_time', 'check_out_time']) out[k] = String(out[k]).slice(0, 5);
    return out;
}
