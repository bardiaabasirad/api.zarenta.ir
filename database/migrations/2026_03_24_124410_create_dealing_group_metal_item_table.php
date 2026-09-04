<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dealing_group_metal_item', function (Blueprint $table) {
            $table->foreignIdFor(\App\Models\DealingGroup::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\MetalItem::class)
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('tolerance_type', ['fixed_amount','percentage'])->default('fixed_amount');
            $table->enum('display_mode', ['quotation','per_gram'])->default('quotation');
            $table->decimal('min_order', 10, 3)->nullable();
            $table->decimal('max_order', 10, 3)->nullable();
            $table->decimal('buy_fee_margin', 16, 2)->nullable();
            $table->decimal('sell_fee_margin', 16, 2)->nullable();
            $table->timestamps();

            $table->primary(['dealing_group_id', 'metal_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dealing_group_metal_item');
    }
};
