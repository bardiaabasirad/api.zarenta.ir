<?php

namespace App\Http\Controllers\api\v1\admin;

use App\Http\Controllers\Controller;
use App\Models\MetalTraderFiatWithdrawal;
use App\Models\MetalTraderFiatWithdrawalRejectionReason;
use App\Services\MetalTraderFiatWithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Throwable;

class MetalTraderFiatWithdrawalController extends Controller
{
    public function __construct(
        private readonly MetalTraderFiatWithdrawalService $withdrawalService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeManage();

        $query = MetalTraderFiatWithdrawal::query()
            ->with([
                'trader:id,name,phone',
                'bankAccount:id,sheba,status',
                'reviewer:id,full_name',
                'rejectionReason:id,value',
            ])
            ->orderByDesc('id');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('metal_trader_id')) {
            $query->where('metal_trader_id', $request->integer('metal_trader_id'));
        }

        if ($request->filled('sheba')) {
            $sheba = strtoupper(str_replace([' ', '-', '_'], '', (string) $request->input('sheba')));
            $query->where('sheba', 'like', '%' . $sheba . '%');
        }

        if ($request->filled('trader_name')) {
            $name = $request->input('trader_name');
            $query->whereHas('trader', function ($q) use ($name) {
                $q->where('name', 'like', '%' . $name . '%')
                    ->orWhere('phone', 'like', '%' . $name . '%');
            });
        }

        return response()->json([
            'data' => $query->paginate($request->input('count') ?? 50),
        ]);
    }

    public function markPaid(Request $request, MetalTraderFiatWithdrawal $withdrawal): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $item = $this->withdrawalService->markPaid(
                $withdrawal,
                $request->user(),
                $data['notes'] ?? null
            );
        } catch (LogicException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'برداشت به‌عنوان پرداخت‌شده ثبت شد.',
            'data' => $item,
        ]);
    }

    public function rejectionReasons(): JsonResponse
    {
        $this->authorizeManage();

        $reasons = MetalTraderFiatWithdrawalRejectionReason::active()->get(['id', 'value', 'sort_order']);

        return response()->json(['data' => $reasons]);
    }

    public function reject(Request $request, MetalTraderFiatWithdrawal $withdrawal): JsonResponse
    {
        $this->authorizeManage();

        $data = $request->validate([
            'rejection_reason_id' => [
                'required',
                'integer',
                'exists:metal_trader_fiat_withdrawal_rejection_reasons,id',
            ],
        ], [
            'rejection_reason_id.required' => 'انتخاب علت رد الزامی است.',
        ]);

        try {
            $item = $this->withdrawalService->reject(
                $withdrawal,
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
            'message' => 'درخواست برداشت رد و مبلغ به کیف کاربر بازگردانده شد.',
            'data' => $item,
        ]);
    }

    private function authorizeManage(): void
    {
        $admin = auth()->user();
        if (
            !$admin
            || (
                !$admin->isAbleTo('manage-metal-trader-fiat-withdrawals')
                && !$admin->isAbleTo('manage-accounting')
            )
        ) {
            abort(403, 'دسترسی به مدیریت برداشت تومان ندارید.');
        }
    }
}
