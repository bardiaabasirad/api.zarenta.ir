<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metal_items', function (Blueprint $table) {
            $table->id();

            $table->integer('sort_order')
                ->default(0)
                ->index();

            $table->foreignId('metal_item_group_id')
                ->constrained('metal_item_groups')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('title', 191)->unique();
            $table->boolean('is_spot')->default(false);
            $table->boolean('is_buy_active')->default(true);
            $table->boolean('is_sell_active')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->decimal('purity', 3, 0)->default(750);
            $table->enum('unit', ['gram','count',])->default('gram');
            $table->decimal('price_change_threshold', 10, 0)->default(0);
            $table->decimal('buy_sell_spread', 10, 0)->default(0);
            $table->unsignedTinyInteger('settlement_working_days')->default(0)
                ->comment('تعداد روزهای کاری آینده جهت تسویه حساب؛ مثلا 2 برای T+2');

            $table->foreignId('settlement_metal_item_id')
                ->nullable()
                ->constrained('metal_items')
                ->cascadeOnUpdate()
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metal_items');
    }
};
