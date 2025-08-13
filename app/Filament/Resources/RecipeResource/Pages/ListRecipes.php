<?php

namespace App\Filament\Resources\RecipeResource\Pages;

use App\Filament\Resources\RecipeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecipes extends ListRecords
{
    protected static string $resource = RecipeResource::class;

    /** Filament’s built-in table selection property (IDs of selected rows) */
    public array $selectedTableRecords = [];

    /** Session key so selection survives search/filter/page/reload */
    protected string $selectionSessionKey = 'recipes.table.selected';

    public function mount(): void
    {
        parent::mount();
        // Restore previously-saved selection
        $this->selectedTableRecords = session($this->selectionSessionKey, []);
    }

    /** Whenever user (de)selects rows, save current selection */
    public function updatedSelectedTableRecords($value = null, ?string $key = null): void
    {
        session()->put($this->selectionSessionKey, $this->selectedTableRecords);
    }

    /** Re-apply saved selection after any table state change */
    public function updatedTableSearch($value = null): void                              { $this->restoreSelection(); }
    public function updatedTableFilters($value = null): void                             { $this->restoreSelection(); }
    public function updatedTableSorts($value = null): void                               { $this->restoreSelection(); }
    public function updatedTableColumnSearches($value = null, ?string $key = null): void { $this->restoreSelection(); }
    public function updatedTableRecordsPerPage($value = null): void                      { $this->restoreSelection(); }
    public function updatedTablePage($value = null): void                                { $this->restoreSelection(); }

    protected function restoreSelection(): void
    {
        $this->selectedTableRecords = session($this->selectionSessionKey, $this->selectedTableRecords);
    }

    /** Optional: a button to clear selection */
    protected function getHeaderActions(): array
    {
        return [
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
