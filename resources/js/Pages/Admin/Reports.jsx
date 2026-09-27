import { Page } from '../../react/kit';

/** CSV exports for any date range. Plain GET forms: the browser downloads the file. */
export default function AdminReports({ reports, from, to }) {
    return (
        <Page eyebrow="Super Admin" title="Reports" subtitle="CSV exports for any date range, by creation date. Opens in any spreadsheet.">
            <div className="grid border-l border-t border-line bg-surface sm:grid-cols-2 xl:grid-cols-3">
                {reports.map((r) => (
                    <form key={r.key} method="GET" action={r.url} className="flex flex-col gap-3 border-b border-r border-line p-5">
                        <p className="font-medium text-fg">{r.label}</p>
                        <div className="grid grid-cols-2 gap-2">
                            <label className="block"><span className="label">From</span><input type="date" name="from" required defaultValue={from} className="field" /></label>
                            <label className="block"><span className="label">To</span><input type="date" name="to" required defaultValue={to} className="field" /></label>
                        </div>
                        <button type="submit" className="btn-ghost btn-sm self-start">Download CSV</button>
                    </form>
                ))}
            </div>
        </Page>
    );
}
