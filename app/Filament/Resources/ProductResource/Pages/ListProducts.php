<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    /** Filament’s built-in table selection property */
    public array $selectedTableRecords = [];

    /** Session key for sticky selection */
    protected string $selectionSessionKey = 'products.table.selected';

    public function mount(): void
    {
        parent::mount();
        // Restore previously saved selection (if any)
        $this->selectedTableRecords = session($this->selectionSessionKey, []);
    }

    /** When user checks/unchecks, save it */
    public function updatedSelectedTableRecords($value = null, ?string $key = null): void
    {
        session()->put($this->selectionSessionKey, $this->selectedTableRecords);
    }

    /** Re-apply saved selection after any table state change */
    public function updatedTableSearch($value = null): void                { $this->restoreSelection(); }
    public function updatedTableFilters($value = null): void               { $this->restoreSelection(); }
    public function updatedTableSorts($value = null): void                 { $this->restoreSelection(); }
    public function updatedTableColumnSearches($value = null, ?string $key = null): void { $this->restoreSelection(); }
    public function updatedTableRecordsPerPage($value = null): void        { $this->restoreSelection(); }
    public function updatedTablePage($value = null): void                  { $this->restoreSelection(); }

    protected function restoreSelection(): void
    {
        $this->selectedTableRecords = session($this->selectionSessionKey, $this->selectedTableRecords);
    }

    /** Optional helper button */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('clearSelection')
                ->label('Clear selection')
                ->icon('heroicon-o-x-mark')
                ->color('secondary')
                ->visible(fn () => ! empty($this->selectedTableRecords))
                ->action(function () {
                    $this->selectedTableRecords = [];
                    session()->forget($this->selectionSessionKey);
                }),
        ];
    }
}
