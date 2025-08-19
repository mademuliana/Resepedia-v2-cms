<?php

namespace App\Filament\Resources\RecipeResource\Pages;

use App\Filament\Resources\RecipeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\Calculations\RecipeCalculator;

class CreateRecipe extends CreateRecord
{
    protected static string $resource = RecipeResource::class;

    protected function afterCreate(): void
    {
        $updates = app(RecipeCalculator::class)->recompute($this->record);
        $this->record->update($updates);
    }
}
