<?php

use App\Http\Controllers\api\v1\admin\AdminController;
use App\Http\Controllers\api\v1\admin\AuthenticationController;
use App\Http\Controllers\api\v1\admin\AutoOrderSettingController;
use App\Http\Controllers\api\v1\admin\BoardCoinController;
use App\Http\Controllers\api\v1\admin\ContactController;
use App\Http\Controllers\api\v1\admin\DealingGroupController;
use App\Http\Controllers\api\v1\admin\DepartmentController;
use App\Http\Controllers\api\v1\admin\KimiaController;
use App\Http\Controllers\api\v1\admin\ManualMetalOrderController;
use App\Http\Controllers\api\v1\admin\MetalCardController;
use App\Http\Controllers\api\v1\admin\MetalItemController;
use App\Http\Controllers\api\v1\admin\MetalItemGroupController;
use App\Http\Controllers\api\v1\admin\MetalSettingController;
use App\Http\Controllers\api\v1\admin\NotificationController;
use App\Http\Controllers\api\v1\admin\PriceSourceController;
use App\Http\Controllers\api\v1\admin\PriceSourceMappingController;
use App\Http\Controllers\api\v1\admin\RawMetalPriceController;
use App\Http\Controllers\api\v1\admin\SelectedAutoOrderExchangeController;
use App\Http\Controllers\api\v1\admin\CategoryController;
use App\Http\Controllers\api\v1\admin\CityController;
use App\Http\Controllers\api\v1\admin\CityShippingMethodController;
use App\Http\Controllers\api\v1\admin\MetalTraderController;
use App\Http\Controllers\api\v1\admin\ColorController;
use App\Http\Controllers\api\v1\admin\DashboardController;
use App\Http\Controllers\api\v1\admin\DirectoryController;
use App\Http\Controllers\api\v1\admin\MetalOrderController;
use App\Http\Controllers\api\v1\admin\InquiryController;
use App\Http\Controllers\api\v1\admin\JibitController;
use App\Http\Controllers\api\v1\admin\MeltedController;
use App\Http\Controllers\api\v1\admin\OptionController;
use App\Http\Controllers\api\v1\admin\OrderController;
use App\Http\Controllers\api\v1\admin\PageController;
use App\Http\Controllers\api\v1\admin\PaymentGatewayController;
use App\Http\Controllers\api\v1\admin\PermissionController;
use App\Http\Controllers\api\v1\admin\PersianCoinController;
use App\Http\Controllers\api\v1\admin\ProductController;
use App\Http\Controllers\api\v1\admin\PropertyController;
use App\Http\Controllers\api\v1\admin\PuppeteerController;
use App\Http\Controllers\api\v1\admin\RoleController;
use App\Http\Controllers\api\v1\admin\SectionController;
use App\Http\Controllers\api\v1\admin\SelectedMetalPriceController;
use App\Http\Controllers\api\v1\admin\SettingController;
use App\Http\Controllers\api\v1\admin\ShippingMethodController;
use App\Http\Controllers\api\v1\admin\SizeUnitController;
use App\Http\Controllers\api\v1\admin\TokenController;
use App\Http\Controllers\api\v1\admin\UserController;
use App\Http\Controllers\api\v1\admin\VarietyController;
use App\Http\Controllers\api\v1\admin\VarietyLogController;
use App\Http\Controllers\api\v1\admin\WorkingHourController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::prefix('v1/admin')
    ->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthenticationController::class, 'login'])
            ->middleware('throttle:5,10,admin-login');
        Route::post('logout', [AuthenticationController::class, 'logout'])->middleware('auth:admin-api');
    });

    Route::middleware(['auth:admin-api'])->group(function () {

        Route::post('/broadcasting/auth', function (Request $request) {
            $admin = $request->user('admin-api');

            if (!$admin) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $channelName = $request->input('channel_name');
            $socketId = $request->input('socket_id');

            if (!$channelName || !$socketId) {
                return response()->json(['error' => 'Missing parameters'], 400);
            }

            // استفاده از سیستم احراز هویت پیش‌فرض Laravel
            return Broadcast::auth($request);
        });

        // dateTime
        Route::get('server-time', function (){
            return response()->json([
                'now' => Carbon\Carbon::now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s')
            ]);
        });
        // dashboard
        Route::get('dashboard', [DashboardController::class, 'all']);
        Route::get('badges', [DashboardController::class, 'badges']);
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index']);
            Route::get('/all', [NotificationController::class, 'all']);
            Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('/', [NotificationController::class, 'store']);
            Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
            Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
            Route::delete('/{id}', [NotificationController::class, 'destroy']);
        });
        // Market price
        Route::get('melted/rates', [MeltedController::class, 'rates']);
        Route::get('melted/channels', [MeltedController::class, 'channels']);
        Route::get('melted/settings', [MeltedController::class, 'settings']);
        Route::patch('melted/settings/{channel}', [MeltedController::class, 'update']);
        // Metal orders
        Route::prefix('metal-orders')->group(function () {
            Route::prefix('manual')->group(function () {
                Route::get('/', [ManualMetalOrderController::class, 'create']);
                Route::post('/', [ManualMetalOrderController::class, 'store']);
            });
            Route::get('/', [MetalOrderController::class, 'index']);
            Route::get('{order}', [MetalOrderController::class, 'show']);
            Route::patch('{order}', [MetalOrderController::class, 'update']);
            Route::get('{order}/log', [MetalOrderController::class, 'log']);
        });
        Route::prefix('selected-metal-prices')->group(function () {
            Route::post('/', [SelectedMetalPriceController::class, 'store']);
            Route::post('use-rate', [SelectedMetalPriceController::class, 'useRate']);
        });
        Route::prefix('price-source-mapping')->group(function () {
            Route::post('/', [PriceSourceMappingController::class, 'store']);
        });
        // Product Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::patch('orders/{order}', [OrderController::class, 'update']);
        Route::get('orders/{order}', [OrderController::class, 'show']);
        Route::patch('orders/{order}/deposit', [OrderController::class, 'deposit']);
        Route::get('orders/{order}/log', [OrderController::class, 'log']);
        //
        Route::get('tokens', [TokenController::class, 'index']);
        Route::delete('tokens/{token}', [TokenController::class, 'destroy']);
        Route::get('info', [AdminController::class, 'info']);
        // products
        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::post('products/properties', [ProductController::class, 'updateProductProperties']);
        Route::get('products/list', [ProductController::class, 'list']);
        Route::delete('products/properties/{product}', [ProductController::class, 'removeProductProperties']);
        Route::get('products/create', [ProductController::class, 'create']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::patch('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        // varieties
        Route::post('varieties', [VarietyController::class, 'store']);
        Route::get('varieties/{variety}', [VarietyController::class, 'show']);
        Route::patch('varieties/{variety}', [VarietyController::class, 'update']);
        Route::patch('varieties/count/{variety}', [VarietyController::class, 'updateCount']);
        // Variety log
        Route::get('variety-log/{variety}', [VarietyLogController::class, 'log']);
        // properties
        Route::get('properties', [PropertyController::class, 'index']);
        Route::post('properties', [PropertyController::class, 'store']);
        Route::post('properties/values/store', [PropertyController::class, 'storeNewValue']);
        Route::get('properties/{property}', [PropertyController::class, 'show']);
        Route::patch('properties/{property}', [PropertyController::class, 'update']);
        Route::delete('properties/{property}', [PropertyController::class, 'remove']);
        Route::get('properties/usage/{property}', [PropertyController::class, 'usage']);
        // Settings
        Route::prefix('settings')->group(function () {
            Route::get('', [SettingController::class, 'index']);
            Route::get('api', [SettingController::class, 'api']);
            Route::get('mazane', [SettingController::class, 'mazane'])->withoutMiddleware('auth:admin-api');
            Route::post('client-messages', [SettingController::class, 'saveClientMessages']);

            // Route::post('api/reference-market', [SettingController::class, 'addReferenceMarket']);
            // Route::patch('api/reference-market/{referenceMarket}', [SettingController::class, 'updateReferenceMarket']);
            // Route::delete('api/reference-market/{referenceMarket}', [SettingController::class, 'deleteReferenceMarket']);

            Route::get('sms', [SettingController::class, 'sms']);
            Route::patch('{setting}', [SettingController::class, 'update']);

            // Sections [landing page items]
            Route::get('sections', [SectionController::class, 'index']);
            Route::post('sections', [SectionController::class, 'store']);
            Route::patch('sections/toggle/{section}', [SectionController::class, 'toggle']);
            Route::get('sections/create', [SectionController::class, 'create']);
            Route::get('sections/{section}', [SectionController::class, 'get']);
            Route::patch('sections/{section}', [SectionController::class, 'update']);
            Route::delete('sections/{section}', [SectionController::class, 'remove']);
            Route::delete('sections/images/{image_id}', [SectionController::class, 'removeImage']);
            Route::post('sections/banner/{banner}', [SectionController::class, 'addImage']);
            Route::post('sections/change-order', [SectionController::class, 'changeOrder']);
            Route::get('shipping-method', [ShippingMethodController::class, 'index']);
            Route::get('shipping-method/{shippingMethod}', [ShippingMethodController::class, 'show']);
            Route::patch('shipping-method/{shippingMethod}', [ShippingMethodController::class, 'update']);
            Route::post('shipping-method', [ShippingMethodController::class, 'store']);
            Route::post('city-shipping-method', [CityShippingMethodController::class, 'store']);
            Route::get('city-shipping-method/{cityShippingMethod}', [CityShippingMethodController::class, 'show']);
            Route::patch('city-shipping-method/{cityShippingMethod}', [CityShippingMethodController::class, 'update']);
            Route::delete('city-shipping-method/{cityShippingMethod}', [CityShippingMethodController::class, 'destroy']);

            // Department
            Route::prefix('departments')->group(function () {
                Route::get('', [DepartmentController::class, 'index']);
                Route::post('', [DepartmentController::class, 'store']);
                Route::get('{department}', [DepartmentController::class, 'show']);
                Route::patch('{department}', [DepartmentController::class, 'update']);
                Route::delete('{department}', [DepartmentController::class, 'destroy']);
            });

            Route::prefix('auto-order-settings')->group(function () {
                Route::post('/', [AutoOrderSettingController::class, 'store']);
                Route::put('/{auto_order_setting}', [AutoOrderSettingController::class, 'update']);
                Route::delete('/{auto_order_setting}', [AutoOrderSettingController::class, 'destroy']);
            });
        });

        // contacts
        Route::prefix('contacts')->group(function () {
            Route::post('update-sort-order', [ContactController::class, 'updateSortOrder']);
            Route::post('', [ContactController::class, 'store']);
            Route::patch('{contact}', [ContactController::class, 'update']);
            Route::delete('{contact}', [ContactController::class, 'destroy']);
        });

        // Gateways
        Route::get('gateways', [PaymentGatewayController::class, 'index']);
        Route::patch('gateways/{gateway}', [PaymentGatewayController::class, 'update']);
        // coins
        Route::get('persian_coins', [PersianCoinController::class, 'index']);
        Route::post('persian_coins', [PersianCoinController::class, 'store']);
        Route::patch('persian_coins/{coin}', [PersianCoinController::class, 'update']);
        Route::delete('persian_coins/{coin}', [PersianCoinController::class, 'destroy']);
        // Options
        Route::get('options', [OptionController::class, 'index']);
        Route::post('options', [OptionController::class, 'store']);
        Route::patch('options/{option}', [OptionController::class, 'update']);
        Route::delete('options/{option}', [OptionController::class, 'delete']);
        // Role & permissions
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('roles/all', [RoleController::class, 'all']);
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::patch('roles/{role}', [RoleController::class, 'update']);
        Route::get('roles/{role}', [RoleController::class, 'show']);
        // Size units
        Route::get('size_units', [SizeUnitController::class, 'index']);
        Route::post('size_units', [SizeUnitController::class, 'store']);
        Route::delete('size_units/{size_unit}', [SizeUnitController::class, 'destroy']);
        Route::get('size_units/usage/{size_unit}', [SizeUnitController::class, 'usage']);
        // Color
        Route::post('colors', [ColorController::class, 'store']);
        Route::delete('colors/{color}', [ColorController::class, 'destroy']);
        Route::get('colors/usage/{color}', [ColorController::class, 'usage']);
        // Category
        Route::get('categories', [CategoryController::class, 'index']);
        Route::post('categories', [CategoryController::class, 'store']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
        Route::patch('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
        // Directory
        Route::get('directories', [DirectoryController::class, 'index']);
        Route::post('directories', [DirectoryController::class, 'store']);
        Route::patch('directories/change-order', [DirectoryController::class, 'changeOrder']);
        Route::get('directories/{directory}', [DirectoryController::class, 'show']);
        Route::patch('directories/{directory}', [DirectoryController::class, 'update']);
        Route::delete('directories/{directory}', [DirectoryController::class, 'destroy']);
        Route::get('directories/{directory}/products', [DirectoryController::class, 'products']);
        // Pages
        Route::get('pages', [PageController::class, 'all']);
        Route::get('pages/about', [PageController::class, 'about']);
        Route::get('pages/contact', [PageController::class, 'contact']);
        Route::patch('pages/update-about', [PageController::class, 'updateAbout']);
        Route::patch('pages/update-contact', [PageController::class, 'updateContact']);
        // Users
        Route::get('users', [UserController::class, 'index']);
        Route::patch('users/{user}', [UserController::class, 'update']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::get('users/{user}/addresses', [UserController::class, 'addresses']);
        Route::get('users/{user}/orders', [UserController::class, 'orders']);
        Route::get('users/{user}/log', [UserController::class, 'log']);
        Route::patch('users/{user}/product-settings', [UserController::class, 'updateProductSettings']);
        // Staffs
        Route::get('staffs', [AdminController::class, 'index']);
        Route::get('staffs/permissions', [AdminController::class, 'permissions']);
        Route::post('staffs', [AdminController::class, 'store']);
        Route::patch('staffs/{staff}', [AdminController::class, 'update']);
        Route::get('staffs/{staff}', [AdminController::class, 'show']);
        // Cities
        Route::get('cities', [CityController::class, 'index']);
        // Store Hours
        Route::get('working-hours', [WorkingHourController::class, 'index']);
        Route::post('working-hours', [WorkingHourController::class, 'store']);
        // inquiries
        Route::prefix('inquiries')->group(function () {
            Route::post('matching', [InquiryController::class, 'matching']);
            Route::post('similarity', [InquiryController::class, 'similarity']);
        });
        // Dealing Groups
        Route::prefix('dealing-groups')->group(function () {
           Route::get('/', [DealingGroupController::class, 'index']);
           Route::post('/', [DealingGroupController::class, 'store']);
           Route::get('/create', [DealingGroupController::class, 'create']);
           Route::get('{group}', [DealingGroupController::class, 'show']);
           Route::patch('{group}', [DealingGroupController::class, 'update']);
           Route::post('{group}/metal-items', [DealingGroupController::class, 'storeDealingGroupMetalItem']);
           Route::patch('{group}/metal-items/{metalItem}', [DealingGroupController::class, 'updateDealingGroupMetalItem']);
           Route::delete('{group}/metal-items/{metalItem}', [DealingGroupController::class, 'deleteDealingGroupMetalItem']);
        });
        // Metal traders
        Route::prefix('metal-traders')->group(function(){
            Route::get('', [MetalTraderController::class, 'index']);
            Route::get('leads', [MetalTraderController::class, 'leads']);
            Route::post('', [MetalTraderController::class, 'store']);
            Route::get('subscription/{subscription}', [MetalTraderController::class, 'showSubscription']);
            Route::patch('subscription/{subscription}', [MetalTraderController::class, 'updateSubscription']);
            Route::get('create', [MetalTraderController::class, 'create']);
            Route::get('{metal_trader}', [MetalTraderController::class, 'show']);
            Route::get('{metal_trader}/balance', [MetalTraderController::class, 'balance']);
            Route::patch('{metal_trader}', [MetalTraderController::class, 'update']);
            Route::patch('{metal_trader}/product-settings', [MetalTraderController::class, 'updateProductSettings']);
            Route::get('{metal_trader}/log', [MetalTraderController::class, 'log']);
            Route::get('{metal_trader}/subscriptions', [MetalTraderController::class, 'subscriptions']);
            Route::post('{metal_trader}/subscriptions', [MetalTraderController::class, 'storeSubscriptions']);
            Route::get('{metal_trader}/subscriptions/create', [MetalTraderController::class, 'createSubscription']);
        });
        Route::prefix('metals')->group(function(){
            Route::prefix('metal-cards')->group(function(){
                Route::get('/', [MetalCardController::class, 'index']);
                Route::patch('/{card}', [MetalCardController::class, 'update']);
            });
            Route::prefix('metal-items')->group(function() {
                Route::get('/', [MetalItemController::class, 'index']);
                Route::post('/', [MetalItemController::class, 'store']);
                Route::get('/create', [MetalItemController::class, 'create']);
                Route::post('/update-sort-order', [MetalItemController::class, 'updateSortOrder']);
                Route::get('/{metalItem}', [MetalItemController::class, 'show']);
                Route::patch('/{metalItem}', [MetalItemController::class, 'update']);
            });
            Route::prefix('metal-item-groups')->group(function() {
                Route::get('/', [MetalItemGroupController::class, 'index']);
                Route::post('/', [MetalItemGroupController::class, 'store']);
                Route::post('/update-order', [MetalItemGroupController::class, 'updateOrder']);
                Route::get('/{metalItemGroup}', [MetalItemGroupController::class, 'show']);
                Route::patch('/{metalItemGroup}', [MetalItemGroupController::class, 'update']);
            });
            Route::prefix('price-sources')->group(function() {
                Route::get('/', [PriceSourceController::class, 'index']);
                Route::post('/', [PriceSourceController::class, 'store']);
                Route::get('/create', [PriceSourceController::class, 'create']);
                Route::get('/{priceSource}', [PriceSourceController::class, 'show']);
                Route::patch('/{priceSource}', [PriceSourceController::class, 'update']);
                Route::post('/{priceSource}/metal-items', [PriceSourceController::class, 'storePriceSourceMetalItem']);
                Route::patch('/{priceSource}/metal-items/{metalItem}', [PriceSourceController::class, 'updatePriceSourceMetalItem']);
                Route::delete('/{priceSource}/metal-items/{metalItem}', [PriceSourceController::class, 'deletePriceSourceMetalItem']);
            });
            Route::prefix('raw-metal-prices')->group(function() {
                Route::get('/', [RawMetalPriceController::class, 'index']);
            });
            Route::prefix('settings')->group(function() {
                Route::get('/', [MetalSettingController::class, 'index']);
            });
        });

        Route::prefix('board')->group(function(){
            Route::get('coins', [BoardCoinController::class, 'index']);
            Route::post('coins', [BoardCoinController::class, 'store']);
            Route::post('coins/update-sort-order', [BoardCoinController::class, 'updateSortOrder']);
            Route::put('coins/{board_coin}', [BoardCoinController::class, 'update']);
            Route::delete('coins/{board_coin}', [BoardCoinController::class, 'destroy']);
        });

        Route::get('/kimi-accounts/search', [MetalTraderController::class, 'searchKimiAccount']);

        Route::prefix('jibit')->group(function(){
            Route::get('tokens/refresh', [JibitController::class, 'refreshToken']);
        });

        Route::prefix('exchange-orders')->group(function(){
            Route::post('/', [PuppeteerController::class, 'store']);
        });

        Route::prefix('selected-auto-order-exchanges')->group(function(){
            Route::patch('/{selectedAutoOrderExchange}', [SelectedAutoOrderExchangeController::class, 'update']);
            Route::delete('/{selectedAutoOrderExchange}', [SelectedAutoOrderExchangeController::class, 'destroy']);
            Route::post('/', [SelectedAutoOrderExchangeController::class, 'store']);
        });
    });

    Route::prefix('kimia')->group(function (){
        Route::get('/', [KimiaController::class, 'kimia']);
    });

});
