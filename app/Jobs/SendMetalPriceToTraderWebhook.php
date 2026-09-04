<?php

namespace App\Jobs;

use App\Constants\AppConstants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SendMetalPriceToTraderWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $maxExceptions = 1;

    public int $timeout = 10;

    public function __construct(
        public readonly array $pricePayload,
        public readonly array $trader,
    ) {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        try {
            $trader = $this->trader;

            if (! $this->canSendWebhook($trader)) {
                return;
            }

            $config = $trader['metal_item_configs'][0] ?? null;

            if (! is_array($config)) {
                return;
            }

            $payload = $this->buildPayload($config);
            $headers = $this->buildHeaders($trader, $payload);

            $this->sendRequest(
                url: $trader['webhook_url'],
                headers: $headers,
                payload: $payload,
            );
        } catch (ConnectionException) {
            // خطای اتصال به سرویس همکار عمداً لاگ نمی‌شود.
            return;
        } catch (Throwable $exception) {
            // فقط خطاهای داخلی سیستم خودمان گزارش می‌شوند.
            report($exception);

            return;
        }
    }

    private function canSendWebhook(array $trader): bool
    {
        if (
            empty($trader['webhook_url'])
            || empty($trader['webhook_secret'])
            || ($trader['webhook_enabled'] ?? true) === false
        ) {
            return false;
        }

        return filter_var($trader['webhook_url'], FILTER_VALIDATE_URL) !== false;
    }

    private function buildPayload(array $config): array
    {
        $buy = (string) ($this->pricePayload['buy'] ?? '');
        $sell = (string) ($this->pricePayload['sell'] ?? '');

        [$buy, $sell] = $this->applyFeeMargins($buy, $sell, $config);

        if (($config['display_mode'] ?? 'quotation') === 'per_gram') {
            $buy = $this->div(
                $buy,
                AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR
            );

            $sell = $this->div(
                $sell,
                AppConstants::MARKET_SPECIFIC_CONVERSION_FACTOR
            );
        }

        return [
            'event' => 'metal_price.updated',
            'event_id' => (string) Str::ulid(),
            'product_id' => (int) ($this->pricePayload['metal_item_id'] ?? 0),
            'product' => $this->pricePayload['product'] ?? null,
            'buy' => $buy,
            'sell' => $sell,
            'min_order' => $this->nullableInt($config['min_order'] ?? null),
            'max_order' => $this->nullableInt($config['max_order'] ?? null),
            'updated_at' => Carbon::parse(
                $this->pricePayload['updated_at'] ?? null
            )->toIso8601String(),
        ];
    }

    private function buildHeaders(array $trader, array $payload): array
    {
        $timestamp = (string) now()->timestamp;
        $eventId = $payload['event_id'];

        $rawBody = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $signature = hash_hmac(
            'sha256',
            "{$timestamp}.{$eventId}.{$rawBody}",
            (string) $trader['webhook_secret']
        );

        return [
            'User-Agent' => 'MetalPriceWebhook/1.0',
            'X-Webhook-Id' => $eventId,
            'X-Webhook-Timestamp' => $timestamp,
            'X-Webhook-Signature' => 'v1=' . $signature,
        ];
    }

    private function sendRequest(
        string $url,
        array $headers,
        array $payload,
    ): void {

        $rawBody = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        Http::acceptJson()
            ->timeout(5)
            ->connectTimeout(2)
            ->retry(0, 0)
            ->withHeaders($headers)
            ->withBody($rawBody, 'application/json')
            ->post($url);
    }

    private function applyFeeMargins(
        string $buy,
        string $sell,
        array $config,
    ): array {
        $buyFee = $config['buy_fee_margin'] ?? null;
        $sellFee = $config['sell_fee_margin'] ?? null;

        if ($buyFee === null && $sellFee === null) {
            return [$buy, $sell];
        }

        if (($config['tolerance_type'] ?? 'fixed_amount') === 'percentage') {
            return [
                $buyFee !== null
                    ? $this->addPercentage($buy, (string) $buyFee)
                    : $buy,

                $sellFee !== null
                    ? $this->addPercentage($sell, (string) $sellFee)
                    : $sell,
            ];
        }

        return [
            $buyFee !== null
                ? $this->add($buy, (string) $buyFee)
                : $buy,

            $sellFee !== null
                ? $this->add($sell, (string) $sellFee)
                : $sell,
        ];
    }

    private function addPercentage(string $value, string $percent): string
    {
        return $this->add(
            $value,
            bcdiv(
                bcmul($value, $percent, 6),
                '100',
                6
            )
        );
    }

    private function add(string $value, string $amount): string
    {
        return $this->normalize(
            bcadd($value, $amount, 6)
        );
    }

    private function div(string $value, string $divisor): string
    {
        return $this->normalize(
            bcdiv($value, $divisor, 6)
        );
    }

    private function normalize(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(
                rtrim($value, '0'),
                '.'
            );
        }

        return $value === '' || $value === '-0'
            ? '0'
            : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
