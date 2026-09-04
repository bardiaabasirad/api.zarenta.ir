<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Events\ClientMessagesUpdated;
use App\Events\SettingsChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingRequest;
use App\Http\Resources\SMSSettingsResource;
use App\Jobs\SmsJob;
use App\Models\Contact;
use App\Models\MetalItem;
use App\Models\MetalItemAutoOrderSetting;
use App\Models\MetalOrder;
use App\Models\MetalOrderRejectionReason;
use App\Models\MetalTrader;
use App\Models\PriceSource;
use App\Models\Setting;
use App\Services\SelectedMetalPriceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    public function index()
    {
        return response()->json(Setting::whereIn('option_key', [
            'support_center_contact_number',
            'support_contact_number',
            'eitaa_channel',
            'instagram_channel',
            'address',
            'cart_duration_validity',
            'payment_deadline',
            'limited_amount_payable_by_gateway',
            'value_added_tax'
        ])->get());
    }

    public function mazane()
    {
        $latestRawPriceFromSelectedSource = SelectedMetalPriceService::getLatestRawPriceFromSelectedSource();

        // یک کوئری واحد برای دریافت همه تنظیمات
        $allSettings = Setting::whereIn('option_key', [
            'market_status',
            'validity_period_of_melted_order_before_expires',
            'submit_outbound_gold_orders_by_bot',
            'auto_order_dispatch_enabled',
            'manual_order_review_duration_seconds',
            'melted_stock_quantity',
            'min_melted_stock_quantity',
            'max_melted_stock_quantity'
        ])->get()->keyBy('option_key');

        $generalKeys = [
            'validity_period_of_melted_order_before_expires',
            'manual_order_review_duration_seconds',
            'melted_stock_quantity',
            'min_melted_stock_quantity',
            'max_melted_stock_quantity',
        ];

        $generalSettings = collect($generalKeys)
            ->map(fn ($key) => $allSettings->get($key))
            ->filter() // حذف کلیدهایی که در دیتابیس نبودند (اختیاری)
            ->values();

        $metalOrders = MetalOrder::whereIn('status', ['pending', 'processing'])
            ->with([
                'leverageCheck',
                'creator',
                'metalOrderExchanges.priceSource'
            ])
            ->get();

        $redisKey = 'market_opening_sms:' . now()->format('Y-m-d');
        $isSMSSentToday = Redis::exists($redisKey) ? 'yes' : 'no';

        $metalItems = MetalItem::with(['selectedMetalPrices' => function ($query) {
            $query
                ->with(['priceSource' => function ($query) {
                    $query->select('id', 'name');
                }])
                ->orderBy('time', 'desc')
                ->limit(4);
        }, 'priceSourceMapping'])
            ->join('metal_item_groups', 'metal_items.metal_item_group_id', '=', 'metal_item_groups.id')
            ->orderBy('metal_item_groups.sort_order')
            ->orderBy('metal_items.sort_order')
            ->select('metal_items.id', 'metal_items.title', 'metal_items.is_buy_active', 'metal_items.is_sell_active', 'metal_items.price_change_threshold', 'metal_items.buy_sell_spread')
            ->get()
            ->map(function ($item) {
                $item->setRelation(
                    'selectedMetalPrices',
                    $item->selectedMetalPrices->reverse()->values()
                );
                return $item;
            });

        return response()->json([
            'price_sources' => PriceSource::get(['id', 'name']),
            'metal_item_auto_order_settings' => MetalItemAutoOrderSetting::with(['metalItem','priceSource'])->get(),
            'metal_items' => $metalItems,
            'contacts' => Contact::orderBy('sort_order', 'asc')->get(['id', 'name', 'phone', 'sort_order']),
            'latest_raw_price_from_selected_source' => $latestRawPriceFromSelectedSource,
            'market_status' => $allSettings->get('market_status'),
            'auto_order_dispatch_enabled' => $allSettings->get('auto_order_dispatch_enabled'),
            'validity_period_of_melted_order_before_expires' => $allSettings->get('validity_period_of_melted_order_before_expires')?->option_value,
            'orders' => $metalOrders,
            'cancel_reasons' => MetalOrderRejectionReason::get(),
            'general_settings' => $generalSettings,
            'client_messages' => Redis::get('client:messages'),
            'submit_outbound_gold_orders_by_bot' => $allSettings->get('submit_outbound_gold_orders_by_bot'),
            'is_sms_sent_today' => $isSMSSentToday,
            'now' => Carbon::now()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
        ]);
    }

    public function saveClientMessages(Request $request)
    {
        // اعتبارسنجی ورودی
        $validator = Validator::make($request->all(), [
            'message' => 'nullable|string|max:10000'
        ], [
            'message.string' => 'متن پیام باید رشته باشد',
            'message.max' => 'متن پیام نباید بیش از 10000 کاراکتر باشد'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $messages = trim($request->input('message'));
        Redis::set('client:messages', $messages);

        // Retrieve the stored messages from Redis
        $storedMessages = Redis::get('client:messages');

        // Convert to array, handling empty or null cases
        $messagesArray = ($storedMessages && trim($storedMessages) !== '')
            ? preg_split('/\r\n|\n|\r/', $storedMessages, -1, PREG_SPLIT_NO_EMPTY)
            : [];

        ClientMessagesUpdated::dispatch($messagesArray);
        SettingsChanged::dispatch();

        return response()->json([
            'messages' => $messagesArray,
        ]);
    }

    public function api()
    {
        return response()->json([
            'settings' => Setting::whereIn('option_key', [
                'melted_gold_validity_period',
                'cost_per_shahkar_inquiry',
                'cost_per_similarity_inquiry',
                'cost_per_matching_inquiry',
                'cost_per_matching_inquiry',
                'cost_per_iban_or_card_inquiry',
                'cost_per_iban_from_card_inquiry',
            ])->get(),
        ]);
    }

    public function sms()
    {
        return response()->json([
            'client_sms_settings' => Setting::whereIn('option_key', [
                'send_notification_message_when_order_confirmed',
                'send_notification_message_when_order_ready_for_delivery',
                'send_notification_message_when_order_ready_to_sent',
                'send_notification_message_when_order_has_been_sent',
            ])->get(),
            'admin_sms_settings' => new SMSSettingsResource(Setting::where('option_key', 'send_notification_message_when_order_stored')->first()),
            'melted_order_sms_settings' => new SMSSettingsResource(Setting::where('option_key', 'send_notification_message_when_melted_order_rejected')->first()),
        ]);
    }

    public function update(UpdateSettingRequest $request, Setting $setting)
    {
        $setting->update($request->validated());

        // بررسی تغییر از inactive به active
        if ($setting->option_key === 'market_status' && $request->has('send_sms') && $request->send_sms == 'ok') {
            // بررسی تغییر option_value به active
            if ($setting->option_value == 'active') {
                $this->sendDailySmsNotification();
            }
        }

        $this->notifyChangedValuesToAdminPanel($setting->option_key);

        return response()->json([
            'message' => 'تنظیمات با موفقیت بروزرسانی شد'
        ]);
    }

    private function notifyChangedValuesToAdminPanel($option_key)
    {
        $keysToFireSettingsChangedEvent = [
            'market_status',
            'validity_period_of_melted_order_before_expires'
        ];

        if (in_array($option_key, $keysToFireSettingsChangedEvent)) {
            event(new SettingsChanged());
        }
    }

    protected function sendDailySmsNotification()
    {
        $redisKey = 'market_opening_sms:' . now()->format('Y-m-d');

        try {

            $users = MetalTrader::where('status', 'active')
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->where('market_opening_notification', 'active')
                ->get();

            $phones = $users->pluck('phone')->filter()->values()->toArray();

            foreach ($phones as $phone) {
                SmsJob::dispatch($phone, "k2vtnx7i3s87cb8", []);
            }

            // تنظیم مقدار
            Redis::set($redisKey, '1');

            // تنظیم expire با timestamp
            Redis::expireAt($redisKey, now()->endOfDay()->timestamp);
        } catch (\Exception $e) {
            Log::error('خطا در ارسال پیامک افتتاح بازار: ' . $e->getMessage());
        }
    }

//    public function addReferenceMarket(Request $request)
//    {
//        $referenceMarket = PriceSourceMapping::create([
//            'reference_channel_id' => $request->reference_channel_id,
//            'type' => $request->type,
//            'buy' => $request->buy,
//            'sell' => $request->sell,
//            'buy_from_sell' => $request->buy_from_sell,
//            'sell_from_buy' => $request->sell_from_buy,
//            'generate_buy_or_sell' => $request->generate_buy_or_sell,
//        ]);
//
//        return response()->json([
//            'reference_market' => $referenceMarket->load('referenceChannel')
//        ]);
//    }

//    public function updateReferenceMarket(Request $request, PriceSourceMapping $referenceMarket)
//    {
//        $referenceMarket->update(
//            $request->only(['reference_channel_id', 'buy', 'sell', 'buy_from_sell', 'sell_from_buy', 'generate_buy_or_sell'])
//        );
//
//        return response()->json(['message' => 'بروزرسانی با موفقیت انجام شد']);
//    }

//    /**
//     * @throws \Throwable
//     */
//    public function deleteReferenceMarket(PriceSourceMapping $referenceMarket)
//    {
//        $referenceMarket->delete();
//    }
}
