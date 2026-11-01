<?php

namespace App\Services;

use App\Events\SidebarUpdate;
use App\Models\User;
use App\Models\MetalTrader;
use App\Models\MetalTraderBankAccount;
use App\Models\MetalTraderFiatWithdrawal;
use App\Models\MetalTraderFiatWithdrawalRejectionReason;
use App\Models\MetalTraderWallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

class MetalTraderFiatWithdrawalService
{
    public function __construct(
        private readonly NotificationService $adminNotificationService,
        private readonly MetalTraderNotificationService $traderNotificationService,
    ) {
    }

    public function create(MetalTrader $trader, string $amount, int $bankAccountId): MetalTraderFiatWithdrawal
    {
        if (!preg_match('/^\d+$/', $amount) || bccomp($amount, '0', 0) <= 0) {
            throw ValidationException::withMessages([
                'amount' => ['مبلغ برداشت باید عددی صحیح و بزرگ‌تر از صفر باشد.'],
            ]);
        }

        $bankAccount = MetalTraderBankAccount::query()
            ->where('metal_trader_id', $trader->id)
            ->whereKey($bankAccountId)
            ->first();

        if (!$bankAccount) {
            throw ValidationException::withMessages([
                'metal_trader_bank_account_id' => ['حساب بانکی انتخاب‌شده معتبر نیست.'],
            ]);
        }

        if (!$bankAccount->isApproved()) {
            throw ValidationException::withMessages([
                'metal_trader_bank_account_id' => ['فقط حساب بانکی تاییدشده قابل انتخاب است.'],
            ]);
        }

        $withdrawal = DB::transaction(function () use ($trader, $amount, $bankAccount) {
            $wallet = $this->lockFiatWallet($trader->id);
            $before = $this->decimal((string) ($wallet->available_balance ?? '0'));

            if (bccomp($before, $amount, 0) < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['موجودی تومان کافی نیست.'],
                ]);
            }

            $after = bcsub($before, $amount, 0);
            $wallet->available_balance = $after;
            $wallet->save();

            $withdrawal = MetalTraderFiatWithdrawal::create([
                'metal_trader_id' => $trader->id,
                'metal_trader_bank_account_id' => $bankAccount->id,
                'sheba' => $bankAccount->sheba,
                'amount' => $amount,
                'status' => MetalTraderFiatWithdrawal::STATUS_PENDING,
            ]);

            $tx = WalletTransaction::create([
                'metal_trader_wallet_id' => $wallet->id,
                'metal_order_id' => null,
                'metal_trader_fiat_withdrawal_id' => $withdrawal->id,
                'related_transaction_id' => null,
                'amount' => $amount,
                'type' => 'withdraw',
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => 'درخواست برداشت تومان به شبا ' . $bankAccount->sheba,
            ]);

            $withdrawal->update(['wallet_transaction_id' => $tx->id]);

            return $withdrawal->fresh(['bankAccount', 'walletTransaction']);
        });

        $this->notifyAdminsAboutPending();

        return $withdrawal;
    }

    public function cancelForTrader(MetalTrader $trader, MetalTraderFiatWithdrawal $withdrawal): MetalTraderFiatWithdrawal
    {
        if ((int) $withdrawal->metal_trader_id !== (int) $trader->id) {
            throw new LogicException('درخواست برداشت متعلق به شما نیست.');
        }

        if (!$withdrawal->isPending()) {
            throw new LogicException('فقط درخواست‌های در انتظار قابل لغو هستند.');
        }

        return DB::transaction(function () use ($withdrawal) {
            $this->refund($withdrawal, 'لغو درخواست برداشت توسط کاربر');
            $withdrawal->update([
                'status' => MetalTraderFiatWithdrawal::STATUS_CANCELLED,
            ]);

            SidebarUpdate::dispatch('fiat_withdrawal', 'none');

            return $withdrawal->fresh(['bankAccount']);
        });
    }

    public function markPaid(MetalTraderFiatWithdrawal $withdrawal, User $admin, ?string $notes = null): MetalTraderFiatWithdrawal
    {
        if (!$withdrawal->isPending()) {
            throw new LogicException('فقط درخواست‌های در انتظار قابل تایید پرداخت هستند.');
        }

        $withdrawal->update([
            'status' => MetalTraderFiatWithdrawal::STATUS_PAID,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'notes' => $notes,
            'reject_reason' => null,
            'rejection_reason_id' => null,
        ]);

        $this->traderNotificationService->create([
            'metal_trader_id' => $withdrawal->metal_trader_id,
            'title' => 'پرداخت برداشت تومان',
            'content' => 'درخواست برداشت ' . number_format((float) $withdrawal->amount) . ' تومان به حساب ' . $withdrawal->sheba . ' انجام شد.',
            'link' => '/withdrawals',
            'type' => 'fiat_withdrawal_paid',
        ]);

        SidebarUpdate::dispatch('fiat_withdrawal', 'none');

        return $withdrawal->fresh(['trader:id,name,phone', 'bankAccount', 'reviewer:id,full_name']);
    }

    public function reject(
        MetalTraderFiatWithdrawal $withdrawal,
        User $admin,
        int $rejectionReasonId
    ): MetalTraderFiatWithdrawal {
        if (!$withdrawal->isPending()) {
            throw new LogicException('فقط درخواست‌های در انتظار قابل رد هستند.');
        }

        $reason = MetalTraderFiatWithdrawalRejectionReason::query()
            ->active()
            ->whereKey($rejectionReasonId)
            ->first();

        if (!$reason) {
            throw ValidationException::withMessages([
                'rejection_reason_id' => ['علت رد انتخاب‌شده معتبر نیست.'],
            ]);
        }

        return DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $this->refund($withdrawal, 'رد درخواست برداشت: ' . $reason->value);

            $withdrawal->update([
                'status' => MetalTraderFiatWithdrawal::STATUS_REJECTED,
                'rejection_reason_id' => $reason->id,
                'reject_reason' => $reason->value,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->traderNotificationService->create([
                'metal_trader_id' => $withdrawal->metal_trader_id,
                'title' => 'رد برداشت تومان',
                'content' => 'درخواست برداشت ' . number_format((float) $withdrawal->amount) . ' تومان رد شد. علت: ' . $reason->value,
                'link' => '/withdrawals',
                'type' => 'fiat_withdrawal_rejected',
            ]);

            SidebarUpdate::dispatch('fiat_withdrawal', 'none');

            return $withdrawal->fresh([
                'trader:id,name,phone',
                'bankAccount',
                'reviewer:id,full_name',
                'rejectionReason:id,value',
            ]);
        });
    }

    public function pendingCount(): int
    {
        return MetalTraderFiatWithdrawal::pending()->count();
    }

    private function refund(MetalTraderFiatWithdrawal $withdrawal, string $description): void
    {
        $wallet = $this->lockFiatWallet($withdrawal->metal_trader_id);
        $amount = $this->decimal((string) $withdrawal->amount);
        $before = $this->decimal((string) ($wallet->available_balance ?? '0'));
        $after = bcadd($before, $amount, 0);

        $wallet->available_balance = $after;
        $wallet->save();

        $tx = WalletTransaction::create([
            'metal_trader_wallet_id' => $wallet->id,
            'metal_order_id' => null,
            'metal_trader_fiat_withdrawal_id' => $withdrawal->id,
            'related_transaction_id' => $withdrawal->wallet_transaction_id,
            'amount' => $amount,
            'type' => 'deposit',
            'balance_before' => $before,
            'balance_after' => $after,
            'description' => $description,
        ]);

        $withdrawal->update(['refund_wallet_transaction_id' => $tx->id]);
    }

    private function lockFiatWallet(int $traderId): MetalTraderWallet
    {
        $wallet = MetalTraderWallet::query()
            ->where('metal_trader_id', $traderId)
            ->whereNull('metal_item_id')
            ->lockForUpdate()
            ->first();

        if (!$wallet) {
            $wallet = MetalTraderWallet::create([
                'metal_trader_id' => $traderId,
                'metal_item_id' => null,
                'available_balance' => '0',
                'blocked_balance' => '0',
            ]);
            $wallet = MetalTraderWallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
        }

        return $wallet;
    }

    private function notifyAdminsAboutPending(): void
    {
        $count = $this->pendingCount();
        $label = $count === 1
            ? '۱ درخواست برداشت تومان بررسی نشده وجود دارد.'
            : $count . ' درخواست برداشت تومان بررسی نشده وجود دارد.';

        $this->adminNotificationService->createPublicMultiRead([
            'title' => 'درخواست برداشت تومان',
            'content' => $label,
            'created_by' => null,
            'link' => '/accounting/fiat-withdrawals?status=pending',
        ]);

        SidebarUpdate::dispatch('fiat_withdrawal', 'created');
    }

    private function decimal(string $value): string
    {
        return bcadd($value, '0', 0);
    }
}
