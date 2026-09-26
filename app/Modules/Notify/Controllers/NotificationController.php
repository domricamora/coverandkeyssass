<?php

namespace App\Modules\Notify\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notify\Models\NotificationPreference;
use App\Modules\Notify\Models\PushDevice;
use App\Modules\Notify\Support\Events;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return \Inertia\Inertia::render('Notifications/Index', [
            'notifications' => $request->user()->notifications()->paginate(25)->through(fn ($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? 'Update',
                'href' => ! empty($n->data['link']) ? route('notifications.open', $n->id) : null,
                'when' => $n->created_at->diffForHumans(),
                'unread' => $n->read_at === null,
            ]),
            'unread' => $request->user()->unreadNotifications()->count(),
            'urls' => ['read' => route('notifications.read'), 'settings' => route('account.notification-settings')],
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }

    /** Mark one read and follow its link (only the owner's own rows). */
    public function open(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $link = $notification->data['link'] ?? null;

        // Only same-site links: data is ours, but never turn it into an open redirect.
        return is_string($link) && str_starts_with($link, url('/')) ? redirect($link) : back();
    }

    public function settings(Request $request)
    {
        $user = $request->user();

        return view('notify::settings', [
            'events' => $this->eventsFor($user),
            'prefs' => NotificationPreference::query()->where('user_id', $user->id)->get()
                ->mapWithKeys(fn ($p) => [$p->event.'.'.$p->channel => $p->enabled]),
        ]);
    }

    public function saveSettings(Request $request)
    {
        $user = $request->user();
        $checked = (array) $request->input('channels', []);

        foreach (array_keys($this->eventsFor($user)) as $event) {
            foreach (array_keys(Events::CHANNELS) as $channel) {
                NotificationPreference::query()->updateOrCreate(
                    ['user_id' => $user->id, 'event' => $event, 'channel' => $channel],
                    ['enabled' => ! empty($checked[$event][$channel])],
                );
            }
        }

        return back()->with('success', 'Notification settings saved.');
    }

    public function registerDevice(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::in(['ios', 'android', 'web'])],
        ]);

        // A token moves to whoever signs in on the device.
        PushDevice::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => $request->user()->id,
            'platform' => $data['platform'],
            'last_seen_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function removeDevice(Request $request)
    {
        $request->validate(['token' => ['required', 'string', 'max:255']]);

        PushDevice::query()->where('user_id', $request->user()->id)->where('token', $request->input('token'))->delete();

        return response()->json(['ok' => true]);
    }

    /** Guests see guest events; business members also see staff events. */
    private function eventsFor($user): array
    {
        $staff = $user->tenants()->exists();

        return array_filter(Events::ALL, fn ($meta) => $meta[1] === 'guest' || $staff);
    }
}
