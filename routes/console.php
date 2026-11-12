<?php

use Illuminate\Support\Facades\Schedule;

//Schedule::job(App\Jobs\RefreshJibitToken::class)
//    ->timezone('Asia/Tehran')
//    ->dailyAt('10:45')
//    ->withoutOverlapping(10);

//Schedule::job(new App\Jobs\DeleteOldRates())
//    ->daily()
//    ->at('01:00')
//    ->timezone('Asia/Tehran');

Schedule::job(new App\Jobs\SettleDueCommitmentsJob)
    ->dailyAt('00:00')
    ->withoutOverlapping(60);

Schedule::job(new \App\Jobs\MarkOverdueMetalOrderCoveragesJob)
    ->timezone('Asia/Tehran')
    ->dailyAt('00:05')
    ->withoutOverlapping(60);

Schedule::job(new \App\Jobs\AutoCloseStaleSupportTicketsJob)
    ->timezone('Asia/Tehran')
    ->hourly()
    ->withoutOverlapping(30);

Schedule::command('orders:cancel-unpaid')
    ->everyMinute()
    ->runInBackground();

Schedule::command('hamtala:inquire-orders-status --limit=50 --status=placed')
    ->everyFiveSeconds()
    ->withoutOverlapping(1)
    ->runInBackground();
