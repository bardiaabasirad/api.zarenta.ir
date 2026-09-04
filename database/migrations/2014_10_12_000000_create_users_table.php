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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name')->nullable();
            $table->decimal('phone', 10, 0)->unsigned()->unique();
            $table->string('national_code', 10)->nullable();
            $table->date('born_at')->nullable();
            $table->enum('status',['initial','incomplete','active', 'inactive'])->default('initial');
            $table->string('ban_reason')->nullable();
            $table->text('ban_description')->nullable();
            $table->string('avatar')->nullable();
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
        Schema::dropIfExists('users');
    }
};
