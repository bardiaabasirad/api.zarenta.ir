<?php

namespace App\Jobs;

use App\Exceptions\HamtalaApiException;
use App\Models\MetalOrder;
use App\Models\MetalOrderExchange;
use App\Services\Hamtala\HamtalaOrderExchangeService;
use App\Services\MetalOrderService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Exceptions\RetryableBusinessException;

class SubmitHamtalaOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int|array $backoff = 5;

    public ?int $exchangeId = null;

    public function __construct(
        public MetalOrder $metalOrder
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(HamtalaOrderExchangeService $service): void
    {
        if ($this->metalOrder->status !== 'processing') {
            Log::channel('checkStaleMetalOrders')->warning("Job skipped: Order {$this->metalOrder->id} is not in processing state.");
            return;
        }

        $preparedData = $service->prepareAutoOrderData($this->metalOrder);

        if (!is_array($preparedData)) {
            if ($service->isSkipReason($preparedData)) {
                return;
            }

            try {
                $this->rejectMetalOrder($preparedData);
            } catch (Throwable $e) {
                Log::channel('checkStaleMetalOrders')->warning('Failed to reject metal order after invalid prepared data', [
                    'order_id' => $this->metalOrder->id ?? $this->metalOrder,
                    'reject_reason' => is_scalar($preparedData) ? $preparedData : 'Prepared data is not a valid array',
                    'exception' => $e->getMessage(),
                ]);
            }
            return;
        }

        $exchange = $this->resolveExchange($service);

        $payload = $service->buildPayload(
            $this->metalOrder,
            $exchange,
            $preparedData
        );

        $effective = $this->metalOrder->computeEffectiveFee();
        $fee = $effective['fee'];

        $buyOrSell = (int)data_get($preparedData, 'buy_or_sell');
        $mazane = (float)data_get($payload, 'mazane');

        // محافظت در برابر قیمت نامعتبر (صفر/null از سرویس قیمت)
        if ($mazane <= 0) {
            $this->rejectMetalOrder();
            return;
//            throw new RetryableBusinessException('invalid_mazane_price');
        }

        if (
            ($buyOrSell === 1 && $fee < $mazane) ||
            ($buyOrSell === 2 && $fee > $mazane)
        ) {
            $this->rejectMetalOrder();
            return;
//            throw new RetryableBusinessException('rate_is_loss_making');
        }

        $service->markExchangeAsProcessing($exchange, $payload);

        if ($this->metalOrder->fresh()->isFinalized()) {
            return;
        }

        try {
            $response = $service->sendPayload($payload);
            $service->syncResponse($exchange->fresh(), $response, $this->metalOrder);
        } catch (HamtalaApiException $e) {
            $this->delete();

            try {
                $this->rejectMetalOrder();
                $service->syncResponse($exchange->fresh(), [
                    'status' => 0,
                    'message' => $e->getMessage(),
                ], $this->metalOrder);
            } catch (Throwable $inner) {
                Log::channel('checkStaleMetalOrders')->warning('Post-delete cleanup failed', [
                    'exchange_id' => $exchange->id,
                    'error' => $inner->getMessage(),
                ]);
            }

            return;
        }
    }

    public function failed(Throwable $exception): void
    {
        $isRetryableBusinessFailure = $exception instanceof \App\Exceptions\RetryableBusinessException;

        try {
            $exchange = MetalOrderExchange::query()->find($this->exchangeId);

            if ($exchange) {
                app(HamtalaOrderExchangeService::class)->markExchangeAsRetryFailed(
                    $exchange,
                    $exception,
                    $this->attempts()
                );
            }
        } catch (Throwable $e) {
            Log::channel('checkStaleMetalOrders')->error('Failed to mark exchange as retry failed in job failed handler', [
                'exchange_id' => $this->exchangeId,
                'order_id' => $this->metalOrder->id ?? $this->metalOrder,
                'job_failure_reason' => $exception->getMessage(),
                'handler_failure_reason' => $e->getMessage(),
            ]);
        }

        try {
            $this->rejectMetalOrder($exception->getMessage());
        } catch (Throwable $e) {
            Log::channel('checkStaleMetalOrders')->warning('Failed to reject metal order after job failure', [
                'order_id' => $this->metalOrder->id ?? $this->metalOrder,
                'exchange_id' => $this->exchangeId,
                'attempt' => $this->attempts(),
                'job_failure_reason' => $exception->getMessage(),
                'reject_failure_reason' => $e->getMessage(),
            ]);
        }

        if ($isRetryableBusinessFailure) {
            Log::channel('checkStaleMetalOrders')->notice('Metal order retries exhausted due to business condition', [
                'order_id' => $this->metalOrder->id ?? $this->metalOrder,
                'exchange_id' => $this->exchangeId,
                'reason' => $exception->getMessage(),
                'attempt' => $this->attempts(),
            ]);
        }
    }

    private function resolveExchange(HamtalaOrderExchangeService $service): MetalOrderExchange
    {
        $exchange = $service->createPendingExchange($this->metalOrder->id);
        $this->exchangeId = $exchange->id;
        return $exchange;
    }

    /**
     * @param string $cause
     * @param string $message
     * @return void
     */
    public function rejectMetalOrder(string $cause = 'partner_retry_limit_reached', string $message = 'تغییر مظنه'): void
    {
        MetalOrderService::markAsRejected(
            $this->metalOrder,
            [
                'cause' => $cause,
                'message' => $message,
                'rejected_at' => Carbon::now(),
            ]
        );
    }
}
