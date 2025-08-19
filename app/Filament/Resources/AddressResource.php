<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';
    protected static ?string $recordTitleAttribute = 'label';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(1)
                ->schema([
                    Forms\Components\Select::make('company_id')
                        ->label('Company')
                        ->relationship('company', 'name')
                        ->required()
                        ->visible(fn() => auth()->user()?->isSuperAdmin()) // admins don’t see this
                        ->live() // so dependent fields can react
                        ->afterStateUpdated(function (Set $set) {
                            // reset dependent selects when company changes
                            foreach (['customer_id', 'address_id', 'courier_id'] as $field) {
                                if (property_exists((object)[], $field)) {
                                } // no-op; keep static analyzer happy
                                $set($field, null);
                            }
                        }),
                ]),
            Forms\Components\TextInput::make('label')->maxLength(255)->placeholder('Home / Office'),
            Forms\Components\TextInput::make('line1')->required()->maxLength(255),
            Forms\Components\TextInput::make('line2')->maxLength(255),
            Forms\Components\TextInput::make('city')->required()->maxLength(255),
            Forms\Components\TextInput::make('state')->maxLength(255),
            Forms\Components\TextInput::make('postal_code')->maxLength(50),
            Forms\Components\TextInput::make('country')->default('ID')->maxLength(2),
            Forms\Components\TextInput::make('latitude')->numeric(),
            Forms\Components\TextInput::make('longitude')->numeric(),
            Forms\Components\Toggle::make('is_default')->inline(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable(),
                Tables\Columns\TextColumn::make('line1')->limit(30),
                Tables\Columns\TextColumn::make('city'),
                Tables\Columns\IconColumn::make('is_default')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
