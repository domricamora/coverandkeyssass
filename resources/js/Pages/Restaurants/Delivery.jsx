import { Link, useForm } from '@inertiajs/react';
import { Action, Badge, Form, Input, Page, Panel, Row, Split, Stack, Table, Tabs, Td } from '../../react/kit';

/** Delivery zones, kitchen prep time + coordinates, and drivers. */
export default function Delivery({ restaurant: r, tabs, zones, drivers, urls }) {
    const zone = useForm({ name: '', fee: '', eta_minutes: 30, radius_km: '', min_order: '', free_over: '' });
    const settings = useForm({ prep_minutes: r.prep_minutes ?? 0, latitude: r.latitude ?? '', longitude: r.longitude ?? '' });
    const driver = useForm({ name: '', phone: '', vehicle: '' });

    return (
        <Page
            title={r.name}
            subtitle={<>Delivery is {r.enabled ? 'on' : 'off'} ({zones.filter((z) => z.active).length} active zone(s)). {!r.enabled && <Link href={urls.profile} className="text-brand hover:underline">Turn it on in the profile.</Link>}</>}
        >
            <Tabs tabs={tabs} />
            <Split side="360px">
                <Stack>
                    <Panel title="Zones">
                        <Table head={['Zone', 'Terms', '']} empty="No zones yet. Customers cannot choose delivery until you add one.">
                            {zones.map((z) => (
                                <tr key={z.id}>
                                    <Td className="font-medium text-fg">{z.name} {!z.active && <Badge>Paused</Badge>}</Td>
                                    <Td muted>{z.terms}</Td>
                                    <Td right>
                                        <span className="inline-flex gap-1.5">
                                            <Action href={z.toggle}>{z.active ? 'Pause' : 'Reopen'}</Action>
                                            <Action href={z.destroy} method="delete" className="btn-danger btn-sm" confirm="Remove this zone?">Remove</Action>
                                        </span>
                                    </Td>
                                </tr>
                            ))}
                        </Table>
                        <div className="border-t border-line p-5">
                            <Form form={zone} url={urls.addZone} reset button="Add zone">
                                <Row cols={3}>
                                    <Input form={zone} name="name" label="Zone name" placeholder="Within 3 km" required />
                                    <Input form={zone} name="fee" type="number" step="0.01" min="0" label="Fee (₱)" required />
                                    <Input form={zone} name="eta_minutes" type="number" min="5" label="Ride minutes" required />
                                </Row>
                                <Row cols={3}>
                                    <Input form={zone} name="radius_km" type="number" step="0.1" min="0.1" label="Radius km" hint="Blank = named area" />
                                    <Input form={zone} name="min_order" type="number" step="0.01" min="0" label="Minimum order (₱)" />
                                    <Input form={zone} name="free_over" type="number" step="0.01" min="0" label="Free over (₱)" />
                                </Row>
                            </Form>
                        </div>
                    </Panel>
                </Stack>

                <Stack>
                    <Panel title="Kitchen & location" pad>
                        <Form form={settings} url={urls.settings} method="patch">
                            <Input form={settings} name="prep_minutes" type="number" min="0" max="240" label="Prep time (minutes)" />
                            <Row>
                                <Input form={settings} name="latitude" type="number" step="0.0000001" label="Latitude" />
                                <Input form={settings} name="longitude" type="number" step="0.0000001" label="Longitude" />
                            </Row>
                        </Form>
                    </Panel>

                    <Panel title="Drivers">
                        <ul className="divide-y divide-line text-[13px]">
                            {drivers.length === 0 && <li className="px-5 py-4 text-fg-3">No drivers yet.</li>}
                            {drivers.map((d) => (
                                <li key={d.id} className="flex items-center justify-between gap-3 px-5 py-2.5">
                                    <span><strong className="font-medium text-fg">{d.name}</strong> {d.detail && <span className="text-fg-3">· {d.detail}</span>} {!d.active && <Badge>Off duty</Badge>}</span>
                                    <Action href={d.toggle}>{d.active ? 'Off duty' : 'On duty'}</Action>
                                </li>
                            ))}
                        </ul>
                        <div className="border-t border-line p-5">
                            <Form form={driver} url={urls.addDriver} reset button="Add driver">
                                <Input form={driver} name="name" label="Name" required />
                                <Row>
                                    <Input form={driver} name="phone" label="Phone" />
                                    <Input form={driver} name="vehicle" label="Vehicle" placeholder="Motorbike" />
                                </Row>
                            </Form>
                        </div>
                    </Panel>
                </Stack>
            </Split>
        </Page>
    );
}
