<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class TenantModule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'module_id', 'status', 'activated_at',
        'trial_ends_at', 'expires_at', 'limits', 'price_cents',
    ];

    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'limits' => 'array',
            'price_cents' => 'string',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function isTrialing(): bool
    {
        if (! $this->trial_ends_at) {
            return false;
        }

        $tz = $this->resolveTimezone();

        return $this->trial_ends_at->copy()->setTimezone($tz)->isFuture();
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        $tz = $this->resolveTimezone();

        return $this->expires_at->copy()->setTimezone($tz)->isPast();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    /**
     * Get the trial end date formatted for display in the current user's timezone.
     * Trial end dates are stored in UTC; this converts to the viewer's timezone.
     */
    public function trialEndsAtDisplay(?string $userTimezone = null): ?string
    {
        if (! $this->trial_ends_at) {
            return null;
        }

        $tz = $userTimezone ?? $this->resolveTimezone();

        return $this->trial_ends_at->copy()->setTimezone($tz)->format('M j, Y g:i A T');
    }

    /**
     * Get the trial end date as a Carbon instance in the user's timezone.
     */
    public function trialEndsAtInTimezone(?string $userTimezone = null): ?Carbon
    {
        if (! $this->trial_ends_at) {
            return null;
        }

        return $this->trial_ends_at->copy()->setTimezone($userTimezone ?? $this->resolveTimezone());
    }

    /**
     * Resolve the timezone to use for display.
     * Prefer the authenticated user's timezone, fall back to UTC.
     */
    protected function resolveTimezone(): string
    {
        $user = Auth::user();

        if ($user && $user->timezone) {
            return $user->timezone;
        }

        return config('app.timezone', 'UTC');
    }
}