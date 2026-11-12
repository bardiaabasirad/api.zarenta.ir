<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            if (Schema::hasColumn('metal_traders', 'kimi_account_id')) {
                $table->dropColumn('kimi_account_id');
            }

            if (Schema::hasColumn('metal_traders', 'aggregated_view_of_invoices')) {
                $table->dropColumn('aggregated_view_of_invoices');
            }
        });

        if (Schema::hasTable('metal_items')) {
            Schema::table('metal_items', function (Blueprint $table) {
                if (Schema::hasColumn('metal_items', 'kimia_product_id')) {
                    $table->dropColumn('kimia_product_id');
                }

                if (Schema::hasColumn('metal_items', 'equivalent_to')) {
                    $table->dropColumn('equivalent_to');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('metal_traders', function (Blueprint $table) {
            if (! Schema::hasColumn('metal_traders', 'kimi_account_id')) {
                $table->string('kimi_account_id')->nullable();
            }

            if (! Schema::hasColumn('metal_traders', 'aggregated_view_of_invoices')) {
                $table->enum('aggregated_view_of_invoices', ['active', 'inactive'])->default('inactive');
            }
        });

        if (Schema::hasTable('metal_items')) {
            Schema::table('metal_items', function (Blueprint $table) {
                if (! Schema::hasColumn('metal_items', 'kimia_product_id')) {
                    $table->unsignedBigInteger('kimia_product_id')->nullable();
                }

                if (! Schema::hasColumn('metal_items', 'equivalent_to')) {
                    $table->unsignedBigInteger('equivalent_to')->nullable();
                }
            });
        }
    }
};
