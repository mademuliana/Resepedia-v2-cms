<?php

namespace App\Filament\Widgets;

use App\Models\Recipe;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopRecipes extends BaseWidget
{
    protected static ?string $heading = 'Most Used Recipes (in Products)';
    protected static ?int $sort = 40;

    public function getColumnSpan(): string|int|array
    {
        return 1;
    }

    public function table(Table $table): Table
    {
        $query = Recipe::query()
            ->whereHas('products') // must appear in product_recipe
            ->withCount(['products as usage_count'])
            ->withSum('products as total_qty', 'product_recipe.quantity')
            ->orderByDesc('usage_count')
            ->limit(10);

        return $table
            ->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->searchable(),
                \Filament\Tables\Columns\TextColumn::make('usage_count')->label('Products')->numeric(),
                \Filament\Tables\Columns\TextColumn::make('total_qty')->label('Total Qty')->numeric(),
            ])
            ->paginated(false);
    }
}
