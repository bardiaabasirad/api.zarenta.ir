<?php

namespace App\Services;

use App\Models\MetalSettlementCase;
use App\Services\Hamtala\HamtalaPriceService;
use Throwable;

/**
 * دکمه تک‌کلیکی فروش/خرید جایگزین از مسیر همکار برای نکول.
 * فعلاً قیمت همکار (در صورت دسترسی) یا نرخ داخلی را برمی‌گرداند؛
 * اجرای اصلاحیه کیف‌پول کاربر در SettlementFulfillmentService::executeDefault انجام می‌شود.
 */
class SettlementPartnerTradeService
{
    public function __construct(
        private readonly SettlementFulfillmentService $fulfillment,
        private readonly HamtalaPriceService $hamtalaPrices,
    ) {
    }

    /**
     * @return array{
     *   channel: string,
     *   qty: string,
     *   unit_price: string,
     *   replacement_loss: string,
     *   one_percent: string,
     *   total: string,
     *   proceeds_or_cost: string,
     *   side: string
     * }
     */
    public function quoteReplacement(MetalSettlementCase $case): array
    {
        $preview = $this->fulfillment->previewDefault($case);
        $side = $preview['side'];

        $unitFromHamtala = $this->tryHamtalaUnitPrice((int) $case->spot_metal_item_id, $side);
        if ($unitFromHamtala !== null) {
            if ($side === 'buy') {
                $proceeds = bcmul($preview['qty'], $unitFromHamtala, 0);
                $loss = bcsub($preview['original_notional'], $proceeds, 0);
                if (bccomp($loss, '0', 0) < 0) {
                    $loss = '0';
                }

                return [
                    'channel' => 'hamtala',
                    'qty' => $preview['qty'],
                    'unit_price' => $unitFromHamtala,
                    'replacement_loss' => $loss,
                    'one_percent' => $preview['one_percent'],
                    'total' => bcadd($loss, $preview['one_percent'], 0),
                    'proceeds_or_cost' => $proceeds,
                    'side' => $side,
                ];
            }

            $cost = bcmul($preview['qty'], $unitFromHamtala, 0);
            $loss = bcsub($cost, $preview['original_notional'], 0);
            if (bccomp($loss, '0', 0) < 0) {
                $loss = '0';
            }

            return [
                'channel' => 'hamtala',
                'qty' => $preview['qty'],
                'unit_price' => $unitFromHamtala,
                'replacement_loss' => $loss,
                'one_percent' => $preview['one_percent'],
                'total' => bcadd($loss, $preview['one_percent'], 0),
                'proceeds_or_cost' => $cost,
                'side' => $side,
            ];
        }

        $estimate = $side === 'buy'
            ? $this->fulfillment->estimatePartnerSellProceeds($case)
            : $this->fulfillment->estimatePartnerBuyCost($case);

        return [
            'channel' => 'internal_market',
            'qty' => $estimate['qty'],
            'unit_price' => $estimate['unit_price'],
            'replacement_loss' => $estimate['replacement_loss'],
            'one_percent' => $estimate['one_percent'],
            'total' => $estimate['total'],
            'proceeds_or_cost' => $estimate['proceeds'] ?? $estimate['cost'],
            'side' => $side,
        ];
    }

    private function tryHamtalaUnitPrice(int $metalItemId, string $side): ?string
    {
        try {
            $price = $this->hamtalaPrices->getProductPrice($metalItemId);
            if (! is_array($price)) {
                return null;
            }

            // فروش جایگزین به همکار ≈ price_buy همکار؛ خرید جایگزین از همکار ≈ price_sell همکار
            $raw = $side === 'buy'
                ? ($price['price_buy'] ?? null)
                : ($price['price_sell'] ?? null);

            if ($raw === null || ! is_numeric($raw)) {
                return null;
            }

            return bcadd((string) $raw, '0', 0);
        } catch (Throwable) {
            return null;
        }
    }
}
