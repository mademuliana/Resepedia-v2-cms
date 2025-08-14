<x-filament::page>
    @php
        $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $fmtKc = fn ($n) => number_format((float) $n, 2) . ' kcal';
    @endphp

    <div class="space-y-2">
        <div class="text-lg font-semibold">Selection Details</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            @if ($this->orders->isEmpty())
                No orders selected. Go back and select some orders first.
            @else
                Showing {{ $this->orders->count() }} selected order(s).
            @endif
        </div>
    </div>

    @if ($this->orders->isNotEmpty())
        {{-- Quick overview of selected orders --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Selected Orders</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-600 dark:text-gray-300">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Order #</th>
                            <th class="py-2 px-3">Customer</th>
                            <th class="py-2 px-3">Date</th>
                            <th class="py-2 px-3 text-right">Total Price</th>
                            <th class="py-2 px-4 text-right">Total Calorie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->orders as $order)
                            <tr>
                                <td class="py-2 pr-4">{{ $order->id }}</td>
                                <td class="py-2 px-3">{{ $order->customer_name }}</td>
                                <td class="py-2 px-3">{{ \Illuminate\Support\Carbon::parse($order->order_date)->toFormattedDateString() }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($order->total_price) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($order->total_calorie) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <!-- this is not necesary, but can be used later to show detail of each order -->
        <!-- {{--
        @foreach ($this->orders as $order)
            <x-filament::section class="mt-6">
                <x-slot name="heading">Order #{{ $order->id }} — Products</x-slot>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-gray-600 dark:text-gray-300">
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="py-2 pr-4">Product</th>
                                <th class="py-2 px-3 text-right">Qty</th>
                                <th class="py-2 px-3 text-right">Line Price</th>
                                <th class="py-2 px-4 text-right">Line Calorie</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @forelse ($order->products as $p)
                                <tr>
                                    <td class="py-2 pr-4">{{ $p->name }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ (int) $p->pivot->quantity }}</td>
                                    <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($p->pivot->product_total_price) }}</td>
                                    <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($p->pivot->product_total_calorie) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-6 text-sm text-gray-500 dark:text-gray-400">No products on this order.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endforeach
        --}} -->


        {{-- Product summary across all selected orders --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Product Summary (all selected orders)</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-600 dark:text-gray-300">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Product</th>
                            <th class="py-2 px-3 text-right">Total Qty</th>
                            <th class="py-2 px-3 text-right">Total Price</th>
                            <th class="py-2 px-4 text-right">Total Calorie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($this->productSummary as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['product']->name }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $row['quantity'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($row['price']) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($row['calorie']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-sm text-gray-500 dark:text-gray-400">No products across selection.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- Ingredient breakdown across all selected orders --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Ingredient Breakdown (all selected orders)</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-600 dark:text-gray-300">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Ingredient</th>
                            <th class="py-2 px-3">Unit</th>
                            <th class="py-2 px-3 text-right">Quantity</th>
                            <th class="py-2 px-3 text-right">Cost</th>
                            <th class="py-2 px-4 text-right">Calorie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse ($this->ingredientSummary as $row)
                            <tr>
                                <td class="py-2 pr-4">{{ $row['ingredient']->name }}</td>
                                <td class="py-2 px-3">{{ $row['unit'] }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($row['quantity'], 2) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($row['cost']) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($row['calorie']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-sm text-gray-500 dark:text-gray-400">No ingredients across selection.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament::page>
