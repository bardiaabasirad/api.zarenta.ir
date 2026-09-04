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
        Schema::create('tel_market_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\ReferenceChannel::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->enum('type', ['tomorrow','day_after_tomorrow','gold_coin_86','gold_half_coin_86','gold_quarter_coin_86','gold_coin_old_version','gold_coin_half_old_version','gold_coin_quarter_old_version','gold_half_coin_403','gold_quarter_coin_403','gold_coin_404','gold_half_coin_404','gold_quarter_coin_404'])->default('tomorrow');
            $table->string('buy')->nullable();
            $table->string('sell')->nullable();
            $table->timestamp('time');
            $table->timestamps();

            // 📌 ایندکس ترکیبی برای سرعت بالای جستجو و ترتیب
            $table->index(['type', 'created_at'], 'type_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tel_market_prices');
    }
};
