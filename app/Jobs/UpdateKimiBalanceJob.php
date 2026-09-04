<?php

namespace App\Jobs;

use App\Events\KimiBalanceUpdated;
use App\Models\MetalTrader;
use App\Services\KimiaService;
use Exception;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateKimiBalanceJob
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct(public MetalTrader $metalTrader)
    {
        //
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(): void
    {
        $balances = KimiaService::getVoucherBalance($this->metalTrader->kimi_account_id, $this->metalTrader->id);

        if ($balances) {
            $balances = is_array($balances) ? $balances : $balances->toArray();

            KimiBalanceUpdated::dispatch($this->metalTrader, $balances);
        }
    }
}
