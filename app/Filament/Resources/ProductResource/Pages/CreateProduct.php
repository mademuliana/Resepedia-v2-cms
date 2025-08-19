<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Services\Calculations\ProductCalculator;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $updates = app(ProductCalculator::class)->recompute($this->record);
        $this->record->update($updates);
    }
}
