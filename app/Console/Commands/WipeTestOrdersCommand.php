<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Variety;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WipeTestOrdersCommand extends Command
{
    protected $signature = 'app:wipe-test-orders
                            {--dry-run : فقط شمارش رکوردها؛ هیچ تغییری اعمال نمی‌شود}
                            {--force : اجرا حتی در محیط production}
                            {--keep-wallets : موجودی و تراکنش‌های واریز/برداشت کیف‌پول را نگه دار}
                            {--keep-treasury : لات‌ها و حرکات خزانه را نگه دار}
                            {--keep-carts : سبد خرید را پاک نکن}
                            {--yes : بدون پرسش تعاملی اجرا کن (برای CI/اسکریپت)}';

    protected $description = 'پاک‌سازی کامل سفارشات فروشگاهی و فلزی و داده‌های وابسته برای ثبت مجدد سفارشات تستی';

    /**
     * جداولی که همیشه (در صورت وجود) خالی می‌شوند.
     *
     * @var list<string>
     */
    private array $coreTables = [
        'metal_order_coverage_allocations',
        'metal_order_coverage_attempts',
        'metal_order_exchanges',
        'metal_order_logs',
        'metal_order_leverage_checks',
        'metal_order_collaterals',
        'metal_orders',
        'order_items',
        'order_logs',
        'transactions',
        'orders',
        'jobs',
        'failed_jobs',
    ];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('در محیط production اجرا نمی‌شود. در صورت اطمینان از --force استفاده کنید.');

            return self::FAILURE;
        }

        $keepWallets = (bool) $this->option('keep-wallets');
        $keepTreasury = (bool) $this->option('keep-treasury');
        $keepCarts = (bool) $this->option('keep-carts');
        $dryRun = (bool) $this->option('dry-run');

        $tables = $this->resolveTables($keepWallets, $keepTreasury, $keepCarts);
        $counts = $this->collectCounts($tables, $keepWallets);

        $this->newLine();
        $this->warn('این عملیات سفارشات و داده‌های وابسته را به‌طور کامل پاک می‌کند.');
        $this->table(['جدول / آیتم', 'تعداد'], collect($counts)->map(fn ($count, $label) => [$label, $count])->values()->all());

        if ($dryRun) {
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

        DB::transaction(function () use ($tables, $keepWallets, $keepTreasury) {
            $this->restoreShopVarietyStock();

            if (! $keepTreasury) {
                $this->nullTreasuryMovementPointers();
            }

            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');
            }

            try {
                foreach ($tables as $table) {
                    if (! Schema::hasTable($table)) {
                        continue;
                    }

                    DB::table($table)->delete();
                    $this->line("cleared: {$table}");
                }
            } finally {
                if ($driver === 'mysql') {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                }
            }

            if (! $keepWallets) {
                $this->resetWallets();
            } else {
                $this->clearOrderLinkedWalletTransactions();
                $this->zeroBlockedBalances();
            }

            $this->clearOrderAuditLogs();
        });

        $this->newLine();
        $this->info('پاک‌سازی سفارشات با موفقیت انجام شد. می‌توانید سفارشات تستی جدید ثبت کنید.');

        if (! $keepWallets) {
            $this->comment('کیف‌پول‌ها صفر شدند؛ در صورت نیاز دوباره واریز تستی انجام دهید.');
        } else {
            $this->comment('تراکنش‌های غیرسفارشی کیف‌پول نگه داشته شد؛ blocked_balance صفر شد.');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTables(bool $keepWallets, bool $keepTreasury, bool $keepCarts): array
    {
        $tables = $this->coreTables;

        $tables = array_merge([
            'metal_physical_delivery_events',
            'metal_physical_delivery_requests',
            'metal_trader_fiat_withdrawals',
        ], $tables);

        if (! $keepTreasury) {
            array_unshift($tables, 'metal_treasury_movements', 'metal_treasury_lots');
        }

        if (! $keepWallets) {
            $tables[] = 'wallet_transactions';
        }

        if (! $keepCarts) {
            $tables[] = 'cart_items';
        }

        return array_values(array_unique($tables));
    }

    /**
     * @param  list<string>  $tables
     * @return array<string, int>
     */
    private function collectCounts(array $tables, bool $keepWallets): array
    {
        $counts = [];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $counts[$table.' (missing)'] = 0;
                continue;
            }

            $counts[$table] = (int) DB::table($table)->count();
        }

        if ($keepWallets && Schema::hasTable('wallet_transactions')) {
            $counts['wallet_transactions (order-linked)'] = (int) DB::table('wallet_transactions')
                ->whereNotNull('metal_order_id')
                ->count();
        }

        if (Schema::hasTable('metal_trader_wallets')) {
            $counts['metal_trader_wallets (will reset balances)'] = (int) DB::table('metal_trader_wallets')->count();
        }

        if (Schema::hasTable('orders')) {
            $counts['shop orders (non-canceled, stock restore)'] = (int) Order::query()
                ->where('status', '!=', OrderStatus::CANCELED)
                ->count();
        }

        return $counts;
    }

    private function restoreShopVarietyStock(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('order_items')) {
            return;
        }

        $orders = Order::query()
            ->where('status', '!=', OrderStatus::CANCELED)
            ->with('items')
            ->get();

        $restored = 0;

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $variety = Variety::query()->find($item->variety_id);
                if (! $variety) {
                    continue;
                }

                $variety->increment('count', (int) $item->count);
                $restored++;
            }
        }

        $this->line("restored variety stock for {$restored} order item(s)");
    }

    private function nullTreasuryMovementPointers(): void
    {
        if (! Schema::hasTable('metal_treasury_movements')) {
            return;
        }

        // شکستن ارجاعات دایره‌ای قبل از حذف allocation/attempt
        $payload = [
            'allocation_id' => null,
            'attempt_id' => null,
            'metal_order_id' => null,
        ];

        if (Schema::hasColumn('metal_treasury_movements', 'physical_delivery_request_id')) {
            $payload['physical_delivery_request_id'] = null;
        }

        DB::table('metal_treasury_movements')->update($payload);
    }

    private function resetWallets(): void
    {
        if (! Schema::hasTable('metal_trader_wallets')) {
            return;
        }

        DB::table('metal_trader_wallets')->update([
            'available_balance' => 0,
            'blocked_balance' => 0,
            'fiat_balance' => 0,
            'avg_buy_price' => 0,
            'updated_at' => now(),
        ]);

        $this->line('reset: metal_trader_wallets balances');
    }

    private function clearOrderLinkedWalletTransactions(): void
    {
        if (! Schema::hasTable('wallet_transactions')) {
            return;
        }

        // ابتدا ارجاعات self-FK را باز کنیم تا حذف امن باشد
        DB::table('wallet_transactions')
            ->whereNotNull('metal_order_id')
            ->update(['related_transaction_id' => null]);

        $deleted = DB::table('wallet_transactions')
            ->whereNotNull('metal_order_id')
            ->delete();

        $this->line("cleared: {$deleted} order-linked wallet_transactions");
    }

    private function zeroBlockedBalances(): void
    {
        if (! Schema::hasTable('metal_trader_wallets')) {
            return;
        }

        DB::table('metal_trader_wallets')->update([
            'blocked_balance' => 0,
            'updated_at' => now(),
        ]);

        $this->line('reset: metal_trader_wallets.blocked_balance');
    }

    private function clearOrderAuditLogs(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $deleted = DB::table('audit_logs')
            ->whereIn('subject_type', [
                'order',
                'metal_order',
                Order::class,
                \App\Models\MetalOrder::class,
            ])
            ->delete();

        $this->line("cleared: {$deleted} order-related audit_logs");
    }
}
