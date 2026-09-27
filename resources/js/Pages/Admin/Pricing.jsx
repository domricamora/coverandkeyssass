import { useForm } from '@inertiajs/react';
import { Page, Panel, Table, Td, submit } from '../../react/kit';

/** Module plan prices (VAT-inclusive, ₱). New prices apply from each business's next invoice. */
export default function AdminPricing({ plans, urls }) {
    const form = useForm({ plans: Object.fromEntries(plans.map((p) => [p.id, { price: p.price, is_active: p.is_active }])) });
    const set = (id, key, value) => form.setData('plans', { ...form.data.plans, [id]: { ...form.data.plans[id], [key]: value } });

    return (
        <Page eyebrow="Super Admin" title="Module pricing" subtitle="Plan prices per module, VAT-inclusive (₱). New prices apply from each business's next invoice.">
            <form onSubmit={submit(form, urls.save, { method: 'put' })}>
                <Panel>
                    <Table head={['Module', 'Plan', 'Price (₱)', 'Limits', 'Active']} empty="No plans defined.">
                        {plans.map((p) => (
                            <tr key={p.id}>
                                <Td className="font-medium text-fg">{p.module}</Td>
                                <Td muted>{p.name}</Td>
                                <Td>
                                    <input type="number" step="0.01" min="0" className="field w-36 tabular-nums" aria-label={`${p.module} ${p.name} price`} value={form.data.plans[p.id].price} onChange={(e) => set(p.id, 'price', e.target.value)} />
                                    {form.errors[`plans.${p.id}.price`] && <span className="mt-1 block text-[12px] text-bad">{form.errors[`plans.${p.id}.price`]}</span>}
                                </Td>
                                <Td muted className="text-[12px]">{p.limits ?? '—'}</Td>
                                <Td>
                                    <input type="checkbox" className="h-4 w-4 text-brand" aria-label={`${p.module} ${p.name} active`} checked={form.data.plans[p.id].is_active} onChange={(e) => set(p.id, 'is_active', e.target.checked)} />
                                </Td>
                            </tr>
                        ))}
                    </Table>
                    <div className="border-t border-line px-5 py-4">
                        <button type="submit" className="btn-primary" disabled={form.processing || !form.isDirty}>Save pricing</button>
                    </div>
                </Panel>
            </form>
        </Page>
    );
}
