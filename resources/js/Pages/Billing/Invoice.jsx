import { Page, Panel, Status, Table, Td } from '../../react/kit';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/** A platform billing invoice for the business; pay online (PayMongo) or by bank transfer. */
export default function BillingInvoice({ invoice: inv, can, urls }) {
    return (
        <Page
            title={`Invoice ${inv.number}`}
            subtitle={`${inv.business} · ${inv.period}`}
            back={{ href: urls.index, label: 'Billing' }}
            actions={<button type="button" className="btn-ghost btn-sm" onClick={() => window.print()}>Print</button>}
        >
            <Panel title="Items" aside={<Status value={inv.status} />}>
                <Table head={['Item', { label: 'Qty', right: true }, { label: 'Price', right: true }, { label: 'Amount', right: true }]}>
                    {inv.items.map((it) => (
                        <tr key={it.id}>
                            <Td className="text-fg">{it.description}</Td>
                            <Td right>{it.quantity}</Td>
                            <Td right>{it.unit}</Td>
                            <Td right>{it.amount}</Td>
                        </tr>
                    ))}
                </Table>
                <dl className="space-y-1.5 border-t border-line px-5 py-4 text-[13px] tabular-nums">
                    <div className="flex justify-between text-fg-2"><dt>Subtotal</dt><dd>{inv.subtotal}</dd></div>
                    {inv.discount && <div className="flex justify-between text-ok"><dt>Coupon {inv.discount.code}</dt><dd>−{inv.discount.amount}</dd></div>}
                    <div className="flex justify-between border-t border-line pt-2 text-[15px] font-semibold"><dt>Total (VAT inclusive)</dt><dd>{inv.total}</dd></div>
                </dl>
                <div className="space-y-3 border-t border-line px-5 py-4 text-[13px] text-fg-2">
                    {inv.paid ? <p>Paid {inv.paid}</p> : inv.status !== 'void' && <p>Due {inv.due}</p>}
                    {can.pay && (
                        <>
                            {/* Plain POST: the endpoint redirects off-site to PayMongo, which an Inertia XHR cannot follow. */}
                            {can.online && (
                                <form method="post" action={urls.pay}>
                                    <input type="hidden" name="_token" value={csrf()} />
                                    <button type="submit" className="btn-primary">Pay {inv.total} with PayMongo</button>
                                </form>
                            )}
                            <p className="text-fg-3">Paying by bank transfer? Put {inv.number} in the reference; we confirm it within one business day.</p>
                        </>
                    )}
                </div>
            </Panel>
        </Page>
    );
}
