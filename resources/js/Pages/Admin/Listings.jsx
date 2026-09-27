import { router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Filter, Input, Main, Page, Pager, Panel, Row, Search, Status, Table, Td, submit } from '../../react/kit';

const TITLES = { properties: 'Properties', restaurants: 'Restaurants' };

/** Every property or restaurant across businesses: approve, suspend, send back, placement. */
export default function AdminListings({ kind, listings, filters }) {
    const here = usePage().url.split('?')[0];
    const [open, setOpen] = useState(null);

    const setStatus = (l, status) => {
        let reason = null;
        if (status === 'suspended') {
            reason = window.prompt(`Why suspend ${l.name}? The reason goes in the audit log.`);
            if (!reason) return;
        }
        router.post(l.urls.status, { status, reason }, { preserveScroll: true });
    };

    return (
        <Page
            eyebrow="Super Admin"
            title={TITLES[kind]}
            subtitle="Approve pending listings, suspend or reinstate, and set placement. Every change is audited."
            actions={(
                <>
                    <Search url={here} value={filters.q} params={filters} placeholder="Search name" />
                    <Filter name="status" value={filters.status} url={here} params={filters} placeholder="Any status" options={['pending', 'published', 'draft', 'suspended']} />
                </>
            )}
        >
            <Panel>
                <Table head={['Listing', 'Business', 'Status', { label: '', right: true }]} empty="Nothing here.">
                    {listings.data.map((l) => [
                        <tr key={l.id}>
                            <Td>
                                <Main title={l.name} />
                                <span className="mt-1 flex flex-wrap gap-1.5">
                                    {l.featured && <Badge tone="brand">Featured</Badge>}
                                    {l.sponsored && <Badge tone="info">Sponsored</Badge>}
                                    {l.placement.verified && <Badge tone="ok">Verified</Badge>}
                                    {l.placement.ranking_boost !== 0 && <Badge>Boost {l.placement.ranking_boost}</Badge>}
                                    {l.view && <a href={l.view} className="text-[12px] text-brand hover:underline">View page</a>}
                                </span>
                            </Td>
                            <Td muted>{l.business}</Td>
                            <Td><Status value={l.status} /></Td>
                            <Td right>
                                <span className="inline-flex flex-wrap justify-end gap-1">
                                    {l.status !== 'published' && <button type="button" className="btn-primary btn-sm" onClick={() => setStatus(l, 'published')}>{l.status === 'pending' ? 'Approve' : 'Publish'}</button>}
                                    {l.status === 'pending' && <button type="button" className="btn-ghost btn-sm" onClick={() => setStatus(l, 'draft')}>Send back</button>}
                                    {l.status !== 'suspended' && <button type="button" className="btn-ghost btn-sm text-bad" onClick={() => setStatus(l, 'suspended')}>Suspend</button>}
                                    <button type="button" className="btn-ghost btn-sm" aria-expanded={open === l.id} onClick={() => setOpen(open === l.id ? null : l.id)}>Placement</button>
                                </span>
                            </Td>
                        </tr>,
                        open === l.id && (
                            <tr key={`${l.id}-p`} className="bg-soft/60">
                                <td colSpan={4} className="px-5 py-4"><Placement listing={l} onDone={() => setOpen(null)} /></td>
                            </tr>
                        ),
                    ])}
                </Table>
                <Pager page={listings} />
            </Panel>
        </Page>
    );
}

function Placement({ listing, onDone }) {
    const form = useForm(listing.placement);
    return (
        <form onSubmit={submit(form, listing.urls.placement, { onSuccess: onDone })} className="max-w-3xl space-y-4">
            <Row cols={3}>
                <Input form={form} name="featured_until" type="date" label="Featured until" hint="Empty = no end" />
                <Input form={form} name="sponsored_until" type="date" label="Sponsored until" hint="Paid placement, labelled" />
                <Input form={form} name="ranking_boost" type="number" min="-50" max="50" label="Ranking boost" hint="−50 to 50" />
            </Row>
            <div className="flex flex-wrap items-center gap-5">
                <Input form={form} name="is_featured" type="checkbox" label="Featured" />
                <Input form={form} name="verified" type="checkbox" label="Verified (documents checked)" />
                <button type="submit" className="btn-primary btn-sm ml-auto" disabled={form.processing}>Save placement</button>
            </div>
        </form>
    );
}
