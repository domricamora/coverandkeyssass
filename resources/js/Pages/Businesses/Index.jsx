import { Badge, Empty, Page, Panel } from '../../react/kit';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** Businesses the user belongs to: open one, or create a new one. */
export default function Businesses({ businesses, urls }) {
    return (
        <Page
            title="Your businesses"
            subtitle="Pick a business to work in, or create a new one."
            actions={<a href={urls.create} className="btn-primary btn-sm">New business</a>}
        >
            {businesses.length === 0 ? (
                <Empty title="No businesses yet" body="Create your first business (a hotel, resort, guesthouse or restaurant) to get started.">
                    <a href={urls.create} className="btn-primary">Create your first business</a>
                </Empty>
            ) : (
                <Panel>
                    <ul className="divide-y divide-line">
                        {businesses.map((b) => (
                            <li key={b.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <span className="min-w-0">
                                    <span className="block text-[15px] font-medium text-fg">{b.name} {b.current && <Badge tone="ok">current</Badge>}</span>
                                    <span className="block text-[12px] text-fg-3">{b.type} · {b.slug}</span>
                                </span>
                                {/* Plain form: switching resets the whole dashboard (new business, new nav). */}
                                <form method="post" action={b.switch}>
                                    <input type="hidden" name="_token" value={csrf()} />
                                    <button type="submit" className={b.current ? 'btn-ghost btn-sm' : 'btn-primary btn-sm'}>{b.current ? 'Open' : 'Switch'}</button>
                                </form>
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}
        </Page>
    );
}
