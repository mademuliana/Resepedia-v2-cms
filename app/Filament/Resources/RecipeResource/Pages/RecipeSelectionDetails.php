<?php

namespace App\Filament\Resources\RecipeResource\Pages;

use App\Filament\Resources\RecipeResource;
use App\Models\Recipe;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class RecipeSelectionDetails extends Page
{
    protected static string $resource = RecipeResource::class;
    protected static string $view = 'filament.resources.recipe-resource.pages.recipe-selection-details';

    /** @var \Illuminate\Support\Collection<int,\App\Models\Recipe> */
    public Collection $recipes;

    /** Aggregated ingredient totals across selected recipes */
    public array $ingredientSummary = [];

    public function mount(): void
    {
        // Read IDs from query (?ids=1,2,3) or fallback to session selection
        $idsCsv = (string) request('ids', '');
        $ids = $idsCsv !== ''
            ? collect(explode(',', $idsCsv))->filter()->map(fn ($v) => (int) $v)->all()
            : session('recipes.table.selected', []);

        if (empty($ids)) {
            $this->recipes = collect();
            $this->ingredientSummary = [];
            return;
        }

        // Eager-load ingredients & pivot fields
        $this->recipes = Recipe::query()
            ->whereIn('id', $ids)
            ->with([
                'ingredients' => fn ($q) => $q->withPivot(['quantity', 'ingredient_total_cost', 'ingredient_total_calorie']),
            ])
            ->orderBy('name')
            ->get();

        $this->ingredientSummary = $this->buildIngredientSummaryAcrossRecipes($this->recipes);
    }

    /**
     * Aggregate ingredients for the selected recipes.
     * Assumption: each recipe contributes its ingredient quantities for ONE portion (recipe.portion_size).
     */
    protected function buildIngredientSummaryAcrossRecipes(Collection $recipes): array
    {
        $summary = [];

        foreach ($recipes as $recipe) {
            $portionSize = max(1, (float) $recipe->portion_size);

            foreach ($recipe->ingredients as $ingredient) {
                // recipe_ingredient.quantity = grams/ml needed to make 'portion_size' of this recipe
                $qtyForRecipePortion = (float) $ingredient->pivot->quantity;

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

                $summary[$id]['quantity'] += $qtyForRecipePortion;
                $summary[$id]['cost']     += $qtyForRecipePortion * (float) $ingredient->cost_per_unit;
                $summary[$id]['calorie']  += $qtyForRecipePortion * (float) $ingredient->calorie_per_unit;
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
        return 'Recipes Selection Details';
    }
}
