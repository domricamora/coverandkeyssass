@php($canManage = auth()->user()->hasPermissionTo('marketing.manage'))
<x-app-layout>
    <div class="dash-row-head">
        <div>
            <h1>{{ $campaign->name }} <span class="badge badge-gray">{{ ucfirst($campaign->status) }}</span></h1>
            <p class="mt-1 text-sm" style="color:var(--text-3)">{{ strtoupper($campaign->channel) }} · {{ $audienceLabel }} · {{ $audienceCount }} opted-in guest(s) reachable{{ $campaign->promotion ? ' · personal coupons from '.$campaign->promotion->code : '' }}</p>
        </div>
        <a href="{{ route('marketing.index') }}" class="btn btn-ghost">Marketing</a>
    </div>
    <x-input-error :messages="$errors->get('campaign')" />
    <x-input-error :messages="$errors->get('scheduled_at')" />

    <div class="grid grid-cols-3 gap-4">
        <div class="card p-6" style="grid-column:span 2">
            @if ($campaign->subject)<p><strong>{{ $campaign->subject }}</strong></p>@endif
            <p class="mt-2 text-sm" style="color:var(--text-2)">{!! nl2br(e($campaign->body)) !!}</p>

            <h2 class="text-lg mt-6">Recipients ({{ $campaign->recipients->count() }})</h2>
            <table class="table mt-2">
                <tbody>
                    @forelse ($campaign->recipients as $r)
                        <tr><td>{{ $r->contact?->name }}</td><td>{{ $r->address }}</td><td>{{ $r->coupon_code }}</td><td>{{ $r->sent_at->format('M j, g:i A') }}</td></tr>
                    @empty
                        <tr><td style="color:var(--text-3)">Not sent yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($canManage && $campaign->status !== 'sent')
            <div class="space-y-4">
                <form method="POST" action="{{ route('marketing.campaigns.send', $campaign->id) }}" class="card p-6" onsubmit="return confirm('Send to {{ $audienceCount }} guest(s) now?')">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">Send now</button>
                </form>
                @if ($campaign->status === 'draft')
                    <form method="POST" action="{{ route('marketing.campaigns.schedule', $campaign->id) }}" class="card p-6 space-y-2">
                        @csrf
                        <input name="scheduled_at" type="datetime-local" required class="form-input" aria-label="Send at" />
                        <button type="submit" class="btn btn-ghost w-full">Schedule</button>
                    </form>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
