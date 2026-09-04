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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Admin::class, 'created_by')
                ->constrained('admins')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignIdFor(\App\Models\SizeUnit::class)
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->decimal('gold_carat', 4,0);
            $table->string('title',255);
            $table->text('description')->nullable();
            $table->enum('status',['active','inactive'])->default('active');
            $table->enum('credit_payment',['active','inactive'])->default('active');
            $table->enum('vat',['active','inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
