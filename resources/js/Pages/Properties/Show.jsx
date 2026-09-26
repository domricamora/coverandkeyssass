import { Action, Badge, Facts, Page, Panel, Stats, Status, Tabs } from '../../react/kit';

/** Property overview: status + publish, counts, profile summary, policies and cover. */
export default function Property({ property: p, tabs, can, urls }) {
    return (
        <Page
            title={p.name}
            subtitle={p.summary}
            actions={
                <>
                    <Status value={p.status} />
                    {p.featured && <Badge tone="warn">Featured</Badge>}
                    {p.published && <a href={p.public} target="_blank" rel="noopener" className="btn-ghost">View listing</a>}
                    {can.publish && (p.published
                        ? <Action href={urls.unpublish} className="btn-ghost">Unpublish</Action>
                        : <Action href={urls.publish} className="btn-primary">Publish</Action>)}
                    {can.delete && <Action href={urls.destroy} method="delete" className="btn-danger" confirm="Delete this property? Its inventory stays for records but the listing disappears.">Delete</Action>}
                </>
            }
        >
            <Tabs tabs={tabs} />
            <Stats items={[['Room types', p.counts.types], ['Rooms', p.counts.rooms], ['Property staff', p.counts.staff], ['Photos', p.photos, `${p.videos} video(s)`]]} />

            <div className="grid gap-8 lg:grid-cols-2">
                <Panel title="Profile">
                    <div className="space-y-2 px-5 py-4 text-[13px] text-fg-2">
                        <p className="text-fg">{p.tagline ?? 'No tagline yet.'}</p>
                        <p className="whitespace-pre-line">{p.description ?? 'No description yet.'}</p>
                    </div>
                    <div className="border-t border-line"><Facts rows={p.facts} /></div>
                    {p.policies.length > 0 && (
                        <>
                            <h3 className="border-t border-line px-5 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-fg-3">Policies</h3>
                            <Facts rows={p.policies} />
                        </>
                    )}
                </Panel>

                <Panel title="Cover">
                    {p.cover ? (
                        <img src={p.cover} alt={p.name} className="aspect-[16/10] w-full object-cover" />
                    ) : (
                        <p className="px-5 py-10 text-center text-[13px] text-fg-3">No cover yet. Add photos under Profile & photos.</p>
                    )}
                    {!p.published && <p className="border-t border-line px-5 py-3 text-[12px] text-fg-3">Drafts are invisible on the marketplace until published.</p>}
                </Panel>
            </div>
        </Page>
    );
}
