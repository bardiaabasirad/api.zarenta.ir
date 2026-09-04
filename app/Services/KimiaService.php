<?php

namespace App\Services;

use App\Constants\AppConstants;
use App\Models\MetalItem;
use App\Models\MetalOrderExchange;
use App\Models\MetalTrader;
use App\Models\MetalOrder;
use App\Jobs\UpdateKimiBalanceJob;
use Exception as ExceptionAlias;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KimiaService
{
    /**
     * متد اصلی برای تمام درخواست‌های HTTP به کیمیا
     *
     * @param string $method نوع متد (GET|POST|PUT|DELETE)
     * @param string $endpoint مسیر بعد از /api/
     * @param array $params پارامترهای query یا body
     * @return array|mixed
     * @throws \Illuminate\Http\Client\RequestException|\Illuminate\Http\Client\ConnectionException
     */
    private static function request(string $method, string $endpoint, array $params = [])
    {
        $baseUrl  = config('services.kimia_api.base_url');
        $username = config('services.kimia_api.username');
        $password = config('services.kimia_api.password');

        $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

        // حذف try-catch داخلی برای فرستادن استثنا به لایه‌های بالاتر (مانند Queue Job)
        $response = Http::withBasicAuth($username, $password)
            ->withHeaders([
                'accept' => 'application/json',
            ])
            ->send($method, $url, [
                ($method === 'GET' ? 'query' : 'json') => $params
            ]);

        // اگر وضعیت پاسخ بین 400 یا 500 باشد، خطای RequestException پرتاب می‌شود
        if ($response->failed()) {
            Log::error("Kimia API Request Failed", [
                'url' => $endpoint,
                'method' => $method,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }

        return $response->json();
    }

    // ═══════════════════════════════════════════════════════════════
    // بخش جدید: ثبت سفارش طلا در کیمیا
    // ═══════════════════════════════════════════════════════════════

    /**
     * ثبت سفارش طلا در سیستم کیمیا
     * @param MetalOrder $metalOrder
     * @return array|mixed|null
     */
    public static function submitGoldOrder(MetalOrder $metalOrder): mixed
    {
        if (! App::environment('production')) return null;

        $lockKey = "kimia_submit_order_{$metalOrder->id}";

        // تلاش برای گرفتن لاک 5 ثانیه‌ای
        $lock = Cache::lock($lockKey, 5);

        if (!$lock->get()) {
            Log::info('KimiaService::submitGoldOrder skipped because lock exists', [
                'order_id' => $metalOrder->id,
            ]);
            return null; // یعنی هم‌زمان یه نفر دیگه در حال ثبتش بوده
        }

        try {
            // فقط برای MetalTrader
            if ($metalOrder->created_type !== (new MetalTrader())->getMorphClass()) {
                return null;
            }

            $metalTrader = MetalTrader::find($metalOrder->created_id);

            if (!$metalTrader || !$metalTrader->kimi_account_id) {
                return null;
            }

            $frozen = $metalOrder->extra_data['frozen'];
            $action = $metalOrder->order_type === 'buy' ? 64 : 32;

            if ($metalOrder->product['tolerance_type'] == 'fixed_amount') {
                $fee = (int)$metalOrder->product['fee'] + $metalOrder->product['fee_margin'];
            } else {
                $fee = (int)$metalOrder->product['fee'] + ((int)$metalOrder->product['fee'] * $metalOrder->product['fee_margin'] / 100);
            }

            $kimiaProductId = $metalOrder->product['kimia_product_id'];
            $displayMode = $metalOrder->product['display_mode'];

            if ($metalOrder->extra_data['accounting_document_id']) {
                $kimiaResponse = self::exchangeCurrency(
                    $metalTrader->kimi_account_id,
                    $action,
                    $fee,
                    $metalOrder->product['quantity'],
                    $kimiaProductId,
                    $metalOrder->extra_data['accounting_document_id']
                );
            } else {
                $kimiaResponse = match ($frozen) {
                    'metal' => self::exchangeGold(
                        $metalTrader->kimi_account_id,
                        $action,
                        $fee,
                        $metalOrder->product['quantity'],
                        $kimiaProductId,
                        $displayMode
                    ),
                    'cash' => self::exchangeMoney(
                        $metalTrader->kimi_account_id,
                        $action,
                        $fee,
                        $metalOrder->product['amount'],
                        $kimiaProductId,
                        $displayMode
                    )
                };
            }

            if ($kimiaResponse) {
                UpdateKimiBalanceJob::dispatch($metalTrader);
            }

            return $kimiaResponse;

        } catch (\Throwable $e) {
            Log::error('KimiaService::submitGoldOrder Error', [
                'order_id' => $metalOrder->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     *  ثبت سفارش طلا در سیستم کیمیا در اپ همکار
     * @param MetalOrder $metalOrder
     * @param MetalOrderExchange $metalOrderExchange
     * @return array|mixed|null
     */
    public static function submitHamtalaOrder(MetalOrder $metalOrder, MetalOrderExchange $metalOrderExchange): mixed
    {
        if (! App::environment('production')) return null;

        $lockKey = "kimia_submit_order_{$metalOrderExchange->id}";

        // تلاش برای گرفتن لاک 5 ثانیه‌ای
        $lock = Cache::lock($lockKey, 5);

        if (!$lock->get()) {
            Log::info('KimiaService::submitGoldOrder skipped because lock exists', [
                'metal_order_exchange_id' => $metalOrderExchange->id
            ]);
            return null; // یعنی هم‌زمان یه نفر دیگه در حال ثبتش بوده
        }

        try {
            $action = $metalOrder->order_type === 'buy' ? 32 : 64;
            $kimiaProductId = $metalOrder->product['kimia_product_id'];
            $accountingDocumentId = $metalOrder->extra_data['accounting_document_id'] ?? null;
            $autoOrderQuantity = (float) ($metalOrder->extra_data['auto_order_quantity'] ?? 0);

            if (!empty($accountingDocumentId)) {
                $kimiaResponse = self::exchangeCurrency(
                    5040,
                    $action,
                    $metalOrderExchange->details['request']['mazane'],
                    $autoOrderQuantity,
                    $kimiaProductId,
                    $accountingDocumentId
                );
            } else {
                $kimiaResponse = self::exchangeGold(
                    5040,
                    $action,
                    $metalOrderExchange->details['request']['mazane'],
                    $autoOrderQuantity,
                    $kimiaProductId
                );
            }

            return $kimiaResponse;

        } catch (\Throwable $e) {
            Log::error('KimiaService::submitGoldOrder Error', [
                'order_id' => $metalOrder->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * تبادل طلا (گرمی)
     * @param int $accountId
     * @param int $action
     * @param int $fee
     * @param float $quantity
     * @param string $currencyId
     * @param string $displayMode
     * @return array|mixed|null
     */
    private static function exchangeGold(
        int         $accountId,
        int         $action,
        int         $fee,
        float       $quantity,
        string      $currencyId,
        string      $displayMode = 'quotation'
    ): mixed
    {
        if($displayMode == 'quotation') {
            $fee = $fee * 10;
            $goldUnit = null;
        } else {
            // if action is buy
            if ($action === 64) {
                $fee = roundUpToThousand($fee / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR) * 10;
            } else {
                $fee = roundDownToThousand($fee / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR) * 10;
            }

            $goldUnit = 1;
        }

        $data = [
            'RequestId' => Str::uuid()->toString(),
            'AddToExistingDateVoucher' => false,
            'AccountId' => $accountId,
            'Date' => null,
            'Comment' => 'معامله آنلاین',
            'Action' => $action,
            'CurrencyId' => $currencyId,
            'GoldPrice' => $fee,
            'GoldUnit' => $goldUnit,
            'Value' => $quantity,
        ];

        return self::voucherExchangeGold($data);
    }

    /**
     * تبادل پولی (ریالی)
     * @param int $accountId
     * @param int $action
     * @param int $fee
     * @param float $quantity
     * @param string $currencyId
     * @param string $displayMode
     * @return array|mixed|null
     */
    private static function exchangeMoney(
        int         $accountId,
        int         $action,
        int         $fee,
        float       $quantity,
        string      $currencyId,
        string      $displayMode = 'quotation'
    ): mixed
    {
        if($displayMode == 'quotation') {
            $fee = $fee * 10;
            $goldUnit = null;
        } else {
            // if action is buy
            if ($action === 64) {
                $fee = roundUpToThousand($fee / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR) * 10;
            } else {
                $fee = roundDownToThousand($fee / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR) * 10;
            }

            $goldUnit = 1;
        }

        return self::voucherExchangeMoney([
            'RequestId' => Str::uuid()->toString(),
            'AddToExistingDateVoucher' => false,
            'AccountId' => $accountId,
            'Date' => null,
            'Comment' => 'معامله آنلاین',
            'Action' => $action,
            'CurrencyId' => $currencyId,
            'GoldPrice' => $fee,
            'GoldUnit' => $goldUnit,
            'Value' => $quantity * 10,
        ]);
    }

    /**
     * تبادل ارز / سکه
     * @param int $accountId
     * @param int $action
     * @param int $fee
     * @param float $quantity
     * @param string $sourceId
     * @param string $targetId
     * @return array|mixed|null
     */
    private static function exchangeCurrency(
        int         $accountId,
        int         $action,
        int         $fee,
        float       $quantity,
        string      $sourceId,
        string      $targetId,
    ): mixed
    {
        return self::voucherExchangeCurrency([
            'RequestId' => Str::uuid()->toString(),
            'AddToExistingDateVoucher' => false,
            'AccountId' => $accountId,
            'Date' => null,
            'Comment' => 'معامله آنلاین',
            'Action' => $action,
            'UnitPrice' => $fee * 10,
            'DivideUnitPrice' => null,
            'Quantity' => $quantity,
            'GoldPrice' => $fee,
            'GoldUnit' => null,
            'SourceId' => $sourceId,
            'TargetId' => $targetId,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // متدهای قبلی (بدون تغییر)
    // ═══════════════════════════════════════════════════════════════

    public static function getBarcode($productId = null)
    {
        $params = [];
        if ($productId) {
            $params['product_id'] = $productId;
        }
        return self::request('GET', 'barcode', $params);
    }

    public static function getAccounts(array $data)
    {
        return self::request('GET', 'account', $data);
    }

    public static function getVoucherBalance($id, $aggregated = 'inactive')
    {
        $response = self::request('GET', "voucher/balance/$id");

        $mergeIds = MetalItem::withoutGlobalScope('visible')
            ->where('unit', 'gram')
            ->pluck('kimia_product_id')
            ->toArray();

        $mergeIds = [
            ...$mergeIds,
            11
        ];

        $refactored = array_map(function ($item) use ($mergeIds) {

            // اگر CurrencyId در لیست باشد
            if (in_array($item['CurrencyId'], $mergeIds)) {

                // تقسیم Money بر 10
                $item['Money'] = round($item['Money'] / 10);

                // تغییر واحد پولی
                if ($item['CurrencySymbol'] === 'ریال') {
                    $item['CurrencySymbol'] = 'تومان';
                }
            }

            return $item;
        }, $response);

        if ($aggregated === 'inactive') {
            return $refactored;
        }

        $items = collect($refactored);
        $toMerge = $items->whereIn('CurrencyId', $mergeIds);

        if ($toMerge->count() > 0) {
            $merged = [
                'Weight' => $toMerge->sum('Weight'),
                'Money' => $toMerge->sum('Money'),
                'CurrencyId' => 11,
                'CurrencySymbol' => 'تومان',
            ];

            $others = $items->whereNotIn('CurrencyId', $mergeIds)->values();
            $result = $others->prepend($merged)->values();
        } else {
            $result = $items;
        }

        return $result;
    }

    public static function getAggregatedVoucherBalance($id): array
    {
        $balance = self::request('GET', "voucher/balance/$id");

        // اعتبارسنجی نوع داده برای اطمینان کامل
        if (!is_array($balance)) {
            throw new \UnexpectedValueException("Invalid response format received from Kimia API for account balance.");
        }

        $metalItems = MetalItem::withoutGlobalScope('visible')->get();

        $totalWeight = 0;
        $totalAmount = 0;

        foreach ($balance as $item) {
            $metalItem = $metalItems->where('kimia_product_id', $item['CurrencyId'])->first();

            if ($metalItem) {
                if ($metalItem->unit == 'count') {
                    $totalWeight += $item['Money'] * $metalItem->equivalent_to;
                } elseif($metalItem->unit == 'gram') {
                    $totalWeight += $item['Weight'] * $metalItem->equivalent_to;
                    $totalAmount += $item['Money'];
                }
            } elseif($item['CurrencyId'] == 11) {
                $totalAmount += $item['Money'] ?? 0;
                $totalWeight += $item['Weight'] ?? 0;
            }
        }

        return [
            $totalWeight,
            round($totalAmount / 10)
        ];
    }

    public static function getVoucherBalances()
    {
        return self::request('GET', 'voucher/balances');
    }

    public static function getProduct()
    {
        return self::request('GET', 'product');
    }

    public static function getProductCoins()
    {
        return self::request('GET', 'product/coins');
    }

    public static function getProductCurrencies()
    {
        return self::request('GET', 'product/currencies');
    }

    public static function getVoucherTransactions($id, array $data)
    {
        return self::request('GET', "voucher/transactions/$id", $data);
    }

    public static function getAccountGroups($accountType = null)
    {
        $params = [];
        if ($accountType) {
            $params['accountType'] = $accountType;
        }
        return self::request('GET', 'account/groups', $params);
    }

    public static function createAccount(array $data)
    {
        return self::request('POST', 'account', $data);
    }

    public static function updateAccount(array $data)
    {
        return self::request('PUT', 'account', $data);
    }

    public static function voucherExchangeCurrency(array $data)
    {
        return self::request('POST', 'voucher/exchangecurrency', $data);
    }

    public static function voucherExchangeGold(array $data)
    {
        return self::request('POST', 'voucher/exchangegold', $data);
    }

    public static function voucherExchangeMoney(array $data)
    {
        return self::request('POST', 'voucher/exchangemoney', $data);
    }
}
