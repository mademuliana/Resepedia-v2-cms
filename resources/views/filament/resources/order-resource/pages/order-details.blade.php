{{-- resources/views/filament/resources/order-resource/pages/order-details.blade.php --}}
<x-filament::page>
    @php
        /** @var \App\Models\Order $order */
        $order = $this->record;
        $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $fmtKc = fn ($n) => number_format((float) $n, 2) . ' kcal';
    @endphp

    {{-- Header --}}
    <div class="space-y-2">
        <div class="text-lg font-semibold">Order #{{ $order->id }}</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            <span class="mr-4">Customer: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $order->customer_name }}</span></span>
            <span class="mr-4">Date: <span class="font-medium text-gray-900 dark:text-gray-100">{{ \Illuminate\Support\Carbon::parse($order->order_date)->toFormattedDateString() }}</span></span>
            <span class="mr-4">Total Price: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $fmtRp($order->total_price) }}</span></span>
            <span>Calorie: <span class="font-medium text-gray-900 dark:text-gray-100">{{ $fmtKc($order->total_calorie) }}</span></span>
        </div>
    </div>

    {{-- Products table --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Products</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-600 dark:text-gray-300">
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4">Product</th>
                        <th class="py-2 px-3 text-right">Qty</th>
                        <th class="py-2 px-3 text-right">Product Total Price</th>
                        <th class="py-2 px-4 text-right">Product Total Calorie</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($order->products as $p)
                        <tr>
                            <td class="py-2 pr-4">{{ $p->name }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ (int) $p->pivot->quantity }} Pcs</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($p->pivot->product_total_price) }}</td>
                            <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($p->pivot->product_total_calorie) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-sm text-gray-500 dark:text-gray-400">
                                No products found on this order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($order->products->isNotEmpty())
                    <tfoot class="border-t border-gray-200 dark:border-gray-700">
                        <tr>
                            <td class="py-2 pr-4 font-medium">Totals</td>
                            <td class="py-2 px-3 text-right font-medium tabular-nums">
                                {{ $order->products->sum(fn($i) => (int) $i->pivot->quantity) }} Pcs
                            </td>
                            <td class="py-2 px-3 text-right font-medium tabular-nums">
                                {{ $fmtRp($order->products->sum(fn($i) => (float) $i->pivot->product_total_price)) }}
                            </td>
                            <td class="py-2 px-4 text-right font-medium tabular-nums">
                                {{ $fmtKc($order->products->sum(fn($i) => (float) $i->pivot->product_total_calorie)) }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </x-filament::section>

    {{-- Ingredient Breakdown (uses $this->ingredientSummary computed in PHP) --}}
    <x-filament::section class="mt-6">
        <x-slot name="heading">Ingredient Breakdown (calculated)</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-600 dark:text-gray-300">
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pr-4">Ingredient</th>
                        <th class="py-2 px-3 text-right">Quantity</th>
                        <th class="py-2 px-3 text-right">Cost</th>
                        <th class="py-2 px-4 text-right">Calorie</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($this->ingredientSummary as $row)
                        <tr>
                            <td class="py-2 pr-4">{{ $row['ingredient']->name }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ number_format($row['quantity'], 2) }} {{ $row['unit'] }}</td>
                            <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($row['cost']) }}</td>
                            <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($row['calorie']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-sm text-gray-500 dark:text-gray-400">
                                No ingredients derived from this order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament::page>
