import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { BTN } from '../../widgets/ui';

/** Event × channel matrix: a table on wide screens, one card per update on phones. */
export default function NotificationSettings({ rows, channels, tabs, urls }) {
    const [on, setOn] = useState(() => Object.fromEntries(rows.map((r) => [r.event, { ...r.on }])));
    const [saving, setSaving] = useState(false);

    const toggle = (event, channel) => setOn((s) => ({ ...s, [event]: { ...s[event], [channel]: !s[event][channel] } }));

    const save = (e) => {
        e.preventDefault();
        const payload = Object.fromEntries(Object.entries(on).map(([event, chans]) => [
            event, Object.fromEntries(Object.entries(chans).filter(([, v]) => v).map(([c]) => [c, '1'])),
        ]));
        router.put(urls.update, { channels: payload }, { preserveScroll: true, onStart: () => setSaving(true), onFinish: () => setSaving(false) });
    };

    const box = (row, channel, label) => (
        <input type="checkbox" className="h-5 w-5 cursor-pointer accent-brand" checked={!!on[row.event][channel]}
            onChange={() => toggle(row.event, channel)} aria-label={`${row.label} by ${label}`} />
    );
    const tag = (row) => row.business && <span className="ml-2 border border-line px-1.5 text-[11px] text-fg-3">Business</span>;

    return (
        <>
            <Head title="Notification settings" />
            <div className="mb-6 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 className="font-display text-[30px] font-medium tracking-tight">Notification settings</h1>
                    <p className="mt-1 text-[14px] text-fg-3">In-app notifications are always on. Choose where else each update reaches you.</p>
                </div>
                <a href={urls.notifications} className="text-[14px] font-medium text-brand hover:underline">Back to notifications</a>
            </div>
            <AccountTabs tabs={tabs} />

            <form onSubmit={save} className="max-w-3xl">
                <table className="hidden w-full border border-line bg-surface text-[14px] sm:table">
                    <thead>
                        <tr className="border-b border-line text-[13px] text-fg-3">
                            <th scope="col" className="px-4 py-3 text-left font-medium">Update</th>
                            {channels.map(([key, label]) => <th key={key} scope="col" className="w-24 px-4 py-3 font-medium">{label}</th>)}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-line">
                        {rows.map((row) => (
                            <tr key={row.event} className="transition-colors duration-150 hover:bg-soft">
                                <th scope="row" className="px-4 py-3 text-left font-normal text-fg">{row.label}{tag(row)}</th>
                                {channels.map(([key, label]) => <td key={key} className="px-4 py-3 text-center">{box(row, key, label)}</td>)}
                            </tr>
                        ))}
                    </tbody>
                </table>

                <ul className="space-y-3 sm:hidden">
                    {rows.map((row) => (
                        <li key={row.event} className="border border-line bg-surface px-4 py-3">
                            <p className="mb-1 text-[14px] text-fg">{row.label}{tag(row)}</p>
                            <div className="flex flex-wrap gap-x-5">
                                {channels.map(([key, label]) => (
                                    <label key={key} className="flex min-h-[44px] items-center gap-2 text-[14px] text-fg-2">
                                        {box(row, key, label)} {label}
                                    </label>
                                ))}
                            </div>
                        </li>
                    ))}
                </ul>

                <button type="submit" disabled={saving} className={`${BTN} mt-5 w-auto`}>{saving ? 'Saving' : 'Save settings'}</button>
            </form>
        </>
    );
}

NotificationSettings.layout = (page) => <PublicShell>{page}</PublicShell>;
