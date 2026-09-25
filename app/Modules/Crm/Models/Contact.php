<?php

namespace App\Modules\Crm\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use App\Modules\Booking\Models\Booking;
use App\Modules\Ordering\Models\Order;
use App\Modules\RestaurantManagement\Models\TableReservation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A guest of this business. Identity = linked account, else email, else phone. */
#[Fillable(['tenant_id', 'user_id', 'name', 'email', 'phone', 'source', 'is_vip', 'marketing_consent', 'consent_at'])]
class Contact extends Model
{
    use BelongsToTenant;

    protected $table = 'crm_contacts';

    protected $attributes = ['source' => 'manual', 'is_vip' => false, 'marketing_consent' => false];

    protected function casts(): array
    {
        return [
            'is_vip' => 'boolean',
            'marketing_consent' => 'boolean',
            'consent_at' => 'datetime',
            'total_spend' => 'decimal:2',
            'first_seen_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'crm_contact_tag', 'crm_contact_id', 'crm_tag_id')->orderBy('name');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'crm_contact_id')->latest();
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class, 'crm_contact_id')->latest('occurred_at');
    }

    // ------------------------------------------------------------------
    // History, read live from the operational modules by identity
    // ------------------------------------------------------------------

    public function bookingsQuery(): Builder
    {
        return $this->matching(Booking::query(), 'user_id', 'guest_email', 'guest_phone');
    }

    public function ordersQuery(): Builder
    {
        return $this->matching(Order::query(), 'user_id', null, 'customer_phone');
    }

    public function reservationsQuery(): Builder
    {
        return $this->matching(TableReservation::query(), 'user_id', 'guest_email', 'guest_phone');
    }

    private function matching(Builder $query, string $userColumn, ?string $emailColumn, string $phoneColumn): Builder
    {
        return $query->where(function (Builder $q) use ($userColumn, $emailColumn, $phoneColumn): void {
            $q->whereRaw('1 = 0');
            if ($this->user_id) {
                $q->orWhere($userColumn, $this->user_id);
            }
            if ($this->email && $emailColumn) {
                $q->orWhere($emailColumn, $this->email);
            }
            if ($this->phone) {
                $q->orWhere($phoneColumn, $this->phone);
            }
        });
    }
}
