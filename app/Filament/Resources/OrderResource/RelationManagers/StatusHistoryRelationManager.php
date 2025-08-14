<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderStatusHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';
    protected static ?string $title = 'Status History';

    public function form(Form $form): Form
    {
        // We’ll only ask for a note; status_from/to + timestamps are set automatically
        return $form->schema([
            Forms\Components\Textarea::make('note')
                ->label('Note')
                ->rows(3)
                ->maxLength(2000)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('changed_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('status_from')->badge()->label('From'),
                Tables\Columns\TextColumn::make('status_to')->badge()->label('To'),
                Tables\Columns\TextColumn::make('changed_at')
                    ->dateTime()->since()->label('When'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System'),
                Tables\Columns\TextColumn::make('note')->wrap()->limit(120),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add note (no status change)')
                    ->modalHeading('Append status note')
                    ->mutateFormDataUsing(function (array $data): array {
                        $order = $this->getOwnerRecord();

                        $data['order_id']   = $order->id;
                        $data['status_from'] = $order->status; // keep current status
                        $data['status_to']   = $order->status; // note only, no change
                        $data['changed_at']  = now();
                        $data['changed_by']  = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([]) // history rows are immutable
            ->bulkActions([]);
    }
}
