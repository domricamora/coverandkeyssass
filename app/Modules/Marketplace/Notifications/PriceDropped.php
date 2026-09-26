<?php

namespace App\Modules\Marketplace\Notifications;

use App\Modules\Marketplace\Models\Property;
use App\Modules\Notify\ChannelNotification;

/** A stay on the guest's wish list got cheaper (favorites:price-drops, daily). */
class PriceDropped extends ChannelNotification
{
    public function __construct(private readonly Property $property, private readonly float $was) {}

    public function event(): string
    {
        return 'price_drop';
    }

    public function subject(): string
    {
        return 'Price drop: '.$this->property->name;
    }

    public function message(): string
    {
        $now = (float) $this->property->base_price;

        return $this->property->name.' dropped from '.$this->property->currency.' '.number_format($this->was, 0)
            .' to '.$this->property->priceLabel().' a night ('.round((1 - $now / $this->was) * 100).'% less).';
    }

    public function link(): ?string
    {
        return route('marketplace.properties.show', $this->property->slug);
    }

    public function data(): array
    {
        return ['property_id' => $this->property->id, 'was' => $this->was, 'now' => (float) $this->property->base_price];
    }
}
