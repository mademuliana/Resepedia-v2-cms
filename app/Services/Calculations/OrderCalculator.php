<?php

namespace App\Services\Calculations;

use App\Models\Order;

class OrderCalculator
{
    /** Sum order_items into order totals. */
    public function recompute(Order $order): array
    {
        $order->loadMissing(['products']);

        $totalPrice = 0.0;
        $totalCal   = 0.0;

        foreach ($order->products as $p) {
            $totalPrice += (float) $p->pivot->product_total_price;
            $totalCal   += (float) $p->pivot->product_total_calorie;
        }

        return [
            'total_price'   => round($totalPrice, 2),
            'total_calorie' => round($totalCal, 2),
        ];
    }
}
