<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Product;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    protected function assertCompanyAccess(Request $request, Company $company): void
    {
        $user = $request->user();
        if (! $user->isSuperAdmin() && $user->company_id !== $company->id) {
            abort(403, 'Forbidden for this company.');
        }
    }

    public function products(Request $request, Company $company)
    {
        $this->assertCompanyAccess($request, $company);

        $rows = Product::query()
            ->where('company_id', $company->id)
            ->select('id','name','price','total_cost','total_calorie')
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }

    public function product(Request $request, Company $company, Product $product)
    {
        $this->assertCompanyAccess($request, $company);
        abort_unless($product->company_id === $company->id, 404);

        $product->load(['recipes:id,name', 'recipes.pivot']);
        return response()->json($product);
    }
}
