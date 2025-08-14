<x-filament::page>
    @php
        $fmtRp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
        $fmtKc = fn ($n) => number_format((float) $n, 2) . ' kcal';
    @endphp

    <div class="space-y-2">
        <div class="text-lg font-semibold">Selection Details — Recipes</div>
        <div class="text-sm text-gray-600 dark:text-gray-300">
            @if ($this->recipes->isEmpty())
                No recipes selected. Go back and select some recipes first.
            @else
                Showing {{ $this->recipes->count() }} selected recipe(s).
            @endif
        </div>
    </div>

    @if ($this->recipes->isNotEmpty())
        {{-- A) List of selected recipes (no extra calculations) --}}
        <x-filament::section class="mt-6">
            <x-slot name="heading">Selected Recipes</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-gray-600 dark:text-gray-300">
                        <tr class="border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Recipe</th>
                            <th class="py-2 px-3 text-right">Prep Time</th>
                            <th class="py-2 px-3 text-right">Portion Size</th>
                            <th class="py-2 px-3 text-right">Cost/Portion</th>
                            <th class="py-2 px-4 text-right">Calorie/Portion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->recipes as $r)
                            <tr>
                                <td class="py-2 pr-4">{{ $r->name }}</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ (int) $r->prep_time_minutes }} minute</td>
                                <td class="py-2 px-3 text-right tabular-nums">{{ $r->portion_size }} pcs</td>
                                <td class="py-2 px-3 text-right tabular-nums">Rp.{{ $fmtRp($r->total_cost_per_portion) }}</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($r->total_calorie_per_portion) }}Kcal</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        {{-- B) Ingredient breakdown across selected recipes --}}
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
                                <td class="py-2 px-3 text-right tabular-nums">{{ number_format($row['quantity'], 2) }} $row['unit']</td>
                                <td class="py-2 px-3 text-right tabular-nums">Rp. {{ $fmtRp($row['cost']) }}/$row['unit']</td>
                                <td class="py-2 px-4 text-right tabular-nums">{{ $fmtKc($row['calorie']) }}Kcal/$row['unit']</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-sm text-gray-500 dark:text-gray-400">
                                    No ingredients derived from the selected recipes.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament::page>
