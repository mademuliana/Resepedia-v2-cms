@php
    /** @var \App\Models\Order $record */
    $record = $getRecord();
    $items  = $record->products;

    $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $fmtKc = fn ($n) => number_format((float) $n, 2) . ' kcal';

    $sumQty   = $items->sum(fn($i) => (int) $i->pivot->quantity);
    $sumPrice = $items->sum(fn($i) => (float) $i->pivot->product_total_price);
    $sumCal   = $items->sum(fn($i) => (float) $i->pivot->product_total_calorie);
@endphp

<div class="p-4">
    <div
        class="rounded-xl border bg-white/60 shadow-sm
               border-gray-200 dark:border-gray-700
               dark:bg-gray-900/60"
    >
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3
                    border-b border-gray-200 dark:border-gray-700">
            <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                Order items
            </h4>

            <div class="flex items-center gap-4 text-xs">
                <span class="text-gray-600 dark:text-gray-300">
                    Qty: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $sumQty }}</span>
                </span>
                <span class="text-gray-600 dark:text-gray-300">
                    Total: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $fmtRp($sumPrice) }}</span>
                </span>
                <span class="text-gray-600 dark:text-gray-300">
                    Calorie: <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $fmtKc($sumCal) }}</span>
                </span>
            </div>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-gray-600 dark:text-gray-300
                              bg-gray-50 dark:bg-gray-900/40">
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2 pl-4 pr-3">Product</th>
                        <th class="py-2 px-3 text-right">Qty</th>
                        <th class="py-2 px-3 text-right">Line Price</th>
                        <th class="py-2 px-4 text-right">Line Calorie</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @forelse ($items as $item)
                        @php
                            $qty   = (int) $item->pivot->quantity;
                            $price = (float) $item->pivot->product_total_price;
                            $cal   = (float) $item->pivot->product_total_calorie;
                        @endphp

                        <tr class="hover:bg-gray-50/80 dark:hover:bg-gray-900/30">
                            <td class="py-2 pl-4 pr-3 text-gray-900 dark:text-gray-100">
                                {{ $item->name }}
                            </td>
                            <td class="py-2 px-3 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                {{ $qty }}
                            </td>
                            <td class="py-2 px-3 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                {{ $fmtRp($price) }}
                            </td>
                            <td class="py-2 px-4 text-right tabular-nums text-gray-900 dark:text-gray-100">
                                {{ $fmtKc($cal) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 px-4 text-sm text-gray-500 dark:text-gray-400">
                                No products in this order.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

                <tfoot class="border-t border-gray-200 dark:border-gray-700">
                    <tr>
                        <td class="py-3 pl-4 pr-3 font-medium text-gray-900 dark:text-gray-100">
                            Totals
                        </td>
                        <td class="py-3 px-3 text-right font-medium tabular-nums text-gray-900 dark:text-gray-100">
                            {{ $sumQty }}
                        </td>
                        <td class="py-3 px-3 text-right font-medium tabular-nums text-gray-900 dark:text-gray-100">
                            {{ $fmtRp($sumPrice) }}
                        </td>
                        <td class="py-3 px-4 text-right font-medium tabular-nums text-gray-900 dark:text-gray-100">
                            {{ $fmtKc($sumCal) }}
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
