<?php

namespace App\Services\Metal;

use App\Constants\MetalCoverageAttemptStatus;
use App\Exceptions\HamtalaApiException;
use App\Models\MetalOrder;
use App\Models\MetalOrderCoverageAttempt;
use App\Services\Hamtala\HamtalaOrderExchangeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * تماس همکار خارج از تراکنش بلند؛ Attempt جدا ثبت می‌شود.
 * شکست همکار سفارش کاربر را reject نمی‌کند.
 */
class MetalPartnerCoverageService
{
    public function __construct(
        private readonly HamtalaOrderExchangeService $hamtala,
        private readonly MetalMaturityService $maturity,
        private readonly MetalTreasuryLedgerService $ledger,
    ) {
    }

    public function coverOpenQtyFromPartner(
        MetalOrder $order,
        string $qty,
        string $idempotencyKey,
    ): MetalOrder {
        $existing = MetalOrderCoverageAttempt::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing && $existing->status === MetalCoverageAttemptStatus::SUCCEEDED) {
            return $order->fresh();
        }

        if ($existing && $existing->isFinalized()) {
            return $this->applyFailedSideEffects($order, $existing);
        }

        $attempt = $existing ?? MetalOrderCoverageAttempt::create([
            'metal_order_id' => $order->id,
            'channel' => 'PARTNER',
            'status' => MetalCoverageAttemptStatus::PENDING,
            'idempotency_key' => $idempotencyKey,
            'requested_qty' => $this->ledger->decimal($qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty'),
            'actor_id' => Auth::id(),
            'actor_type' => Auth::check() ? Auth::user()->getMorphClass() : null,
        ]);

        // آماده‌سازی payload داخل TX کوتاه
        $prepared = null;
        $exchange = null;

        try {
            DB::transaction(function () use ($order, $attempt, &$prepared, &$exchange) {
                $order = MetalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

                // وزن درخواستی برای همکار = کسری باز (نه لزوماً کل سفارش)
                $extra = $order->extra_data ?? [];
                $extra['auto_order_quantity'] = (float) $attempt->requested_qty;
                $extra['coverage_attempt_id'] = $attempt->id;
                $order->extra_data = $extra;
                $order->save();

                $prepared = $this->hamtala->prepareAutoOrderData($order);

                if (!is_array($prepared)) {
                    $attempt->update([
                        'status' => MetalCoverageAttemptStatus::INVALID,
                        'error_code' => is_string($prepared) ? $prepared : 'validation_failed',
                        'error_message' => 'اعتبارسنجی سفارش همکار ناموفق بود.',
                        'resolved_at' => now(),
                    ]);

                    return;
                }

                // وزن را به کسری باز محدود کن
                if (isset($prepared['weight']) && $prepared['weight'] !== null) {
                    $prepared['weight'] = (float) $attempt->requested_qty;
                }

                $exchange = $this->hamtala->createPendingExchange($order->id);
                $attempt->metal_order_exchange_id = $exchange->id;
                $attempt->save();

                $payload = $this->hamtala->buildPayload($order, $exchange, $prepared);
                $sanitized = $this->sanitizePayload($payload);

                $attempt->update([
                    'status' => MetalCoverageAttemptStatus::SENT,
                    'request_payload' => $sanitized,
                    'sent_at' => now(),
                ]);

                $this->hamtala->markExchangeAsProcessing($exchange, $payload);
                $prepared = $payload;
            });
        } catch (Throwable $e) {
            $attempt->update([
                'status' => MetalCoverageAttemptStatus::FAILED,
                'error_code' => 'prepare_failed',
                'error_message' => $e->getMessage(),
                'resolved_at' => now(),
            ]);

            return $this->applyFailedSideEffects($order->fresh(), $attempt->fresh());
        }

        $attempt = $attempt->fresh();

        if ($attempt->status === MetalCoverageAttemptStatus::INVALID) {
            return $this->applyFailedSideEffects($order->fresh(), $attempt);
        }

        if (!$prepared || !is_array($prepared)) {
            return $this->applyFailedSideEffects($order->fresh(), $attempt);
        }

        // تماس شبکه خارج از تراکنش
        try {
            $response = $this->hamtala->sendPayload($prepared);
        } catch (HamtalaApiException $e) {
            return $this->resolveAttemptFailure(
                $order,
                $attempt,
                $exchange,
                MetalCoverageAttemptStatus::FAILED,
                'hamtala_api',
                $e->getMessage(),
                ['message' => $e->getMessage()]
            );
        } catch (Throwable $e) {
            Log::channel('checkStaleMetalOrders')->warning('Partner coverage timeout/error', [
                'order_id' => $order->id,
                'attempt_id' => $attempt->id,
                'error' => $e->getMessage(),
            ]);

            return $this->resolveAttemptFailure(
                $order,
                $attempt,
                $exchange,
                MetalCoverageAttemptStatus::TIMEOUT,
                'timeout_or_network',
                $e->getMessage(),
                ['message' => $e->getMessage()]
            );
        }

        return $this->applyPartnerResponse($order, $attempt->fresh(), $exchange?->fresh(), $response, $idempotencyKey);
    }

    public function retryPartner(MetalOrder $order, ?string $idempotencyKey = null): MetalOrder
    {
        $coverage = app(MetalOrderCoverageService::class);
        $coverage->initializeCoverageFields($order);
        $order = $coverage->refreshCoverageCaches($order->fresh());

        if (bccomp((string) $order->open_qty, '0', MetalTreasuryLedgerService::METAL_SCALE) <= 0) {
            return $order;
        }

        $key = $idempotencyKey ?: ('retry-partner:' . $order->id . ':' . now()->format('YmdHis'));

        return $this->coverOpenQtyFromPartner($order, (string) $order->open_qty, $key);
    }

    private function applyPartnerResponse(
        MetalOrder $order,
        MetalOrderCoverageAttempt $attempt,
        $exchange,
        array $response,
        string $idempotencyKey,
    ): MetalOrder {
        $remoteStatus = (int) ($response['status'] ?? 0);
        $sanitizedResponse = $this->sanitizePayload($response);

        if ($remoteStatus === \App\Services\Hamtala\HamtalaConstants::ORDER_PENDING) {
            // ثبت شد ولی هنوز تأیید نشده — کسری باز می‌ماند تا inquire/confirm
            DB::transaction(function () use ($attempt, $exchange, $sanitizedResponse, $response, $order) {
                $attempt->update([
                    'status' => MetalCoverageAttemptStatus::SENT,
                    'response_payload' => $sanitizedResponse,
                    'partner_order_id' => isset($response['order_id']) ? (string) $response['order_id'] : null,
                ]);

                if ($exchange && !$exchange->isFinalized()) {
                    $details = $exchange->details ?? [];
                    $details['response'] = $sanitizedResponse;
                    $exchange->update([
                        'status' => 'placed',
                        'details' => $details,
                        'placed_at' => now(),
                        'message' => $response['message'] ?? null,
                    ]);
                }

                $order->last_coverage_attempt_at = now();
                $order->save();
            });

            return $order->fresh();
        }

        $success = $remoteStatus === \App\Services\Hamtala\HamtalaConstants::CONFIRM_SUCCESS;

        if (!$success) {
            return $this->resolveAttemptFailure(
                $order,
                $attempt,
                $exchange,
                MetalCoverageAttemptStatus::FAILED,
                'partner_rejected',
                $response['message'] ?? 'همکار سفارش را نپذیرفت',
                $sanitizedResponse
            );
        }

        return DB::transaction(function () use ($order, $attempt, $exchange, $response, $sanitizedResponse, $idempotencyKey, $remoteStatus) {
            $order = MetalOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $attempt = MetalOrderCoverageAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($attempt->status === MetalCoverageAttemptStatus::SUCCEEDED) {
                return $order->fresh();
            }

            $rate = $this->resolveExecutedRate($order, $response, $attempt);
            $qty = $this->ledger->decimal($attempt->requested_qty, MetalTreasuryLedgerService::METAL_SCALE, 'qty');
            $maturity = $this->maturity->codeForOrder($order);

            $coverage = app(MetalOrderCoverageService::class);

            $coverage->createPartnerAllocation(
                order: $order,
                qty: $qty,
                unitCost: $rate,
                maturityCode: $maturity,
                idempotencyKey: $idempotencyKey . ':partner-alloc',
                attemptId: $attempt->id,
                sourceId: $exchange?->id,
            );

            $attempt->update([
                'status' => MetalCoverageAttemptStatus::SUCCEEDED,
                'executed_qty' => $qty,
                'executed_rate' => $rate,
                'partner_order_id' => isset($response['order_id']) ? (string) $response['order_id'] : null,
                'response_payload' => $sanitizedResponse,
                'resolved_at' => now(),
                'error_code' => null,
                'error_message' => null,
            ]);

            if ($exchange && !$exchange->isFinalized()) {
                $details = $exchange->details ?? [];
                $details['response'] = $sanitizedResponse;
                $exchange->update([
                    'status' => $remoteStatus === \App\Services\Hamtala\HamtalaConstants::ORDER_PENDING
                        ? 'placed'
                        : 'confirmed',
                    'rate' => $rate,
                    'details' => $details,
                    'placed_at' => now(),
                    'resolved_at' => $remoteStatus === \App\Services\Hamtala\HamtalaConstants::CONFIRM_SUCCESS
                        ? now()
                        : null,
                    'message' => $response['message'] ?? null,
                ]);
            }

            $order->last_coverage_attempt_at = now();
            $order->last_partner_error = null;
            $order->save();

            $order = $coverage->refreshCoverageCaches($order->fresh());

            if ($order->coverage_status === \App\Constants\MetalCoverageStatus::FULLY_COVERED
                && !$order->fresh()->isFinalized()
            ) {
                \App\Services\MetalOrderService::markAsSucceed($order->fresh(), [
                    'succeed_at' => now(),
                    'coverage_finalized' => true,
                    'partner_covered' => true,
                ]);
            }

            return $order->fresh();
        });
    }

    private function resolveAttemptFailure(
        MetalOrder $order,
        MetalOrderCoverageAttempt $attempt,
        $exchange,
        string $status,
        string $errorCode,
        string $message,
        array $response,
    ): MetalOrder {
        DB::transaction(function () use ($attempt, $exchange, $status, $errorCode, $message, $response) {
            $attempt->refresh();

            if (!$attempt->isFinalized()) {
                $attempt->update([
                    'status' => $status,
                    'error_code' => $errorCode,
                    'error_message' => mb_substr($message, 0, 500),
                    'response_payload' => $this->sanitizePayload($response),
                    'resolved_at' => now(),
                ]);
            }

            if ($exchange && !$exchange->fresh()->isFinalized()) {
                $details = $exchange->details ?? [];
                $details['response'] = $this->sanitizePayload($response);
                $exchange->update([
                    'status' => 'error',
                    'details' => $details,
                    'message' => mb_substr($message, 0, 255),
                    'resolved_at' => now(),
                ]);
            }
        });

        return $this->applyFailedSideEffects($order->fresh(), $attempt->fresh());
    }

    private function applyFailedSideEffects(MetalOrder $order, MetalOrderCoverageAttempt $attempt): MetalOrder
    {
        $order->last_coverage_attempt_at = now();
        $order->last_partner_error = $attempt->error_message ?: $attempt->error_code;
        $order->save();

        // سفارش reject نمی‌شود؛ فقط کسری باز می‌ماند
        return app(MetalOrderCoverageService::class)->refreshCoverageCaches($order->fresh());
    }

    private function resolveExecutedRate(MetalOrder $order, array $response, MetalOrderCoverageAttempt $attempt): string
    {
        if (isset($response['mazane']) && is_numeric($response['mazane'])) {
            return $this->ledger->decimal((string) (int) $response['mazane'], MetalTreasuryLedgerService::PRICE_SCALE, 'mazane');
        }

        $request = $attempt->request_payload ?? [];

        if (isset($request['mazane']) && is_numeric($request['mazane'])) {
            return $this->ledger->decimal((string) (int) $request['mazane'], MetalTreasuryLedgerService::PRICE_SCALE, 'mazane');
        }

        // fallback: نرخ فروش به کاربر (بدون سود)
        return $this->ledger->decimal((string) (int) round((float) $order->fee), MetalTreasuryLedgerService::PRICE_SCALE, 'fee');
    }

    private function sanitizePayload(array $payload): array
    {
        $blocked = ['token', 'password', 'authorization', 'api_key', 'apiKey', 'secret', 'credential', 'credentials'];

        $clean = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                $clean[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $clean[$key] = $this->sanitizePayload($value);
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
