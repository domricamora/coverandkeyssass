import { router } from '@inertiajs/react';
import { Empty, Filter, Page, Panel, Tabs } from '../../react/kit';

/** Recipes: what each dish uses; stock is deducted when the kitchen accepts an order. */
export default function Recipes({ restaurants, restaurant, dishes, items, locations, units, tabs, can, urls }) {
    const opts = { preserveScroll: true };

    return (
        <Page
            title="Recipes"
            subtitle="What each dish uses. Stock is deducted when the kitchen accepts an order."
            actions={restaurants.length > 1 && <Filter name="restaurant" value={restaurant?.slug} url={urls.self} options={restaurants} />}
        >
            <Tabs tabs={tabs} />
            {!restaurant ? <Empty title="No restaurant yet" body="Add a restaurant first." /> : (
                <>
                    <div className="flex flex-wrap items-center gap-3 border border-line bg-surface px-5 py-3 text-[13px]">
                        <span className="text-fg-2">Kitchen stock location for {restaurant.name}:</span>
                        <select
                            className="field w-auto"
                            aria-label="Kitchen stock location"
                            disabled={!can.manage}
                            value={restaurant.stock_location_id ?? ''}
                            onChange={(e) => router.post(restaurant.location, { stock_location_id: e.target.value || null }, opts)}
                        >
                            <option value="">Not linked (sales do not touch stock)</option>
                            {locations.map(([v, t]) => <option key={v} value={v}>{t}</option>)}
                        </select>
                    </div>

                    {dishes.length === 0 && <Empty title="No menu items" body="This restaurant has no menu items yet." />}
                    <div className="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                        {dishes.map((d) => (
                            <Panel key={d.id} title={d.name} aside={d.price}>
                                <ul className="divide-y divide-line text-[13px]">
                                    {d.ingredients.length === 0 && <li className="px-5 py-3 text-fg-3">No recipe.</li>}
                                    {d.ingredients.map((ing) => (
                                        <li key={ing.id} className="flex items-center justify-between gap-2 px-5 py-2">
                                            <span className="text-fg-2">{ing.text} {ing.base && <span className="text-fg-3">({ing.base})</span>}</span>
                                            {can.manage && <button className="text-fg-3 hover:text-bad" aria-label="Remove ingredient" onClick={() => router.delete(ing.destroy, opts)}>×</button>}
                                        </li>
                                    ))}
                                </ul>
                                {can.manage && items.length > 0 && (
                                    <form
                                        className="grid grid-cols-[64px_72px_1fr_auto] gap-1.5 border-t border-line px-5 py-3"
                                        onSubmit={(e) => {
                                            e.preventDefault();
                                            const f = e.currentTarget;
                                            router.post(restaurant.store, { menu_item_id: d.id, quantity: f.quantity.value, unit: f.unit.value, inventory_item_id: f.inventory_item_id.value }, { ...opts, onSuccess: () => f.reset() });
                                        }}
                                    >
                                        <input name="quantity" type="number" step="0.001" min="0.001" required className="field px-2" placeholder="Qty" aria-label="Quantity" />
                                        <select name="unit" className="field px-2" aria-label="Unit">{units.map((u) => <option key={u} value={u}>{u}</option>)}</select>
                                        <select name="inventory_item_id" className="field" aria-label="Stock item">{items.map(([v, t]) => <option key={v} value={v}>{t}</option>)}</select>
                                        <button className="btn-primary btn-sm h-9">Add</button>
                                    </form>
                                )}
                            </Panel>
                        ))}
                    </div>
                </>
            )}
        </Page>
    );
}
