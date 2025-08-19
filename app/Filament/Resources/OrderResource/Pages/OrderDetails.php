<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\Page;

class OrderDetails extends Page
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.resources.order-resource.pages.order-details';

    public Order $record;

    public string $recipeQtyMeaning = 'mass';

    /** Precomputed ingredient summary for the view. */
    public array $ingredientSummary = [];

    public function mount(Order $record): void
    {
        // ✅ Force eager-load all needed relations (with pivot columns), even if models didn't define withPivot()
        $record->load([
            'products' => fn ($q) => $q->withPivot(['quantity', 'product_total_price', 'product_total_calorie']),
            'products.recipes' => fn ($q) => $q->withPivot(['quantity', 'recipe_total_cost', 'recipe_total_calorie']),
            'products.recipes.ingredients' => fn ($q) => $q->withPivot(['quantity', 'ingredient_total_cost', 'ingredient_total_calorie']),
        ]);

        $this->record = $record;
        $this->ingredientSummary = $this->buildIngredientSummary();
    }

    /**
     * Aggregate ingredients across the entire order with correct scaling:
     * order qty × product_recipe qty (mass or portion×portion_size)
     * × (ingredient qty per portion_size / portion_size)
     */
    protected function buildIngredientSummary(): array
    {
        $summary = [];

        foreach ($this->record->products as $product) {
            $qProd = (int) $product->pivot->quantity; // order_items.quantity

            foreach ($product->recipes as $recipe) {
                $portionSize = max(1, (float) $recipe->portion_size);

                // Qty of this recipe included in ONE product (grams/ml)
                $qRecipeInProduct = $this->recipeQtyMeaning === 'portion'
                    ? (float) $recipe->pivot->quantity * $portionSize
                    : (float) $recipe->pivot->quantity;

                foreach ($recipe->ingredients as $ingredient) {
                    // recipe_ingredient.quantity is grams/ml to make 'portion_size' of recipe
                    $qtyInRecipePortion = (float) $ingredient->pivot->quantity;

                    // ingredient per 1 gram/ml of recipe:
                    $ingredientPerUnitRecipe = $qtyInRecipePortion / $portionSize;

                    // ingredient used in ONE product (for this recipe line)
                    $qtyInOneProduct = $qRecipeInProduct * $ingredientPerUnitRecipe;

                    // for the whole order line:
                    $qtyForOrderLine = $qProd * $qtyInOneProduct;

                    $id = $ingredient->id;

                    if (! isset($summary[$id])) {
                        $summary[$id] = [
                            'ingredient' => $ingredient,
                            'unit'       => $ingredient->unit,
                            'quantity'   => 0.0,
                            'cost'       => 0.0,
                            'calorie'    => 0.0,
                        ];
                    }

                    $summary[$id]['quantity'] += $qtyForOrderLine;
                    $summary[$id]['cost']     += $qtyForOrderLine * (float) $ingredient->cost_per_unit;
                    $summary[$id]['calorie']  += $qtyForOrderLine * (float) $ingredient->calorie_per_unit;
                }
            }
        }

        // Sort for stable display & round numbers for UI
        uasort($summary, fn ($a, $b) => strcmp($a['ingredient']->name, $b['ingredient']->name));
        foreach ($summary as &$row) {
            $row['quantity'] = round($row['quantity'], 2);
            $row['cost']     = round($row['cost'], 2);
            $row['calorie']  = round($row['calorie'], 2);
        }
        unset($row);

        return array_values($summary);
    }
}
