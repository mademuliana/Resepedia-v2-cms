<?php

namespace App\Filament\Widgets;

use App\Models\Recipe;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Table;

class TopRecipes extends BaseWidget
{
    protected static ?string $heading = 'Most Used Recipes';
    protected static ?int $sort = 40; // second row (right)

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Only recipes used by products; adds counts & sums; limit(10)
                Recipe::query()->mostUsed(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Products')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Total Qty')
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
