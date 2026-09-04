<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->change();
            $table->dateTime('ends_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('starts_at')->nullable()->change();
            $table->timestamp('ends_at')->nullable()->change();
        });
    }
};
