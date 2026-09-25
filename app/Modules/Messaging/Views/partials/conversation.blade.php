{{-- Messages + reply box. $thread, $canReply, $replyRoute; $me = auth user. --}}
@php($me = auth()->user())
<div style="display:grid;gap:10px;">
    @foreach ($thread->messages as $m)
        @php($mine = (int) $m->user_id === (int) $me->id)
        <div style="max-width:75%;{{ $mine ? 'margin-left:auto;background:var(--surface-2, #f1f5f9);' : 'background:var(--surface, #fff);border:1px solid var(--border, #e5e7eb);' }}padding:10px 12px;border-radius:12px;">
            <p style="margin:0 0 4px;font-size:12px;color:var(--text-3, #6b7280);">{{ $m->author?->name ?? 'Deleted user' }} · {{ ucfirst($m->side) }} · {{ $m->created_at->format('M j, g:i A') }}</p>
            <p style="margin:0;white-space:pre-line;">{{ $m->body }}</p>
            @foreach ($m->attachments as $file)
                <p style="margin:6px 0 0;font-size:13px;"><a href="{{ route('messages.attachment', [$thread->id, $file->id]) }}" target="_blank" rel="noopener">📎 {{ $file->alt ?? 'Attachment' }}</a></p>
            @endforeach
        </div>
    @endforeach
</div>

@error('body')<p role="alert" style="color:var(--danger, #b91c1c);">{{ $message }}</p>@enderror
@if ($canReply)
    <form method="POST" action="{{ $replyRoute }}" enctype="multipart/form-data" style="display:grid;gap:8px;margin-top:16px;">
        @csrf
        <textarea name="body" rows="3" required class="form-input" placeholder="Write a reply…" aria-label="Reply"></textarea>
        <div style="display:flex;gap:8px;align-items:center;justify-content:space-between;">
            <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" aria-label="Attach files" />
            <button type="submit" class="btn btn-primary">Send</button>
        </div>
    </form>
@elseif ($thread->status === 'closed')
    <p style="color:var(--text-3, #6b7280);margin-top:12px;">This conversation is closed.</p>
@endif
