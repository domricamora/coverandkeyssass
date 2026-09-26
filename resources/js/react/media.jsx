import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Badge, Panel } from './kit';

/**
 * Listing photos + videos (properties and restaurants): WebP multi-upload
 * tagged with a tour area, make cover, remove, and video links.
 * media: { photos, videos, store, areas } from MediaUploads::payload().
 */
export default function MediaManager({ media, video = true }) {
    const upload = useForm({ kind: 'image', photos: [], caption: '', url: '' });
    const clip = useForm({ kind: 'video', url: '' });
    const post = (url, method = 'post') => router[method](url, {}, { preserveScroll: true });
    const photoError = upload.errors.photos ?? Object.entries(upload.errors).find(([k]) => k.startsWith('photos.'))?.[1];

    return (
        <>
            <Panel title="Photos" aside={`${media.photos.length} in the tour`}>
                {media.photos.length === 0 ? (
                    <p className="px-5 py-6 text-[13px] text-fg-3">No photos yet.</p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 xl:grid-cols-4">
                        {media.photos.map((m) => (
                            <li key={m.id} className="border border-line">
                                <img src={m.url} alt={m.alt ?? ''} className="aspect-[4/3] w-full object-cover" loading="lazy" />
                                <div className="flex flex-wrap items-center gap-1.5 p-2">
                                    <Badge>{m.caption || 'Gallery'}</Badge>
                                    {m.cover && <Badge tone="ok">Cover</Badge>}
                                    <span className="ml-auto flex gap-1">
                                        {!m.cover && <button className="btn-ghost btn-sm" onClick={() => post(m.make_cover)}>Cover</button>}
                                        <button className="btn-danger btn-sm" onClick={() => window.confirm('Remove this photo?') && post(m.destroy, 'delete')}>Remove</button>
                                    </span>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
                <form
                    className="grid gap-4 border-t border-line p-5 sm:grid-cols-[1fr_220px_auto] sm:items-end"
                    onSubmit={(e) => { e.preventDefault(); upload.post(media.store, { preserveScroll: true, forceFormData: true, onSuccess: () => { upload.reset(); e.target.reset(); } }); }}
                >
                    <label className="block">
                        <span className="label">Upload photos</span>
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" className="field h-auto py-1.5" onChange={(e) => upload.setData('photos', [...e.target.files])} />
                        <span className="mt-1 block text-[12px] text-fg-3">JPG, PNG or WebP, up to 8 MB each, 20 at a time. Saved as WebP.</span>
                        {photoError && <span className="mt-1 block text-[12px] text-bad">{photoError}</span>}
                    </label>
                    <label className="block">
                        <span className="label">Tour area</span>
                        <input list="media-areas" maxLength={60} className="field" placeholder={`e.g. ${media.areas[1] ?? media.areas[0]}`} value={upload.data.caption} onChange={(e) => upload.setData('caption', e.target.value)} />
                        <datalist id="media-areas">{media.areas.map((a) => <option key={a} value={a} />)}</datalist>
                    </label>
                    <button className="btn-primary" disabled={upload.processing || !upload.data.photos.length}>Add to tour</button>
                </form>
            </Panel>

            {video && (
                <Panel title="Videos">
                    <ul className="divide-y divide-line text-[13px]">
                        {media.videos.length === 0 && <li className="px-5 py-4 text-fg-3">No videos yet.</li>}
                        {media.videos.map((v) => (
                            <li key={v.id} className="flex items-center gap-3 px-5 py-3">
                                <Badge tone="info">Video</Badge>
                                <a href={v.url} target="_blank" rel="noopener" className="min-w-0 flex-1 truncate text-fg-2 hover:text-fg">{v.alt ?? v.url}</a>
                                <button className="btn-danger btn-sm" onClick={() => post(v.destroy, 'delete')}>Remove</button>
                            </li>
                        ))}
                    </ul>
                    <form className="flex flex-wrap items-end gap-3 border-t border-line p-5" onSubmit={(e) => { e.preventDefault(); clip.post(media.store, { preserveScroll: true, onSuccess: () => clip.reset() }); }}>
                        <label className="min-w-64 flex-1">
                            <span className="label">Add video (URL)</span>
                            <input type="url" required className="field" placeholder="https://…/tour.mp4" value={clip.data.url} onChange={(e) => clip.setData('url', e.target.value)} />
                            {clip.errors.url && <span className="mt-1 block text-[12px] text-bad">{clip.errors.url}</span>}
                        </label>
                        <button className="btn-primary" disabled={clip.processing}>Add video</button>
                    </form>
                </Panel>
            )}
        </>
    );
}

/**
 * Compact photo strip for things inside a listing (room types, dishes, menu
 * sections): thumbnails, main photo, remove, and "Add photos" that uploads
 * straight away. photos: PhotoController::payload() → { photos, store }.
 */
export function PhotoStrip({ photos: data, label = 'Photos', editable = true }) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);
    const act = (url, method = 'post') => router[method](url, {}, { preserveScroll: true });

    const upload = (files) => {
        if (!files.length) return;
        setBusy(true);
        setError(null);
        router.post(data.store, { photos: [...files] }, {
            forceFormData: true,
            preserveScroll: true,
            onError: (e) => setError(e.photos ?? Object.entries(e).find(([k]) => k.startsWith('photos'))?.[1] ?? 'Upload failed.'),
            onFinish: () => setBusy(false),
        });
    };

    return (
        <div className="px-5 py-3">
            <p className="mb-2 text-[12px] font-medium uppercase tracking-[0.08em] text-fg-3">{label} <span className="normal-case tracking-normal">· {data.photos.length}</span></p>
            <div className="flex flex-wrap gap-2">
                {data.photos.map((m) => (
                    <figure key={m.id} className="group relative h-20 w-28 overflow-hidden border border-line">
                        <img src={m.url} alt={m.alt ?? ''} loading="lazy" className="h-full w-full object-cover" />
                        {m.cover && <span className="absolute left-1 top-1 bg-brand px-1.5 py-0.5 text-[10px] font-medium text-white">Main</span>}
                        {editable && (
                            <span className="absolute inset-x-0 bottom-0 flex justify-between bg-black/55 px-1 py-0.5 opacity-0 transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">
                                {!m.cover ? <button type="button" className="text-[11px] text-white hover:underline" onClick={() => act(m.make_cover)}>Make main</button> : <span />}
                                <button type="button" className="text-[11px] text-white hover:underline" onClick={() => window.confirm('Remove this photo?') && act(m.destroy, 'delete')} aria-label="Remove photo">Remove</button>
                            </span>
                        )}
                    </figure>
                ))}
                {editable && (
                    <label className={`flex h-20 w-28 cursor-pointer flex-col items-center justify-center border border-dashed border-line-strong text-center text-[12px] text-fg-3 hover:border-brand hover:text-brand ${busy ? 'pointer-events-none opacity-60' : ''}`}>
                        <span className="text-[18px] leading-none">+</span>
                        {busy ? 'Uploading…' : 'Add photos'}
                        <input type="file" multiple accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(e) => { upload(e.target.files); e.target.value = ''; }} />
                    </label>
                )}
            </div>
            {error && <p className="mt-1.5 text-[12px] text-bad">{error}</p>}
        </div>
    );
}
