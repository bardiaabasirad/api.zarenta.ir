<?php

namespace App\Services;

use App\Events\SidebarUpdate;
use App\Models\User;
use App\Models\MetalTrader;
use App\Models\MetalTraderBankAccount;
use App\Models\MetalTraderBankAccountRejectionReason;
use App\Rules\IranianSheba;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class MetalTraderBankAccountService
{
    public function __construct(
        private readonly NotificationService $adminNotificationService,
        private readonly MetalTraderNotificationService $traderNotificationService,
    ) {
    }

    public function normalizeSheba(string $value): string
    {
        $persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabicDigits = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $englishDigits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        $value = str_replace($persianDigits, $englishDigits, $value);
        $value = str_replace($arabicDigits, $englishDigits, $value);
        $sheba = Str::upper(str_replace([' ', '-', '_'], '', trim($value)));

        if (!Str::startsWith($sheba, 'IR')) {
            $sheba = 'IR' . $sheba;
        }

        return $sheba;
    }

    public function create(MetalTrader $trader, string $rawSheba): MetalTraderBankAccount
    {
        $sheba = $this->normalizeSheba($rawSheba);

        $validator = validator(
            ['sheba' => $sheba],
            ['sheba' => ['required', new IranianSheba()]]
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $exists = MetalTraderBankAccount::query()
            ->where('metal_trader_id', $trader->id)
            ->where('sheba', $sheba)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'sheba' => ['این شماره شبا قبلاً ثبت شده است.'],
            ]);
        }

        $account = DB::transaction(function () use ($trader, $sheba) {
            return MetalTraderBankAccount::create([
                'metal_trader_id' => $trader->id,
                'sheba' => $sheba,
                'status' => MetalTraderBankAccount::STATUS_PENDING,
            ]);
        });

        $this->notifyAdminsAboutPending();

        return $account;
    }

    public function deleteForTrader(MetalTrader $trader, MetalTraderBankAccount $account): void
    {
        if ((int) $account->metal_trader_id !== (int) $trader->id) {
            throw new LogicException('حساب بانکی متعلق به شما نیست.');
        }

        $wasPending = $account->isPending();
        $account->delete();

        if ($wasPending) {
            SidebarUpdate::dispatch('bank_account', 'none');
        }
    }

    public function approve(MetalTraderBankAccount $account, User $admin): MetalTraderBankAccount
    {
        if (!$account->isPending()) {
            throw new LogicException('فقط حساب‌های در انتظار بررسی قابل تایید هستند.');
        }

        return DB::transaction(function () use ($account, $admin) {
            $account->update([
                'status' => MetalTraderBankAccount::STATUS_APPROVED,
                'rejection_reason_id' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $account->loadMissing('trader');

            $this->traderNotificationService->create([
                'metal_trader_id' => $account->metal_trader_id,
                'title' => 'تایید حساب بانکی',
                'content' => 'حساب بانکی شما با شماره شبا ' . $account->sheba . ' تایید شد.',
                'link' => '/bank-accounts',
                'type' => 'bank_account_approved',
            ]);

            SidebarUpdate::dispatch('bank_account', 'none');

            return $account->fresh(['trader:id,name,phone', 'rejectionReason', 'reviewer:id,full_name']);
        });
    }

    public function reject(
        MetalTraderBankAccount $account,
        User $admin,
        int $rejectionReasonId
    ): MetalTraderBankAccount {
        if ($account->isRejected()) {
            throw new LogicException('این حساب بانکی قبلاً رد شده است.');
        }

        $reason = MetalTraderBankAccountRejectionReason::query()
            ->active()
            ->find($rejectionReasonId);

        if (!$reason) {
            throw ValidationException::withMessages([
                'rejection_reason_id' => ['علت رد انتخاب‌شده معتبر نیست.'],
            ]);
        }

        $wasPending = $account->isPending();

        return DB::transaction(function () use ($account, $admin, $reason, $wasPending) {
            $account->update([
                'status' => MetalTraderBankAccount::STATUS_REJECTED,
                'rejection_reason_id' => $reason->id,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->traderNotificationService->create([
                'metal_trader_id' => $account->metal_trader_id,
                'title' => 'رد حساب بانکی',
                'content' => 'حساب بانکی شما با شماره شبا ' . $account->sheba . ' رد شد. علت: ' . $reason->value,
                'link' => '/bank-accounts',
                'type' => 'bank_account_rejected',
            ]);

            if ($wasPending) {
                SidebarUpdate::dispatch('bank_account', 'none');
            }

            return $account->fresh(['trader:id,name,phone', 'rejectionReason', 'reviewer:id,full_name']);
        });
    }

    public function deleteByAdmin(MetalTraderBankAccount $account): void
    {
        $wasPending = $account->isPending();
        $account->delete();

        if ($wasPending) {
            SidebarUpdate::dispatch('bank_account', 'none');
        }
    }

    public function pendingCount(): int
    {
        return MetalTraderBankAccount::pending()->count();
    }

    private function notifyAdminsAboutPending(): void
    {
        $count = $this->pendingCount();
        $label = $this->pendingLabel($count);

        $this->adminNotificationService->createPublicMultiRead([
            'title' => __('messages.a_new_bank_account_has_been_created'),
            'content' => $label,
            'created_by' => null,
            'link' => '/dealing/bank-accounts?status=pending',
        ]);

        SidebarUpdate::dispatch('bank_account', 'created');
    }

    private function pendingLabel(int $count): string
    {
        if ($count <= 0) {
            return 'حساب بانکی بررسی‌نشده‌ای وجود ندارد.';
        }

        if ($count === 1) {
            return '۱ حساب بانکی بررسی نشده وجود دارد.';
        }

        return $count . ' حساب بانکی بررسی نشده وجود دارد.';
    }
}
