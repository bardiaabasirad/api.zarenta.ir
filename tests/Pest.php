<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

beforeAll(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite.database', ':memory:');

    \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--seed' => true]);
});
