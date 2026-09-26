import { Link } from '@inertiajs/react';
import { Action, Facts, Page, Panel, Stats, Status, Tabs } from '../../react/kit';

/** Restaurant overview: publish, counts, profile, hours and cover. */
export default function Restaurant({ restaurant: r, tabs, can, urls }) {
    return (
        <Page
            title={r.name}
            subtitle={r.summary}
            actions={
                <>
                    <Status value={r.status} />
                    <Link href={urls.floor} className="btn-ghost">Restaurant floor</Link>
                    {r.published && <a href={r.public} target="_blank" rel="noopener" className="btn-ghost">View listing</a>}
                    {can.publish && (r.published
                        ? <Action href={urls.unpublish} className="btn-ghost">Unpublish</Action>
                        : <Action href={urls.publish} className="btn-primary">Publish</Action>)}
                    {can.delete && <Action href={urls.destroy} method="delete" className="btn-danger" confirm="Delete this restaurant?">Delete</Action>}
                </>
            }
        >
            <Tabs tabs={tabs} />
            <Stats items={[['Menu categories', r.counts.categories], ['Menu items', r.counts.items], ['Tables', r.counts.tables, `in ${r.counts.areas} area(s)`], ['Status', r.published ? 'Live' : 'Draft']]} />

            <div className="grid gap-8 lg:grid-cols-2">
                <Panel title="Profile">
                    <div className="space-y-2 px-5 py-4 text-[13px] text-fg-2">
                        <p className="text-fg">{r.tagline ?? 'No tagline yet.'}</p>
                        <p className="whitespace-pre-line">{r.description ?? 'No description yet.'}</p>
                    </div>
                    <div className="border-t border-line"><Facts rows={r.facts} /></div>
                    {r.hours.length > 0 && (
                        <>
                            <h3 className="border-t border-line px-5 pb-1 pt-4 text-[11px] font-semibold uppercase tracking-[0.08em] text-fg-3">Opening hours</h3>
                            <Facts rows={r.hours} />
                        </>
                    )}
                </Panel>
                <Panel title="Cover">
                    {r.cover ? <img src={r.cover} alt={r.name} className="aspect-[16/10] w-full object-cover" /> : <p className="px-5 py-10 text-center text-[13px] text-fg-3">No cover yet. Add photos under Profile & photos.</p>}
                    {!r.published && <p className="border-t border-line px-5 py-3 text-[12px] text-fg-3">Drafts are invisible on the marketplace until published.</p>}
                </Panel>
            </div>
        </Page>
    );
}
