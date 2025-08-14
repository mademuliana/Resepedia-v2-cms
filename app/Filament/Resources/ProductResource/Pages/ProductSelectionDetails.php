<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ProductSelectionDetails extends Page
{
    protected static string $resource = ProductResource::class;

    protected static string $view = 'filament.resources.product-resource.pages.product-selection-details';

    /** @var \Illuminate\Support\Collection<int,\App\Models\Product> */
    public Collection $products;

    /** Aggregated ingredient totals across selected products */
    public array $ingredientSummary = [];

    /**
     * Meaning of product_recipe.quantity:
     *  - 'mass'    => grams/ml of recipe included in ONE product
     *  - 'portion' => portion count; 1 portion = recipe.portion_size grams/ml
     */
    public string $recipeQtyMeaning = 'mass';

    public function mount(): void
    {
        // Read IDs from query (?ids=1,2,3) or fallback to session selection
        $idsCsv = (string) request('ids', '');
        $ids = $idsCsv !== ''
            ? collect(explode(',', $idsCsv))->filter()->map(fn($v) => (int) $v)->all()
            : session('products.table.selected', []);

        if (empty($ids)) {
            $this->products = collect();
            $this->ingredientSummary = [];
            return;
        }

        // Eager-load recipes & ingredients with pivot columns
        $this->products = Product::query()
            ->whereIn('id', $ids)
            ->with([
                'recipes' => fn ($q) => $q->withPivot(['quantity', 'recipe_total_cost', 'recipe_total_calorie']),
                'recipes.ingredients' => fn ($q) => $q->withPivot(['quantity', 'ingredient_total_cost', 'ingredient_total_calorie']),
            ])
            ->orderBy('name')
            ->get();

        $this->ingredientSummary = $this->buildIngredientSummaryAcrossProducts($this->products);
    }

    /**
     * Aggregate ingredients across selected products:
     * For each product:
     *   for each recipe (pivot.quantity is mass OR portion × portion_size):
     *     for each ingredient:
     *       qty += qRecipeInProduct * (ingredient_qty_per_portion / portion_size)
     */
    protected function buildIngredientSummaryAcrossProducts(Collection $products): array
    {
        $summary = [];

        foreach ($products as $product) {
            foreach ($product->recipes as $recipe) {
                $portion = max(1, (float) $recipe->portion_size);

                // How much of this recipe is in ONE product
                $qRecipeInProduct = $this->recipeQtyMeaning === 'portion'
                    ? (float) $recipe->pivot->quantity * $portion
                    : (float) $recipe->pivot->quantity;

                foreach ($recipe->ingredients as $ingredient) {
                    // recipe_ingredient.quantity: grams/ml needed to make 'portion_size' of the recipe
                    $qtyInRecipePortion = (float) $ingredient->pivot->quantity;

                    // per-gram/ml of recipe
                    $ingredientPerUnitRecipe = $qtyInRecipePortion / $portion;

                    // ingredient qty used by this product via this recipe
                    $qtyForProduct = $qRecipeInProduct * $ingredientPerUnitRecipe;

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

                    $summary[$id]['quantity'] += $qtyForProduct;
                    $summary[$id]['cost']     += $qtyForProduct * (float) $ingredient->cost_per_unit;
                    $summary[$id]['calorie']  += $qtyForProduct * (float) $ingredient->calorie_per_unit;
                }
            }
        }

        // sort & round
        uasort($summary, fn ($a, $b) => strcmp($a['ingredient']->name, $b['ingredient']->name));
        foreach ($summary as &$row) {
            $row['quantity'] = round($row['quantity'], 2);
            $row['cost']     = round($row['cost'], 2);
            $row['calorie']  = round($row['calorie'], 2);
        }
        unset($row);

        return array_values($summary);
    }

    public function getTitle(): string
    {
        return 'Products Selection Details';
    }
}
