<?php

namespace App\Modules\Notify;

use App\Modules\Notify\Channels\PushChannel;
use App\Modules\Notify\Channels\SmsChannel;
use App\Modules\Notify\Models\NotificationPreference;
use App\Modules\Notify\Models\PushDevice;
use App\Modules\Notify\Support\Events;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every notification (Phase 26). A subclass says what happened
 * (event(), message(), link(), data()); this class decides where it goes:
 *
 *   in-app (database)  always
 *   email / SMS / push the user's preference for that event, else the event's default —
 *                      and only when the user has an email / phone / registered device
 *
 * Push goes to an outbox (push_messages) so a mobile worker can deliver it later.
 */
abstract class ChannelNotification extends Notification implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;

    /**
     * Business the notification belongs to, captured at dispatch (in via())
     * and restored by RestoreTenantContext while a queue worker renders it.
     */
    public ?int $tenantId = null;

    /** In-app stays immediate (the bell updates now); mail, SMS and push go through the queue. */
    public function viaConnections(): array
    {
        return ['database' => 'sync'];
    }

    /** @return list<object> */
    public function middleware(object $notifiable, string $channel): array
    {
        return [new \App\Support\RestoreTenantContext($this->tenantId)];
    }

    /** The tenant of the first tenant-owned model this notification carries. */
    private function ownedTenantId(): ?int
    {
        foreach ((new \ReflectionObject($this))->getProperties() as $property) {
            $value = $property->getValue($this);

            if ($value instanceof \Illuminate\Database\Eloquent\Model && $value->getAttribute('tenant_id')) {
                return (int) $value->getAttribute('tenant_id');
            }
        }

        return null;
    }

    abstract public function event(): string;

    /** One line for the in-app list, SMS and push body. */
    abstract public function message(): string;

    public function subject(): string
    {
        return Events::ALL[$this->event()][0] ?? config('app.name');
    }

    public function link(): ?string
    {
        return null;
    }

    /** Extra keys for the in-app payload. @return array<string, mixed> */
    public function data(): array
    {
        return [];
    }

    public function via(object $notifiable): array
    {
        // Captured while the dispatching code still knows the business (or from
        // the model itself, e.g. webhooks that run without a context).
        $this->tenantId ??= app(\App\Support\TenantContext::class)->id() ?? $this->ownedTenantId();

        $channels = ['database'];

        if ($notifiable->email && $this->allows($notifiable, 'mail')) {
            $channels[] = 'mail';
        }
        if ($notifiable->phone && $this->allows($notifiable, 'sms')) {
            $channels[] = SmsChannel::class;
        }
        if ($this->allows($notifiable, 'push') && PushDevice::query()->where('user_id', $notifiable->id)->exists()) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return $this->data() + ['event' => $this->event(), 'message' => $this->message(), 'link' => $this->link()];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject())->greeting('Hi '.(strtok((string) $notifiable->name, ' ') ?: 'there').',')->line($this->message());

        return $this->link() ? $mail->action('Open', $this->link()) : $mail;
    }

    public function toSms(object $notifiable): string
    {
        return $this->message();
    }

    /** @return array{title: string, body: string, data: array} */
    public function toPush(object $notifiable): array
    {
        return ['title' => $this->subject(), 'body' => $this->message(), 'data' => ['event' => $this->event(), 'link' => $this->link()] + $this->data()];
    }

    private function allows(object $notifiable, string $channel): bool
    {
        $preference = NotificationPreference::query()->where('user_id', $notifiable->id)->where('event', $this->event())->where('channel', $channel)->value('enabled');

        return $preference === null ? Events::defaultOn($this->event(), $channel) : (bool) $preference;
    }
}
