<?php

namespace App\Filament\Resources\RecipeResource\Pages;

use App\Filament\Resources\RecipeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Services\Calculations\RecipeCalculator;

class EditRecipe extends EditRecord
{
    protected static string $resource = RecipeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $updates = app(RecipeCalculator::class)->recompute($this->record);
        $this->record->update($updates);
    }
}
