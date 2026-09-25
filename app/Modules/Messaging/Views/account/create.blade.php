<x-public-layout title="New message" :show-search="false">
    <div class="container section" style="max-width:720px;">
        <div class="section-head">
            <span class="eyebrow">{{ $support ? 'Platform support' : 'Message' }}</span>
            <h1>{{ $support ? 'How can we help?' : 'About '.($about->name ?? $about->reference) }}</h1>
        </div>
        <form method="POST" action="{{ route('account.messages.store', request()->only(['property', 'restaurant', 'booking', 'order', 'reservation'])) }}" enctype="multipart/form-data" class="card" style="padding:18px;display:grid;gap:10px;">
            @csrf
            <input name="subject" type="text" required class="form-input" value="{{ old('subject', $support ? '' : 'Question about '.($about->name ?? $about->reference)) }}" aria-label="Subject" />
            <textarea name="body" rows="5" required class="form-input" placeholder="Your message" aria-label="Message">{{ old('body') }}</textarea>
            <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" aria-label="Attach files" />
            @foreach (['subject', 'body', 'files.0'] as $field)
                @error($field)<p role="alert" style="color:var(--danger, #b91c1c);margin:0;">{{ $message }}</p>@enderror
            @endforeach
            <button type="submit" class="btn btn-primary">Send</button>
        </form>
    </div>
</x-public-layout>
