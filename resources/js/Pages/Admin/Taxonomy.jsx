import { useForm } from '@inertiajs/react';
import { Action, Page, Panel, Tabs, submit } from '../../react/kit';

/** Locations, property categories, cuisines and amenities: what guests filter by. */
export default function AdminTaxonomy({ type, label, tabs, items, urls }) {
    const flag = type === 'locations' ? 'Featured destination' : 'Visible';
    const region = type === 'locations';
    const group = type === 'property-types' || type === 'amenities';
    const blank = { name: '', slug: '', region: '', category: '', sort_order: '', flag: type !== 'locations' };
    const add = useForm(blank);

    return (
        <Page eyebrow="Super Admin" title="Categories & locations" subtitle="What guests filter by. Hide an entry instead of deleting it once listings use it.">
            <Tabs tabs={tabs} />

            <Panel title={`Add to ${label.toLowerCase()}`} pad>
                <form onSubmit={submit(add, urls.store, { reset: true })} className="flex flex-wrap items-center gap-2">
                    <Fields form={add} region={region} group={group} flag={flag} />
                    <button type="submit" className="btn-primary btn-sm" disabled={add.processing}>Add</button>
                </form>
                {add.errors.name && <p className="text-[12px] text-bad">{add.errors.name}</p>}
                {add.errors.slug && <p className="text-[12px] text-bad">{add.errors.slug}</p>}
            </Panel>

            <Panel title={label} aside={`${items.length}`}>
                {items.length ? (
                    <ul className="divide-y divide-line">
                        {items.map((item) => <Item key={item.id} item={item} region={region} group={group} flag={flag} />)}
                    </ul>
                ) : <p className="px-5 py-10 text-center text-[13px] text-fg-3">Nothing yet.</p>}
            </Panel>
        </Page>
    );
}

function Item({ item, region, group, flag }) {
    const form = useForm({ name: item.name, slug: item.slug, region: item.region, category: item.category, sort_order: item.sort_order, flag: item.flag });
    return (
        <li className="px-5 py-3">
            <form onSubmit={submit(form, item.update, { method: 'put' })} className="flex flex-wrap items-center gap-2">
                <Fields form={form} region={region} group={group} flag={flag} />
                <button type="submit" className="btn-ghost btn-sm" disabled={form.processing || !form.isDirty}>Save</button>
                <Action href={item.destroy} method="delete" confirm={`Delete ${item.name}?`} className="btn-ghost btn-sm text-bad">Delete</Action>
            </form>
            {(form.errors.name || form.errors.slug) && <p className="mt-1 text-[12px] text-bad">{form.errors.name ?? form.errors.slug}</p>}
        </li>
    );
}

function Fields({ form, region, group, flag }) {
    const input = (name, props) => (
        <input className="field" value={form.data[name] ?? ''} onChange={(e) => form.setData(name, e.target.value)} {...props} />
    );
    return (
        <>
            {input('name', { required: true, placeholder: 'Name', 'aria-label': 'Name', className: 'field w-52' })}
            {input('slug', { placeholder: 'slug (auto)', 'aria-label': 'Slug', className: 'field w-44' })}
            {region && input('region', { placeholder: 'Region', 'aria-label': 'Region', className: 'field w-40' })}
            {group && input('category', { placeholder: 'Group (e.g. hotel)', 'aria-label': 'Group', className: 'field w-40' })}
            {input('sort_order', { type: 'number', min: 0, placeholder: 'Order', 'aria-label': 'Sort order', className: 'field w-24' })}
            <label className="flex items-center gap-2 text-[13px] text-fg-2">
                <input type="checkbox" className="h-4 w-4 text-brand" checked={!!form.data.flag} onChange={(e) => form.setData('flag', e.target.checked)} /> {flag}
            </label>
        </>
    );
}
