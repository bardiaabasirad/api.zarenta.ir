<?php

namespace App\Http\Controllers\api\v1\external;

use App\Constants\AppConstants;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MetalOrder\StoreMetalOrderRequest;
use App\Http\Resources\ExternalClientOrdersResource;
use App\Jobs\CheckKimiaBalance;
use App\Models\MetalOrder;
use App\Models\MetalItem;
use App\Models\MetalTrader;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Services\MetalOrderService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class ClientOrderController extends Controller
{
    public function index()
    {
        $metalTrader = request()->get('api_client');
        $status = request()->input('status');
        $sortBy = request()->input('sortBy', 'created_at');
        $dir = request()->input('dir', 'desc');
        $count = request()->input('count', config('app.per_page', 50));

        $orders = MetalOrder::query()
            ->where('created_type', (new MetalTrader())->getMorphClass())
            ->where('created_id', $metalTrader->id)
            ->when(isset($status), function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderBy($sortBy, $dir)
            ->simplePaginate($count);

        $orders->getCollection()->each(fn($order) => $order->updateProductFee());

        return response()->json(ExternalClientOrdersResource::collection($orders));
    }

    #[OA\Get(
        path: "/api/orders/track",
        summary: "دریافت وضعیت سفارش(ات)",
        security: [["X-API-Key" => []]],
        tags: ["Orders"],
        parameters: [
            new OA\Parameter(
                name: "tracking_code",
                description: "کد رهگیری تکی",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "7987950561")
            ),
            new OA\Parameter(
                name: "source_order_id",
                description: "شناسه سفارش منبع تکی",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "58R4rG6")
            ),
            new OA\Parameter(
                name: "source_order_ids",
                description: "شناسه‌های سفارش منبع (چند مقداری)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "7987950561")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "لیست سفارش(ات)",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/OrderStatusItem")
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized", content: new OA\JsonContent(ref: "#/components/schemas/Error401")),
            new OA\Response(response: 403, description: "Forbidden", content: new OA\JsonContent(ref: "#/components/schemas/Error403"))
        ]
    )]
    public function track(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tracking_code' => 'nullable|integer|exists:metal_orders,tracking_code',
            'source_order_id' => 'nullable|string',
            'source_order_ids' => 'nullable|string',
        ], [
            'tracking_code.exists' => 'سفارش با این شناسه یافت نشد',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $metalTrader = $request->get('api_client');

        $query = MetalOrder::query()
            ->where('created_type', (new MetalTrader())->getMorphClass())
            ->where('created_id', $metalTrader->id);

        if ($request->filled('tracking_code')) {
            $query->where('tracking_code', $request->tracking_code);
        } elseif ($request->filled('source_order_id')) {
            $query->where('source_order_id', $request->source_order_id);
        } elseif ($request->filled('source_order_ids')) {
            $sourceOrderIds = array_filter(
                array_map('trim', explode(',', $request->source_order_ids))
            );

            $query->whereIn('source_order_id', $sourceOrderIds);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'حداقل یکی از پارامترهای order_id، source_order_id یا source_order_ids الزامی است',
            ], 400);
        }

        // برای تک سفارش
        if ($request->filled('order_id') || $request->filled('source_order_id')) {
            $metalOrder = $query->firstOrFail();
            $metalOrder->updateProductFee();

            return response()->json(ExternalClientOrdersResource::make($metalOrder));
        }

        // برای چند سفارش
        $orders = $query->get();

        if ($orders->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'هیچ سفارشی با مشخصات ارسالی یافت نشد',
            ], 404);
        }

        $orders->each(function ($order) {
            $order->updateProductFee();
        });

        return response()->json(ExternalClientOrdersResource::collection($orders));
    }

    #[OA\Post(
        path: "/api/clients/orders",
        summary: "ثبت سفارش خرید/فروش",
        security: [["X-API-Key" => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/OrderRequest")
        ),
        tags: ["Order"],
        responses: [
            new OA\Response(
                response: 200,
                description: "سفارش با موفقیت ثبت شد",
                content: new OA\JsonContent(ref: "#/components/schemas/OrderResponse")
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized",
                content: new OA\JsonContent(ref: "#/components/schemas/Error401")
            ),
            new OA\Response(
                response: 422,
                description: "Validation Error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "errors", type: "object")
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: "Conflict - مظنه ارسالی با آخرین مظنه بازار مطابقت ندارد",
                content: new OA\JsonContent(ref: "#/components/schemas/OrderValidationError")
            )
        ]
    )]
    public function store(StoreMetalOrderRequest $request)
    {
        $validated = $request->validated();

        $quantity = $validated['quantity'];
        $metal_item_id = $validated['product_id'];
        $action = $validated['order_type'];
        $mazane = $validated['mazane'] ?? null;
        $order_id = $validated['order_id'] ?? null;
        $frozen = 'metal';

        $metalTrader = $request->get('api_client');

        if ($order_id && MetalOrder::where([
                'source_order_id' => $order_id,
                'created_type' => 'metal_trader',
                'created_id' => $metalTrader->id,
            ])->exists()) {
            throw ValidationException::withMessages([
                'order_id' => 'شناسه سفارش ارسالی شما تکراری می‌باشد',
            ]);
        }

        $metalItem = MetalItem::find($metal_item_id);

        if ($metalItem->unit == 'count' && floor($quantity) != $quantity) {
            throw ValidationException::withMessages([
                'quantity' => 'برای این محصول، تعداد باید عدد صحیح باشد',
            ]);
        }

        $extraData = [
            'order_reference' => 'api',
            'frozen' => $frozen,
            'accounting_document_id' => $metalItem->requires_accounting_document_id ? $metalItem->accounting_document_id : null,
        ];

        $status = 'pending';

        if ($metalTrader->dealing_group_id) {
            $dealing_group_id = $metalTrader->dealing_group_id;
        } else {
            $setting = Setting::firstWhere('option_key', 'default_metal_trader_group_id');
            $dealing_group_id = $setting->option_value;
        }

        $latestPriceObject = SelectedMetalPrice::where('metal_item_id', $metal_item_id)->latest()->first();

        $persianUnit = $metalItem->unit == 'count' ? 'عدد' : 'گرم';

        $dealingGroupMetalItem = DB::table('dealing_group_metal_item')
            ->where('metal_item_id', $metal_item_id)
            ->where('dealing_group_id', $dealing_group_id)
            ->first();

        $marketStatus = Setting::where('option_key', 'market_status')
            ->value('option_value');

        $productStatus = match ($action) {
            'buy' => $metalItem->is_sell_active,
            'sell' => $metalItem->is_buy_active,
            default => true,
        };

        if ($marketStatus == 'inactive' || ! $productStatus || ! $latestPriceObject) {
            $status = 'rejected';

            $extraData = array_merge($extraData, [
                'cause' => 'closed_market',
                'message' => 'بسته بودن بازار یا عدم پذیرش سفارش در محصول انتخابی',
            ]);
        }

        $minOrder = $dealingGroupMetalItem->min_order ?? 0.001;
        $maxOrder = $dealingGroupMetalItem->max_order ?? INF;

        // اعتبارسنجی حداقل و حداکثر مقدار سفارش
        if ($quantity < $minOrder) {
            $status = 'rejected';

            $extraData = array_merge($extraData, [
                'cause' => 'min_weight_requirement',
                'message' => sprintf(
                    "کمتر بودن مقدار سفارش از حداقل مجاز (%s {$persianUnit})",
                    rtrim(rtrim($minOrder, '0'), '.')
                ),
                'min_order' => $minOrder,
            ]);
        } elseif ($quantity > $maxOrder) {
            $status = 'rejected';

            $extraData = array_merge($extraData, [
                'cause' => 'max_weight_requirement',
                'message' => sprintf(
                    "بیشتر بودن مقدار سفارش از حداکثر مجاز (%s {$persianUnit})",
                    rtrim(rtrim($maxOrder, '0'), '.')
                ),
                'min_order' => $maxOrder,
            ]);
        }

        if ($action == 'buy') {
            $rawLatestPrice = $latestPriceObject->sell;
            $tolerance = $dealingGroupMetalItem->sell_fee_margin ?? 0;
        } else {
            $rawLatestPrice = $latestPriceObject->buy;
            $tolerance = $dealingGroupMetalItem->buy_fee_margin ?? 0;
        }

        if ($dealingGroupMetalItem->tolerance_type == 'fixed_amount') {
            $latestBasePrice = $rawLatestPrice + $tolerance;
        } else {
            $latestBasePrice = $rawLatestPrice + ($rawLatestPrice * $tolerance / 100);
        }

        // ✅ مقایسه مظنه ارسالی با مظنه محاسبه‌شده
        if ($mazane !== null && $mazane != $latestBasePrice) {
            return response()->json([
                'message' => 'مظنه ارسالی با آخرین مظنه بازار مطابقت ندارد.',
                'errors' => [
                    'mazane' => [
                        sprintf('مظنه فعلی: %s تومان', number_format($latestBasePrice))
                    ]
                ],
                'current_mazane' => $latestBasePrice,
                'submitted_mazane' => $mazane,
            ], 409);
        }

        if ($metalItem->unit == 'count') {
            if ($action == 'buy') {
                $latestPrice = roundUpToThousand($latestBasePrice);
            } else {
                $latestPrice = roundDownToThousand($latestBasePrice);
            }
        } else {
            if ($action == 'buy') {
                $latestPrice = roundUpToThousand($latestBasePrice / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
            } else {
                $latestPrice = roundDownToThousand($latestBasePrice / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
            }
        }

        $amount = $quantity * $latestPrice;

        $payload = [
            'tracking_code' => MetalOrderService::generateTrackingCode(),
            'source_order_id' => $order_id,
            'created_id' => $metalTrader->id,
            'created_type' => (new MetalTrader())->getMorphClass(),
            'order_type' => $action,
            'status' => $status,
            'extra_data' => $extraData,
            'product' => [
                'name' => $metalItem->title,
                'metal_item_id' => $metalItem->id,
                'kimia_product_id' => $metalItem->kimia_product_id,
                'fee' => $rawLatestPrice,
                'fee_margin' => $tolerance,
                'unit' => $persianUnit,
                'en_unit' => $metalItem->unit,
                'quantity' => $quantity,
                'tolerance_type' => $dealingGroupMetalItem->tolerance_type,
                'display_mode' => $dealingGroupMetalItem->display_mode,
                'amount' => $amount,
            ],
        ];

        $order = DB::transaction(function () use ($payload, $metalTrader, $amount) {

            $order = MetalOrder::create($payload);

            if ($metalTrader->trade_leverage && $metalTrader->trade_leverage > 0) {
                $order->leverageCheck()->create([
                    'status' => 'pending',
                    'applied_leverage' => $metalTrader->trade_leverage,
                    'order_value' => $amount,
                ]);
            }

            return $order;
        });

        if ($metalTrader->trade_leverage && $metalTrader->trade_leverage > 0) {
            CheckKimiaBalance::dispatch($order, $metalTrader->trade_leverage, $metalTrader->kimi_account_id);
        }

        return response()->json(ExternalClientOrdersResource::make($order));
    }
}
