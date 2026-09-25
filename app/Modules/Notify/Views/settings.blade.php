<x-public-layout title="Notification settings" :show-search="false">
    <div class="container section" style="max-width:820px;">
        <div class="section-head">
            <span class="eyebrow">Your account</span>
            <h1>Notification settings</h1>
            <p class="muted">In-app notifications are always on. Choose where else each update reaches you.</p>
        </div>

        @include('customer::partials.nav')

        @if (session('success'))
            <p role="status" class="card" style="padding:10px 14px;">{{ session('success') }}</p>
        @endif

        <form method="POST" action="{{ route('account.notification-settings.update') }}" class="card" style="padding:18px;overflow-x:auto;">
            @csrf
            @method('PUT')
            <table style="width:100%;border-collapse:collapse;">
                <thead>
                    <tr>
                        <th scope="col" style="text-align:left;padding:6px;">Update</th>
                        @foreach (\App\Modules\Notify\Support\Events::CHANNELS as $label)
                            <th scope="col" style="padding:6px;">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($events as $event => [$label, $audience])
                        <tr style="border-top:1px solid var(--line, #e5e7eb);">
                            <th scope="row" style="text-align:left;padding:6px;font-weight:500;">{{ $label }} @if ($audience === 'staff')<small class="muted">· business</small>@endif</th>
                            @foreach (array_keys(\App\Modules\Notify\Support\Events::CHANNELS) as $channel)
                                @php($on = $prefs[$event.'.'.$channel] ?? \App\Modules\Notify\Support\Events::defaultOn($event, $channel))
                                <td style="text-align:center;padding:6px;">
                                    <input type="checkbox" name="channels[{{ $event }}][{{ $channel }}]" value="1" @checked($on) aria-label="{{ $label }} by {{ $channel }}" />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="submit" class="btn btn-primary" style="margin-top:12px;">Save</button>
        </form>
    </div>
</x-public-layout>
