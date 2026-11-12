<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sub_feature_pivot');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_features');

        Schema::table('metal_traders', function (Blueprint $table) {
            if (Schema::hasColumn('metal_traders', 'webhook_url')) {
                $table->dropColumn([
                    'webhook_url',
                    'webhook_secret',
                    'webhook_secret_version',
                    'webhook_enabled',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('webhook_secret_version')->nullable();
            $table->boolean('webhook_enabled')->default(false);
        });

        // Recreate subscription tables minimally for rollback only.
        Schema::create('subscription_features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metal_trader_id')->constrained('metal_traders')->cascadeOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->timestamps();
        });

        Schema::create('sub_feature_pivot', function (Blueprint $table) {
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('subscription_feature_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->primary(['subscription_id', 'subscription_feature_id']);
        });
    }
};
