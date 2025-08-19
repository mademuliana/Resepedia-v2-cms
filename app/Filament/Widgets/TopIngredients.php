<?php

namespace App\Filament\Widgets;

use App\Models\Ingredient;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopIngredients extends BaseWidget
{
    protected static ?string $heading = 'Most Used Ingredients (in Recipes)';
    protected static ?int $sort = 20;

    public function getColumnSpan(): string|int|array
    {
        return 1;
    }

    public function table(Table $table): Table
    {
        $query = Ingredient::query()
            ->whereHas('recipes') // must appear in recipe_ingredient
            ->withCount(['recipes as usage_count'])
            ->withSum('recipes as total_qty', 'recipe_ingredient.quantity')
            ->orderByDesc('usage_count')
            ->limit(10);

        return $table
            ->query($query)
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->searchable(),
                \Filament\Tables\Columns\TextColumn::make('usage_count')->label('Recipes')->numeric(),
                \Filament\Tables\Columns\TextColumn::make('total_qty')
                    ->label('Total Qty')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 0) . ' ' . $record->unit),
            ])
            ->paginated(false);
    }
}
