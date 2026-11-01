<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderBankAccount;
use App\Models\MetalTraderFiatWithdrawal;
use App\Models\MetalTraderWallet;
use App\Services\MetalTraderFiatWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

class FiatWithdrawalController extends Controller
{
    public function __construct(
        private readonly MetalTraderFiatWithdrawalService $withdrawalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $items = MetalTraderFiatWithdrawal::query()
            ->with(['bankAccount:id,sheba,status', 'rejectionReason:id,value'])
            ->where('metal_trader_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate($request->input('count') ?? 50);

        return response()->json(['data' => $items]);
    }

    public function formMeta(Request $request): JsonResponse
    {
        $trader = $request->user();

        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $trader->id)
            ->whereNull('metal_item_id')
            ->first();

        $banks = MetalTraderBankAccount::query()
            ->where('metal_trader_id', $trader->id)
            ->where('status', MetalTraderBankAccount::STATUS_APPROVED)
            ->orderByDesc('id')
            ->get(['id', 'sheba', 'status']);

        return response()->json([
            'data' => [
                'available_balance' => (string) ($wallet->available_balance ?? '0'),
                'bank_accounts' => $banks,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required'],
            'metal_trader_bank_account_id' => ['required', 'integer'],
        ], [
            'amount.required' => 'مبلغ برداشت الزامی است.',
            'metal_trader_bank_account_id.required' => 'انتخاب حساب بانکی الزامی است.',
        ]);

        $amount = preg_replace('/[^\d]/', '', (string) $data['amount']);

        try {
            $withdrawal = $this->withdrawalService->create(
                $request->user(),
                (string) $amount,
                (int) $data['metal_trader_bank_account_id']
            );
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست برداشت ثبت شد.',
            'data' => $withdrawal,
        ], 201);
    }

    public function cancel(Request $request, MetalTraderFiatWithdrawal $withdrawal): JsonResponse
    {
        try {
            $item = $this->withdrawalService->cancelForTrader($request->user(), $withdrawal);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'درخواست برداشت لغو و مبلغ به کیف تومان بازگردانده شد.',
            'data' => $item,
        ]);
    }
}
