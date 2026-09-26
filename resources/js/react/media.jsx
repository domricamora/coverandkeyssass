import { router, useForm } from '@inertiajs/react';
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
