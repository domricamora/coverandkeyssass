const TONE = { confirmed: 'bg-ok-bg text-ok', checked_in: 'bg-ok-bg text-ok', completed: 'bg-ok-bg text-ok', pending: 'bg-warn-bg text-warn', held: 'bg-warn-bg text-warn' };

export const TripStatus = ({ status, label }) => <span className={`inline-block px-2 py-0.5 text-[12px] font-medium ${TONE[status] ?? 'bg-soft text-fg-3'}`}>{label}</span>;

/** One trip in a list: photo, property, dates, status, total. */
export default function TripCard({ trip }) {
    return (
        <a href={trip.href} className="flex gap-4 border border-line bg-surface p-3 transition-colors hover:border-line-strong">
            {trip.cover ? <img src={trip.cover} alt="" loading="lazy" className="h-20 w-24 shrink-0 object-cover sm:w-32" /> : <span className="h-20 w-24 shrink-0 bg-soft sm:w-32" />}
            <span className="flex min-w-0 flex-1 flex-col justify-between gap-1 sm:flex-row sm:items-center">
                <span className="min-w-0">
                    <span className="block truncate text-[15px] font-medium text-fg">{trip.property}</span>
                    <span className="block text-[13px] text-fg-3">{trip.dates} · {trip.reference}</span>
                </span>
                <span className="flex items-center gap-3 sm:flex-col sm:items-end sm:gap-1">
                    <TripStatus status={trip.status} label={trip.statusLabel} />
                    <span className="text-[13px] tabular-nums text-fg-2">{trip.total}</span>
                </span>
            </span>
        </a>
    );
}
