<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Address;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Collection;
use App\Support\Format;
use App\Services\Calculations\OrderFormCalculator;


class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function form(Form $form): Form
    {
        return $form
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
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('customer_name')
                            ->label('Customer Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('customer_phone')
                            ->label('Customer Phone Number')
                            ->required()
                            ->default(+62),
                    ]),

                Forms\Components\Section::make('Customer & Address')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Customer')
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\TextInput::make('email')->email(),
                                Forms\Components\TextInput::make('phone'),
                                Forms\Components\Textarea::make('notes'),
                            ]),

                        Forms\Components\Select::make('address_id')
                            ->label('Address')
                            ->options(function (callable $get) {
                                $cid = $get('customer_id');
                                return $cid
                                    ? Address::where('customer_id', $cid)
                                    ->orderByDesc('is_default')
                                    ->orderBy('label')
                                    ->pluck('label', 'id')
                                    : [];
                            })
                            ->disabled(fn(callable $get) => ! $get('customer_id'))
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\Hidden::make('customer_id')->default(fn(callable $get) => $get('customer_id')),
                                Forms\Components\TextInput::make('label'),
                                Forms\Components\TextInput::make('line1')->required(),
                                Forms\Components\TextInput::make('line2'),
                                Forms\Components\TextInput::make('city')->required(),
                                Forms\Components\TextInput::make('state'),
                                Forms\Components\TextInput::make('postal_code'),
                                Forms\Components\TextInput::make('country')->default('ID'),
                                Forms\Components\TextInput::make('latitude'),
                                Forms\Components\TextInput::make('longitude'),
                                Forms\Components\Toggle::make('is_default'),
                            ]),

                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('Snapshot customer to order contact')
                                ->visible(fn(callable $get) => filled($get('customer_id')))
                                ->action(function (array $data, callable $set) {
                                    $customer = Customer::find($data['customer_id'] ?? null);
                                    if ($customer) {
                                        $set('customer_name',  $customer->name);
                                        $set('customer_email', $customer->email);
                                        $set('customer_phone', $customer->phone);
                                    }
                                }),
                        ])->alignment('left'),
                    ])->collapsible(),

                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('total_price')
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp.')
                            ->disabled(),

                        Forms\Components\TextInput::make('total_calorie')
                            ->numeric()
                            ->default(0)
                            ->suffix('kcal')
                            ->disabled(),
                    ]),

                Forms\Components\Grid::make(1)
                    ->schema([
                        Forms\Components\Repeater::make('products')
                            ->relationship('products')
                            ->reactive()
                            ->debounce(300)
                            ->afterStateHydrated(function (Set $set, ?array $state, Get $get) {
                                // Normalize & compute once on load/open
                                self::computeRowsAndTotals($state ?? [], $set, $get);
                            })
                            ->afterStateUpdated(function (?array $state, Set $set, Get $get) {
                                // Single source of truth: recompute using ENTIRE repeater snapshot
                                self::computeRowsAndTotals($state ?? [], $set, $get);
                            })
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::all()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->reactive(), // no per-field afterStateUpdated (avoid race)

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->live(debounce: 300),

                                Forms\Components\TextInput::make('product_total_price')
                                    ->label('Price')
                                    ->numeric()
                                    ->prefix('Rp.')
                                    ->default(0),

                                Forms\Components\TextInput::make('product_total_calorie')
                                    ->label('Calorie')
                                    ->numeric()
                                    ->suffix('kcal')
                                    ->default(0),
                            ])
                            ->columns(4)
                            ->createItemButtonLabel('Add Product'),
                    ]),
                Forms\Components\Section::make('Payments')
                    ->collapsible()
                    ->schema([

                        // Live summary from current form state (not just DB)
                        Forms\Components\Placeholder::make('payment_summary')
                            ->label('Summary')
                            ->content(function (Get $get) {
                                $rows = $get('payments') ?? [];
                                $paid = 0.0;
                                $refund = 0.0;

                                foreach ($rows as $row) {
                                    $amount = (float) ($row['amount'] ?? 0);
                                    $type   = (string) ($row['type'] ?? '');
                                    $status = (string) ($row['status'] ?? '');

                                    if ($status === 'paid') {
                                        if ($type === 'refund') {
                                            $refund += $amount;
                                        } else {
                                            $paid += $amount;
                                        }
                                    }
                                }

                                $paidNet   = $paid - $refund;
                                $total     = (float) ($get('total_price') ?? 0);
                                $balance   = max(0, $total - $paidNet);

                                $fmt = fn($n) => 'Rp.' . number_format((float)$n, 2, '.', ',');

                                return "Paid: {$fmt($paidNet)}  •  Order Total: {$fmt($total)}  •  Balance Due: {$fmt($balance)}";
                            }),

                        // Inline hasMany editor for payments
                        Forms\Components\Repeater::make('payments')
                            ->relationship('payments')      // hasMany(Payment::class)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columns(12)
                            ->schema([
                                Forms\Components\Select::make('type')
                                    ->options([
                                        'deposit' => 'Deposit',
                                        'balance' => 'Balance',
                                        'full'    => 'Full',
                                        'refund'  => 'Refund',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\Select::make('method')
                                    ->options([
                                        'cash'          => 'Cash',
                                        'bank_transfer' => 'Bank Transfer',
                                        'ewallet'       => 'eWallet',
                                        'card'          => 'Card',
                                        'other'         => 'Other',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('Rp.')
                                    ->required()
                                    ->columnSpan(3),

                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending'  => 'Pending',
                                        'paid'     => 'Paid',
                                        'failed'   => 'Failed',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->columnSpan(2),

                                Forms\Components\DateTimePicker::make('paid_at')
                                    ->native(false)
                                    ->seconds(false)
                                    ->label('Paid at')
                                    ->columnSpan(3),

                                Forms\Components\TextInput::make('reference')
                                    ->label('Reference')
                                    ->placeholder('Bank ref / VA / Txn ID')
                                    ->columnSpan(6),

                                Forms\Components\Textarea::make('notes')
                                    ->rows(2)
                                    ->columnSpan(6),
                            ])

                            // Normalize each row before create/save
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data) {
                                $data['amount'] = isset($data['amount']) ? (float) $data['amount'] : 0.0;
                                $data['reference'] = trim((string) ($data['reference'] ?? ''));
                                $data['notes'] = trim((string) ($data['notes'] ?? ''));
                                return $data;
                            })
                            ->mutateRelationshipDataBeforeSaveUsing(function (array $data) {
                                $data['amount'] = isset($data['amount']) ? (float) $data['amount'] : 0.0;
                                $data['reference'] = trim((string) ($data['reference'] ?? ''));
                                $data['notes'] = trim((string) ($data['notes'] ?? ''));
                                return $data;
                            }),

                        // Helper actions to prefill rows quickly
                        Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('addDeposit')
                                ->label('Add deposit (from order deposit amount)')
                                ->visible(fn(Get $get) => (bool) $get('deposit_required') && (float) ($get('deposit_amount') ?? 0) > 0)
                                ->action(function (Get $get, Set $set) {
                                    $rows = $get('payments') ?? [];
                                    $rows[] = [
                                        'type'    => 'deposit',
                                        'method'  => 'cash',
                                        'amount'  => (float) ($get('deposit_amount') ?? 0),
                                        'status'  => 'pending',
                                        'paid_at' => null,
                                        'reference' => null,
                                        'notes'     => null,
                                    ];
                                    $set('payments', $rows);
                                }),

                            \Filament\Forms\Components\Actions\Action::make('addBalanceFromDue')
                                ->label('Add balance (from current balance due)')
                                ->action(function (Get $get, Set $set) {
                                    $rows = $get('payments') ?? [];
                                    // recompute due from current rows
                                    $paid = 0.0;
                                    $refund = 0.0;
                                    foreach ($rows as $row) {
                                        $amt = (float) ($row['amount'] ?? 0);
                                        $typ = (string) ($row['type'] ?? '');
                                        $sts = (string) ($row['status'] ?? '');
                                        if ($sts === 'paid') {
                                            if ($typ === 'refund') $refund += $amt;
                                            else $paid += $amt;
                                        }
                                    }
                                    $paidNet = $paid - $refund;
                                    $total   = (float) ($get('total_price') ?? 0);
                                    $due     = max(0, $total - $paidNet);

                                    if ($due <= 0) return;

                                    $rows[] = [
                                        'type'    => 'balance',
                                        'method'  => 'cash',
                                        'amount'  => $due,
                                        'status'  => 'pending',
                                        'paid_at' => null,
                                        'reference' => null,
                                        'notes'     => null,
                                    ];
                                    $set('payments', $rows);
                                }),
                        ])->alignment('left'),
                    ]),

                Forms\Components\Section::make('Delivery & Courier')
                    ->collapsible()
                    ->schema([
                        // Inline edit of the hasOne(OrderDelivery) snapshot
                        Forms\Components\Group::make()
                            ->relationship('delivery') // <-- hasOne relation on Order model
                            ->columns(12)
                            ->schema([
                                Forms\Components\TextInput::make('contact_name')
                                    ->label('Contact name')
                                    ->required()
                                    ->columnSpan(4),
                                Forms\Components\TextInput::make('contact_phone')
                                    ->label('Contact phone')
                                    ->tel()
                                    ->required()
                                    ->columnSpan(4),

                                Forms\Components\Select::make('courier_id')
                                    ->relationship('courier', 'name')
                                    ->label('Courier')
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('courier_name')
                                    ->helperText('Snapshot name (used if courier record changes).')
                                    ->columnSpan(4),

                                Forms\Components\TextInput::make('tracking_code')
                                    ->columnSpan(4),

                                Forms\Components\DateTimePicker::make('delivery_window_start')
                                    ->native(false)
                                    ->seconds(false)
                                    ->label('Window start')
                                    ->columnSpan(4),
                                Forms\Components\DateTimePicker::make('delivery_window_end')
                                    ->native(false)
                                    ->seconds(false)
                                    ->label('Window end')
                                    ->columnSpan(4),

                                Forms\Components\DateTimePicker::make('delivered_at')
                                    ->native(false)
                                    ->seconds(false)
                                    ->columnSpan(4),

                                Forms\Components\Textarea::make('delivery_instructions')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Forms\Components\Fieldset::make('Address snapshot')
                                    ->columns(12)
                                    ->schema([
                                        Forms\Components\TextInput::make('line1')->label('Line 1')->required()->columnSpan(6),
                                        Forms\Components\TextInput::make('line2')->label('Line 2')->columnSpan(6)->nullable(),
                                        Forms\Components\TextInput::make('city')->required()->columnSpan(3),
                                        Forms\Components\TextInput::make('state')->columnSpan(3),
                                        Forms\Components\TextInput::make('postal_code')->label('Postal code')->columnSpan(3),
                                        Forms\Components\TextInput::make('country')->default('ID')->columnSpan(3),
                                        Forms\Components\TextInput::make('latitude')->numeric()->label('Lat')->columnSpan(3),
                                        Forms\Components\TextInput::make('longitude')->numeric()->label('Lng')->columnSpan(3),
                                    ]),
                            ]),

                        // Helper actions (root-level) that write into the nested hasOne using dot paths
                        Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('snapshotFromOrderAddress')
                                ->label('Use order address for delivery snapshot')
                                ->visible(fn(Get $get) => filled($get('address_id')))
                                ->requiresConfirmation()
                                ->action(function (Get $get, Set $set) {
                                    $addrId = $get('address_id');
                                    $addr   = $addrId ? \App\Models\Address::find($addrId) : null;
                                    if (! $addr) return;

                                    // write into the hasOne fields via dot-notation
                                    $set('delivery.line1',        $addr->line1);
                                    $set('delivery.line2',        $addr->line2);
                                    $set('delivery.city',         $addr->city);
                                    $set('delivery.state',        $addr->state);
                                    $set('delivery.postal_code',  $addr->postal_code);
                                    $set('delivery.country',      $addr->country ?? 'ID');
                                    $set('delivery.latitude',     $addr->latitude);
                                    $set('delivery.longitude',    $addr->longitude);

                                    // If order contact empty, hydrate from order snapshot
                                    if (! $get('delivery.contact_name')) {
                                        $set('delivery.contact_name',  $get('customer_name'));
                                    }
                                    if (! $get('delivery.contact_phone')) {
                                        $set('delivery.contact_phone', $get('customer_phone'));
                                    }
                                }),

                            \Filament\Forms\Components\Actions\Action::make('snapshotCourierName')
                                ->label('Snapshot current courier name')
                                ->visible(fn(Get $get) => filled($get('delivery.courier_id')))
                                ->action(function (Get $get, Set $set) {
                                    $courierId = $get('delivery.courier_id');
                                    $c = $courierId ? \App\Models\Courier::find($courierId) : null;
                                    if ($c) {
                                        $set('delivery.courier_name', $c->name);
                                    }
                                }),

                            \Filament\Forms\Components\Actions\Action::make('snapshotContactFromOrder')
                                ->label('Use order contact for delivery contact')
                                ->visible(fn(Get $get) => filled($get('customer_name')) || filled($get('customer_phone')))
                                ->action(function (Get $get, Set $set) {
                                    $set('delivery.contact_name',  $get('customer_name'));
                                    $set('delivery.contact_phone', $get('customer_phone'));
                                }),
                        ])->alignment('left'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->persistColumnSearchesInSession()
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('total_price')
                    ->formatStateUsing(fn ($state) => Format::idr($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('paid_total')
                    ->label('Paid')
                    ->state(fn($record) => $record->paid_total)
                    ->formatStateUsing(fn ($state) => Format::idr($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance')
                    ->state(fn($record) => $record->balance_due)
                    ->formatStateUsing(fn ($state) => Format::idr($state))
                    ->color(fn($state) => (float)$state > 0 ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('status')->badge()->sortable(),

                Tables\Columns\TextColumn::make('statusHistory.first.changed_at')
                    ->label('Last status change')->since()->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('total_calorie')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => Format::kcal($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('products_list')
                    ->label('Products')
                    ->getStateUsing(
                        fn($record) => $record->products
                            ->map(fn($item) => e($item->name) . " ({$item->pivot->quantity}ps)")
                            ->implode('<br>')
                    )
                    ->html()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: false)


            ])
            ->actions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->url(fn($record) => static::getUrl('details', ['record' => $record]))
                    ->openUrlInNewTab(false),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('selectionDetails')
                    ->label('View selection details')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->action(function (Collection $records) {
                        $ids = $records->pluck('id')->all();
                        if (empty($ids)) return;

                        // keep in session (optional but handy)
                        session()->put('orders.table.selected', $ids);

                        // go to the combined report page for these IDs
                        return redirect(
                            static::getUrl('selection', ['ids' => implode(',', $ids)])
                        );
                    }),

                Tables\Actions\DeleteBulkAction::make()
                    ->deselectRecordsAfterCompletion(),

            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\OrderResource\RelationManagers\StatusHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'details' => Pages\OrderDetails::route('/{record}/details'),
            'selection' => Pages\OrderSelectionDetails::route('/selection-details'),
        ];
    }

    /* ============================================================
     *                 Calculation inside the Resource
     * ============================================================
     */

    /**
     * Compute row totals (product_total_price/calorie) and aggregate order totals
     * from the full repeater snapshot. Write back once to avoid race/flicker.
     */
    protected static function computeRowsAndTotals(array $rows, Set $set, Get $get): void
    {
        /** @var OrderFormCalculator $svc */
        $svc  = app(OrderFormCalculator::class);
        $calc = $svc->compute($rows, true); // formatted strings for UI

        // Keep the exact same setters to avoid UI race conditions
        $set('products', array_values($calc['rows']));
        $set('total_price',   $calc['total_price']);
        $set('total_calorie', $calc['total_calorie']);
    }

    protected static function fmt(float $n): string
    {
        return number_format($n, 2, '.', '');
    }
}
