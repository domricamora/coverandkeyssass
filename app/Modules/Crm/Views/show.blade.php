@php($canManage = auth()->user()->hasPermissionTo('crm.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $contact->name }} @if ($contact->is_vip)<span class="badge badge-amber">VIP</span>@endif</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">
                {{ $contact->email ?? 'no email' }} · {{ $contact->phone ?? 'no phone' }} · {{ $contact->user ? 'has an account' : 'no account' }}
                · first seen {{ $contact->first_seen_at?->format('M j, Y') ?? '—' }}
            </p>
            <div class="flex flex-wrap gap-1 mt-1">
                @foreach ($segments as $label)<span class="badge badge-blue">{{ $label }}</span>@endforeach
                @foreach ($contact->tags as $tag)<span class="badge badge-gray">{{ $tag->name }}</span>@endforeach
            </div>
        </div>
        <a href="{{ route('crm.index') }}" class="btn btn-ghost">All guests</a>
    </div>

    <div class="grid grid-cols-4 gap-4 mt-4">
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Lifetime spend</p><h2>₱{{ number_format((float) $contact->total_spend, 2) }}</h2></div>
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Stays</p><h2>{{ $contact->bookings_count }}</h2></div>
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Food orders</p><h2>{{ $contact->orders_count }}</h2></div>
        <div class="card p-4"><p class="text-sm" style="color:var(--text-3)">Table visits</p><h2>{{ $contact->reservations_count }}</h2></div>
    </div>

    @foreach (['name', 'email', 'channel', 'body'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4 mt-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="card p-6">
                <h2 class="text-lg">Booking history</h2>
                <table class="table mt-2">
                    <tbody>
                        @forelse ($bookings as $b)
                            <tr><td>{{ $b->reference }}</td><td>{{ $b->property?->name }}</td><td>{{ $b->check_in->format('M j') }}–{{ $b->check_out->format('M j, Y') }}</td><td><span class="badge badge-gray">{{ $b->statusLabel() }}</span></td><td class="text-right">₱{{ number_format((float) $b->total, 2) }}</td></tr>
                        @empty
                            <tr><td style="color:var(--text-3)">No stays.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card p-6">
                <h2 class="text-lg">Order & table history</h2>
                <table class="table mt-2">
                    <tbody>
                        @foreach ($orders as $o)
                            <tr><td>{{ $o->reference }}</td><td>{{ $o->restaurant?->name }}</td><td>{{ $o->created_at->format('M j, Y') }} · {{ $o->fulfillmentLabel() }}</td><td><span class="badge badge-gray">{{ $o->statusLabel() }}</span></td><td class="text-right">{{ $o->money($o->total) }}</td></tr>
                        @endforeach
                        @foreach ($reservations as $r)
                            <tr><td>{{ $r->reference }}</td><td>{{ $r->restaurant?->name }}</td><td>{{ $r->reserved_at->format('M j, Y g:i A') }} · table for {{ $r->party_size }}</td><td><span class="badge badge-gray">{{ $r->statusLabel() }}</span></td><td></td></tr>
                        @endforeach
                        @if ($orders->isEmpty() && $reservations->isEmpty())
                            <tr><td style="color:var(--text-3)">No orders or table visits.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card p-6">
                <h2 class="text-lg">Communication history</h2>
                <ul class="mt-2 text-sm space-y-2">
                    @forelse ($contact->interactions as $i)
                        <li><strong>{{ \Illuminate\Support\Str::headline($i->channel) }}</strong> · {{ $i->direction }} · {{ $i->occurred_at->format('M j, g:i A') }} · {{ $i->author?->name ?? 'system' }}
                            @if ($i->subject)<br>{{ $i->subject }}@endif @if ($i->body)<br><span style="color:var(--text-2)">{{ \Illuminate\Support\Str::limit($i->body, 300) }}</span>@endif</li>
                    @empty
                        <li style="color:var(--text-3)">Nothing logged yet.</li>
                    @endforelse
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('crm.interactions.store', $contact->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <div class="flex gap-2">
                            <select name="channel" class="form-input" aria-label="Channel">@foreach (\App\Modules\Crm\Models\Interaction::CHANNELS as $c)<option value="{{ $c }}">{{ \Illuminate\Support\Str::headline($c) }}</option>@endforeach</select>
                            <select name="direction" class="form-input" aria-label="Direction"><option value="outbound">We contacted them</option><option value="inbound">They contacted us</option></select>
                        </div>
                        <input name="subject" type="text" class="form-input" placeholder="Subject" aria-label="Subject" />
                        <textarea name="body" rows="2" class="form-input" placeholder="What was said" aria-label="Details"></textarea>
                        <button type="submit" class="btn btn-sm btn-ghost">Log</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            @if ($canManage)
                <form method="POST" action="{{ route('crm.update', $contact->id) }}" class="card p-6 space-y-2">
                    @csrf
                    @method('PATCH')
                    <h2 class="text-lg">Profile</h2>
                    <input name="name" type="text" required value="{{ $contact->name }}" class="form-input" aria-label="Name" />
                    <input name="email" type="email" value="{{ $contact->email }}" class="form-input" placeholder="Email" aria-label="Email" />
                    <input name="phone" type="text" value="{{ $contact->phone }}" class="form-input" placeholder="Phone" aria-label="Phone" />
                    <input name="tags" type="text" value="{{ $contact->tags->pluck('name')->implode(', ') }}" class="form-input" placeholder="Tags, comma separated" aria-label="Tags" />
                    <label class="text-sm flex items-center gap-1"><input type="checkbox" name="is_vip" value="1" @checked($contact->is_vip) /> VIP</label>
                    <label class="text-sm flex items-center gap-1"><input type="checkbox" name="marketing_consent" value="1" @checked($contact->marketing_consent) /> Agreed to marketing{{ $contact->consent_at ? ' ('.$contact->consent_at->format('M j, Y').')' : '' }}</label>
                    <button type="submit" class="btn btn-primary w-full">Save</button>
                </form>
            @endif
            <div class="card p-6 text-sm">
                <h2 class="text-lg">Notes</h2>
                <ul class="mt-2 space-y-2">
                    @forelse ($contact->notes as $note)
                        <li>{!! nl2br(e($note->body)) !!}<br><small style="color:var(--text-3)">{{ $note->author?->name }} · {{ $note->created_at->format('M j, Y') }}</small></li>
                    @empty
                        <li style="color:var(--text-3)">No notes.</li>
                    @endforelse
                </ul>
                @if ($canManage)
                    <form method="POST" action="{{ route('crm.notes.store', $contact->id) }}" class="mt-3 space-y-2">
                        @csrf
                        <textarea name="body" rows="2" required class="form-input" placeholder="Allergies, preferences, anniversaries…" aria-label="Note"></textarea>
                        <button type="submit" class="btn btn-sm btn-ghost">Add note</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
