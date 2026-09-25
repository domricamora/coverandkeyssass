@php($canManage = auth()->user()->hasPermissionTo('marketing.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>Marketing</h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ $consented }} guest(s) opted in to marketing. Placeholders: {name} {business} {link} {coupon}</p>
        </div>
        <a href="{{ route('crm.index') }}" class="btn btn-ghost">Guests</a>
    </div>

    @foreach (['name', 'channel', 'audience', 'subject', 'body', 'campaign'] as $field)
        <x-input-error :messages="$errors->get($field)" />
    @endforeach

    <div class="grid grid-cols-3 gap-4">
        <div class="space-y-4" style="grid-column:span 2">
            <div class="table-wrap card">
                <table class="table">
                    <thead><tr><th>Campaign</th><th>Channel</th><th>Status</th><th class="text-right">Sent</th></tr></thead>
                    <tbody>
                        @forelse ($campaigns as $c)
                            <tr>
                                <td><a href="{{ route('marketing.campaigns.show', $c->id) }}"><strong>{{ $c->name }}</strong></a><br><small style="color:var(--text-3)">{{ $c->created_at->format('M j') }}{{ $c->scheduled_at ? ' · scheduled '.$c->scheduled_at->format('M j g:i A') : '' }}</small></td>
                                <td>{{ strtoupper($c->channel) }}</td>
                                <td><span class="badge {{ $c->status === 'sent' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($c->status) }}</span></td>
                                <td class="text-right">{{ $c->sent_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-8 text-center text-sm" style="color:var(--text-3)">No campaigns yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card p-6">
                <h2 class="text-lg">Automations</h2>
                <p class="text-sm" style="color:var(--text-3)">Run by the scheduler (<code>php artisan marketing:run</code>). Each guest gets each follow-up once.</p>
                @foreach ($automations as $type => $a)
                    <form method="POST" action="{{ route('marketing.automations.update', $type) }}" class="mt-4 pt-4 space-y-2" style="border-top:1px solid var(--border)">
                        @csrf
                        @method('PATCH')
                        <div class="flex justify-between items-center">
                            <strong>{{ $a->label() }}</strong>
                            <span class="text-sm" style="color:var(--text-3)">{{ $a->needsConsent() ? 'marketing — opted-in guests only' : 'service message' }}</span>
                        </div>
                        <div class="flex gap-2 items-center text-sm">
                            <label class="flex items-center gap-1"><input type="checkbox" name="enabled" value="1" @checked($a->enabled) @disabled(! $canManage) /> On</label>
                            <label class="flex items-center gap-1">after <input name="delay_hours" type="number" min="0" value="{{ $a->delay_hours }}" class="form-input" style="width:80px" @disabled(! $canManage) /> h</label>
                            @if ($a->needsConsent())
                                <select name="promotion_id" class="form-input" aria-label="Coupon from" @disabled(! $canManage)>
                                    <option value="">No coupon</option>
                                    @foreach ($promotions as $p)<option value="{{ $p->id }}" @selected($a->promotion_id === $p->id)>{{ $p->code }} ({{ $p->label() }})</option>@endforeach
                                </select>
                            @endif
                        </div>
                        <input name="subject" type="text" value="{{ $a->subject }}" required class="form-input" aria-label="{{ $a->label() }} subject" @disabled(! $canManage) />
                        <textarea name="body" rows="2" required class="form-input" aria-label="{{ $a->label() }} message" @disabled(! $canManage)>{{ $a->body }}</textarea>
                        @if ($canManage)<button type="submit" class="btn btn-sm btn-ghost">Save</button>@endif
                    </form>
                @endforeach
            </div>
        </div>

        <div class="space-y-4">
            @if ($canManage)
                <form method="POST" action="{{ route('marketing.campaigns.store') }}" class="card p-6 space-y-2">
                    @csrf
                    <h2 class="text-lg">New campaign</h2>
                    <input name="name" type="text" required class="form-input" placeholder="Rainy season getaway" aria-label="Campaign name" />
                    <select name="channel" class="form-input" aria-label="Channel"><option value="email">Email</option><option value="sms">SMS</option></select>
                    <select name="audience" class="form-input" aria-label="Audience">
                        @foreach ($audiences as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                    <input name="subject" type="text" class="form-input" placeholder="Subject (email)" aria-label="Subject" />
                    <textarea name="body" rows="4" required class="form-input" placeholder="Hi {name}, …" aria-label="Message"></textarea>
                    <select name="promotion_id" class="form-input" aria-label="Personal coupons from">
                        <option value="">No coupon</option>
                        @foreach ($promotions as $p)<option value="{{ $p->id }}">{{ $p->code }} — {{ $p->label() }} ({{ $p->applies_to }})</option>@endforeach
                    </select>
                    <button type="submit" class="btn btn-primary w-full">Save draft</button>
                </form>
            @endif

            <div class="card p-6 text-sm">
                <h2 class="text-lg">Promotions & coupons</h2>
                <p style="color:var(--text-3)">Shared codes are created on the booking and restaurant order screens. Campaigns issue one personal coupon per guest from a promotion.</p>
                <ul class="mt-2 space-y-1">
                    @forelse ($promotions as $p)
                        <li><strong>{{ $p->code }}</strong> · {{ $p->label() }} · {{ $p->applies_to }} · used {{ $p->used_count }} · coupons {{ $p->coupons_used_count }}/{{ $p->coupons_count }} redeemed @unless ($p->is_active)<span class="badge badge-gray">off</span>@endunless</li>
                    @empty
                        <li style="color:var(--text-3)">No promotions yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
