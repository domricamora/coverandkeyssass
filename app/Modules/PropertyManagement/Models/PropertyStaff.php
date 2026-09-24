<?php

namespace App\Modules\PropertyManagement\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Marketplace\Models\Property;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant member assigned to a specific property (front desk,
 * housekeeping, maintenance…). Assignments are a strict subset of the
 * tenant's active members and are unique per property + user.
 */
#[Fillable([
    'tenant_id', 'property_id', 'user_id', 'role', 'assigned_by',
])]
class PropertyStaff extends Model
{
    use BelongsToTenant;
    use HasFactory;

    public const ROLE_MANAGER = 'manager';

    public const ROLE_FRONT_DESK = 'front_desk';

    public const ROLE_HOUSEKEEPING = 'housekeeping';

    public const ROLE_MAINTENANCE = 'maintenance';

    public const ROLE_STAFF = 'staff';

    public static function roles(): array
    {
        return [
            self::ROLE_MANAGER,
            self::ROLE_FRONT_DESK,
            self::ROLE_HOUSEKEEPING,
            self::ROLE_MAINTENANCE,
            self::ROLE_STAFF,
        ];
    }

    // ------------------------------------------------------------------
    // Relationships
    // ------------------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_MANAGER => 'Property manager',
            self::ROLE_FRONT_DESK => 'Front desk',
            self::ROLE_HOUSEKEEPING => 'Housekeeping',
            self::ROLE_MAINTENANCE => 'Maintenance',
            default => 'Staff',
        };
    }
}
