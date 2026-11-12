<?php

namespace App\Console\Commands;

use App\Models\MetalOrder;
use App\Models\MetalSettlementCase;
use App\Models\MetalTrader;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeMetalTraderCommand extends Command
{
    protected $signature = 'app:wipe-metal-trader
                            {trader? : شناسه تریدر}
                            {--phone= : پیدا کردن تریدر با شماره تلفن}
                            {--name= : پیدا کردن تریدر با نام دقیق}
                            {--name-like= : پیدا کردن تریدر با LIKE روی نام}
                            {--dry-run : فقط شمارش؛ هیچ حذفی انجام نمی‌شود}
                            {--force : اجرا حتی در محیط production}
                            {--yes : بدون پرسش تعاملی اجرا کن}';

    protected $description = 'حذف کامل یک تریدر فلزی و تمام داده‌های وابسته (سفارش، کیف، پرونده سررسید، برداشت و ...)';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('در محیط production اجرا نمی‌شود. در صورت اطمینان از --force استفاده کنید.');

            return self::FAILURE;
        }

        $traders = $this->resolveTraders();
        if ($traders->isEmpty()) {
            $this->error('تریدری با معیارهای داده‌شده پیدا نشد. یکی از trader / --phone / --name / --name-like را بدهید.');

            return self::FAILURE;
        }

        if ($traders->count() > 1 && ! $this->option('yes') && ! $this->option('dry-run')) {
            $this->warn('چند تریدر پیدا شد:');
            $this->table(
                ['id', 'name', 'phone', 'deleted_at'],
                $traders->map(fn (MetalTrader $t) => [$t->id, $t->name, $t->phone, $t->deleted_at])->all()
            );

            if (! $this->confirm('همه این تریدرها حذف شوند؟', false)) {
                $this->info('لغو شد.');

                return self::SUCCESS;
            }
        }

        $plan = [];
        foreach ($traders as $trader) {
            $plan[$trader->id] = $this->collectPlan($trader);
        }

        $this->newLine();
        $this->warn('این عملیات تریدر و داده‌های وابسته را به‌طور کامل (hard delete) پاک می‌کند.');

        foreach ($plan as $traderId => $counts) {
            $trader = $traders->firstWhere('id', $traderId);
            $this->line("تریدر #{$traderId} — {$trader?->name} ({$trader?->phone})");
            $this->table(
                ['آیتم', 'تعداد'],
                collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all()
            );
            $this->newLine();
        }

        if ($this->option('dry-run')) {
            $this->info('حالت dry-run: تغییری اعمال نشد.');

            return self::SUCCESS;
        }

        if (! $this->option('yes')) {
            if (! $this->confirm('ادامه می‌دهید؟ این کار قابل بازگشت نیست.', false)) {
                $this->info('لغو شد.');

                return self::SUCCESS;
            }

            $token = $this->ask('برای تأیید نهایی عبارت WIPE را وارد کنید');
            if ($token !== 'WIPE') {
                $this->error('عبارت تأیید نادرست بود. عملیات لغو شد.');

                return self::FAILURE;
            }
        }

        DB::transaction(function () use ($traders) {
            foreach ($traders as $trader) {
                $this->wipeTrader($trader);
            }
        });

        $this->newLine();
        $this->info('حذف تریدر(ها) با موفقیت انجام شد.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, MetalTrader>
     */
    private function resolveTraders(): Collection
    {
        if (
            ($this->argument('trader') === null || $this->argument('trader') === '')
            && ! $this->option('phone')
            && ! $this->option('name')
            && ! $this->option('name-like')
        ) {
            return collect();
        }

        $query = MetalTrader::withTrashed()->newQuery();

        if ($this->argument('trader') !== null && $this->argument('trader') !== '') {
            $query->where('id', (int) $this->argument('trader'));
        }

        if ($this->option('phone')) {
            $query->where('phone', $this->option('phone'));
        }

        if ($this->option('name')) {
            $query->where('name', $this->option('name'));
        }

        if ($this->option('name-like')) {
            $query->where('name', 'like', $this->option('name-like'));
        }

        return $query->orderBy('id')->get();
    }

    /**
     * @return array<string, int>
     */
    private function collectPlan(MetalTrader $trader): array
    {
        $ids = $this->relatedIds($trader);
        $counts = [
            'metal_orders' => $ids['orderIds']->count(),
            'metal_trader_wallets' => $ids['walletIds']->count(),
            'metal_settlement_cases' => $ids['caseIds']->count(),
            'metal_physical_delivery_requests' => $ids['deliveryIds']->count(),
            'metal_trader_fiat_withdrawals' => $ids['withdrawalIds']->count(),
        ];

        if (Schema::hasTable('wallet_transactions') && $ids['walletIds']->isNotEmpty()) {
            $counts['wallet_transactions'] = (int) DB::table('wallet_transactions')
                ->whereIn('metal_trader_wallet_id', $ids['walletIds'])
                ->count();
        }

        foreach ([
            'metal_trader_bank_accounts',
            'metal_trader_notifications',
            'metal_trader_logs',
            'metal_trader_suspensions',
        ] as $table) {
            if (Schema::hasTable($table)) {
                $counts[$table] = (int) DB::table($table)->where('metal_trader_id', $trader->id)->count();
            }
        }

        if (Schema::hasTable('personal_access_tokens')) {
            $counts['personal_access_tokens'] = (int) DB::table('personal_access_tokens')
                ->whereIn('tokenable_type', $this->morphTypes())
                ->where('tokenable_id', $trader->id)
                ->count();
        }

        $counts['metal_traders'] = 1;

        return $counts;
    }

    /**
     * @return array{
     *   orderIds: Collection<int, int>,
     *   walletIds: Collection<int, int>,
     *   caseIds: Collection<int, int>,
     *   deliveryIds: Collection<int, int>,
     *   withdrawalIds: Collection<int, int>
     * }
     */
    private function relatedIds(MetalTrader $trader): array
    {
        $orderIds = Schema::hasTable('metal_orders')
            ? DB::table('metal_orders')
                ->where('created_type', 'metal_trader')
                ->where('created_id', $trader->id)
                ->pluck('id')
            : collect();

        $walletIds = Schema::hasTable('metal_trader_wallets')
            ? DB::table('metal_trader_wallets')->where('metal_trader_id', $trader->id)->pluck('id')
            : collect();

        $caseIds = Schema::hasTable('metal_settlement_cases')
            ? DB::table('metal_settlement_cases')->where('metal_trader_id', $trader->id)->pluck('id')
            : collect();

        $deliveryIds = Schema::hasTable('metal_physical_delivery_requests')
            ? DB::table('metal_physical_delivery_requests')->where('metal_trader_id', $trader->id)->pluck('id')
            : collect();

        $withdrawalIds = Schema::hasTable('metal_trader_fiat_withdrawals')
            ? DB::table('metal_trader_fiat_withdrawals')->where('metal_trader_id', $trader->id)->pluck('id')
            : collect();

        return compact('orderIds', 'walletIds', 'caseIds', 'deliveryIds', 'withdrawalIds');
    }

    private function wipeTrader(MetalTrader $trader): void
    {
        $ids = $this->relatedIds($trader);
        $traderId = (int) $trader->id;

        $this->line("wiping trader #{$traderId} ...");

        // لات‌های خزانه نگه داشته می‌شوند؛ فقط ارجاع به سفارش این تریدر قطع می‌شود
        if (Schema::hasTable('metal_treasury_movements') && $ids['orderIds']->isNotEmpty()) {
            $payload = [
                'metal_order_id' => null,
                'allocation_id' => null,
                'attempt_id' => null,
            ];
            if (Schema::hasColumn('metal_treasury_movements', 'physical_delivery_request_id')) {
                $payload['physical_delivery_request_id'] = null;
            }

            DB::table('metal_treasury_movements')
                ->whereIn('metal_order_id', $ids['orderIds'])
                ->update($payload);
            $this->line('  nulled: metal_treasury_movements pointers');
        }

        $this->clearWalletTransactions($ids);

        $this->deleteByIds('metal_settlement_case_order', 'metal_settlement_case_id', $ids['caseIds']);
        $this->deleteByIds('metal_order_coverage_allocations', 'metal_order_id', $ids['orderIds']);
        $this->deleteByIds('metal_order_coverage_attempts', 'metal_order_id', $ids['orderIds']);
        $this->deleteByIds('metal_order_exchanges', 'metal_order_id', $ids['orderIds']);
        $this->deleteByIds('metal_order_logs', 'metal_order_id', $ids['orderIds']);
        $this->deleteByIds('metal_order_leverage_checks', 'metal_order_id', $ids['orderIds']);
        $this->deleteByIds('metal_order_collaterals', 'metal_order_id', $ids['orderIds']);
        $this->deleteByIds('metal_settlement_cases', 'id', $ids['caseIds']);
        $this->deleteByIds('metal_physical_delivery_events', 'metal_physical_delivery_request_id', $ids['deliveryIds']);
        $this->deleteByIds('metal_physical_delivery_requests', 'id', $ids['deliveryIds']);
        $this->deleteByIds('metal_trader_fiat_withdrawals', 'id', $ids['withdrawalIds']);

        $this->deleteWhere('metal_trader_bank_accounts', 'metal_trader_id', $traderId);
        $this->deleteWhere('metal_trader_notifications', 'metal_trader_id', $traderId);
        $this->deleteWhere('metal_trader_logs', 'metal_trader_id', $traderId);
        $this->deleteWhere('metal_trader_suspensions', 'metal_trader_id', $traderId);
        $this->deleteWhere('inquiry_metal_trader', 'metal_trader_id', $traderId);

        $this->deleteByIds('metal_orders', 'id', $ids['orderIds']);
        $this->deleteByIds('metal_trader_wallets', 'id', $ids['walletIds']);

        if (Schema::hasTable('personal_access_tokens')) {
            $n = DB::table('personal_access_tokens')
                ->whereIn('tokenable_type', $this->morphTypes())
                ->where('tokenable_id', $traderId)
                ->delete();
            $this->line("  cleared: {$n} personal_access_tokens");
        }

        if (Schema::hasTable('verification_codes') && filled($trader->phone)) {
            $n = DB::table('verification_codes')
                ->whereIn('authenticatable_type', $this->morphTypes())
                ->where('phone', (string) $trader->phone)
                ->delete();
            $this->line("  cleared: {$n} verification_codes");
        }

        if (Schema::hasTable('auth_logs')) {
            $n = DB::table('auth_logs')
                ->whereIn('authenticatable_type', $this->morphTypes())
                ->where('authenticatable_id', $traderId)
                ->delete();
            $this->line("  cleared: {$n} auth_logs");
        }

        $this->clearAuditLogs($traderId, $ids);

        MetalTrader::withTrashed()->where('id', $traderId)->forceDelete();
        $this->line("  deleted: metal_traders #{$traderId}");
    }

    /**
     * @param  array{
     *   orderIds: Collection<int, int>,
     *   walletIds: Collection<int, int>,
     *   caseIds: Collection<int, int>,
     *   deliveryIds: Collection<int, int>,
     *   withdrawalIds: Collection<int, int>
     * }  $ids
     */
    private function clearWalletTransactions(array $ids): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        $hasAny =
            $ids['walletIds']->isNotEmpty()
            || $ids['orderIds']->isNotEmpty()
            || $ids['caseIds']->isNotEmpty()
            || $ids['deliveryIds']->isNotEmpty()
            || $ids['withdrawalIds']->isNotEmpty();

        if (! $hasAny) {
            return;
        }

        $apply = function (Builder $q) use ($ids): void {
            $q->where(function (Builder $inner) use ($ids) {
                if ($ids['walletIds']->isNotEmpty()) {
                    $inner->orWhereIn('metal_trader_wallet_id', $ids['walletIds']);
                }
                if ($ids['orderIds']->isNotEmpty()) {
                    $inner->orWhereIn('metal_order_id', $ids['orderIds']);
                }
                if (
                    Schema::hasColumn('wallet_transactions', 'metal_settlement_case_id')
                    && $ids['caseIds']->isNotEmpty()
                ) {
                    $inner->orWhereIn('metal_settlement_case_id', $ids['caseIds']);
                }
                if (
                    Schema::hasColumn('wallet_transactions', 'metal_physical_delivery_request_id')
                    && $ids['deliveryIds']->isNotEmpty()
                ) {
                    $inner->orWhereIn('metal_physical_delivery_request_id', $ids['deliveryIds']);
                }
                if (
                    Schema::hasColumn('wallet_transactions', 'metal_trader_fiat_withdrawal_id')
                    && $ids['withdrawalIds']->isNotEmpty()
                ) {
                    $inner->orWhereIn('metal_trader_fiat_withdrawal_id', $ids['withdrawalIds']);
                }
            });
        };

        $updateQuery = DB::table('wallet_transactions');
        $apply($updateQuery);
        $updateQuery->update(['related_transaction_id' => null]);

        $deleteQuery = DB::table('wallet_transactions');
        $apply($deleteQuery);
        $deleted = $deleteQuery->delete();
        $this->line("  cleared: {$deleted} wallet_transactions");
    }

    /**
     * @param  array{
     *   orderIds: Collection<int, int>,
     *   caseIds: Collection<int, int>
     * }  $ids
     */
    private function clearAuditLogs(int $traderId, array $ids): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $n = DB::table('audit_logs')
            ->where(function (Builder $q) use ($traderId, $ids) {
                $q->where(function (Builder $inner) use ($traderId) {
                    $inner->whereIn('actor_type', $this->morphTypes())
                        ->where('actor_id', $traderId);
                })->orWhere(function (Builder $inner) use ($traderId) {
                    $inner->whereIn('subject_type', $this->morphTypes())
                        ->where('subject_id', $traderId);
                });

                if ($ids['orderIds']->isNotEmpty()) {
                    $q->orWhere(function (Builder $inner) use ($ids) {
                        $inner->whereIn('subject_type', ['metal_order', MetalOrder::class])
                            ->whereIn('subject_id', $ids['orderIds']);
                    });
                }

                if ($ids['caseIds']->isNotEmpty()) {
                    $q->orWhere(function (Builder $inner) use ($ids) {
                        $inner->whereIn('subject_type', ['metal_settlement_case', MetalSettlementCase::class])
                            ->whereIn('subject_id', $ids['caseIds']);
                    });
                }
            })
            ->delete();

        $this->line("  cleared: {$n} audit_logs");
    }

    /**
     * @return list<string>
     */
    private function morphTypes(): array
    {
        return [
            'metal_trader',
            MetalTrader::class,
        ];
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function deleteByIds(string $table, string $column, Collection $ids): void
    {
        if (! Schema::hasTable($table) || $ids->isEmpty()) {
            return;
        }

        $n = DB::table($table)->whereIn($column, $ids)->delete();
        $this->line("  cleared: {$n} {$table}");
    }

    private function deleteWhere(string $table, string $column, int $value): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $n = DB::table($table)->where($column, $value)->delete();
        $this->line("  cleared: {$n} {$table}");
    }
}
