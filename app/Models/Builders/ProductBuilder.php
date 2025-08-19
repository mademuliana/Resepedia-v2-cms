<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class ProductBuilder extends Builder
{
    public function withOrderStats(): static
    {
        return $this->withSum('orders as total_qty', 'order_items.quantity')
                    ->withSum('orders as total_revenue', 'order_items.product_total_price');
    }

    public function orderedOnly(): static
    {
        return $this->whereHas('orders');
    }

    public function topOrdered(int $limit = 10): static
    {
        return $this->withOrderStats()->orderedOnly()->orderByDesc('total_revenue')->limit($limit);
    }
}
