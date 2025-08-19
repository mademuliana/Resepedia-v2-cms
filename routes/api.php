<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductBuilderController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\IngredientsController;
use App\Http\Controllers\Api\OrdersController;

// Auth
Route::post('/login', [AuthController::class, 'login']); // returns token

Route::middleware('auth:sanctum')->group(function () {
    // Token management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/tokens/issue', [AuthController::class, 'issue']);     // optional (also via widget)
    Route::delete('/tokens/current', [AuthController::class, 'revoke']); // revoke current token

    // Product builder
    Route::get('/builder/recipes', [ProductBuilderController::class, 'recipes']);              // list recipes (name, kcal/portion)
    Route::post('/builder/compute-product', [ProductBuilderController::class, 'compute']);     // compute totals; optional persist
    Route::post('/builder/ingredients', [ProductBuilderController::class, 'ingredients']);     // aggregate ingredients for a custom build

    // Catalog by company (super admin can choose company; admins are auto-scoped)
    Route::get('/companies/{company}/products', [CatalogController::class, 'products']);
    Route::get('/companies/{company}/products/{product}', [CatalogController::class, 'product']);

    // Ingredients listing with filter flags
    Route::get('/ingredients', [IngredientsController::class, 'index']); // ?cost=1&calorie=1

    // Orders
    Route::get('/companies/{company}/orders', [OrdersController::class, 'index']);
    Route::post('/customers', [OrdersController::class, 'storeCustomer']); // new customer (auto company)
    Route::post('/orders/preview-ingredients', [OrdersController::class, 'previewIngredients']); // from items
    Route::post('/orders', [OrdersController::class, 'store']); // create order (new/existing customer)
});
