import { useForm } from '@inertiajs/react';
import { Input, Page, Panel, Row, Select, Tabs, TextArea, submit } from '../../react/kit';
import MediaManager from '../../react/media';

const FLAGS = [
    ['reservations_enabled', 'Accepts reservations'],
    ['ordering_enabled', 'Online ordering'],
    ['delivery_enabled', 'Offers delivery'],
    ['room_service_enabled', 'Room service for in-house guests'],
    ['tax_inclusive', 'Menu prices include tax (VAT)'],
];

/** Create or edit a restaurant: profile, services, tax, opening hours, cuisines, and photos when editing. */
export default function RestaurantForm({ restaurant, title, locations, cuisines, days, media, tabs, urls }) {
    const editing = !!restaurant;
    const blank = {
        name: '', tagline: '', description: '', location_id: '', address_line: '', city: '', region: '', phone: '', email: '',
        price_level: 2, reservations_enabled: false, ordering_enabled: false, delivery_enabled: false, room_service_enabled: false,
        tax_inclusive: true, tax_rate: 12, reservation_duration_minutes: 90, cuisines: [], hours: Object.fromEntries(days.map((d) => [d, ''])),
    };
    const form = useForm(editing ? { ...blank, ...Object.fromEntries(Object.entries(restaurant).map(([k, v]) => [k, v ?? blank[k] ?? ''])) } : blank);
    const toggleCuisine = (id) => form.setData('cuisines', form.data.cuisines.includes(id) ? form.data.cuisines.filter((c) => c !== id) : [...form.data.cuisines, id]);

    return (
        <Page
            title={editing ? title : 'New restaurant'}
            subtitle="Profile, services, opening hours and cuisines."
            back={{ href: urls.back, label: editing ? 'Overview' : 'Restaurants' }}
        >
            <Tabs tabs={tabs} />
            <form onSubmit={submit(form, urls.submit, { method: editing ? 'patch' : 'post' })} className="space-y-8">
                <Panel title="Profile" pad>
                    <Row>
                        <Input form={form} name="name" label="Name" required />
                        <Input form={form} name="tagline" label="Tagline" />
                    </Row>
                    <TextArea form={form} name="description" label="Description" rows={4} />
                    <Row cols={3}>
                        <Select form={form} name="location_id" label="Destination" placeholder="—" options={locations} />
                        <Input form={form} name="city" label="City" />
                        <Input form={form} name="region" label="Region" />
                    </Row>
                    <Input form={form} name="address_line" label="Address" />
                    <Row cols={3}>
                        <Input form={form} name="phone" label="Phone" />
                        <Input form={form} name="email" type="email" label="Email" />
                        <Select form={form} name="price_level" label="Price level" options={[1, 2, 3, 4].map((l) => [l, '₱'.repeat(l)])} />
                    </Row>
                </Panel>

                <Panel title="Services & tax" pad>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {FLAGS.map(([name, text]) => <Input key={name} form={form} name={name} type="checkbox" label={text} />)}
                    </div>
                    <Row>
                        <Input form={form} name="reservation_duration_minutes" type="number" min="15" max="480" step="15" label="Sitting length (minutes)" />
                        <Input form={form} name="tax_rate" type="number" min="0" max="50" step="0.01" label="Tax rate (%)" />
                    </Row>
                </Panel>

                <Panel title="Opening hours" aside="e.g. 11:00–22:00 or Closed; blank hides the day" pad>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {days.map((d) => (
                            <label key={d} className="flex items-center gap-3">
                                <span className="w-24 text-[13px] capitalize text-fg-2">{d}</span>
                                <input className="field" value={form.data.hours[d] ?? ''} onChange={(e) => form.setData('hours', { ...form.data.hours, [d]: e.target.value })} />
                            </label>
                        ))}
                    </div>
                </Panel>

                <Panel title="Cuisines" pad>
                    <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-4">
                        {cuisines.map(([id, name]) => (
                            <label key={id} className="flex items-center gap-2 text-[13px] text-fg-2">
                                <input type="checkbox" className="h-4 w-4 accent-[var(--primary)]" checked={form.data.cuisines.includes(id)} onChange={() => toggleCuisine(id)} />
                                {name}
                            </label>
                        ))}
                    </div>
                </Panel>

                <div className="flex justify-end">
                    <button type="submit" className="btn-primary" disabled={form.processing}>{editing ? 'Save changes' : 'Create restaurant'}</button>
                </div>
            </form>

            {editing && <MediaManager media={media} video={false} />}
        </Page>
    );
}
