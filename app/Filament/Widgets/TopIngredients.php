<?php

namespace App\Filament\Widgets;

use App\Models\Ingredient;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Table;

class TopIngredients extends BaseWidget
{
    protected static ?string $heading = 'Most Used Ingredients';
    protected static ?int $sort = 30; // second row (left)

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Only ingredients used in recipes; adds counts & sums; limit(10)
                Ingredient::query()->mostUsed(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('usage_count')
                    ->label('Recipes')
                    ->numeric(),

                Tables\Columns\TextColumn::make('total_qty')
                    ->label('Total Qty')
                    ->state(function ($record) {
                        $qty  = (float) ($record->total_qty ?? 0);
                        $unit = $record->unit ?? '';
                        // compact quantity string without extra zeros
                        $display = rtrim(rtrim(number_format($qty, 0, '.', ','), '0'), '.');
                        return $display . ($unit ? " {$unit}" : '');
                    }),
            ])
            ->paginated(false);
    }
}
