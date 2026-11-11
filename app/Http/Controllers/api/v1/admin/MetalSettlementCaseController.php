<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettlementCaseDefaultRequest;
use App\Http\Requests\SettlementCaseDepositRequest;
use App\Http\Requests\SettlementCaseFulfillRequest;
use App\Http\Requests\SettlementCaseMetalDeliveryRequest;
use App\Models\MetalSettlementCase;
use App\Models\MetalTraderWallet;
use App\Services\SettlementFulfillmentService;
use App\Services\SettlementPartnerTradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use RuntimeException;
use Throwable;

class MetalSettlementCaseController extends Controller
{
    public function __construct(
        private readonly SettlementFulfillmentService $fulfillment,
        private readonly SettlementPartnerTradeService $partnerTrade,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = MetalSettlementCase::query()
            ->with([
                'trader:id,name,phone',
                'commitmentMetalItem:id,title,unit',
                'spotMetalItem:id,title,unit',
            ])
            ->withCount('orders')
            ->orderByDesc('id');

        $status = $request->input('status', 'open');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('metal_trader_id')) {
            $query->where('metal_trader_id', $request->integer('metal_trader_id'));
        }

        if ($request->filled('trader_name')) {
            $name = $request->input('trader_name');
            $query->whereHas('trader', function ($q) use ($name) {
                $q->where('name', 'like', '%' . $name . '%')
                    ->orWhere('phone', 'like', '%' . $name . '%');
            });
        }

        $paginator = $query->paginate($request->input('count') ?? 50);

        return response()->json(['data' => $paginator]);
    }

    public function show(MetalSettlementCase $settlementCase): JsonResponse
    {
        $settlementCase->load([
            'trader:id,name,phone',
            'commitmentMetalItem:id,title,unit',
            'spotMetalItem:id,title,unit',
            'orders',
            'walletTransactions' => fn ($q) => $q->orderByDesc('id'),
        ]);

        $wallets = $this->currentWallets($settlementCase);

        return response()->json([
            'data' => $settlementCase,
            'wallets' => $wallets,
            'default_preview' => $settlementCase->isOpen()
                ? $this->fulfillment->previewDefault($settlementCase)
                : null,
        ]);
    }

    public function deposit(SettlementCaseDepositRequest $request, MetalSettlementCase $settlementCase): JsonResponse
    {
        try {
            $tx = $this->fulfillment->deposit(
                $settlementCase,
                (string) $request->input('amount'),
                $request->input('reference_number'),
                $request->input('description'),
            );

            return response()->json([
                'message' => 'واریز ثبت شد.',
                'transaction' => $tx,
                'case' => $settlementCase->fresh(['orders']),
                'wallets' => $this->currentWallets($settlementCase->fresh()),
            ]);
        } catch (LogicException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'خطا در ثبت واریز.'], 500);
        }
    }

    public function metalDelivery(SettlementCaseMetalDeliveryRequest $request, MetalSettlementCase $settlementCase): JsonResponse
    {
        try {
            $tx = $this->fulfillment->recordMetalDelivery(
                $settlementCase,
                (string) $request->input('qty'),
                $request->input('description'),
            );

            return response()->json([
                'message' => 'تحویل فلز ثبت شد.',
                'transaction' => $tx,
                'case' => $settlementCase->fresh(['orders']),
                'wallets' => $this->currentWallets($settlementCase->fresh()),
            ]);
        } catch (LogicException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'خطا در ثبت تحویل فلز.'], 500);
        }
    }

    public function fulfill(SettlementCaseFulfillRequest $request, MetalSettlementCase $settlementCase): JsonResponse
    {
        try {
            $case = $this->fulfillment->fulfillManually(
                $settlementCase,
                $request->input('notes'),
            );

            return response()->json([
                'message' => 'پرونده تکمیل شد. مانده منفی در صورت وجود، بدهی یتیم کیف‌پول است.',
                'case' => $case,
                'wallets' => $this->currentWallets($case),
            ]);
        } catch (LogicException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function defaultPreview(MetalSettlementCase $settlementCase): JsonResponse
    {
        try {
            return response()->json([
                'preview' => $this->fulfillment->previewDefault($settlementCase),
                'partner_quote' => $this->partnerTrade->quoteReplacement($settlementCase),
            ]);
        } catch (LogicException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function defaultExecute(SettlementCaseDefaultRequest $request, MetalSettlementCase $settlementCase): JsonResponse
    {
        try {
            $usePartner = (bool) $request->boolean('use_partner_trade');
            $partnerMeta = null;
            $loss = (string) $request->input('replacement_loss');

            if ($usePartner) {
                $partnerMeta = $this->partnerTrade->quoteReplacement($settlementCase);
            }

            $case = $this->fulfillment->executeDefault(
                $settlementCase,
                $loss,
                $usePartner,
                $request->input('notes'),
                $partnerMeta,
            );

            return response()->json([
                'message' => 'نکول با اصلاحیه و کسر جریمه ثبت شد.',
                'case' => $case,
                'wallets' => $this->currentWallets($case),
            ]);
        } catch (LogicException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'خطا در اجرای نکول.'], 500);
        }
    }

    /**
     * دکمه تک‌کلیکی: quote همکار + اجرای نکول با همان ضرر محاسبه‌شده.
     */
    public function partnerDefault(MetalSettlementCase $settlementCase): JsonResponse
    {
        try {
            $quote = $this->partnerTrade->quoteReplacement($settlementCase);
            $case = $this->fulfillment->executeDefault(
                $settlementCase,
                $quote['replacement_loss'],
                true,
                'نکول با معامله جایگزین همکار (' . $quote['channel'] . ')',
                $quote,
            );

            return response()->json([
                'message' => 'نکول با معامله جایگزین همکار ثبت شد.',
                'quote' => $quote,
                'case' => $case,
                'wallets' => $this->currentWallets($case),
            ]);
        } catch (LogicException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'خطا در نکول همکار.'], 500);
        }
    }

    private function currentWallets(MetalSettlementCase $case): array
    {
        $cash = MetalTraderWallet::query()
            ->where('metal_trader_id', $case->metal_trader_id)
            ->whereNull('metal_item_id')
            ->first();

        $spot = MetalTraderWallet::query()
            ->where('metal_trader_id', $case->metal_trader_id)
            ->where('metal_item_id', $case->spot_metal_item_id)
            ->first();

        return [
            'cash' => [
                'available_balance' => $cash?->available_balance ?? '0',
            ],
            'spot_metal' => [
                'metal_item_id' => $case->spot_metal_item_id,
                'available_balance' => $spot?->available_balance ?? '0',
            ],
        ];
    }
}
