<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class IngredientBuilder extends Builder
{
    public function withUsage(): static
    {
        return $this->withCount(['recipes as usage_count'])
                    ->withSum('recipes as total_qty', 'recipe_ingredient.quantity');
    }

    public function usedOnly(): static
    {
        return $this->whereHas('recipes');
    }

    public function mostUsed(int $limit = 10): static
    {
        return $this->withUsage()->usedOnly()->orderByDesc('usage_count')->limit($limit);
    }
}
