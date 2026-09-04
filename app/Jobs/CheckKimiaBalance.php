<?php

namespace App\Jobs;

use App\Constants\AppConstants;
use App\Events\MetalOrderUpdated;
use App\Models\MetalOrder;
use App\Models\SelectedMetalPrice;
use App\Models\Setting;
use App\Services\KimiaService;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CheckKimiaBalance implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = 5; // فاصله بین retry ها (ثانیه)

    /**
     * Create a new job instance.
     */
    public function __construct(
        public MetalOrder $metal_order,
        public            $leverage,
        public            $kimia_id
    ) {  }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (!$this->kimia_id) {
            $this->metal_order->leverageCheck->update([
                'status' => 'missing_accounting_id'
            ]);

            sleep(1);
        } else {
            $balanceMetalItemId = Cache::rememberForever('setting.balance_metal_item_id', function () {
                return Setting::where('option_key', 'balance_metal_item_id')
                    ->value('option_value');
            });

            $latestPrice = SelectedMetalPrice::select('id', 'buy', 'sell', 'created_at')
                ->where('metal_item_id', $balanceMetalItemId)
                ->latest('id')
                ->first();

            [$a16, $b16] = KimiaService::getAggregatedVoucherBalance(
                $this->kimia_id
            );

            $c16 = $this->leverage;
            $e16 = $this->metal_order->order_type;

            $f16 = $this->metal_order->product['amount'];

            $priceSource = $e16 === 'buy' ? $latestPrice->sell : $latestPrice->buy;
            $oneGram = round($priceSource / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);

            $d16 = $f16 / $oneGram;

            $price = ($b16 >= 0)
                ? ($latestPrice->sell ?? $latestPrice->buy)
                : ($latestPrice->buy ?? $latestPrice->sell);

            $g16 = $a16 + (($b16 / $price) * AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR);

            // استخراج قیمت با در نظر گرفتن منطق Fallback
            $price = ($a16 >= 0)
                ? ($latestPrice->buy ?? $latestPrice->sell)
                : ($latestPrice->sell ?? $latestPrice->buy);

            // محاسبه نهایی
            $h16 = ($a16 * $price / AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR) + $b16;

            $i16 = $g16 * $c16;
            $j16 = $h16 * $c16;
            $k16 = $j16 - $b16;
            $l16 = $i16 - $a16;

            $m16 = $e16 === 'buy' ?
                $g16 / ($d16 + $a16) * 100 :
                $h16 / ($b16 + $f16) * 100;

            $m16 = round($m16, 2);

            $canTrade = ($e16 === 'buy')
                ? $l16 >= $d16
                : $k16 >= $f16;

            if ($canTrade) {
                $this->metal_order->leverageCheck->update([
                    'status' => 'sufficient',
                    'order_percentage' => $m16,
                    'net_toman_balance' => $h16,
                ]);
            } else {
                $this->metal_order->leverageCheck->update([
                    'status' => 'insufficient',
                    'order_percentage' => $m16,
                    'net_toman_balance' => $h16,
                ]);
            }
        }

        MetalOrderUpdated::dispatch();
    }

    /**
     * ✅ این متد فقط وقتی صدا زده میشه که همه tries ها fail بشن
     */
    public function failed(Throwable $exception): void
    {
        $this->metal_order->leverageCheck?->update([
            'status' => 'connection_failed',
        ]);

        MetalOrderUpdated::dispatch();
    }
}

