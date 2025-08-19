<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class RecipeBuilder extends Builder
{
    public function withUsage(): static
    {
        return $this->withCount(['products as usage_count'])
                    ->withSum('products as total_qty', 'product_recipe.quantity');
    }

    public function usedOnly(): static
    {
        return $this->whereHas('products');
    }

    public function mostUsed(int $limit = 10): static
    {
        return $this->withUsage()->usedOnly()->orderByDesc('usage_count')->limit($limit);
    }
}
