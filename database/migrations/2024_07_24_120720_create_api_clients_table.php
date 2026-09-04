<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\DealingGroup::class)
                ->nullable()
                ->constrained()
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('kimi_account_id')->nullable();
            $table->string('name');
            $table->string('api_key', 32)->unique();
            $table->decimal('phone', 10, 0)->unsigned()->unique();
            $table->decimal('balance', 10,0)->default(0);
            $table->decimal('request_made', 10,0)->default(0);
            $table->unsignedTinyInteger('trade_leverage')->default(0);
            $table->json('products_settings')->nullable();
            $table->enum('status',['active', 'inactive'])->default('active');
            $table->enum('market_opening_notification',['active', 'inactive'])->default('active');
            $table->enum('aggregated_view_of_invoices',['active', 'inactive'])->default('inactive');
            $table->timestamp('last_seen')->nullable();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
