<?php

use App\Jobs\DeleteOldRates;
use App\Jobs\RefreshJibitToken;
use Illuminate\Support\Facades\Schedule;

Schedule::job(RefreshJibitToken::class)
    ->timezone('Asia/Tehran')
    ->dailyAt('10:45')
    ->withoutOverlapping(10);

Schedule::job(new DeleteOldRates())
    ->daily()
    ->at('01:00')
    ->timezone('Asia/Tehran');

Schedule::command('job:market-price')
    ->everyMinute()
    ->runInBackground();

Schedule::command('job:unpaid-cancellation')
    ->everyMinute()
    ->runInBackground();

Schedule::command('hamtala:inquire-orders-status --limit=50 --status=placed')
    ->everyFiveSeconds()
    ->withoutOverlapping(1)
    ->runInBackground();

//Schedule::command('hamtala:check-price-feed')
//    ->everyMinute()
//    ->runInBackground();
