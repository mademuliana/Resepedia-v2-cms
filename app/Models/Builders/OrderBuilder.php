<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class OrderBuilder extends Builder
{
    public function withItems(): static
    {
        return $this->with(['products']);
    }

    public function recentWithItems(int $limit = 10): static
    {
        return $this->whereHas('products')
            ->latest('ordered_at')
            ->latest('created_at')
            ->limit($limit)
            ->withItems();
    }
}
