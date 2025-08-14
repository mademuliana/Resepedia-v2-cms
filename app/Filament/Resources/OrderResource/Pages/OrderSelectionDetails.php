<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class OrderSelectionDetails extends Page
{
    protected static string $resource = OrderResource::class;

    protected static string $view = 'filament.resources.order-resource.pages.order-selection-details';

    /** Selected order IDs (from session) */
    public array $selectedIds = [];

    /** Loaded orders with everything we need */
    public Collection $orders;

    /** Aggregated product totals across all selected orders */
    public array $productSummary = [];

    /** Aggregated ingredient totals across all selected orders */
    public array $ingredientSummary = [];

    /**
     * Meaning of product_recipe.quantity:
     *  - 'mass'    => grams/ml of recipe included in ONE product
     *  - 'portion' => portion count; 1 portion = recipe.portion_size grams/ml
     */
    public string $recipeQtyMeaning = 'mass';

    public function mount(): void
    {
        $this->selectedIds = session('orders.table.selected', []);

        if (empty($this->selectedIds)) {
            Notification::make()
                ->title('No orders selected')
                ->body('Go back to the Orders list, select some orders, then click “View selection details”.')
                ->warning()
                ->send();

            // Load empty collection to keep the view simple
            $this->orders = collect();
            $this->productSummary = [];
            $this->ingredientSummary = [];
            return;
        }

        // Eager-load everything down to ingredients & pivot columns
        $this->orders = Order::query()
            ->whereIn('id', $this->selectedIds)
            ->with([
                'products' => fn ($q) => $q->withPivot(['quantity', 'product_total_price', 'product_total_calorie']),
                'products.recipes' => fn ($q) => $q->withPivot(['quantity', 'recipe_total_cost', 'recipe_total_calorie']),
                'products.recipes.ingredients' => fn ($q) => $q->withPivot(['quantity', 'ingredient_total_cost', 'ingredient_total_calorie']),
            ])
            ->orderByDesc('created_at')
            ->get();

        $this->productSummary    = $this->buildProductSummary($this->orders);
        $this->ingredientSummary = $this->buildIngredientSummaryAcrossOrders($this->orders);
    }

    /** Sum product line totals across all selected orders. */
    protected function buildProductSummary(Collection $orders): array
    {
        $summary = [];

        foreach ($orders as $order) {
            foreach ($order->products as $p) {
                $id = $p->id;

                if (! isset($summary[$id])) {
                    $summary[$id] = [
                        'product' => $p,
                        'quantity' => 0,
                        'price' => 0.0,
                        'calorie' => 0.0,
                    ];
                }

                $summary[$id]['quantity'] += (int) $p->pivot->quantity; // order_items.quantity (units of product)
                $summary[$id]['price']    += (float) $p->pivot->product_total_price; // line total
                $summary[$id]['calorie']  += (float) $p->pivot->product_total_calorie; // line total
            }
        }

        // Sort by product name for stable display + round
        uasort($summary, fn ($a, $b) => strcmp($a['product']->name, $b['product']->name));
        foreach ($summary as &$row) {
            $row['price']   = round($row['price'], 2);
            $row['calorie'] = round($row['calorie'], 2);
        }
        unset($row);

        return array_values($summary);
    }

    /**
     * Aggregate ingredients across all selected orders with portion math:
     * order qty × product_recipe qty (mass or portion×portion_size)
     * × (ingredient qty per portion_size / portion_size)
     */
    protected function buildIngredientSummaryAcrossOrders(Collection $orders): array
    {
        $summary = [];

        foreach ($orders as $order) {
            foreach ($order->products as $product) {
                $qProd = (int) $product->pivot->quantity; // order_items.quantity

                foreach ($product->recipes as $recipe) {
                    $portion = max(1, (float) $recipe->portion_size);

                    // Qty of this recipe included in ONE product (grams/ml)
                    $qRecipeInProduct = $this->recipeQtyMeaning === 'portion'
                        ? (float) $recipe->pivot->quantity * $portion
                        : (float) $recipe->pivot->quantity;

                    foreach ($recipe->ingredients as $ingredient) {
                        // recipe_ingredient.quantity = grams/ml for 'portion_size' of recipe
                        $qtyInRecipePortion = (float) $ingredient->pivot->quantity; // grams/ml per portion

                        // Ingredient per 1 gram/ml of recipe:
                        $ingredientPerUnitRecipe = $qtyInRecipePortion / $portion;

                        // Ingredient used in ONE product (for this recipe line)
                        $qtyInOneProduct = $qRecipeInProduct * $ingredientPerUnitRecipe;

                        // For the whole order line:
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
        }

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
