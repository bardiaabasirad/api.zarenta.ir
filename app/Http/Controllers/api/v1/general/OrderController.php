<?php

namespace App\Http\Controllers\api\v1\general;

use App\Enums\OrderStatus;
use App\Events\OrderUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\RepaymentRequest;
use App\Models\MarketPrice;
use App\Models\Order;
use App\Models\OrderLog;
use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\VarietyLog;
use App\Models\WorkingHour;
use App\Services\CartService;
use App\Services\JibitPaymentService;
use App\Services\ProductPriceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Mockery\Exception;
use Shetabit\Multipay\Invoice;
use Shetabit\Payment\Facade\Payment;

class OrderController extends Controller
{
    public function index()
    {
        $orders = Order::latest()->where('user_id', Auth::guard('user-api')->id())
            ->with('items')
            ->paginate(6);
        return response()->json($orders);
    }

    public function show($code)
    {
        $order = Order::where('code', $code)->with('items')->first();
        Gate::authorize('view', $order);

        // Retrieve the JSON data from the database and decode
        $data = json_decode(Setting::where('option_key', 'working_hours')->first()->option_value, true);
        // Sort the array by the "start" key in ascending order
        usort($data, function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        return response()->json([
            'order' => $order,
            'transactions' => Transaction::latest()->with('gateway')->where('order_id', $order->id)->get(),
            'store_opening_times' => $data,
            'working_hours' => WorkingHour::orderBy('order')->get(),
            'cart_duration_validity' => Setting::where('option_key', 'cart_duration_validity')->first()->option_value,
            'limited_amount_payable_by_gateway' => Setting::where('option_key', 'limited_amount_payable_by_gateway')->first()->option_value,
            'last_change' => OrderLog::where('order_id', $order->id)->orderBy('created_at', 'desc')->first(),
        ]);
    }

    public function cancel(Order $order)
    {
        Gate::authorize('delete', $order);

        $orderLog = OrderLog::make([
            'order_id' => $order->id,
            'new_values' => ['status' => OrderStatus::CANCELED],
            'old_values' => ['status' => $order->status],
        ]);

        $orderLog->save();

        // Retrieve the order items
        $orderItems = $order->items;
        // Iterate over each order item and increment the variety count
        foreach ($orderItems as $orderItem) {
            $variety = $orderItem->variety;

            $varietyLog = new VarietyLog();
            $varietyLog->variety_id = $variety->id;
            $varietyLog->old_values = ['count' => (string) $variety->count];
            $varietyLog->new_values = ['count' => (string) ($variety->count + (int) $orderItem->count)];
            $varietyLog->details = ['order_id' => (string) $order->id];
            $varietyLog->save();

            $variety->increment('count', $orderItem->count);
        }

        $order->status = OrderStatus::CANCELED;
        $order->cancellation_reason = 'پایان یافتن مهلت پرداخت';
        $order->save();

        $order->load('items');

        $orderData = [
            'id' => $order->id,
            'code' => $order->code,
            'status' => OrderStatus::CANCELED,
            'user_id' => $order->user_id,
            'sale' => $order->sale,
            'purchase' => $order->purchase,
            'preferred_shipping_method' => $order->preferred_shipping_method,
            'user' => $order->user
        ];

        event(new OrderUpdated($orderData));

        return response()->json([
            'order' => $order,
            'last_change' => OrderLog::where('order_id', $order->id)->orderBy('created_at', 'desc')->first(),
        ]);
    }

    public function repayment(RepaymentRequest $request, Order $order)
    {
        Gate::authorize('update', $order);

        if (! isStoreOpenNow()){
            return response()->json(['error' => 'با توجه به بسته بودن بازار امکان ثبت سفارش در این لحظه وجود ندارد.'], 422);
        }

        $market = (object) $request->validated('market');

        $settings = Setting::whereIn('option_key', ['cart_duration_validity', 'payment_deadline', 'value_added_tax', 'limited_amount_payable_by_gateway'])
            ->pluck('option_value', 'option_key');

        $cartDurationValidity = $settings['cart_duration_validity'] ?? 300;
        $limitedAmountPayableByGateway = $settings['limited_amount_payable_by_gateway'] ?? 500000000;
        $cartDurationValidityPlusSeconds = (int) $cartDurationValidity;

        $marketPrice = MarketPrice::where('id', $market->id)
            ->where('price', $market->price)
            ->where('read_at', '>', \Carbon\Carbon::now()->subSeconds($cartDurationValidityPlusSeconds))->first();

        if (! $marketPrice) {
            return response()->json(['error' => 'مدت زمان اعتبار قیمت‌ها به پایان رسید، لطفا مجدد تلاش کنید.'], 422);
        }

        $order->load('items');
        $vat = $settings['value_added_tax'] ?? 0;

        // محاسبه مجدد مبلغ قابل پرداخت سبد خرید
        $calculatedPayable = CartService::payable($order->items->toArray(), $marketPrice, $order->shipping_cost, $vat);
        $purchasePrice = CartService::purchase($order->items->toArray(), $marketPrice);

        if ($calculatedPayable != (int) $request->payable) {
            $diff = abs($calculatedPayable - (int) $request->payable);

            try {
                Log::info('Repayment Price difference', [
                    'diff' => $diff,
                    'request_payable' => (int) $request->payable,
                    'calculated_payable' => $calculatedPayable,
                    'cart_items' => $order->items->toArray(),
                    'received_market_price' => $marketPrice,
                    'shipping_cost' => $order->shipping_cost,
                    'vat_option_value' => $vat,
                    'database_market_price' => $marketPrice,
                ]);
            } catch (Exception $exception){

            }

            return response()->json([
                'error' => 'مبلغ پرداختی با مبلغ سفارش همخوانی ندارد. در صورتی که مجدد به این خطا برخوردید با پشتیبانی تماس بگیرید.',
            ], 422);
        }

        // اگر قیمت درست بود باید قیمت سفارش و آیتم‌های آن بروز شود
        // خواندن مهلت پرداخت از جدول تنظیمات، باید مهلت پرداخت سفارش رفرش شود
        $payment_deadline = $settings['payment_deadline'] ?? 900;

        // بروزرسانی سفارش با مبلغ جدید محاسبه شده
        $order->purchase = $purchasePrice;
        $order->sale = $request->payable - $order->shipping_cost;
        $order->market = (int) $marketPrice->price;
        $order->payment_deadline = Carbon::now()->addSeconds((int) $payment_deadline);
        $order->save();

        $azkiItems = [];

        // بروزرسانی قیمت آیتم‌های سفارش
        foreach ($order->items as $item) {
            $product = (object) $item->product;
            $variety = (object) $item->variety;

            $buyPrice = ProductPriceService::purchasePrice($product, $marketPrice) * (int) $item['count'];
            $priceWithDiscount = ProductPriceService::priceWithDiscount($variety, $marketPrice, $product->vat, $vat) * (int) $item->count;
            $priceWithoutDiscount = ProductPriceService::priceWithoutDiscount($variety, $marketPrice, $product->vat, $vat) * (int) $item->count;
            $discount = (($priceWithoutDiscount - $priceWithDiscount) / $priceWithoutDiscount) * 100;
            $discount = (float) number_format($discount, 1, '.', '');

            $product->gold_price = $marketPrice->price;
            $product->price_with_discount = $priceWithDiscount;
            $product->price_without_discount = $priceWithDiscount != $priceWithoutDiscount ? $priceWithoutDiscount:null;
            $product->discount = $discount > 0 ? $discount:null;
            $product->total_profit = $priceWithDiscount - $buyPrice;

            $item->product = $product;
            $item->save();

            $azkiItems[] = [
                "name" => $product->title,
                "count" => $item['count'],
                "amount" => $priceWithDiscount,
                "url" => "https://zhikgold.ir/products/$product->id"
            ];
        }

        // سفارش به درگاه پرداخت هدایت شود

        $payable = $totalToPay = $order->sale + $order->shipping_cost;

        if ($totalToPay > $limitedAmountPayableByGateway){
            $payable = $limitedAmountPayableByGateway;
        }

        $driver = $request->validated('driver', 'jibit');

        switch ($driver) {
            case 'jibit':
                $paymentGateway = PaymentGateway::where('driver', 'jibit')->firstOrFail();

                $jibit = new JibitPaymentService(
                    apiKey: $paymentGateway->merchant_id,
                    secretKey: $paymentGateway->key,
                );

                // clientReferenceNumber باید یکتا باشد؛ از شماره سفارش + timestamp استفاده می‌کنیم
                $clientReferenceId = "zhikgold-{$order->tracking_code}-" . now()->timestamp;

                $additional = [];

                if ($paymentGateway->owner_card_payment) {
                    $additional = [
                        'payerMobileNumber' => '0' . ltrim($order->user->phone, '0'),
                        'checkPayerMobileNumber' => true,
                    ];

                    if (filled($order->user->national_code)) {
                        $additional['checkPayerNationalCode'] = true;
                        $additional['nationalCode'] = $order->user->national_code;
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

                break;
            case 'azki':
                $paymentGateway = PaymentGateway::where('driver', 'azki')->firstOrFail();

                $user = Auth::guard('user-api')->user();

                $invoice = new Invoice;
                $invoice->amount($payable);
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
                break;
            default:
                $paymentURL = '';
        }

        return $paymentURL;
    }
}
