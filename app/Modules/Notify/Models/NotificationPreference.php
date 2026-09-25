<?php

namespace App\Modules\Notify\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'event', 'channel', 'enabled'])]
class NotificationPreference extends Model
{
    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }
}
