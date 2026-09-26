import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import PublicShell, { AccountTabs } from '../../react/PublicShell';
import { BTN, FIELD, LABEL } from '../../widgets/ui';

const Err = ({ msg }) => msg ? <span role="alert" className="mt-1 block text-[13px] text-bad">{msg}</span> : null;

function Field({ label, error, ...input }) {
    return (
        <label className="block">
            <span className={LABEL}>{label}</span>
            <input className={FIELD} {...input} />
            <Err msg={error} />
        </label>
    );
}

function Section({ title, hint, children }) {
    return (
        <section className="border-t border-line py-8 first:border-t-0 first:pt-0 md:grid md:grid-cols-[240px_minmax(0,1fr)] md:gap-10">
            <header className="mb-5 md:mb-0">
                <h2 className="text-[16px] font-semibold">{title}</h2>
                <p className="mt-1 text-[13px] text-fg-3">{hint}</p>
            </header>
            <div className="max-w-md">{children}</div>
        </section>
    );
}

/** Guest profile: details, password and account deletion, in the public account shell. */
export default function Profile({ user, unverified, status, tabs, urls }) {
    const details = useForm({ name: user.name, email: user.email });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const deletion = usePage().props.errors?.userDeletion ?? {};
    const dialog = useRef(null);
    const csrf = typeof document !== 'undefined' ? document.querySelector('meta[name="csrf-token"]')?.content : '';

    // A wrong password on delete comes back as a full page load: reopen the dialog with the error.
    useEffect(() => { if (deletion.password) dialog.current?.showModal(); }, [deletion.password]);

    const saved = (s) => status === s && <span role="status" className="text-[13px] text-brand">Saved</span>;

    return (
        <>
            <Head title="Profile" />
            <h1 className="mb-6 font-display text-[30px] font-medium tracking-tight">Profile</h1>
            <AccountTabs tabs={tabs} />

            <Section title="Your details" hint="Hosts see this name on your bookings; receipts go to this email.">
                <form className="space-y-4" onSubmit={(e) => { e.preventDefault(); details.patch(urls.profile, { preserveScroll: true }); }}>
                    <Field label="Name" required autoComplete="name" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} error={details.errors.name} />
                    <Field label="Email" type="email" required autoComplete="username" value={details.data.email} onChange={(e) => details.setData('email', e.target.value)} error={details.errors.email} />
                    {unverified && (
                        <p className="text-[13px] text-fg-2">
                            Your email address is not verified.{' '}
                            <button type="button" className="text-brand underline" onClick={() => router.post(urls.verify, {}, { preserveScroll: true })}>Send the link again</button>
                            {status === 'verification-link-sent' && <span className="mt-1 block text-brand">A new link is on its way.</span>}
                        </p>
                    )}
                    <div className="flex items-center gap-4">
                        <button type="submit" disabled={details.processing} className={`${BTN} w-auto`}>Save details</button>
                        {saved('profile-updated')}
                    </div>
                </form>
            </Section>

            <Section title="Password" hint="Use a long, random password you don't use anywhere else.">
                <form className="space-y-4" onSubmit={(e) => {
                    e.preventDefault();
                    password.put(urls.password, { errorBag: 'updatePassword', preserveScroll: true, onSuccess: () => password.reset() });
                }}>
                    <Field label="Current password" type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} error={password.errors.current_password} />
                    <Field label="New password" type="password" autoComplete="new-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} error={password.errors.password} />
                    <Field label="Confirm new password" type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} error={password.errors.password_confirmation} />
                    <div className="flex items-center gap-4">
                        <button type="submit" disabled={password.processing} className={`${BTN} w-auto`}>Update password</button>
                        {saved('password-updated')}
                    </div>
                </form>
            </Section>

            <Section title="Delete account" hint="Removes your profile and sign-in for good. Past bookings stay with the hosts for their records.">
                <button type="button" onClick={() => dialog.current?.showModal()}
                    className="h-11 border border-bad px-5 text-[14px] font-medium text-bad transition-[transform,background-color,color] duration-150 hover:bg-bad hover:text-white active:scale-[0.97]">Delete account</button>
            </Section>

            {/* Plain form: the success redirect leaves the Inertia app for the marketing home. */}
            <dialog ref={dialog} className="w-[min(440px,calc(100vw-32px))] border border-line bg-surface p-0 backdrop:bg-black/40">
                <form method="POST" action={urls.destroy} className="space-y-4 p-6">
                    <input type="hidden" name="_token" value={csrf} />
                    <input type="hidden" name="_method" value="DELETE" />
                    <h2 className="text-[17px] font-semibold">Delete your account?</h2>
                    <p className="text-[14px] text-fg-2">This can't be undone. Enter your password to confirm.</p>
                    <Field label="Password" type="password" name="password" required autoComplete="current-password" error={deletion.password} />
                    <div className="flex justify-end gap-3">
                        <button type="button" onClick={() => dialog.current?.close()} className="h-11 px-4 text-[14px] text-fg-2 hover:text-fg">Keep account</button>
                        <button type="submit" className="h-11 bg-bad px-5 text-[14px] font-medium text-white transition-transform duration-150 active:scale-[0.97]">Delete account</button>
                    </div>
                </form>
            </dialog>
        </>
    );
}

Profile.layout = (page) => <PublicShell>{page}</PublicShell>;
