<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE widgets MODIFY COLUMN type ENUM('navbar','header','footer','hero','last_products','search') NOT NULL");

        $exists = DB::table('widgets')->where('type', 'search')->exists();
        if (!$exists) {
            $widgetId = DB::table('widgets')->insertGetId([
                'title' => 'جستجو',
                'type' => 'search',
            ]);

            DB::table('sections')
                ->where('order', '>=', 3)
                ->update(['order' => DB::raw('`order` + 1')]);

            $adminId = DB::table('admins')->orderBy('id')->value('id') ?? 1;

            DB::table('sections')->insert([
                'created_by' => $adminId,
                'sectionable_type' => 'App\\Models\\Widget',
                'sectionable_id' => $widgetId,
                'status' => 'active',
                'show_on' => 'all',
                'order' => 3,
                'fixed' => false,
                'show_only_available_products' => null,
                'description' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $widget = DB::table('widgets')->where('type', 'search')->first();
        if ($widget) {
            DB::table('sections')
                ->where('sectionable_type', 'App\\Models\\Widget')
                ->where('sectionable_id', $widget->id)
                ->delete();

            DB::table('widgets')->where('id', $widget->id)->delete();
        }

        DB::statement("ALTER TABLE widgets MODIFY COLUMN type ENUM('navbar','header','footer','hero','last_products') NOT NULL");
    }
};
