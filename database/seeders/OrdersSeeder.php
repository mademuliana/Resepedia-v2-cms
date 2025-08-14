<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Courier;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDelivery;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrdersSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $customers = Customer::with('addresses')->get();
            $couriers  = Courier::where('active', true)->get();
            $products  = Product::all();

            if ($customers->count() < 3 || $couriers->count() < 2 || $products->count() < 3) {
                // Basic guard to ensure dependencies exist
                return;
            }

            // Helper to add items & compute totals
            $addItems = function (Order $order, array $lines) use ($products) {
                $totalPrice = 0.0;
                $totalCal   = 0.0;

                foreach ($lines as [$productName, $qty]) {
                    $p = $products->firstWhere('name', $productName);
                    if (! $p) continue;

                    $linePrice = (float) $p->price * (int) $qty;
                    $lineCal   = (float) $p->total_calorie * (int) $qty;

                    DB::table('order_items')->insert([
                        'order_id'              => $order->id,
                        'product_id'            => $p->id,
                        'quantity'              => (int) $qty,
                        'product_total_price'   => round($linePrice, 2),
                        'product_total_calorie' => round($lineCal, 2),
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);

                    $totalPrice += $linePrice;
                    $totalCal   += $lineCal;
                }

                $order->update([
                    'total_price'   => round($totalPrice, 2),
                    'total_calorie' => round($totalCal, 2),
                ]);
            };

            // Helper to snapshot delivery (hasOne)
            $snapshotDelivery = function (Order $order, Address $addr, ?Courier $courier = null, array $opts = []) {
                $od = OrderDelivery::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'address_id'   => $addr->id,
                        'contact_name' => $order->customer_name,
                        'contact_phone'=> $order->customer_phone,
                        'line1'        => $addr->line1,
                        'line2'        => $addr->line2,
                        'city'         => $addr->city,
                        'state'        => $addr->state,
                        'postal_code'  => $addr->postal_code,
                        'country'      => $addr->country ?? 'ID',
                        'latitude'     => $addr->latitude,
                        'longitude'    => $addr->longitude,
                        'courier_id'   => $courier?->id,
                        'courier_name' => $courier?->name,
                        'tracking_code'=> $opts['tracking_code'] ?? null,
                        'delivery_window_start' => $opts['window_start'] ?? null,
                        'delivery_window_end'   => $opts['window_end'] ?? null,
                        'delivered_at'          => $opts['delivered_at'] ?? null,
                        'delivery_instructions' => $opts['instructions'] ?? null,
                    ]
                );

                // keep quick pointer
                $order->address_id = $addr->id;
                $order->save();

                return $od;
            };

            // Helper to add payments
            $pay = function (Order $order, string $type, float $amount, string $status = 'paid', string $method = 'cash', ?string $ref = null, ?string $note = null, ?string $paidAt = null) {
                Payment::create([
                    'order_id'  => $order->id,
                    'type'      => $type,
                    'method'    => $method,
                    'amount'    => round($amount, 2),
                    'status'    => $status,
                    'paid_at'   => $paidAt ? now()->parse($paidAt) : now(),
                    'reference' => $ref,
                    'notes'     => $note,
                ]);
            };

            // Pick some product names for variety
            $names = $products->pluck('name')->take(6)->values()->all();

            // 3 DELIVERED (one underpaid)
            for ($i = 0; $i < 3; $i++) {
                $cust = $customers[$i % $customers->count()];
                $addr = $cust->addresses->firstWhere('is_default', true) ?? $cust->addresses->first();
                $cour = $couriers[$i % $couriers->count()];

                $order = Order::create([
                    'customer_id'    => $cust->id,
                    'address_id'     => $addr?->id,
                    'customer_name'  => $cust->name,
                    'customer_phone' => $cust->phone,
                    'customer_email' => $cust->email,
                    'total_price'    => 0,
                    'total_calorie'  => 0,
                    'status'         => 'confirmed', // will set delivered_at in snapshot
                    'ordered_at'     => now()->subDays(5 - $i),
                    'required_at'    => now()->subDays(1),
                    'deposit_required' => false,
                    'deposit_amount'   => null,
                    'notes'            => 'Standard delivery.',
                ]);

                $addItems($order, [
                    [$names[0], 1 + $i],
                    [$names[1], 1],
                    [$names[2], 1],
                ]);

                $snapshotDelivery($order, $addr, $cour, [
                    'tracking_code' => 'TRK-DELIV-' . strtoupper(substr(md5($order->id), 0, 8)),
                    'window_start'  => now()->subDays(2)->setTime(9, 0),
                    'window_end'    => now()->subDays(2)->setTime(17, 0),
                    'delivered_at'  => now()->subDay(), // delivered
                    'instructions'  => 'Leave at front desk.',
                ]);

                // Payments
                $total = (float) $order->total_price;
                if ($i === 0) {
                    // fully paid
                    $pay($order, 'full', $total, 'paid', 'bank_transfer', 'INV-FULL-' . $order->id, 'Paid in full');
                } elseif ($i === 1) {
                    // deposit + balance (both paid)
                    $deposit = round($total * 0.3, 2);
                    $balance = $total - $deposit;
                    $pay($order, 'deposit', $deposit, 'paid', 'ewallet', 'INV-DEP-' . $order->id);
                    $pay($order, 'balance', $balance, 'paid', 'card', 'INV-BAL-' . $order->id);
                } else {
                    // underpaid: two payments, still lacking
                    $p1 = round($total * 0.3, 2);
                    $p2 = round($total * 0.4, 2);
                    $pay($order, 'deposit', $p1, 'paid', 'cash', 'INV-U1-' . $order->id);
                    $pay($order, 'balance', $p2, 'paid', 'bank_transfer', 'INV-U2-' . $order->id);
                    // (30% still due)
                }

                // History (optional explicit writes; if your Order model boot logs, this is redundant)
                OrderStatusHistory::create([
                    'order_id'    => $order->id,
                    'status_from' => 'pending',
                    'status_to'   => 'confirmed',
                    'changed_at'  => now()->subDays(3),
                    'changed_by'  => null,
                    'note'        => 'Auto-confirmed.',
                ]);
                OrderStatusHistory::create([
                    'order_id'    => $order->id,
                    'status_from' => 'confirmed',
                    'status_to'   => 'delivered',
                    'changed_at'  => now()->subDay(),
                    'changed_by'  => null,
                    'note'        => 'Delivered to recipient.',
                ]);
            }

            // 4 PENDING (1 with DP, 1 fully paid, 2 no payment)
            for ($i = 0; $i < 4; $i++) {
                $cust = $customers[($i + 1) % $customers->count()];
                $addr = $cust->addresses->firstWhere('is_default', true) ?? $cust->addresses->first();

                $order = Order::create([
                    'customer_id'    => $cust->id,
                    'address_id'     => $addr?->id,
                    'customer_name'  => $cust->name,
                    'customer_phone' => $cust->phone,
                    'customer_email' => $cust->email,
                    'total_price'    => 0,
                    'total_calorie'  => 0,
                    'status'         => 'pending',
                    'ordered_at'     => now()->subDays(2),
                    'required_at'    => now()->addDays(1),
                    'deposit_required' => ($i === 0 || $i === 2), // some require deposit
                    'deposit_amount'   => null,
                    'notes'            => 'Customer reviewing options.',
                ]);

                $addItems($order, [
                    [$names[3], 1 + ($i % 2)],
                    [$names[4], 1],
                ]);

                // Payments:
                $total = (float) $order->total_price;
                if ($i === 0) {
                    // with down payment (paid)
                    $dp = round($total * 0.25, 2);
                    $order->update(['deposit_amount' => $dp]);
                    $pay($order, 'deposit', $dp, 'paid', 'ewallet', 'DEP-PEND-' . $order->id);
                } elseif ($i === 1) {
                    // fully paid (even though pending)
                    $pay($order, 'full', $total, 'paid', 'bank_transfer', 'FULL-PEND-' . $order->id);
                } else {
                    // no payments
                }
            }

            // 3 "ON THE WAY" → treat as confirmed + delivery snapshot (no delivered_at yet)
            for ($i = 0; $i < 3; $i++) {
                $cust = $customers[($i + 2) % $customers->count()];
                $addr = $cust->addresses->firstWhere('is_default', true) ?? $cust->addresses->first();
                $cour = $couriers[$i % $couriers->count()];

                $order = Order::create([
                    'customer_id'    => $cust->id,
                    'address_id'     => $addr?->id,
                    'customer_name'  => $cust->name,
                    'customer_phone' => $cust->phone,
                    'customer_email' => $cust->email,
                    'total_price'    => 0,
                    'total_calorie'  => 0,
                    'status'         => 'confirmed', // "on the way"
                    'ordered_at'     => now()->subDay(),
                    'required_at'    => now()->addDay(),
                    'deposit_required' => ($i === 0), // first one has half DP
                    'deposit_amount'   => null,
                    'notes'            => 'Out for delivery soon.',
                ]);

                $addItems($order, [
                    [$names[5] ?? $names[0], 1 + $i],
                    [$names[2], 1],
                ]);

                $snapshotDelivery($order, $addr, $cour, [
                    'tracking_code' => 'TRK-OTW-' . strtoupper(substr(md5($order->id), 0, 8)),
                    'window_start'  => now()->setTime(9, 0),
                    'window_end'    => now()->setTime(18, 0),
                    'delivered_at'  => null,
                    'instructions'  => 'Call on arrival.',
                ]);

                $total = (float) $order->total_price;
                if ($i === 0) {
                    // one with 50% deposit
                    $dp = round($total * 0.5, 2);
                    $order->update(['deposit_amount' => $dp]);
                    $pay($order, 'deposit', $dp, 'paid', 'card', 'DEP-OTW-' . $order->id);
                } else {
                    // no payment yet
                }

                OrderStatusHistory::create([
                    'order_id'    => $order->id,
                    'status_from' => 'pending',
                    'status_to'   => 'confirmed',
                    'changed_at'  => now()->subHours(3),
                    'changed_by'  => null,
                    'note'        => 'Packed and handed to courier.',
                ]);
            }
        });
    }
}
