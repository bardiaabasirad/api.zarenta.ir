<?php

namespace App\Http\Controllers\api\v1\metalTrader;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderBankAccount;
use App\Services\MetalTraderBankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

class BankAccountController extends Controller
{
    public function __construct(
        private readonly MetalTraderBankAccountService $bankAccountService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $trader = $request->user();

        $accounts = MetalTraderBankAccount::query()
            ->with(['rejectionReason:id,value'])
            ->where('metal_trader_id', $trader->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sheba' => ['required', 'string', 'max:34'],
        ], [
            'sheba.required' => 'شماره شبا ضروری است.',
        ]);

        try {
            $account = $this->bankAccountService->create($request->user(), $data['sheba']);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $account->load(['rejectionReason:id,value']);

        return response()->json([
            'message' => 'حساب بانکی ثبت شد و پس از بررسی مدیر فعال می‌شود.',
            'data' => $account,
        ], 201);
    }

    public function destroy(Request $request, MetalTraderBankAccount $bankAccount): JsonResponse
    {
        try {
            $this->bankAccountService->deleteForTrader($request->user(), $bankAccount);
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'حساب بانکی حذف شد.']);
    }
}
