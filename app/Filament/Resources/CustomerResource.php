<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers\AddressesRelationManager;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Set;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Customer')
                ->schema([
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
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->email()->maxLength(255),
                    Forms\Components\TextInput::make('phone')->maxLength(255),
                    Forms\Components\Textarea::make('notes')->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('email')->toggleable(),
            Tables\Columns\TextColumn::make('phone')->toggleable(),
            Tables\Columns\TextColumn::make('addresses_count')
                ->counts('addresses')->label('Addresses')->sortable(),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()->since()->label('Created'),
        ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AddressesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
            'view'   => Pages\DetailCustomer::route('/{record}'),
        ];
    }
}
