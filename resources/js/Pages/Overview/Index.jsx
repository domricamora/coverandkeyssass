import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { cx, fmtDay, label, money } from '../../react/ui';

/**
 * Owner overview: today's pulse, period KPIs vs the previous period, daily
 * revenue (rooms + F&B) and occupancy, channel mix, top dishes and — for
 * owners of several businesses — the portfolio. Money needs accounting.view.
 */
// Chart hues: lagoon one step brighter than --primary (chroma floor) + coral; validated with dataviz.
const ROOMS = '#119990';
const FNB = '#d9603f';

export default function Overview(props) {
    const { company: business, range, money: canMoney, steps, today, kpis, previous, daily, channels, dishes, team, portfolio, urls } = props;

    return (
        <div className="px-4 py-6 sm:px-6 lg:px-10 lg:py-8">
            <Head title="Overview" />

            <header className="flex flex-wrap items-end gap-x-6 gap-y-4">
                <div className="min-w-0 basis-full md:basis-0 md:flex-1">
                    <p className="text-[12px] font-medium uppercase tracking-[0.1em] text-coral-deep">{business.type}{business.role ? ` · ${business.role}` : ''}</p>
                    <h1 className="mt-1 font-display text-[28px] font-medium tracking-tight text-fg">{business.name}</h1>
                </div>
                <div className="flex border border-line bg-surface" role="tablist" aria-label="Period">
                    {[7, 30, 90].map((d) => (
                        <button key={d} role="tab" aria-selected={range === d} className={cx('h-9 px-4 text-[13px] tabular-nums', range === d ? 'bg-brand text-white' : 'text-fg-2')} onClick={() => router.get(urls.self, { range: d }, { preserveScroll: true })}>
                            {d} days
                        </button>
                    ))}
                </div>
            </header>

            {steps && <Setup steps={steps} />}

            <section className="mt-6" aria-label="Today">
                <h2 className="text-[12px] font-semibold uppercase tracking-[0.1em] text-fg-3">Right now</h2>
                <dl className="mt-2 grid grid-cols-2 border-l border-t border-line bg-surface sm:grid-cols-4 xl:grid-cols-7">
                    <Pulse term="Arrivals" value={today.arrivals} href={urls.frontdesk} />
                    <Pulse term="In house" value={today.in_house} href={urls.frontdesk} />
                    <Pulse term="Departures" value={today.departures} href={urls.frontdesk} />
                    <Pulse term="Rooms to clean" value={today.dirty} warn={today.dirty > 0} />
                    <Pulse term="Open repairs" value={today.maintenance} warn={today.maintenance > 0} />
                    <Pulse term="Open tables" value={today.open_tickets} />
                    <Pulse term="Staff on shift" value={today.on_shift} />
                </dl>
            </section>

            {canMoney ? (
                <>
                    <section className="mt-8" aria-label="Performance">
                        <h2 className="text-[12px] font-semibold uppercase tracking-[0.1em] text-fg-3">Last {range} days <span className="font-normal normal-case tracking-normal">vs the {range} days before</span></h2>
                        <dl className="mt-2 grid grid-cols-2 border-l border-t border-line bg-surface md:grid-cols-3 xl:grid-cols-6">
                            <Kpi term="Revenue" value={money(kpis.revenue)} now={kpis.revenue} before={previous.revenue} />
                            <Kpi term="Occupancy" value={`${kpis.occupancy}%`} now={kpis.occupancy} before={previous.occupancy} points />
                            <Kpi term="ADR" value={money(kpis.adr)} now={kpis.adr} before={previous.adr} />
                            <Kpi term="RevPAR" value={money(kpis.revpar)} now={kpis.revpar} before={previous.revpar} />
                            <Kpi term="Avg F&B ticket" value={money(kpis.avg_ticket)} now={kpis.avg_ticket} before={previous.avg_ticket} note={`${kpis.orders} tickets`} />
                            <Kpi term="Cancellations" value={`${kpis.cancellation_rate}%`} now={kpis.cancellation_rate} before={previous.cancellation_rate} points lowerIsBetter note={`of ${kpis.bookings} bookings`} />
                        </dl>
                    </section>

                    <div className="mt-8 grid gap-8 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                        <Panel title="Revenue by day" aside={<Legend items={[['Rooms', ROOMS, kpis.room_revenue], ['F&B', FNB, kpis.fnb_revenue]]} />}>
                            <RevenueChart daily={daily} />
                            <DataTable daily={daily} />
                        </Panel>
                        <Panel title="Occupancy by day" aside={<span className="text-[12px] font-normal normal-case tracking-normal text-fg-3 tabular-nums">{kpis.nights_sold} room nights · {kpis.rooms} rooms</span>}>
                            <OccupancyChart daily={daily} />
                        </Panel>
                    </div>
                </>
            ) : (
                <p className="mt-8 border border-line bg-surface px-5 py-4 text-[13px] text-fg-3">Revenue figures are visible to roles with accounting access.</p>
            )}

            <div className="mt-8 grid gap-8 lg:grid-cols-3">
                <Panel title="Bookings by channel">
                    <Bars rows={Object.entries(channels).sort((a, b) => b[1] - a[1]).map(([k, v]) => [label(k), v, String(v)])} empty="No stays in this period." />
                </Panel>
                {canMoney && (
                    <Panel title="Top dishes" aside={<Link href={urls.accounting} className="text-[12px] font-normal normal-case tracking-normal text-brand hover:underline">Accounting</Link>}>
                        <Bars rows={dishes.map((d) => [d.name, d.revenue, `${money(d.revenue)} · ${d.qty}×`])} empty="No completed tickets in this period." />
                    </Panel>
                )}
                <Panel title="Team & setup">
                    <dl className="grid grid-cols-3 divide-x divide-line">
                        {[['Team', team.members], ['Listings', team.listings], ['Modules on', team.modules]].map(([t, v]) => (
                            <div key={t} className="px-5 py-5">
                                <dt className="text-[11px] uppercase tracking-[0.08em] text-fg-3">{t}</dt>
                                <dd className="mt-1.5 font-display text-[24px] leading-none tabular-nums text-fg">{v}</dd>
                            </div>
                        ))}
                    </dl>
                    {canMoney && (
                        <p className="border-t border-line px-5 py-3 text-[12px] text-fg-3 tabular-nums">{kpis.labour_hours} labour hours · {kpis.late_clock_ins} late clock-ins</p>
                    )}
                </Panel>
            </div>

            {portfolio.length > 0 && <Portfolio rows={portfolio} />}
        </div>
    );
}

function Setup({ steps }) {
    const done = steps.filter((s) => s.done).length;
    return (
        <section className="mt-6 border border-line bg-surface">
            <h2 className="flex items-center justify-between border-b border-line px-5 py-3 text-[13px] font-semibold text-fg">
                Finish setting up <span className="pill bg-brand-soft text-brand-deep tabular-nums">{done} / {steps.length}</span>
            </h2>
            <ul className="grid divide-y divide-line sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-4">
                {steps.map((s) => (
                    <li key={s.label}>
                        <Link href={s.href} className={cx('flex items-center gap-3 px-5 py-3 text-[13px] hover:bg-soft', s.done ? 'text-fg-3 line-through' : 'text-fg')}>
                            <span className={cx('grid h-5 w-5 shrink-0 place-items-center border text-[11px]', s.done ? 'border-ok bg-ok-bg text-ok' : 'border-line-strong')}>{s.done ? '✓' : ''}</span>
                            {s.label}
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

function Pulse({ term, value, href, warn }) {
    const body = (
        <>
            <dt className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">{term}</dt>
            <dd className={cx('mt-1.5 font-display text-[26px] font-medium leading-none tabular-nums', warn ? 'text-coral-deep' : 'text-fg')}>{value}</dd>
        </>
    );
    const box = 'block border-b border-r border-line px-5 py-4';
    return href ? <Link href={href} className={cx(box, 'hover:bg-soft')}>{body}</Link> : <div className={box}>{body}</div>;
}

function Kpi({ term, value, now, before, points, lowerIsBetter, note }) {
    // Change vs the previous period: percentage points for rates, percent otherwise.
    const diff = points ? now - before : before ? ((now - before) / before) * 100 : null;
    const good = diff === null || Math.abs(diff) < 0.05 ? null : (diff > 0) !== !!lowerIsBetter;
    return (
        <div className="border-b border-r border-line px-5 py-4">
            <dt className="text-[11px] font-medium uppercase tracking-[0.08em] text-fg-3">{term}</dt>
            <dd className="mt-1.5 font-display text-[24px] font-medium leading-none tabular-nums text-fg">{value}</dd>
            <dd className="mt-2 flex flex-wrap items-center gap-x-2 text-[12px] tabular-nums">
                {diff === null ? (
                    <span className="text-fg-3">no prior data</span>
                ) : (
                    <span className={cx(good === null ? 'text-fg-3' : good ? 'text-ok' : 'text-bad')}>
                        {diff > 0 ? '▲' : diff < 0 ? '▼' : '–'} {Math.abs(diff).toFixed(1)}{points ? ' pts' : '%'}
                    </span>
                )}
                {note && <span className="text-fg-3">{note}</span>}
            </dd>
        </div>
    );
}

function Panel({ title, aside, children }) {
    return (
        <section className="min-w-0 border border-line bg-surface">
            <h2 className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">
                {title} {aside}
            </h2>
            {children}
        </section>
    );
}

function Legend({ items }) {
    return (
        <span className="flex gap-4 text-[12px] font-normal normal-case tracking-normal text-fg-2">
            {items.map(([name, color, total]) => (
                <span key={name} className="flex items-center gap-1.5">
                    <i className="inline-block h-2.5 w-2.5 rounded-[2px]" style={{ background: color }} />
                    {name} <span className="tabular-nums text-fg-3">{money(total)}</span>
                </span>
            ))}
        </span>
    );
}

// Shared plot geometry (SVG user units; the SVG scales to the panel width).
const W = 720;
const H = 240;
const PAD = { l: 56, r: 12, t: 12, b: 26 };

/** Round the axis top up to a 1/2/5 × 10ⁿ step so gridlines land on clean numbers. */
function niceMax(v) {
    if (v <= 0) return 1;
    const p = 10 ** Math.floor(Math.log10(v));
    return [1, 2, 5, 10].map((m) => m * p).find((m) => m >= v);
}

const compact = (n) => new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 }).format(n);

/** Hovered day index from the pointer's x over the plot area. */
function useHover(n) {
    const [i, setI] = useState(null);
    const onMove = (e) => {
        const box = e.currentTarget.getBoundingClientRect();
        const x = ((e.clientX - box.left) / box.width) * W;
        const k = Math.floor(((x - PAD.l) / (W - PAD.l - PAD.r)) * n);
        setI(k >= 0 && k < n ? k : null);
    };
    return [i, { onPointerMove: onMove, onPointerLeave: () => setI(null) }];
}

function Axes({ max, fmt, daily }) {
    const plotH = H - PAD.t - PAD.b;
    const every = Math.ceil(daily.length / 6);
    const colW = (W - PAD.l - PAD.r) / daily.length;
    return (
        <g className="text-[11px]" fill="var(--text-3)">
            {[0, 0.25, 0.5, 0.75, 1].map((f) => {
                const y = PAD.t + plotH * (1 - f);
                return (
                    <g key={f}>
                        <line x1={PAD.l} x2={W - PAD.r} y1={y} y2={y} stroke="var(--border)" strokeWidth="1" />
                        <text x={PAD.l - 8} y={y + 4} textAnchor="end">{fmt(max * f)}</text>
                    </g>
                );
            })}
            {daily.map((d, k) => (k % every === 0 ? <text key={d.date} x={PAD.l + colW * (k + 0.5)} y={H - 8} textAnchor="middle">{fmtDay(d.date)}</text> : null))}
        </g>
    );
}

function Tip({ i, n, children }) {
    if (i === null) return null;
    const left = ((PAD.l + ((W - PAD.l - PAD.r) * (i + 0.5)) / n) / W) * 100;
    return (
        <div className="pointer-events-none absolute top-2 z-10 whitespace-nowrap border border-line bg-surface px-3 py-2 text-[12px] shadow-[0_8px_24px_-12px_rgba(11,12,14,.35)]" style={left > 60 ? { right: `${100 - left + 2}%` } : { left: `${left + 2}%` }}>
            {children}
        </div>
    );
}

function RevenueChart({ daily }) {
    const n = daily.length;
    const [i, hover] = useHover(n);
    const max = niceMax(Math.max(...daily.map((d) => d.rooms + d.fnb)));
    const plotH = H - PAD.t - PAD.b;
    const colW = (W - PAD.l - PAD.r) / n;
    const barW = Math.max(2, colW - 2); // 2px surface gap between bars
    const y = (v) => (v / max) * plotH;
    const base = H - PAD.b;

    return (
        <div className="relative px-3 pb-2 pt-3">
            <svg viewBox={`0 0 ${W} ${H}`} className="block w-full touch-none" role="img" aria-label="Daily revenue, rooms and food and beverage" {...hover}>
                <Axes max={max} fmt={compact} daily={daily} />
                {daily.map((d, k) => {
                    const x = PAD.l + colW * k + (colW - barW) / 2;
                    const hr = y(d.rooms);
                    const hf = y(d.fnb);
                    return (
                        <g key={d.date} opacity={i === null || i === k ? 1 : 0.45}>
                            {hr > 0 && <rect x={x} y={base - hr} width={barW} height={hr} fill={ROOMS} />}
                            {/* F&B stacks on rooms with a 2px surface gap between segments. */}
                            {hf > 0 && <rect x={x} y={base - hr - hf - (hr > 0 ? 2 : 0)} width={barW} height={hf} fill={FNB} />}
                        </g>
                    );
                })}
            </svg>
            <Tip i={i} n={n}>
                {i !== null && (
                    <>
                        <p className="font-semibold text-fg">{fmtDay(daily[i].date, { weekday: 'short', month: 'short', day: 'numeric' })}</p>
                        <TipRow color={ROOMS} name="Rooms" value={money(daily[i].rooms)} />
                        <TipRow color={FNB} name="F&B" value={money(daily[i].fnb)} />
                        <p className="mt-1 border-t border-line pt-1 tabular-nums text-fg">Total {money(daily[i].rooms + daily[i].fnb)}</p>
                    </>
                )}
            </Tip>
        </div>
    );
}

function TipRow({ color, name, value }) {
    return (
        <p className="mt-1 flex items-center gap-2 text-fg-2">
            <i className="inline-block h-2 w-2 rounded-[2px]" style={{ background: color }} />
            {name} <span className="ml-auto pl-4 tabular-nums text-fg">{value}</span>
        </p>
    );
}

function OccupancyChart({ daily }) {
    const n = daily.length;
    const [i, hover] = useHover(n);
    const plotH = H - PAD.t - PAD.b;
    const colW = (W - PAD.l - PAD.r) / n;
    const pt = (d, k) => [PAD.l + colW * (k + 0.5), PAD.t + plotH * (1 - d.occupancy / 100)];
    const path = daily.map((d, k) => `${k ? 'L' : 'M'}${pt(d, k).join(',')}`).join(' ');

    return (
        <div className="relative px-3 pb-2 pt-3">
            <svg viewBox={`0 0 ${W} ${H}`} className="block w-full touch-none" role="img" aria-label="Daily occupancy percentage" {...hover}>
                <Axes max={100} fmt={(v) => `${v}%`} daily={daily} />
                <path d={path} fill="none" stroke={ROOMS} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
                {i !== null && (
                    <>
                        <line x1={pt(daily[i], i)[0]} x2={pt(daily[i], i)[0]} y1={PAD.t} y2={H - PAD.b} stroke="var(--border-strong)" />
                        <circle cx={pt(daily[i], i)[0]} cy={pt(daily[i], i)[1]} r="5" fill={ROOMS} stroke="var(--surface)" strokeWidth="2" />
                    </>
                )}
            </svg>
            <Tip i={i} n={n}>
                {i !== null && (
                    <>
                        <p className="font-semibold text-fg">{fmtDay(daily[i].date, { weekday: 'short', month: 'short', day: 'numeric' })}</p>
                        <p className="mt-1 tabular-nums text-fg">{daily[i].occupancy}% occupied</p>
                    </>
                )}
            </Tip>
        </div>
    );
}

function DataTable({ daily }) {
    return (
        <details className="border-t border-line px-5 py-3 text-[12px]">
            <summary className="cursor-pointer text-fg-3 hover:text-fg">Show as table</summary>
            <div className="mt-3 max-h-72 overflow-auto">
                <table className="w-full tabular-nums">
                    <thead className="sticky top-0 bg-surface text-left text-fg-3">
                        <tr><th className="py-1 font-medium">Day</th><th className="text-right font-medium">Rooms</th><th className="text-right font-medium">F&B</th><th className="text-right font-medium">Occupancy</th></tr>
                    </thead>
                    <tbody className="divide-y divide-line text-fg-2">
                        {daily.map((d) => (
                            <tr key={d.date}><td className="py-1">{fmtDay(d.date)}</td><td className="text-right">{money(d.rooms)}</td><td className="text-right">{money(d.fnb)}</td><td className="text-right">{d.occupancy}%</td></tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </details>
    );
}

/** Horizontal single-hue bars: [label, value, display text]. */
function Bars({ rows, empty }) {
    if (!rows.length) return <p className="px-5 py-10 text-center text-[13px] text-fg-3">{empty}</p>;
    const max = Math.max(...rows.map((r) => r[1]));
    return (
        <ul className="space-y-3 px-5 py-5">
            {rows.map(([name, value, text]) => (
                <li key={name}>
                    <p className="flex justify-between gap-3 text-[13px]"><span className="truncate text-fg">{name}</span><span className="shrink-0 tabular-nums text-fg-2">{text}</span></p>
                    <div className="mt-1.5 h-2 bg-soft">
                        <div className="h-full rounded-r-[4px]" style={{ width: `${(value / max) * 100}%`, background: ROOMS }} />
                    </div>
                </li>
            ))}
        </ul>
    );
}

function Portfolio({ rows }) {
    return (
        <section className="mt-8 border border-line bg-surface">
            <h2 className="border-b border-line px-5 py-3 text-[13px] font-semibold uppercase tracking-[0.1em] text-fg-3">Your businesses <span className="font-normal normal-case tracking-normal">· last 30 days</span></h2>
            <div className="overflow-x-auto">
                <table className="w-full min-w-[640px] text-[13px] tabular-nums">
                    <thead className="text-left text-[11px] uppercase tracking-[0.08em] text-fg-3">
                        <tr className="border-b border-line">
                            <th className="px-5 py-2.5 font-medium">Business</th>
                            <th className="px-5 text-right font-medium">Revenue</th>
                            <th className="px-5 text-right font-medium">Occupancy</th>
                            <th className="px-5 text-right font-medium">ADR</th>
                            <th className="px-5 text-right font-medium">In house</th>
                            <th className="px-5" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {rows.map((b) => (
                            <tr key={b.id} className={b.current ? 'bg-brand-soft/40' : ''}>
                                <td className="px-5 py-3"><span className="font-medium text-fg">{b.name}</span> <span className="text-fg-3">· {b.type}</span></td>
                                <td className="px-5 text-right text-fg">{money(b.revenue)}</td>
                                <td className="px-5 text-right text-fg-2">{b.occupancy}%</td>
                                <td className="px-5 text-right text-fg-2">{money(b.adr)}</td>
                                <td className="px-5 text-right text-fg-2">{b.in_house}</td>
                                <td className="px-5 text-right">
                                    {b.current ? (
                                        <span className="text-[12px] text-fg-3">Viewing</span>
                                    ) : (
                                        <button className="text-[12px] text-brand hover:underline" onClick={() => router.post(b.switch)}>Open</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
