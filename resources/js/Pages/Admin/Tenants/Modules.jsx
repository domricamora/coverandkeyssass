import { Action, Badge, Page } from '../../../react/kit';
import { cx } from '../../../react/ui';

/** Switch modules on or off for one business (no charge; billing follows the subscription). */
export default function AdminTenantModules({ tenant, modules, urls }) {
    return (
        <Page eyebrow="Super Admin" title={`${tenant}: modules`} subtitle="Switch modules on or off for this business." back={{ href: urls.back, label: tenant }}>
            {modules.length ? (
                <div className="grid border-l border-t border-line bg-surface sm:grid-cols-2 xl:grid-cols-3">
                    {modules.map((m) => (
                        <div key={m.id} className={cx('flex flex-col gap-3 border-b border-r border-line p-5', m.active && 'bg-brand-soft/40')}>
                            <div className="flex items-start justify-between gap-3">
                                <p className="font-medium text-fg">{m.name}</p>
                                <span className="flex gap-1.5">
                                    {m.core && <Badge tone="info">Core</Badge>}
                                    {m.active && <Badge tone="ok">On</Badge>}
                                </span>
                            </div>
                            {m.description && <p className="text-[13px] text-fg-3">{m.description}</p>}
                            <div className="mt-auto flex items-center justify-between gap-3 pt-1">
                                <span className="text-[12px] text-fg-3">{m.trialEnds ? `Trial ends ${m.trialEnds}` : ''}</span>
                                {m.active
                                    ? <Action href={m.disable} method="delete" confirm={`Switch off ${m.name} for ${tenant}?`} className="btn-ghost btn-sm text-bad">Switch off</Action>
                                    : <Action href={urls.enable} data={{ module_id: m.id }} className="btn-primary btn-sm">Switch on</Action>}
                            </div>
                        </div>
                    ))}
                </div>
            ) : <p className="text-[13px] text-fg-3">No modules defined yet.</p>}
        </Page>
    );
}
