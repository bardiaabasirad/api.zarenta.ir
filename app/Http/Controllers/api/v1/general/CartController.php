<?php

namespace App\Http\Controllers\api\v1\general;

use App\Constants\AppConstants;
use App\Enums\OrderStatus;
use App\Events\OrderCreated;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckCartRequest;
use App\Http\Requests\UpdateCartQuantityRequest;
use App\Http\Requests\InvoiceRequest;
use App\Jobs\SmsJob;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\City;
use App\Models\CityShippingMethod;
use App\Models\MarketPrice;
use App\Models\MetalOrder;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Province;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Variety;
use App\Models\VarietyLog;
use App\Models\WorkingHour;
use App\Services\CartService;
use App\Services\JibitPaymentService;
use App\Services\MetalOrderService;
use App\Services\OrderService;
use App\Services\ProductPriceService;
use App\Services\VarietyLogService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Shetabit\Multipay\Exceptions\InvalidPaymentException;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;

class CartController extends Controller
{
    public function get()
    {
        $cartItems = CartItem::where('user_id', Auth::guard('user-api')->id())
            ->with(['product.size_unit', 'variety.images', 'variety.color'])
            ->get();

        return response()->json($cartItems);
    }

    public function checkCartWithPaymentGateways(CheckCartRequest $request): JsonResponse
    {
        $optionKeys = ['post_shopping_cost', 'tipax_shopping_cost', 'cart_duration_validity', 'limited_amount_payable_by_gateway'];
        $options = Setting::whereIn('option_key', $optionKeys)->get()->keyBy('option_key');

        $messages = [];
        $user_id = Auth::guard('user-api')->id();
        $cartItems = CartItem::where('user_id', $user_id)->get();

        if ($cartItems) {
            if ($request->items) {
                $varietyIds = collect($request->items)->pluck('variety_id')->toArray();

                // Load all relevant varieties at once
                $varieties = Variety::whereIn('id', $varietyIds)->get();
                $varietyMap = $varieties->keyBy('id');

                foreach ($request->items as $item) {
                    $variety = $varietyMap[$item['variety_id']] ?? null;

                    if ($variety) {
                        // Cart exists, so find the item you want to increment
                        $existingItem = $cartItems->where('product_id', $item['product_id'])->where('variety_id', $item['variety_id'])->first();

                        if ($existingItem) {
                            if ($variety->count > 0 && $variety->count < $item['count']) {
                                $existingItem->count = $variety->count;

                                $messages[] = [
                                    'product_id' => (int)$item['product_id'],
                                    'variety_id' => (int)$item['variety_id'],
                                    'message' => "موجودی این محصول از {$item['count']} عدد به $variety->count عدد کاهش یافت",
                                    'status' => 'reduce',
                                ];
                            } elseif ($variety->count == 0) {
                                $existingItem->count = 0;
                                $messages[] = [
                                    'product_id' => (int)$item['product_id'],
                                    'variety_id' => (int)$item['variety_id'],
                                    'message' => 'این محصول از سبد خرید شما حذف شده است',
                                    'status' => 'remove',
                                ];
                            } else {
                                $existingItem->count = $item['count'];
                            }
                            $existingItem->save();
                        } else {
                            if ($variety->count > 0 && $variety->count < $item['count']) {
                                CartItem::create([
                                    'user_id' => $user_id,
                                    'product_id' => $item['product_id'],
                                    'variety_id' => $item['variety_id'],
                                    'count' => $variety->count,
                                ]);

                                $messages[] = [
                                    'product_id' => (int)$item['product_id'],
                                    'variety_id' => (int)$item['variety_id'],
                                    'message' => "موجودی این محصول از {$item['count']} عدد به {$variety->count} عدد کاهش یافت",
                                    'status' => 'reduce',
                                ];
                            } else if ($variety->count == 0) {
                                CartItem::create([
                                    'user_id' => $user_id,
                                    'product_id' => $item['product_id'],
                                    'variety_id' => $item['variety_id'],
                                    'count' => 0,
                                ]);

                                $messages[] = [
                                    'product_id' => (int)$item['product_id'],
                                    'variety_id' => (int)$item['variety_id'],
                                    'message' => 'این محصول از سبد خرید شما حذف شده است',
                                    'status' => 'remove',
                                ];
                            } else {
                                CartItem::create([
                                    'user_id' => $user_id,
                                    'product_id' => $item['product_id'],
                                    'variety_id' => $item['variety_id'],
                                    'count' => $item['count'],
                                ]);
                            }
                        }
                    }
                }
            }
        } else {
            if ($request->items) {
                $varietyIds = collect($request->items)->pluck('variety_id')->toArray();

                // Load all relevant varieties at once
                $varieties = Variety::whereIn('id', $varietyIds)->get();
                $varietyMap = $varieties->keyBy('id');

                foreach ($request->items as $item) {
                    $variety = $varietyMap[$item['variety_id']] ?? null;

                    if ($variety) {
                        if ($variety->count > 0 && $variety->count < $item['count']) {
                            CartItem::create([
                                'user_id' => $user_id,
                                'product_id' => $item['product_id'],
                                'variety_id' => $item['variety_id'],
                                'count' => $variety->count,
                            ]);

                            $messages[] = [
                                'product_id' => (int)$item['product_id'],
                                'variety_id' => (int)$item['variety_id'],
                                'message' => "موجودی این محصول از {$item['count']} عدد به {$variety->count} عدد کاهش یافت",
                                'status' => 'reduce',
                            ];
                        } else if ($variety->count == 0) {
                            CartItem::create([
                                'user_id' => $user_id,
                                'product_id' => $item['product_id'],
                                'variety_id' => $item['variety_id'],
                                'count' => 0,
                            ]);

                            $messages[] = [
                                'product_id' => (int)$item['product_id'],
                                'variety_id' => (int)$item['variety_id'],
                                'message' => trans('messages.this_product_removed_from_your_cart'),
                                'status' => 'remove',
                            ];
                        } else {
                            CartItem::create([
                                'user_id' => $user_id,
                                'product_id' => $item['product_id'],
                                'variety_id' => $item['variety_id'],
                                'count' => $item['count'],
                            ]);
                        }
                    }
                }
            }
        }

        $cartItems = CartItem::where('user_id', $user_id)
            ->with(['product.size_unit', 'variety.images', 'variety.color'])
            ->get();

        // Remove items marked for deletion:
        foreach ($messages as $message) {
            if ($message['status'] == 'remove') {
                CartItem::where('user_id', $user_id)
                    ->where('product_id', $message['product_id'])
                    ->where('variety_id', $message['variety_id'])
                    ->delete();
            }
        }

        return response()->json([
            'cart_items' => $cartItems,
            'payment_gateways' => PaymentGateway::select('id', 'driver', 'owner_card_payment', 'status')->get(),
            'working_hours' => WorkingHour::orderBy('order')->get(),
            'limited_amount_payable_by_gateway' => $options['limited_amount_payable_by_gateway']->option_value,
            'messages' => $messages,
            'cart_duration_validity' => $options['cart_duration_validity']->option_value ?? null
        ]);
    }

    public function checkCart(CheckCartRequest $request): JsonResponse
    {
        $provinces = Province::all();
        $cities = City::all();
        $optionKeys = ['post_shopping_cost', 'tipax_shopping_cost', 'cart_duration_validity', 'limited_amount_payable_by_gateway'];
        $options = Setting::whereIn('option_key', $optionKeys)->get()->keyBy('option_key');

        if (Auth::guard('user-api')->check()) {
            $messages = [];
            $user_id = Auth::guard('user-api')->id();
            $cartItems = CartItem::where('user_id', $user_id)->get();

            if ($cartItems) {
                if ($request->items) {
                    $varietyIds = collect($request->items)->pluck('variety_id')->toArray();

                    // Load all relevant varieties at once
                    $varieties = Variety::whereIn('id', $varietyIds)->get();
                    $varietyMap = $varieties->keyBy('id');

                    foreach ($request->items as $item) {
                        $variety = $varietyMap[$item['variety_id']] ?? null;

                        if ($variety) {
                            // Cart exists, so find the item you want to increment
                            $existingItem = $cartItems->where('product_id', $item['product_id'])->where('variety_id', $item['variety_id'])->first();

                            if ($existingItem) {
                                if ($variety->count > 0 && $variety->count < $item['count']) {
                                    $existingItem->count = $variety->count;

                                    $messages[] = [
                                        'product_id' => (int)$item['product_id'],
                                        'variety_id' => (int)$item['variety_id'],
                                        'message' => "موجودی این محصول از {$item['count']} عدد به {$variety->count} عدد کاهش یافت",
                                        'status' => 'reduce',
                                    ];
                                } elseif ($variety->count == 0) {
                                    $existingItem->count = 0;
                                    $messages[] = [
                                        'product_id' => (int)$item['product_id'],
                                        'variety_id' => (int)$item['variety_id'],
                                        'message' => trans('messages.this_product_removed_from_your_cart'),
                                        'status' => 'remove',
                                    ];
                                } else {
                                    $existingItem->count = $item['count'];
                                }
                                $existingItem->save();
                            } else {
                                if ($variety->count > 0 && $variety->count < $item['count']) {
                                    CartItem::create([
                                        'user_id' => $user_id,
                                        'product_id' => $item['product_id'],
                                        'variety_id' => $item['variety_id'],
                                        'count' => $variety->count,
                                    ]);

                                    $messages[] = [
                                        'product_id' => (int)$item['product_id'],
                                        'variety_id' => (int)$item['variety_id'],
                                        'message' => "موجودی این محصول از {$item['count']} عدد به {$variety->count} عدد کاهش یافت",
                                        'status' => 'reduce',
                                    ];
                                } else if ($variety->count == 0) {
                                    CartItem::create([
                                        'user_id' => $user_id,
                                        'product_id' => $item['product_id'],
                                        'variety_id' => $item['variety_id'],
                                        'count' => 0,
                                    ]);

                                    $messages[] = [
                                        'product_id' => (int)$item['product_id'],
                                        'variety_id' => (int)$item['variety_id'],
                                        'message' => trans('messages.this_product_removed_from_your_cart'),
                                        'status' => 'remove',
                                    ];
                                } else {
                                    CartItem::create([
                                        'user_id' => $user_id,
                                        'product_id' => $item['product_id'],
                                        'variety_id' => $item['variety_id'],
                                        'count' => $item['count'],
                                    ]);
                                }
                            }
                        }
                    }
                }
            } else {
                if ($request->items) {
                    $varietyIds = collect($request->items)->pluck('variety_id')->toArray();

                    // Load all relevant varieties at once
                    $varieties = Variety::whereIn('id', $varietyIds)->get();
                    $varietyMap = $varieties->keyBy('id');

                    foreach ($request->items as $item) {
                        $variety = $varietyMap[$item['variety_id']] ?? null;

                        if ($variety) {
                            if ($variety->count > 0 && $variety->count < $item['count']) {
                                CartItem::create([
                                    'user_id' => $user_id,
                                    'product_id' => $item['product_id'],
                                    'variety_id' => $item['variety_id'],
                                    'count' => $variety->count,
                                ]);

                                $messages[] = [
                                    'product_id' => (int)$item['product_id'],
                                    'variety_id' => (int)$item['variety_id'],
                                    'message' => "موجودی این محصول از {$item['count']} عدد به {$variety->count} عدد کاهش یافت",
                                    'status' => 'reduce',
                                ];
                            } else if ($variety->count == 0) {
                                CartItem::create([
                                    'user_id' => $user_id,
                                    'product_id' => $item['product_id'],
                                    'variety_id' => $item['variety_id'],
                                    'count' => 0,
                                ]);

                                $messages[] = [
                                    'product_id' => (int)$item['product_id'],
                                    'variety_id' => (int)$item['variety_id'],
                                    'message' => trans('messages.this_product_removed_from_your_cart'),
                                    'status' => 'remove',
                                ];
                            } else {
                                CartItem::create([
                                    'user_id' => $user_id,
                                    'product_id' => $item['product_id'],
                                    'variety_id' => $item['variety_id'],
                                    'count' => $item['count'],
                                ]);
                            }
                        }
                    }
                }
            }

            $cartItems = CartItem::where('user_id', $user_id)
                ->with(['product.size_unit', 'variety.images', 'variety.color'])
                ->get();

            // Remove items marked for deletion:
            foreach ($messages as $message) {
                if ($message['status'] == 'remove') {
                    CartItem::where('user_id', $user_id)
                        ->where('product_id', $message['product_id'])
                        ->where('variety_id', $message['variety_id'])
                        ->delete();
                }
            }

            $addresses = Address::where('user_id', $user_id)->with('city.province')->get();

            return response()->json([
                'cart_items' => $cartItems,
                'working_hours' => WorkingHour::orderBy('order')->get(),
                'messages' => $messages,
                'provinces' => $provinces,
                'cities' => $cities,
                'addresses' => $addresses,
                'limited_amount_payable_by_gateway' => $options['limited_amount_payable_by_gateway']->option_value,
                'cart_duration_validity' => $options['cart_duration_validity']->option_value ?? null
            ]);
        } else {
            $varietyIds = collect($request->items)->pluck('variety_id')->toArray();
            $productIds = collect($request->items)->pluck('product_id')->toArray();
            // Fetch all relevant varieties with their products and images
            $varieties = Variety::with(['images', 'color'])->whereIn('id', $varietyIds)->get();
            $products = Product::with(['size_unit'])->whereIn('id', $productIds)->get();

            $cartItems = [];
            $messages = [];

            if ($request->items) {
                foreach ($request->items as $item) {
                    $variety = $varieties->firstWhere('id', $item['variety_id']);
                    $product = $products->firstWhere('id', $item['product_id']);

                    if ($variety && $variety->count >= $item['count']) {
                        $cartItems[] = [
                            'product_id' => (int)$item['product_id'],
                            'product' => $product,
                            'variety_id' => (int)$item['variety_id'],
                            'variety' => $variety,
                            'count' => (int)$item['count'],
                        ];
                    } else if ($variety->count > 0 && $variety->count < $item['count']) {
                        $cartItems[] = [
                            'product_id' => (int)$item['product_id'],
                            'product' => $product,
                            'variety_id' => (int)$item['variety_id'],
                            'variety' => $variety,
                            'count' => $variety->count,
                        ];

                        $messages[] = [
                            'product_id' => (int)$item['product_id'],
                            'variety_id' => (int)$item['variety_id'],
                            'message' => "موجودی این محصول از {$item['count']} عدد به {$variety->count} عدد کاهش یافت",
                            'status' => 'reduce',
                        ];
                    } else if ($variety->count == 0) {
                        $cartItems[] = [
                            'product_id' => (int)$item['product_id'],
                            'product' => $product,
                            'variety_id' => (int)$item['variety_id'],
                            'variety' => $variety,
                            'count' => 0,
                        ];

                        $messages[] = [
                            'product_id' => (int)$item['product_id'],
                            'variety_id' => (int)$item['variety_id'],
                            'message' => trans('messages.this_product_removed_from_your_cart'),
                            'status' => 'remove',
                        ];
                    }
                }
            }

            return response()->json([
                'cart_items' => $cartItems,
                'working_hours' => WorkingHour::orderBy('order')->get(),
                'messages' => $messages,
                'provinces' => $provinces,
                'cities' => $cities,
                'limited_amount_payable_by_gateway' => $options['limited_amount_payable_by_gateway']->option_value,
                'cart_duration_validity' => $options['cart_duration_validity']->option_value ?? null
            ]);
        }
    }

    public function add(UpdateCartQuantityRequest $request)
    {
        $user_id = Auth::guard('user-api')->id();

        $cartItem = CartItem::where('user_id', Auth::guard('user-api')->id())
            ->where('product_id', $request->product_id)
            ->where('variety_id', $request->variety_id)
            ->first();

        if ($cartItem) {
            $cartItem->increment('count');
        } else {
            CartItem::create([
                'user_id' => $user_id,
                'product_id' => $request->product_id,
                'variety_id' => $request->variety_id,
                'count' => 1,
            ]);
        }

        $cartItem = CartItem::where('user_id', $user_id)
            ->with(['product.size_unit', 'variety.images', 'variety.color'])
            ->get();

        return response()->json($cartItem);
    }

    public function subtract(UpdateCartQuantityRequest $request)
    {
        $user_id = Auth::guard('user-api')->id();

        $cartItem = CartItem::where('user_id', $user_id)
            ->where('product_id', $request->product_id)
            ->where('variety_id', $request->variety_id)
            ->first();

        if ($cartItem) {
            // Ensure the count doesn't go below zero
            $newCount = max(0, $cartItem->count - 1);
            $cartItem->count = $newCount;
            $cartItem->save();

            if ($newCount === 0) {
                // Remove the cart item if count becomes zero
                $cartItem->delete();
            }
        }

        $cartItems = CartItem::where('user_id', $user_id)
            ->with(['product.size_unit', 'variety.images', 'variety.color'])
            ->get();

        return response()->json($cartItems);
    }

    public function remove(UpdateCartQuantityRequest $request)
    {
        $user_id = Auth::guard('user-api')->id();

        CartItem::where('user_id', $user_id)
            ->where('product_id', $request->product_id)
            ->where('variety_id', $request->variety_id)
            ->delete();

        $cartItems = CartItem::where('user_id', $user_id)
            ->with(['product.size_unit', 'variety.images', 'variety.color'])
            ->get();

        return response()->json($cartItems);
    }

    public function shoppingCosts()
    {
        return response()->json([
            'cart_duration_validity' => $option->option_value ?? null,
        ]);
    }

    public function pay(InvoiceRequest $request)
    {
        if (!isStoreOpenNow()) {
            return response()->json([
                'error' => 'با توجه به بسته بودن بازار امکان ثبت سفارش در این لحظه وجود ندارد.'
            ], 422);
        }

        $cartItems = $request->input('items');
        $received_market_price = $request->input('market_price');

        // ۱. بررسی مدت اعتبار قیمت بازار
        $settings = Setting::whereIn('option_key', ['cart_duration_validity', 'payment_deadline', 'value_added_tax', 'limited_amount_payable_by_gateway'])
            ->pluck('option_value', 'option_key');

        $cartDurationValidityPlusSeconds = (int)($settings['cart_duration_validity'] ?? 300);

        $database_market_price = MarketPrice::where('id', $received_market_price['id'])
            ->where('price', $received_market_price['price'])
            ->where('created_at', '>', Carbon::now()->subSeconds($cartDurationValidityPlusSeconds))
            ->first();

        if (!$database_market_price) {
            return response()->json([
                'error' => 'مدت زمان اعتبار قیمت‌ها به پایان رسید، لطفا مجدداً تلاش فرمایید.'
            ], 422);
        }

        // ۲. محاسبه هزینه ارسال با توجه به Request پاک‌سازی شده
        $shipping_cost = 0;
        $shippingMethod = null;

        if ($request->filled('delivery_method')) {
            $cityShippingMethod = CityShippingMethod::where('shipping_method_id', $request->delivery_method)
                ->where('city_id', $request->city_id)
                ->with('shippingMethod')
                ->first();

            if (!$cityShippingMethod || $cityShippingMethod->status === 'inactive') {
                return response()->json([
                    'error' => 'این روش ارسال موقتا غیر فعال شده است، لطفا روش‌ ارسال دیگری را انتخاب کنید.'
                ], 422);
            }

            $shippingMethod = $cityShippingMethod->shippingMethod;
            $shipping_cost = $cityShippingMethod->shipping_cost;
        }

        $vatValue = $settings['value_added_tax'] ?? 0;

        // ۳. بررسی همخوانی قیمت پرداختی
        $calculatedPayable = CartService::payable($cartItems, $received_market_price, $shipping_cost, $vatValue);
        $purchasePrice = CartService::purchase($cartItems, $database_market_price);

        if ($calculatedPayable !== (int)$request->payable) {
            $diff = abs($calculatedPayable - (int)$request->payable);

            try {
                Log::info('Price difference detected', [
                    'diff' => $diff,
                    'request_payable' => (int)$request->payable,
                    'calculated_payable' => $calculatedPayable,
                    'cart_items' => $cartItems,
                    'received_market_price' => $received_market_price,
                    'shipping_cost' => $shipping_cost,
                    'database_market_price' => $database_market_price,
                ]);
            } catch (\Exception $e) {
                // نادیده گرفتن خطای لاگین برای جلوگیری از شکست فرآیند پرداخت
            }

            return response()->json([
                'error' => 'مبلغ پرداختی با مبلغ سفارش همخوانی ندارد. در صورتی که مجدد به این خطا برخوردید با پشتیبانی تماس بگیرید.',
            ], 422);
        }

        // ۴. واکشی دسته‌جمعی محصولات و تنوع‌ها (Eager Loading) برای جلوگیری از مشکل N+1 Query
        $productIds = collect($cartItems)->pluck('product_id')->unique()->toArray();
        $varietyIds = collect($cartItems)->pluck('variety_id')->unique()->toArray();

        $products = Product::with(['size_unit', 'properties'])->whereIn('id', $productIds)->get()->keyBy('id');

        $azkiItems = [];

        try {
            DB::beginTransaction();

            $user = Auth::guard('user-api')->user();
            $payment_deadline = $settings['payment_deadline'] ?? 900;

            // ساخت سفارش پایه
            $order = new Order();
            $order->code = CartService::generateUniqueCode();
            $order->user_id = $user->id;
            $order->status = OrderStatus::WAIT_PAYMENT;
            $order->city_id = $request->city_id;
            $order->purchase = $purchasePrice;
            $order->sale = (int)$request->payable - $shipping_cost;
            $order->shipping_cost = (int)$shipping_cost;
            $order->preferred_shipping_method = $shippingMethod;
            $order->address = $request->input('address');
            $order->market = (int)$received_market_price['price'];
            $order->melted = null;
            $order->payment_deadline = Carbon::now()->addSeconds((int)$payment_deadline);
            $order->save();

            foreach ($cartItems as $item) {
                $product = $products->get($item['product_id']);

                // استفاده از lockForUpdate جهت جلوگیری از Race Condition در ثبت موجودی کالا
                $variety = Variety::with(['color', 'images'])
                    ->lockForUpdate()
                    ->find($item['variety_id']);

                if (!$product || !$variety) {
                    throw new \Exception("کالا یا تنوع انتخاب شده یافت نشد.");
                }

                // بررسی نهایی موجودی واقعی در پایگاه‌داده تحت Lock
                if ($variety->count < (int)$item['count']) {
                    throw new \Exception("موجودی کالا به پایان رسیده یا کافی نیست: {$product->title}");
                }

                VarietyLogService::make($variety, (string)($variety->count - (int)$item['count']), ['order_id' => (string)$order->id]);

                // کسر موجودی و ذخیره
                $variety->count -= (int)$item['count'];
                $variety->save();

                $buyPrice = ProductPriceService::purchasePrice($variety, $database_market_price) * (int)$item['count'];
                $priceWithDiscount = ProductPriceService::priceWithDiscount($variety, $database_market_price, $product->vat, $vatValue) * (int)$item['count'];
                $priceWithoutDiscount = ProductPriceService::priceWithoutDiscount($variety, $received_market_price, $product->vat, $vatValue) * (int)$item['count'];

                $discount = 0.0;
                if ($priceWithoutDiscount > 0) {
                    $discount = (($priceWithoutDiscount - $priceWithDiscount) / $priceWithoutDiscount) * 100;
                    $discount = (float)number_format($discount, 1, '.', '');
                }

                $azkiItems[] = [
                    "name" => $product->title,
                    "count" => (int)$item['count'],
                    "amount" => $priceWithDiscount / 10, // تبدیل به تومان/ریال بر اساس نیاز درایور
                    "url" => "https://zhikgold.ir/products/{$product->id}"
                ];

                $object = OrderService::getCollectProduct($product, $variety, $priceWithDiscount, $priceWithoutDiscount, $discount, $buyPrice);

                $order->items()->create([
                    'product_id' => $product->id,
                    'variety_id' => $variety->id,
                    'product' => $object,
                    'count' => $item['count']
                ]);
            }

            // حذف فیزیکی آیتم‌های سبد خرید کاربر
            CartItem::where('user_id', $user->id)->delete();

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json(['error' => $exception->getMessage()], 422);
        }

        // ۵. شروع فرآیند پرداخت و تراکنش بانکی (خارج از قفل‌های سنگین دیتابیس)
        try {
            $user = Auth::guard('user-api')->user();
            $payable = $order->sale + $order->shipping_cost;

            $limitValue = (int)$settings['limited_amount_payable_by_gateway'] ?? 500000000;

            if ($payable > $limitValue) {
                $payable = $limitValue;
            }

            $driver = $request->input('driver', 'jibit');
            $paymentGateway = PaymentGateway::where('driver', $driver)->firstOrFail();

            $invoice = new Invoice();
            $invoice->amount($payable);

            if ($driver === 'jibit') {

                $jibit = new JibitPaymentService(
                    apiKey: $paymentGateway->merchant_id,
                    secretKey: $paymentGateway->key,
                );

                // clientReferenceNumber باید یکتا باشد؛ از شماره سفارش + timestamp استفاده می‌کنیم
                $clientReferenceId = "zhikgold-{$order->tracking_code}-" . now()->timestamp;

                $additional = [];

                if ($paymentGateway->owner_card_payment) {
                    $additional = [
                        'payerMobileNumber' => '0' . ltrim($user->phone, '0'),
                        'checkPayerMobileNumber' => true,
                    ];

                    if (filled($user->national_code)) {
                        $additional['checkPayerNationalCode'] = true;
                        $additional['nationalCode'] = $user->national_code;
                    }
                }

                $result = $jibit->createPurchase(
                    amount: $payable,
                    callbackUrl: route('jibit.payment.callback'),
                    clientReferenceId: $clientReferenceId,
                    additional: $additional,
                );

                Transaction::create([
                    'order_id' => $order->id,
                    'payment_gateway_id' => $paymentGateway->id,
                    'purchase_id' => $result['purchaseId'],
                    'client_reference_id' => $clientReferenceId,
                    'amount' => $payable,
                ]);

                $paymentURL = ['action' => $result['pspSwitchingUrl']];
            } elseif ($driver === 'azki') {
                $invoice->detail([
                    'mobile' => $user->phone,
                    'items' => $azkiItems
                ]);

                $paymentURL = Payment::via($driver)
                    ->config([
                        'merchantId' => $paymentGateway->merchant_id,
                        'key' => $paymentGateway->key,
                    ])
                    ->callbackUrl('https://api.zhikgold.ir/checkout/azki/confirmation')
                    ->purchase(
                        $invoice,
                        function ($driver, $purchase_id) use ($payable, $paymentGateway, $order) {
                            $transaction = new Transaction();
                            $transaction->order_id = $order->id;
                            $transaction->payment_gateway_id = $paymentGateway->id;
                            $transaction->purchase_id = $purchase_id;
                            $transaction->amount = $payable;
                            $transaction->save();
                        }
                    )
                    ->pay()->toJson();
            } else {
                throw new \Exception("درگاه پرداخت پشتیبانی نمی‌شود.");
            }

            return $paymentURL;

        } catch (\Exception $exception) {
            // 1) همیشه خطای اصلی درگاه را ثبت کن — حتی اگر بازیابی موجودی هم نباشد.
            Log::error('Payment gateway request failed', [
                'order_id' => $order?->id,
                'gateway_error' => $exception->getMessage(),
                'gateway_code' => $exception->getCode(),
                'exception_class' => get_class($exception),
                'trace' => $exception->getTraceAsString(),
            ]);

            if ($order) {
                try {
                    $result = DB::transaction(function () use ($order) {
                        $order->refresh();

                        // جلوگیری از اجرای دوباره‌ی لگ بازیابی.
                        if ($order->status === OrderStatus::CANCELED) {
                            return 'skipped_already_canceled';
                        }

                        $order->loadMissing('items');

                        foreach ($order->items as $item) {
                            $variety = Variety::lockForUpdate()->find($item->variety_id);

                            if (!$variety) {
                                // محصول ممکن است حذف شده باشد؛ فقط هشدار بده و ادامه بده.
                                Log::warning('Variety not found during stock restore; skipped', [
                                    'order_id' => $order->id,
                                    'variety_id' => $item->variety_id,
                                ]);
                                continue;
                            }

                            $oldCount = (int)$variety->count;
                            $newCount = $oldCount + (int)$item->count;

                            $varietyLog = new VarietyLog();
                            $varietyLog->variety_id = $variety->id;
                            $varietyLog->old_values = ['count' => (string)$oldCount];
                            $varietyLog->new_values = ['count' => (string)$newCount];
                            $varietyLog->details = [
                                'order_id' => (string)$order->id,
                                'action' => 'payment_failed_stock_restore',
                            ];
                            $varietyLog->save();

                            $variety->count = $newCount;
                            $variety->save();
                        }

                        $orderLog = OrderLog::make([
                            'order_id' => $order->id,
                            'new_values' => ['status' => OrderStatus::CANCELED],
                            'old_values' => ['status' => $order->status],
                        ]);

                        $orderLog->save();

                        $order->status = OrderStatus::CANCELED;
                        $order->cancellation_reason = 'خطا در ارتباط با درگاه پرداخت';
                        $order->save();

                        return 'restored';
                    });

                    // 2) نتیجه‌ی تلاش بازیابی را همیشه گزارش کن.
                    Log::info('Order stock restore after payment failure handled', [
                        'order_id' => $order->id,
                        'result' => $result,
                    ]);
                } catch (\Exception $rollbackException) {
                    // 3) شکست کامل بازیابی — بحرانی، با تمام جزئیات.
                    Log::critical('Failed to restore stock after payment gateway error', [
                        'order_id' => $order->id,
                        'gateway_error' => $exception->getMessage(),
                        'rollback_error' => $rollbackException->getMessage(),
                        'rollback_class' => get_class($rollbackException),
                        'trace' => $rollbackException->getTraceAsString(),
                    ]);
                }
            }

            return response()->json([
                'error' => 'خطا در ارتباط با درگاه پرداخت: ' . $exception->getMessage(),
            ], 400);
        }

    }

    public function azkiConfirmation()
    {
        $transaction = Transaction::where('purchase_id', request()->ticketId)->first();
        $order = Order::find($transaction->order_id);

        try {
            if ($order->status === OrderStatus::CANCELED) {
                Log::error('Order is already canceled', $order->id);
                throw new InvalidPaymentException('ORDER_IS_ALREADY_CANCELED');
            }

            $order->load('items');

            if (request()->status == 'Done') {
                DB::transaction(function () use ($transaction, $order) {
                    $order->status = OrderStatus::PAID;
                    $order->paid_at = Carbon::now();
                    $order->total_paid = (int)$order->sale + (int)$order->shipping_cost;
                    $order->save();

                    $paymentGateway = PaymentGateway::where('driver', 'azki')->firstOrFail();

                    $receipt = Payment::config([
                        'merchantId' => $paymentGateway->merchant_id,
                        'key' => $paymentGateway->key,
                    ])
                        ->via('azki')
                        ->amount($order->total_paid)
                        ->transactionId(request()->ticketId)
                        ->verify();

                    $transaction->update([
                        'status' => 'SUCCESSFUL',
                        'details' => [
                            'reference_id' => $receipt->getReferenceId(),
                        ]
                    ]);
                });

                try {
                    $this->placeOrder($order);
                } catch (\Exception $exception) {
                    Log::error('Error placing order: ' . $exception->getMessage());
                }

                try {
                    $this->notifyOrderEvent($order);
                } catch (\Exception $exception) {
                    Log::error('Error notify order: ' . $exception->getMessage());
                }

            } else if (request()->status === 'Failed') {
                throw new InvalidPaymentException('ORDER_PAYMENT_FAILED');
            }

            return redirect("https://zhikgold.ir/checkout/confirmation/" . request()->ticketId);
        } catch (InvalidPaymentException $exception) {

            Log::error('AZKI Confirmation has an error: ' . $exception->getMessage());

            $transaction->update([
                'status' => 'FAILED',
                'details' => [
                    'fail_reason' => $exception->getMessage(),
                ]
            ]);

            $orderData = [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'user_id' => $order->user_id,
                'sale' => $order->sale,
                'purchase' => $order->purchase,
                'preferred_shipping_method' => $order->preferred_shipping_method,
                'user' => $order->user
            ];

            try {
                event(new OrderCreated($orderData));
            } catch (\Exception $exception) {
                Log::error('Socket error on Azki order confirmation: ' . $exception->getMessage());
            }

            return redirect("https://zhikgold.ir/checkout/confirmation/" . request()->ticketId);
        }
    }

    public function confirmation()
    {
        $transaction = Transaction::where('purchase_id', request()->purchaseId)->first();
        $order = Order::findOrFail($transaction->order_id);

        try {
            if (request()->status === 'FAILED') {
                throw new InvalidPaymentException(request()->failReason);
            }

            if ($order->status === OrderStatus::CANCELED) {
                Log::error('Order is already canceled: ' . $order->id);
                throw new InvalidPaymentException('ORDER_IS_ALREADY_CANCELED');
            }

            DB::transaction(function () use ($transaction, $order) {
                $transaction->paid_at = now();
                $order->load('items');

                $totalPaid = (int)request()->amount / 10;
                $limitedAmountPayableByGateway = Setting::where('option_key', 'limited_amount_payable_by_gateway')->first();

                if ($totalPaid == $limitedAmountPayableByGateway->option_value && $totalPaid < ((int)$order->sale + (int)$order->shipping_cost)) {
                    $order->status = OrderStatus::RESERVED;
                } else {
                    $order->status = OrderStatus::PAID;
                }

                $paymentGateway = PaymentGateway::where('driver', 'jibit')->firstOrFail();

                $receipt = Payment::config([
                    'merchantId' => $paymentGateway->merchant_id,
                    'key' => $paymentGateway->key,
                ])
                    ->via('jibit')
                    ->amount(request()->amount)
                    ->transactionId(request()->purchaseId)
                    ->verify();

                $order->paid_at = Carbon::now();
                $order->total_paid = $totalPaid;
                $order->save();

                $transaction->update([
                    'status' => 'SUCCESSFUL',
                    'details' => [
                        'wage' => request()->wage,
                        'fee' => request()->fee,
                        'shaparak_fee' => request()->shaparakFee,
                        'reference_id' => $receipt->getReferenceId(),
                        'net_amount' => request()->netAmount,
                        'currency' => request()->currency,
                        'client_reference_number' => request()->clientReferenceNumber,
                        'payer_masked_card_number' => request()->payerMaskedCardNumber,
                        'psp_reference_number' => request()->pspReferenceNumber,
                        'psp_rrn' => request()->pspRRN,
                        'psp_hashed_card_number' => request()->payerMaskedCardNumber,
                        'psp_name' => request()->pspHashedCardNumber,
                        'payer_ip' => request()->payerIp,
                        'fail_reason' => request()->failReason,
                    ]
                ]);
            });

            try {
                $this->placeOrder($order);
            } catch (\Exception $exception) {
                Log::error('Error placing order: ' . $exception->getMessage());
            }

            try {
                $this->notifyOrderEvent($order);
            } catch (\Exception $exception) {
                Log::error('Error notify order: ' . $exception->getMessage());
            }

            return redirect("https://zhikgold.ir/checkout/confirmation/" . request()->purchaseId);
        } catch (InvalidPaymentException $exception) {

            Log::info('InvalidPaymentException caught: CartController', [
                'exception_class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
                'previous' => $exception->getPrevious()?->getMessage(),
            ]);

            $transaction->update([
                'status' => 'FAILED',
                'details' => [
                    'wage' => request()->wage,
                    'fee' => request()->fee,
                    'shaparak_fee' => request()->shaparakFee,
                    'net_amount' => request()->netAmount,
                    'currency' => request()->currency,
                    'client_reference_number' => request()->clientReferenceNumber,
                    'payer_masked_card_number' => request()->payerMaskedCardNumber,
                    'psp_reference_number' => request()->pspReferenceNumber,
                    'psp_rrn' => request()->pspRRN,
                    'psp_hashed_card_number' => request()->payerMaskedCardNumber,
                    'psp_name' => request()->pspHashedCardNumber,
                    'payer_ip' => request()->payerIp,
                    'fail_reason' => request()->failReason,
                ]
            ]);

            $orderData = [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'user_id' => $order->user_id,
                'sale' => $order->sale,
                'purchase' => $order->purchase,
                'preferred_shipping_method' => $order->preferred_shipping_method,
                'user' => $order->user
            ];

            try {
                event(new OrderCreated($orderData));
            } catch (\Exception $exception) {
                Log::error('Socket error on Jibit order confirmation: ' . $exception->getMessage());
            }

            return redirect("https://zhikgold.ir/checkout/confirmation/" . request()->purchaseId);
        }
    }

    /**
     * @throws \Exception
     */
    private function notifyOrderEvent(Order $order): void
    {
        try {
            $this->notifyToOperators($order);

            $orderData = [
                'id' => $order->id,
                'code' => $order->code,
                'status' => $order->status,
                'user_id' => $order->user_id,
                'sale' => $order->sale,
                'purchase' => $order->purchase,
                'preferred_shipping_method' => $order->preferred_shipping_method,
                'user' => $order->user
            ];

            event(new OrderCreated($orderData));
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function notifyToOperators($order)
    {
        $orderWeight = $this->getOrderTotalWeight($order->items);
        $notificationSetting = Setting::where('option_key', 'send_notification_message_when_order_stored')->first();
        $notificationSetting->option_value = json_decode($notificationSetting->option_value);
        if ($notificationSetting->option_value->status == 'active') {
            foreach ($notificationSetting->option_value->phone_numbers as $number) {
                if ($number->status === 'active') {
                    SmsJob::dispatch(
                        $number->phone,
                        "gds9xhehg7qf6rs",
                        [
                            'weight' => "$orderWeight گرم"
                        ]
                    )->onConnection('sync');
                }
            }
        }
    }

    private function getOrderTotalWeight($items): string
    {
        $weight = 0;

        foreach ($items as $item) {
            $weight += $item->product['weight'] * $item->count;
        }

        return englishToPersianNumbers($weight);
    }

    public function check($purchase_id)
    {
        $transaction = Transaction::with('order')->where('purchase_id', $purchase_id)->first();

        if (!$transaction || (!$transaction->order)) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        if ($transaction->order->status !== OrderStatus::PAID) {
            return response()->json([
                'order_id' => $transaction->order->code,
                'status' => 'failed',
            ]);
        }

        return response()->json([
            'order_id' => $transaction->order->code,
            'status' => 'success',
        ]);
    }

    public function placeOrder(Order $order): void
    {
        try {
            // Get the latest price
            $setting = Cache::rememberForever(
                'setting.default_metal_trader_group_id',
                fn() => Setting::firstWhere('option_key', 'default_metal_trader_group_id')
            );

            $latestMetalPrice = SelectedMetalPrice::where('metal_item_id', $setting->option_value)
                ->with('metalItem')->latest()->first();

            if (!$latestMetalPrice) {
                throw new \Exception('CartController placeOrder: Latest price not found.');
            }

            $price = $minPartOfMetal = $latestMetalPrice->sell ?? $latestMetalPrice->buy;

            if ($latestMetalPrice->metalItem->unit == 'gram') {
                $minPartOfMetal = round($price / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);
            }

            $quantity = round((int)$order->sale / $minPartOfMetal, 3);

            if ($latestMetalPrice->metalItem->unit == 'count' && hasRealDecimal($quantity)) {
                throw new \Exception('CartController placeOrder: Order submission is not possible because the entered quantity contains decimals, and fractional quantities cannot be accepted due to the nature of the metal.');
            }

            DB::transaction(function () use ($minPartOfMetal, $price, $order, $latestMetalPrice, $quantity) {
                $order->update(['melted' => $price]);

                $amount = round($quantity * $minPartOfMetal);

                MetalOrder::create([
                    'tracking_code' => MetalOrderService::generateTrackingCode(),
                    'product' => [
                        'name' => $latestMetalPrice->metalItem->title,
                        'en_unit' => 'gram',
                        'metal_item_id' => $latestMetalPrice->metalItem->id,
                        'kimia_product_id' => $latestMetalPrice->metalItem->kimia_product_id,
                        'fee' => $price,
                        'fee_margin' => '0',
                        'unit' => $latestMetalPrice->metalItem->unit == 'gram' ? 'گرم' : 'عدد',
                        'quantity' => $quantity,
                        'tolerance_type' => 'fixed_amount',
                        'display_mode' => 'quotation',
                        'amount' => $amount,
                    ],
                    'extra_data' => [
                        'frozen' => 'metal',
                    ],
                    'order_type' => 'buy',
                    'created_id' => $order->id,
                    'created_type' => (new Order())->getMorphClass(),
                    'status' => 'pending',
                ]);

                OrderService::calculateOrderProfitLoss($order);
            });
        } catch (\Exception $exception) {
            Log::error("❌ Order $order->id failed: " . $exception->getMessage(), [
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function cartDurationValidity()
    {
        $cartDurationValidity = Setting::where('option_key', 'cart_duration_validity')->first();
        return $cartDurationValidity->option_value;
    }
}
