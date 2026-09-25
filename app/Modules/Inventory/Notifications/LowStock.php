<?php

namespace App\Modules\Inventory\Notifications;

use App\Modules\Inventory\Models\InventoryItem;
use Illuminate\Notifications\Notification;

/** An item's total stock fell to or below its reorder level. */
class LowStock extends Notification
{
    public function __construct(private readonly InventoryItem $item, private readonly float $total) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'inventory_item_id' => $this->item->id,
            'message' => 'Low stock: '.$this->item->name.' is at '.$this->item->qty($this->total).' (reorder at '.$this->item->qty($this->item->reorder_level).').',
        ];
    }
}
