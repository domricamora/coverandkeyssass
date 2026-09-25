<?php

namespace App\Modules\Inventory\Notifications;

use App\Modules\Inventory\Models\InventoryItem;
use App\Modules\Notify\ChannelNotification;

/** An item's total stock fell to or below its reorder level. */
class LowStock extends ChannelNotification
{
    public function __construct(private readonly InventoryItem $item, private readonly float $total) {}

    public function event(): string
    {
        return 'low_stock';
    }

    public function message(): string
    {
        return 'Low stock: '.$this->item->name.' is at '.$this->item->qty($this->total).' (reorder at '.$this->item->qty($this->item->reorder_level).').';
    }

    public function link(): ?string
    {
        return route('inventory.items.show', $this->item->id);
    }

    public function data(): array
    {
        return ['inventory_item_id' => $this->item->id];
    }
}
