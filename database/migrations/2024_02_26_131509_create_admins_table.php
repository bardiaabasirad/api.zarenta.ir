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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 100)->nullable();
            $table->decimal('phone', 10, 0)->unsigned()->unique();
            $table->decimal('national_code', 10, 0)->unsigned()->unique();
            $table->string('password');
            $table->string('avatar')->nullable();
            $table->enum('status',['active','inactive'])->default('active');
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
        Schema::dropIfExists('admins');
    }
};
