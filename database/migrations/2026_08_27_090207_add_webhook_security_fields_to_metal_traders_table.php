<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->text('webhook_secret')
                ->nullable()
                ->after('webhook_url');
            $table->string('webhook_secret_version', 20)
                ->default('v1')
                ->after('webhook_secret');
            $table->boolean('webhook_enabled')
                ->default(false)
                ->after('webhook_secret_version');
        });
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->dropColumn([
                'webhook_secret',
                'webhook_secret_version',
                'webhook_enabled',
            ]);
        });
    }
};
