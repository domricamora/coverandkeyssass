import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Action, Badge, Empty, Form, Input, Page, Panel, Row, Select, Split, Stack, Tabs } from '../../react/kit';
import { PhotoStrip } from '../../react/media';

/** Menu builder: categories, items with prices, modifier groups and options. */
export default function Menu({ restaurant, tabs, categories, can, urls }) {
    const category = useForm({ name: '', description: '' });
    const item = useForm({ menu_category_id: categories[0]?.id ?? '', name: '', price: '', description: '', photo_url: '' });

    return (
        <Page title={restaurant.name} subtitle="Categories, items, prices, modifiers and add-ons.">
            <Tabs tabs={tabs} />
            <Split side="380px">
                <Stack>
                    {categories.length === 0 && <Empty title="No menu yet" body="Start with a category, then add items and their modifiers." />}
                    {categories.map((c) => (
                        <Panel
                            key={c.id}
                            title={<span className="flex items-center gap-2">{c.name} {!c.active && <Badge>Hidden</Badge>}</span>}
                            aside={can.manage && (
                                <span className="flex gap-1.5">
                                    <Action href={c.update} method="patch" data={{ name: c.name, description: c.description, is_active: !c.active }}>{c.active ? 'Hide' : 'Show'}</Action>
                                    <Action href={c.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this category?">Remove</Action>
                                </span>
                            )}
                        >
                            {c.description && <p className="border-b border-line px-5 py-2.5 text-[13px] text-fg-3">{c.description}</p>}
                            <div className="border-b border-line"><PhotoStrip photos={c.photos} label="Section banner" editable={can.manage} /></div>
                            {c.items.length === 0 && <p className="px-5 py-6 text-[13px] text-fg-3">No items in this category yet.</p>}
                            <ul className="divide-y divide-line">
                                {c.items.map((i) => <Item key={i.id} item={i} can={can} />)}
                            </ul>
                        </Panel>
                    ))}
                </Stack>

                {can.manage && (
                    <Stack>
                        <Panel title="Add category" pad>
                            <Form form={category} url={urls.addCategory} reset button="Add category">
                                <Input form={category} name="name" label="Name" placeholder="Burgers" required />
                                <Input form={category} name="description" label="Description" />
                            </Form>
                        </Panel>
                        {categories.length > 0 && (
                            <Panel title="Add menu item" pad>
                                <Form form={item} url={urls.addItem} reset button="Add item">
                                    <Row>
                                        <Select form={item} name="menu_category_id" label="Category" options={categories.map((c) => [c.id, c.name])} />
                                        <Input form={item} name="price" type="number" step="0.01" min="0" label="Price (₱)" required />
                                    </Row>
                                    <Input form={item} name="name" label="Name" placeholder="Burger" required />
                                    <Input form={item} name="description" label="Description" />
                                    <Input form={item} name="photo_url" type="url" label="Photo URL" />
                                </Form>
                            </Panel>
                        )}
                    </Stack>
                )}
            </Split>
        </Page>
    );
}

function Item({ item: i, can }) {
    const [adding, setAdding] = useState(false);
    const group = useForm({ name: '', min_select: 0, max_select: '' });
    const opts = { preserveScroll: true };

    return (
        <li className="px-5 py-4">
            <div className="flex flex-wrap items-start gap-4">
                {!i.photos.photos.length && i.fields.photo_url && <img src={i.fields.photo_url} alt="" className="h-14 w-14 object-cover" loading="lazy" />}
                <div className="min-w-0 flex-1">
                    <p className="text-[14px] text-fg"><span className="font-medium">{i.fields.name}</span> <span className="text-fg-2">· {i.price}</span> {!i.available && <Badge tone="warn">Unavailable</Badge>}</p>
                    {i.fields.description && <p className="mt-0.5 text-[13px] text-fg-3">{i.fields.description}</p>}
                </div>
                {can.manage && (
                    <span className="flex gap-1.5">
                        <Action href={i.update} method="patch" data={{ ...i.fields, is_available: !i.available }}>{i.available ? 'Mark unavailable' : 'Mark available'}</Action>
                        <Action href={i.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this item?">Remove</Action>
                    </span>
                )}
            </div>
            <div className="-mx-5 mt-2"><PhotoStrip photos={i.photos} label="Dish photos" editable={can.manage} /></div>

            {i.groups.map((g) => (
                <div key={g.id} className="ml-2 mt-3 border-l-2 border-line pl-4 text-[13px]">
                    <p className="flex items-center gap-2">
                        <strong className="font-medium text-fg">{g.name}</strong> <span className="text-fg-3">{g.rule}</span>
                        {can.manage && <button className="text-fg-3 hover:text-bad" aria-label={`Remove group ${g.name}`} onClick={() => router.delete(g.destroy, opts)}>×</button>}
                    </p>
                    <ul className="mt-1 flex flex-wrap gap-1.5">
                        {g.options.map((o) => (
                            <li key={o.id} className="pill bg-soft text-fg-2">
                                {o.name} +{o.price}
                                {can.manage && <button className="hover:text-bad" aria-label={`Remove option ${o.name}`} onClick={() => router.delete(o.destroy, opts)}>×</button>}
                            </li>
                        ))}
                    </ul>
                    {can.manage && (
                        <form
                            className="mt-2 flex flex-wrap gap-2"
                            onSubmit={(e) => { e.preventDefault(); const f = e.currentTarget; router.post(g.addOption, { name: f.name.value, price: f.price.value }, { ...opts, onSuccess: () => f.reset() }); }}
                        >
                            <input name="name" required className="field w-40" placeholder="Cheese" aria-label="Option name" />
                            <input name="price" type="number" step="0.01" min="0" className="field w-24" placeholder="+₱" aria-label="Option price" />
                            <button className="btn-ghost btn-sm h-9">Add option</button>
                        </form>
                    )}
                </div>
            ))}

            {can.manage && (adding ? (
                <form className="ml-2 mt-3 flex flex-wrap items-end gap-2" onSubmit={(e) => { e.preventDefault(); group.post(i.addGroup, { ...opts, onSuccess: () => { group.reset(); setAdding(false); } }); }}>
                    <Input form={group} name="name" label="Group" placeholder="Add-ons" required className="w-40" />
                    <Input form={group} name="min_select" type="number" min="0" label="Min" className="w-20" />
                    <Input form={group} name="max_select" type="number" min="1" label="Max" placeholder="Any" className="w-20" />
                    <button className="btn-primary btn-sm h-9">Add group</button>
                    <button type="button" className="btn-ghost btn-sm h-9" onClick={() => setAdding(false)}>Cancel</button>
                </form>
            ) : (
                <button className="ml-2 mt-3 text-[12px] text-fg-3 hover:text-fg" onClick={() => setAdding(true)}>+ Modifier group / add-ons</button>
            ))}
        </li>
    );
}
