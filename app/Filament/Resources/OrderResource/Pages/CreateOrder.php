<?php

namespace App\Filament\Resources\OrderResource\Pages;
use App\Services\Calculations\OrderCalculator;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        $updates = app(OrderCalculator::class)->recompute($this->record);
        $this->record->update($updates);
    }
}
