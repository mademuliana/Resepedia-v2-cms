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
            $companyIds = DB::table('companies')->pluck('id');
            $clamp = fn($n,$min,$max)=>max($min,min($max,$n));

            foreach ($companyIds as $cid) {
                $customers = Customer::where('company_id',$cid)->with('addresses')->get();
                $couriers  = Courier::where('company_id',$cid)->where('active',true)->get();
                $products  = Product::where('company_id',$cid)->get();

                if ($customers->count() < 2 || $couriers->count() < 1 || $products->count() < 3) {
                    continue;
                }

                $offset = (crc32((string) $cid) % 5) - 2; // -2..+2
                $totalOrders = $clamp(10 + $offset, 6, 50);

                // Split orders: delivered / pending / otw
                $delivered = $clamp(3 + (int) round($offset / 2), 2, $totalOrders - 2);
                $pending   = $clamp(4 + $offset, 2, $totalOrders - $delivered - 1);
                $otw       = max(1, $totalOrders - $delivered - $pending);

                $productNames = $products->pluck('name')->values()->all();

                $addItems = function (Order $order, array $lines) use ($products) {
                    $totalPrice = 0.0; $totalCal = 0.0;
                    foreach ($lines as [$productName,$qty]) {
                        $p = $products->firstWhere('name',$productName);
                        if (! $p) continue;
                        DB::table('order_items')->updateOrInsert(
                            ['order_id'=>$order->id,'product_id'=>$p->id],
                            ['quantity'=>(int)$qty,'product_total_price'=>round((float)$p->price*(int)$qty,2),'product_total_calorie'=>round((float)$p->total_calorie*(int)$qty,2),'created_at'=>now(),'updated_at'=>now()]
                        );
                        $totalPrice += (float)$p->price*(int)$qty;
                        $totalCal   += (float)$p->total_calorie*(int)$qty;
                    }
                    $order->update(['total_price'=>round($totalPrice,2),'total_calorie'=>round($totalCal,2)]);
                };

                $snapshotDelivery = function (Order $order, Address $addr, ?Courier $courier = null, array $opts = []) {
                    OrderDelivery::updateOrCreate(
                        ['order_id'=>$order->id],
                        [
                            'address_id'=>$addr->id,'contact_name'=>$order->customer_name,'contact_phone'=>$order->customer_phone,
                            'line1'=>$addr->line1,'line2'=>$addr->line2,'city'=>$addr->city,'state'=>$addr->state,'postal_code'=>$addr->postal_code,
                            'country'=>$addr->country ?? 'ID','latitude'=>$addr->latitude,'longitude'=>$addr->longitude,
                            'courier_id'=>$courier?->id,'courier_name'=>$courier?->name,'tracking_code'=>$opts['tracking_code'] ?? null,
                            'delivery_window_start'=>$opts['window_start'] ?? null,'delivery_window_end'=>$opts['window_end'] ?? null,
                            'delivered_at'=>$opts['delivered_at'] ?? null,'delivery_instructions'=>$opts['instructions'] ?? null,
                        ]
                    );
                    $order->address_id = $addr->id; $order->save();
                };

                $pay = function (Order $order, string $type, float $amount, string $status='paid', string $method='cash', ?string $ref=null) {
                    Payment::create([
                        'order_id'=>$order->id,'type'=>$type,'method'=>$method,'amount'=>round($amount,2),
                        'status'=>$status,'paid_at'=>now(),'reference'=>$ref,'notes'=>null,
                    ]);
                };

                // ---- Delivered (ensure last one is underpaid)
                for ($i=0; $i<$delivered; $i++) {
                    $cust = $customers[$i % $customers->count()];
                    $addr = $cust->addresses->firstWhere('is_default',true) ?? $cust->addresses->first();
                    $cour = $couriers[$i % $couriers->count()];

                    $order = Order::create([
                        'company_id'=>$cid,'customer_id'=>$cust->id,'address_id'=>$addr?->id,
                        'customer_name'=>$cust->name,'customer_phone'=>$cust->phone,'customer_email'=>$cust->email,
                        'total_price'=>0,'total_calorie'=>0,'status'=>'confirmed',
                        'ordered_at'=>now()->subDays(5-$i%3),'required_at'=>now()->subDays(1),
                        'deposit_required'=>false,'deposit_amount'=>null,'notes'=>'Standard delivery.',
                    ]);

                    $addItems($order, [
                        [$productNames[$i % count($productNames)], 1 + ($i % 2)],
                        [$productNames[(($i+1) % count($productNames))], 1],
                    ]);
                    $snapshotDelivery($order, $addr, $cour, [
                        'tracking_code'=>'TRK-DELIV-'.strtoupper(substr(md5($order->id),0,8)),
                        'window_start'=>now()->subDays(2)->setTime(9,0),'window_end'=>now()->subDays(2)->setTime(17,0),
                        'delivered_at'=>now()->subDay(),'instructions'=>'Leave at front desk.',
                    ]);

                    $total = (float) $order->total_price;
                    if ($i === 0) {
                        $pay($order,'full',$total,'paid','bank_transfer','INV-FULL-'.$order->id);
                    } elseif ($i === 1) {
                        $d = round($total*0.3,2); $b = $total-$d;
                        $pay($order,'deposit',$d,'paid','ewallet','INV-DEP-'.$order->id);
                        $pay($order,'balance',$b,'paid','card','INV-BAL-'.$order->id);
                    } elseif ($i === $delivered-1) {
                        // underpaid
                        $p1 = round($total*0.3,2); $p2 = round($total*0.4,2);
                        $pay($order,'deposit',$p1,'paid','cash','INV-U1-'.$order->id);
                        $pay($order,'balance',$p2,'paid','bank_transfer','INV-U2-'.$order->id);
                    } else {
                        // default to fully paid
                        $pay($order,'full',$total,'paid','bank_transfer','INV-FULL-'.$order->id);
                    }

                    OrderStatusHistory::create([
                        'order_id'=>$order->id,'status_from'=>'pending','status_to'=>'confirmed',
                        'changed_at'=>now()->subDays(3),'changed_by'=>null,'note'=>'Auto-confirmed.',
                    ]);
                    OrderStatusHistory::create([
                        'order_id'=>$order->id,'status_from'=>'confirmed','status_to'=>'delivered',
                        'changed_at'=>now()->subDay(),'changed_by'=>null,'note'=>'Delivered to recipient.',
                    ]);
                }

                // ---- Pending (ensure one DP, one full, rest none)
                for ($i=0; $i<$pending; $i++) {
                    $cust = $customers[($i+1) % $customers->count()];
                    $addr = $cust->addresses->firstWhere('is_default',true) ?? $cust->addresses->first();

                    $order = Order::create([
                        'company_id'=>$cid,'customer_id'=>$cust->id,'address_id'=>$addr?->id,
                        'customer_name'=>$cust->name,'customer_phone'=>$cust->phone,'customer_email'=>$cust->email,
                        'total_price'=>0,'total_calorie'=>0,'status'=>'pending',
                        'ordered_at'=>now()->subDays(2),'required_at'=>now()->addDays(1),
                        'deposit_required'=>($i===0),'deposit_amount'=>null,'notes'=>'Customer reviewing options.',
                    ]);

                    $addItems($order, [
                        [$productNames[($i+2) % count($productNames)], 1 + ($i % 2)],
                        [$productNames[($i+3) % count($productNames)], 1],
                    ]);

                    $total = (float) $order->total_price;
                    if ($i === 0) {
                        $dp = round($total*0.25,2); $order->update(['deposit_amount'=>$dp]);
                        $pay($order,'deposit',$dp,'paid','ewallet','DEP-PEND-'.$order->id);
                    } elseif ($i === 1) {
                        $pay($order,'full',$total,'paid','bank_transfer','FULL-PEND-'.$order->id);
                    }
                }

                // ---- On the way (confirmed + snapshot; ensure one 50% DP)
                for ($i=0; $i<$otw; $i++) {
                    $cust = $customers[($i+2) % $customers->count()];
                    $addr = $cust->addresses->firstWhere('is_default',true) ?? $cust->addresses->first();
                    $cour = $couriers[$i % $couriers->count()];

                    $order = Order::create([
                        'company_id'=>$cid,'customer_id'=>$cust->id,'address_id'=>$addr?->id,
                        'customer_name'=>$cust->name,'customer_phone'=>$cust->phone,'customer_email'=>$cust->email,
                        'total_price'=>0,'total_calorie'=>0,'status'=>'confirmed',
                        'ordered_at'=>now()->subDay(),'required_at'=>now()->addDay(),
                        'deposit_required'=>($i===0),'deposit_amount'=>null,'notes'=>'Out for delivery soon.',
                    ]);

                    $addItems($order, [
                        [$productNames[($i+4) % count($productNames)], 1 + ($i % 2)],
                        [$productNames[($i+2) % count($productNames)], 1],
                    ]);

                    $snapshotDelivery($order, $addr, $cour, [
                        'tracking_code'=>'TRK-OTW-'.strtoupper(substr(md5($order->id),0,8)),
                        'window_start'=>now()->setTime(9,0),'window_end'=>now()->setTime(18,0),
                        'delivered_at'=>null,'instructions'=>'Call on arrival.',
                    ]);

                    if ($i === 0) {
                        $total = (float) $order->total_price; $dp = round($total*0.5,2);
                        $order->update(['deposit_amount'=>$dp]);
                        $pay($order,'deposit',$dp,'paid','card','DEP-OTW-'.$order->id);
                    }

                    OrderStatusHistory::create([
                        'order_id'=>$order->id,'status_from'=>'pending','status_to'=>'confirmed',
                        'changed_at'=>now()->subHours(3),'changed_by'=>null,'note'=>'Packed & handed to courier.',
                    ]);
                }
            }
        });
    }
}
