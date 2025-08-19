<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends Controller
{
    public function index(Request $request, Company $company)
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->company_id !== $company->id) {
            abort(403);
        }

        $rows = Order::query()
            ->where('company_id', $company->id)
            ->select('id','customer_name','status','total_price','total_calorie','ordered_at','required_at')
            ->latest('ordered_at')->latest('id')
            ->paginate(20);

        return response()->json($rows);
    }

    // Create new customer (for new order)
    public function storeCustomer(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required','string','max:255'],
            'email' => ['nullable','email'],
            'phone' => ['nullable','string','max:255'],
            'address' => ['required','array'],
            'address.label' => ['nullable','string','max:255'],
            'address.line1' => ['required','string','max:255'],
            'address.line2' => ['nullable','string','max:255'],
            'address.city'  => ['required','string','max:255'],
            'address.state' => ['nullable','string','max:255'],
            'address.postal_code' => ['nullable','string','max:50'],
            'address.country' => ['nullable','string','max:2'],
            'address.latitude' => ['nullable','numeric'],
            'address.longitude'=> ['nullable','numeric'],
        ]);

        $user = $request->user();
        $companyId = $user->company_id;

        return DB::transaction(function () use ($data, $companyId) {
            $customer = Customer::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'email'=> $data['email'] ?? null,
                'phone'=> $data['phone'] ?? null,
            ]);

            $addrData = $data['address'];
            $addr = Address::create([
                'company_id' => $companyId,
                'customer_id'=> $customer->id,
                'label' => $addrData['label'] ?? 'Primary',
                'line1' => $addrData['line1'],
                'line2' => $addrData['line2'] ?? null,
                'city'  => $addrData['city'],
                'state' => $addrData['state'] ?? null,
                'postal_code' => $addrData['postal_code'] ?? null,
                'country' => $addrData['country'] ?? 'ID',
                'latitude'=> $addrData['latitude'] ?? null,
                'longitude'=> $addrData['longitude'] ?? null,
                'is_default' => true,
            ]);

            return response()->json(['customer_id'=>$customer->id,'address_id'=>$addr->id], 201);
        });
    }

    // Ingredient preview from items for an order (without saving the order)
    public function previewIngredients(Request $request)
    {
        $data = $request->validate([
            'items' => ['required','array','min:1'],
            'items.*.product_id' => ['required','integer','exists:products,id'],
            'items.*.quantity' => ['required','numeric','min:1'],
        ]);

        $ids = collect($data['items'])->pluck('product_id')->unique()->values();
        $products = Product::with(['recipes:id,name,portion_size', 'recipes.ingredients' => function ($q) {
            $q->select('ingredients.id','ingredients.name','ingredients.unit');
        }])->whereIn('id',$ids)->get();

        $byId = $products->keyBy('id');

        $agg = [];
        foreach ($data['items'] as $it) {
            $product = $byId[$it['product_id']] ?? null; if (! $product) continue;
            $productQty = (int) $it['quantity'];

            foreach ($product->recipes as $recipe) {
                $portion = max(1.0, (float) $recipe->portion_size);
                $scale   = $product->pivot->quantity / $portion; // recipe grams/ml per product
                foreach ($recipe->ingredients as $ing) {
                    $addQty = (float) $ing->pivot->quantity * $scale * $productQty;
                    $addCost= (float) $ing->pivot->ingredient_total_cost * $scale * $productQty;
                    $addCal = (float) $ing->pivot->ingredient_total_calorie * $scale * $productQty;

                    $agg[$ing->id] ??= ['name'=>$ing->name,'unit'=>$ing->unit,'quantity'=>0,'cost'=>0,'calorie'=>0];
                    $agg[$ing->id]['quantity'] += $addQty;
                    $agg[$ing->id]['cost']     += $addCost;
                    $agg[$ing->id]['calorie']  += $addCal;
                }
            }
        }

        $out = [];
        foreach ($agg as $ingId => $v) {
            $out[] = [
                'ingredient_id' => $ingId,
                'name' => $v['name'], 'unit'=>$v['unit'],
                'quantity' => round($v['quantity'], 2),
                'total_cost' => round($v['cost'], 2),
                'total_calorie'=> round($v['calorie'], 2),
            ];
        }

        return response()->json([
            'ingredients' => array_values($out),
            'totals' => [
                'cost'    => round(array_sum(array_column($out,'total_cost')), 2),
                'calorie' => round(array_sum(array_column($out,'total_calorie')), 2),
            ],
        ]);
    }

    // Create order from products for the current company
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable','integer','exists:customers,id'],
            'address_id'  => ['nullable','integer','exists:addresses,id'],
            'customer'    => ['nullable','array'], // if new customer inline
            'items'       => ['required','array','min:1'],
            'items.*.product_id' => ['required','integer','exists:products,id'],
            'items.*.quantity'   => ['required','integer','min:1'],
            'notes'       => ['nullable','string'],
            'required_at' => ['nullable','date'],
            'deposit_required' => ['nullable','boolean'],
        ]);

        $user = $request->user();
        $companyId = $user->company_id;

        return DB::transaction(function () use ($data, $companyId) {
            // Ensure customer/address
            if (! ($data['customer_id'] ?? null)) {
                abort_if(! isset($data['customer']), 422, 'customer or customer_id is required.');
                $custCtrl = app(self::class);
                $req = request(); // reuse current request instance
                $req->replace(['name'=>$data['customer']['name'] ?? null, 'email'=>$data['customer']['email'] ?? null, 'phone'=>$data['customer']['phone'] ?? null, 'address'=>$data['customer']['address'] ?? []]);
                $resp = $custCtrl->storeCustomer($req);
                $payload = $resp->getOriginalContent();
                $data['customer_id'] = $payload['customer_id'];
                $data['address_id']  = $payload['address_id'];
            }

            $customer = Customer::where('company_id',$companyId)->findOrFail($data['customer_id']);
            $address  = Address::where('company_id',$companyId)->findOrFail($data['address_id']);

            $order = Order::create([
                'company_id' => $companyId,
                'customer_id'=> $customer->id,
                'address_id' => $address->id,
                'customer_name'  => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'total_price'=>0,'total_calorie'=>0,
                'status' => 'pending',
                'ordered_at' => now(),
                'required_at'=> $data['required_at'] ?? now()->addDay(),
                'deposit_required' => (bool) ($data['deposit_required'] ?? false),
                'notes' => $data['notes'] ?? null,
            ]);

            $totalPrice = 0.0; $totalCal = 0.0;
            foreach ($data['items'] as $it) {
                $p = Product::where('company_id',$companyId)->findOrFail($it['product_id']);
                $qty = (int) $it['quantity'];
                $rowPrice = (float) $p->price * $qty;
                $rowCal   = (float) $p->total_calorie * $qty;

                DB::table('order_items')->insert([
                    'order_id' => $order->id,
                    'product_id' => $p->id,
                    'quantity' => $qty,
                    'product_total_price'   => round($rowPrice,2),
                    'product_total_calorie' => round($rowCal,2),
                    'created_at'=>now(), 'updated_at'=>now(),
                ]);

                $totalPrice += $rowPrice; $totalCal += $rowCal;
            }

            $order->update(['total_price'=>round($totalPrice,2),'total_calorie'=>round($totalCal,2)]);

            return response()->json(['order_id'=>$order->id], 201);
        });
    }
}
