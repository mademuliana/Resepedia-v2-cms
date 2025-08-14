<x-filament::page>
    @php
        $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $fmtKc = fn ($n) => number_format((float) $n, 2) . ' kcal';
    @endphp

    <div class="space-y-2">
        <div class="text-lg font-semibold">Selection Details — Products</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            @if ($this->products->isEmpty())
                No products selected. Go back and select some products first.
            @else
                Showing {{ $this->products->count() }} selected product(s).
            @endif
        </div>
    </div>

    @if ($this->products->isNotEmpty())
        {{-- A) List of selected products (no extra calculations) --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Selected Products</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-600 dark:text-gray-300">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Product</th>
                            <th class="py-2 px-3 text-right">Price</th>
                            <th class="py-2 px-3 text-right">Total Cost</th>
                            <th class="py-2 px-4 text-right">Total Calorie</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->products as $p)
                            <tr>
                                <td class="py-2 pr-4">{{ $p->name }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($p->price) }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $fmtRp($p->total_cost) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($p->total_calorie) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- B) Ingredient breakdown across all selected products --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Ingredient Breakdown (calculated)</x-slot>
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
                                <td colspan="5" class="py-6 text-sm text-gray-500 dark:text-gray-400">
                                    No ingredients derived from the selected products.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament::page>
