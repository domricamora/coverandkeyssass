import { Link } from '@inertiajs/react';
import { Facts, Page, Panel } from '../../react/kit';
import { cx, label, money } from '../../react/ui';

/** End-of-day Z-report for one register session. */
export default function ZReport({ restaurant, session: s, report: r, urls }) {
    return (
        <Page
            title={`Z-report · ${restaurant}`}
            subtitle={`Opened ${s.opened}${s.closed ? ` · closed ${s.closed}` : ' · still open'}`}
            back={{ href: urls.floor, label: 'Restaurant floor' }}
            actions={<button className="btn-ghost" onClick={() => window.print()}>Print</button>}
        >
            <div className="grid gap-8 lg:grid-cols-2">
                <Panel title="Takings">
                    <Facts rows={[
                        ...Object.entries(r.by_method).map(([m, v]) => [label(m), money(v)]),
                        ['Refunds', `−${money(r.refunds)}`],
                        ['Net', <strong key="n">{money(r.net)}</strong>],
                        ['Tickets', r.orders],
                        ['Discounts given', money(r.discounts)],
                        ['Tax (in totals)', money(r.tax)],
                    ]} />
                </Panel>
                <Panel title="Cash drawer">
                    <Facts rows={[
                        ['Opening float', money(s.float)],
                        ['Expected cash', money(r.cash_expected)],
                        s.counted !== null && ['Counted cash', money(s.counted)],
                        s.variance !== null && ['Variance', <strong key="v" className={cx(s.variance < 0 && 'text-bad')}>{money(s.variance)}</strong>],
                    ]} />
                    {s.notes && <p className="border-t border-line px-5 py-3 text-[13px] text-fg-3">{s.notes}</p>}
                </Panel>
            </div>
            <Link href={urls.floor} className="btn-ghost">Back to the floor</Link>
        </Page>
    );
}
