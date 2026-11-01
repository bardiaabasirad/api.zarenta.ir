<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderBankAccount;
use App\Models\MetalTraderBankAccountRejectionReason;
use App\Services\MetalTraderBankAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

class MetalTraderBankAccountController extends Controller
{
    public function __construct(
        private readonly MetalTraderBankAccountService $bankAccountService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $query = MetalTraderBankAccount::query()
            ->with([
                'trader:id,name,phone',
                'rejectionReason:id,value',
                'reviewer:id,full_name',
            ])
            ->orderByDesc('id');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('sheba')) {
            $sheba = strtoupper(str_replace([' ', '-', '_'], '', (string) $request->input('sheba')));
            $query->where('sheba', 'like', '%' . $sheba . '%');
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

        $page = $query->paginate($request->input('count') ?? 50);

        return response()->json(['data' => $page]);
    }

    public function rejectionReasons(): JsonResponse
    {
        $this->authorizeManage();

        $reasons = MetalTraderBankAccountRejectionReason::active()->get(['id', 'value', 'sort_order']);

        return response()->json(['data' => $reasons]);
    }

    public function approve(Request $request, MetalTraderBankAccount $bankAccount): JsonResponse
    {
        $this->authorizeManage();

        try {
            $account = $this->bankAccountService->approve($bankAccount, $request->user());
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'حساب بانکی تایید شد.',
            'data' => $account,
        ]);
    }

    public function reject(Request $request, MetalTraderBankAccount $bankAccount): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'rejection_reason_id' => ['required', 'integer', 'exists:metal_trader_bank_account_rejection_reasons,id'],
        ], [
            'rejection_reason_id.required' => 'انتخاب علت رد الزامی است.',
        ]);

        try {
            $account = $this->bankAccountService->reject(
                $bankAccount,
                $request->user(),
                (int) $data['rejection_reason_id']
            );
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                throw $e;
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'حساب بانکی رد شد.',
            'data' => $account,
        ]);
    }

    public function destroy(Request $request, MetalTraderBankAccount $bankAccount): JsonResponse
    {
        $this->authorizeManage();

        $this->bankAccountService->deleteByAdmin($bankAccount);

        return response()->json(['message' => 'حساب بانکی حذف شد.']);
    }

    private function authorizeManage(): void
    {
        $admin = auth()->user();
        if (
            !$admin
            || (
                !$admin->isAbleTo('manage-metal-trader-bank-accounts')
                && !$admin->isAbleTo('manage-clients')
            )
        ) {
            abort(403, 'دسترسی به مدیریت حساب‌های بانکی ندارید.');
        }
    }
}
