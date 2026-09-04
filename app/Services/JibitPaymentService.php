<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class JibitPaymentService
{
    protected string $baseUrl;

    public function __construct(
        protected string $apiKey,
        protected string $secretKey,
    ) {
        $this->baseUrl = config('services.jibit.ppg.base_url');
    }

    public function createPurchase(
        int $amount,
        string $callbackUrl,
        string $clientReferenceId,
        string $currency = 'IRR',
        array $additional = [],
    ): array {
        $payload = array_merge([
            'amount'                => $amount * 10,
            'currency'              => $currency,
            'callbackUrl'           => $callbackUrl,
            'clientReferenceNumber' => $clientReferenceId,
        ], array_filter($additional, fn ($value) => $value !== null));

        $response = $this->client()->post('/purchases', $payload);

        if ($response->failed()) {
            Log::error('Jibit PPG createPurchase failed', [
                'status'    => $response->status(),
                'body'      => $response->json(),
                'reference' => $clientReferenceId,
            ]);

            throw new RuntimeException(
                'خطا در ایجاد تراکنش جیبیت: ' . ($response->json('errors.0.code') ?? $response->status())
            );
        }

        return $response->json();
    }

    protected function accessToken(): string
    {
        $cacheKey = 'jibit_ppg_access_' . md5($this->apiKey);

        return Cache::remember($cacheKey, now()->addHours(23), function () {
            return $this->refreshTokens() ?? $this->generateTokens();
        });
    }

    protected function generateTokens(): string
    {
        $response = $this->rawClient()->post('/tokens', [
            'apiKey'    => $this->apiKey,
            'secretKey' => $this->secretKey,
        ]);

        if ($response->failed()) {
            Log::error('Jibit PPG token generation failed', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            throw new RuntimeException(
                'خطا در دریافت توکن جیبیت: ' . ($response->json('errors.0.code') ?? $response->status())
            );
        }

        return $this->storeTokens($response->json());
    }

    protected function refreshTokens(): ?string
    {
        $refreshToken = Cache::get('jibit_ppg_refresh_' . md5($this->apiKey));

        if (! $refreshToken) {
            return null;
        }

        $response = $this->rawClient()->post('/tokens/refresh', [
            'refreshToken' => $refreshToken,
        ]);

        if ($response->failed()) {
            Log::warning('Jibit PPG token refresh failed, falling back to generate', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            return null;
        }

        return $this->storeTokens($response->json());
    }

    protected function storeTokens(array $data): string
    {
        if (empty($data['accessToken'])) {
            throw new RuntimeException('توکن دسترسی جیبیت دریافت نشد.');
        }

        if (! empty($data['refreshToken'])) {
            Cache::put(
                'jibit_ppg_refresh_' . md5($this->apiKey),
                $data['refreshToken'],
                now()->addHours(47),
            );
        }

        return $data['accessToken'];
    }

    protected function rawClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken($this->accessToken())
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
