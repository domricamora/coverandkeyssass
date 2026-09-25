<?php

namespace App\Modules\Workforce\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A person on the business's staff. `user_id` links the member account
 * that clocks in, sees "My work" and carries the tenant role (access).
 */
#[Fillable([
    'tenant_id', 'user_id', 'department_id', 'position_id', 'property_id', 'employee_no',
    'name', 'email', 'phone', 'hire_date', 'employment_type', 'status',
])]
class Employee extends Model
{
    use BelongsToTenant;

    public const TYPES = ['full_time', 'part_time', 'contract'];

    public const ACTIVE = 'active';

    public const TERMINATED = 'terminated';

    protected $attributes = ['status' => self::ACTIVE, 'employment_type' => 'full_time'];

    protected function casts(): array
    {
        return ['hire_date' => 'date'];
    }

    /** The active tenant's employee record of a user, if any. */
    public static function forUser(User $user): ?self
    {
        return static::query()->where('user_id', $user->id)->first();
    }

    public static function nextNumber(): string
    {
        // From this business's own numbers (tenant-scoped), not the global
        // auto-increment id: ids are shared across tenants and never roll back,
        // so an id-based sequence skipped numbers whenever another business hired.
        $last = (int) static::query()->pluck('employee_no')
            ->map(fn (?string $no) => (int) preg_replace('/\D/', '', (string) $no))
            ->max();

        do {
            $number = 'EMP-'.str_pad((string) ++$last, 4, '0', STR_PAD_LEFT);
        } while (static::query()->where('employee_no', $number)->exists());

        return $number;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class)->orderBy('starts_at');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->latest('clock_in_at');
    }

    public function openAttendance(): HasOne
    {
        return $this->hasOne(Attendance::class)->whereNull('clock_out_at')->latestOfMany('clock_in_at');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest('starts_on');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }
}
