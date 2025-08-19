 Resepedia CMS (Laravel)

 A Laravel-based catering/meal-prep CMS with multi-company support, nutrition & cost calculations,
 order management, delivery & payments, a Filament admin panel, and a token-secured REST API.
 Every line in this README is commented so you can copy sections freely.

 -----------------------------------------------------------------------------
 1) Requirements
 -----------------------------------------------------------------------------
 - PHP 8.2+ (recommended for Laravel 10/11)
 - Composer 2+
 - MySQL 8+ (utf8mb4), or compatible MariaDB
 - Node 18+ (optional, only if you tweak frontend assets)
 - Redis (optional for queues/cache)

 -----------------------------------------------------------------------------
 2) Installation
 -----------------------------------------------------------------------------
 2.1) Clone the project
 $ git clone <your-repo-url> resepedia-cms && cd resepedia-cms

 2.2) Install PHP dependencies
 $ composer install --no-interaction --prefer-dist

 2.3) Environment file
 $ cp .env.example .env
   -> Open .env and set DB_* credentials to your MySQL
   -> Example: DB_CONNECTION=mysql
               DB_HOST=127.0.0.1
               DB_PORT=3306
               DB_DATABASE=resepedia
               DB_USERNAME=resepedia
               DB_PASSWORD=resepedia

 2.4) Generate app key
 $ php artisan key:generate

 2.5) Storage symlink (for uploaded files if any)
 $ php artisan storage:link

 2.6) Run migrations
 $ php artisan migrate

 2.7) Seed demo data (users, companies, catalog, orders)
 $ php artisan db:seed
   -> If you have specific seeders use:
      $ php artisan db:seed --class=CateringSeeder
      $ php artisan db:seed --class=MultiCompanySeeder
   -> The demo creates 3 users (see Section 6).

 2.8) (Optional) Build frontend assets if you changed them
 $ npm install && npm run build

 -----------------------------------------------------------------------------
 3) Running locally
 -----------------------------------------------------------------------------
 Start the Laravel dev server:
 $ php artisan serve
 Visit the Filament admin at:
   http://127.0.0.1:8000/admin

 -----------------------------------------------------------------------------
 4) Core domain & tables (high level)
 -----------------------------------------------------------------------------
 Ingredients (global/shared)
   - id, name, type, unit, calorie_per_unit, cost_per_unit, stock_quantity, timestamps
   - Global data across companies (no company_id).

 Recipes
   - id, name, prep_time_minutes, portion_size, total_calorie_per_portion, total_cost_per_portion, notes, timestamps
   - recipe_ingredient pivot: quantity, ingredient_total_cost, ingredient_total_calorie
   - recipe_steps: ordered instructions (step_no, instruction, duration_minutes, media_url)

 Products
   - id, company_id, name, price, total_cost, total_calorie, notes, timestamps
   - product_recipe pivot: quantity (grams/ml), recipe_total_cost, recipe_total_calorie

 Customers & Addresses (per company)
   - customers: company_id, name, email, phone, notes
   - addresses: company_id, customer_id (nullable), label, line1..country, lat/lng, is_default

 Orders
   - id, company_id, customer_id, address_id, snapshot fields (customer_*), totals, status,
     ordered_at, required_at, deposit flags/amount, notes
   - order_items pivot: product_id, quantity, product_total_price, product_total_calorie
   - order_delivery (1:1): snapshot of address + courier, tracking, delivery window/times
   - payments (1:N): type, method, amount, status, paid_at, reference, notes
   - order_status_histories (1:N): status transitions with timestamp and user

 Couriers
   - id, name, type('internal'|'third_party'), phone, notes, active

 Companies (multi-tenant light)
   - id, name, slug, notes; users belong to a single company (admins), ingredients remain global.

 -----------------------------------------------------------------------------
 5) Access control & multi-company model
 -----------------------------------------------------------------------------
 Roles
   - Super Admin: can view/manage data across all companies.
   - Admin: belongs to exactly one company; only sees their company's data.

 Scoping
   - Most Eloquent models carry a company_id (except global ones like ingredients).
   - Admin users are automatically scoped to their company via policies/scopes.
   - API endpoints verify company access (super admin bypasses; admin must match company_id).

 -----------------------------------------------------------------------------
 6) Default users (seeded)
 -----------------------------------------------------------------------------
 Email(s):
   - superadmin@example.com  (role=super_admin)
   - admin1@example.com      (role=admin, company A)
   - admin2@example.com      (role=admin, company B)
 Password for all: resepedia
   -> Change passwords in production.

 -----------------------------------------------------------------------------
 7) Filament admin panel
 -----------------------------------------------------------------------------
 Location
   - /admin (login with seeded users)

 Dashboard widgets
   - SalesChart: daily paid totals (last 30 days).
   - RecentOrders: last 10 orders.
   - TopProducts: top ordered products (qty & revenue).
   - TopIngredients: most used ingredients (by recipes composition).
   - TopRecipes: most used recipes (by products composition).
   - WideAccountWidget: full-width account card with "Generate API token" button.

 Customization
   - Change app title/logo: config/filament.php (brand) or Filament panel provider.
   - Reorder widgets: override getWidgets() sort orders in app/Filament/Pages/Dashboard.php.

 -----------------------------------------------------------------------------
 8) Services & calculations (consistency layer)
 -----------------------------------------------------------------------------
 Central services under app/Services handle domain math and formatting:
   - Calculations (server truth): RecipeCalculator, ProductCalculator, OrderCalculator
     -> Re-run after save to persist canonical totals based on pivots.
   - Form live calc (UI only): RecipeFormCalculator, ProductFormCalculator, OrderFormCalculator
     -> Used by Filament forms to compute row totals without rehydration flicker.
   - Analytics: SalesAnalytics (paid amounts per day for charts).
   - Formatting helper: App\Support\Format (e.g., IDR/kcal display).
 Query builders (scoped aggregates):
   - Product::topOrdered(10), Ingredient::mostUsed(10), Recipe::mostUsed(10), Order::recentWithItems(10)
   - These avoid ONLY_FULL_GROUP_BY issues by selecting minimal grouped columns.

 -----------------------------------------------------------------------------
 9) REST API (Sanctum)
 -----------------------------------------------------------------------------
 Auth
   - Token-based via Laravel Sanctum. First, POST /api/login with email/password.
   - The WideAccountWidget has a button to generate a token from the dashboard.
   - Send Authorization: Bearer <token> for all subsequent requests.

 Key endpoints (examples)
   - POST /api/login                              -> returns token + user info
   - GET  /api/builder/recipes                    -> list recipes (name, kcal/portion)
   - POST /api/builder/compute-product            -> compute (and optionally persist) a custom product
   - POST /api/builder/ingredients                -> aggregate ingredients for a custom build
   - GET  /api/companies/{company}/products       -> products by company (admin scoped)
   - GET  /api/companies/{company}/products/{id}  -> product detail with composition
   - GET  /api/ingredients?cost=1&calorie=1       -> ingredients list with optional fields
   - GET  /api/companies/{company}/orders         -> paginated orders by company
   - POST /api/customers                          -> create customer + address (for new orders)
   - POST /api/orders/preview-ingredients         -> compute ingredient totals for cart
   - POST /api/orders                             -> create order for current company

 Postman
   - A ready-to-import collection is provided (or copy JSON from docs) to test all endpoints quickly.

 -----------------------------------------------------------------------------
 10) Company scoping rules (API & Admin)
 -----------------------------------------------------------------------------
 - Admin tokens are restricted to their company_id; server verifies company on each endpoint.
 - Super admin tokens can query any {company} path.
 - Ingredients remain global and are visible to all roles.

 -----------------------------------------------------------------------------
 11) Common tasks
 -----------------------------------------------------------------------------
 Clear caches/autoload
 $ php artisan optimize:clear
 $ composer dump-autoload -o

 Rebuild database with demo data
 $ php artisan migrate:fresh --seed

 Create an admin user manually (example)
 $ php artisan tinker
 >>> \App\Models\User::create(['name'=>'Admin X','email'=>'adminx@example.com','password'=>bcrypt('resepedia'),'role'=>'admin','company_id'=>1]);

 -----------------------------------------------------------------------------
 12) Production notes
 -----------------------------------------------------------------------------
 - Set APP_ENV=production, APP_DEBUG=false, proper APP_URL in .env.
 - Configure a real cache/queue (Redis) if you enable jobs or heavy analytics.
 - Use a web server (Nginx/Apache) + PHP-FPM; serve /public as the document root.
 - Keep storage/ and bootstrap/cache writable by the web user.
 - Rotate Sanctum tokens periodically; provide a UI to revoke if needed.

 -----------------------------------------------------------------------------
 13) Troubleshooting
 -----------------------------------------------------------------------------
 Class not found / autoload issues
   - Run: composer dump-autoload -o; php artisan optimize:clear

 SQL ONLY_FULL_GROUP_BY errors
   - Use provided query scopes/builders that select only grouped columns.

 Filament widget layout not matching
   - Adjust getColumns() / columnSpan in widgets and dashboard page; clear caches.

 Token auth failing
   - Ensure Sanctum installed/migrated; User model uses HasApiTokens; include Authorization header.
