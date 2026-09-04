<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            PRSeeder::class,
            RoleAdminSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ColorSeeder::class,
            PageSeeder::class,
            SettingSeeder::class,
            OptionSeeder::class,
            PersianCoinSeeder::class,
            ProductSeeder::class,
            MarketPriceSeeder::class,
            SizeUnitSeeder::class,
            PersonalAccessTokenSeeder::class,
            DirectorySeeder::class,
            PropertySeeder::class,
            SortOptionSeeder::class,
            WidgetSeeder::class,
            SectionSeeder::class,
            ProvinceSeeder::class,
            CitySeeder::class,
            ShippingMethodSeeder::class,
            PhysicalAddressSeeder::class,
            PaymentGatewaySeeder::class,
            WorkingHourSeeder::class,
            ReferenceChannelSeeder::class,
            ApiClientSeeder::class,
            SubscriptionFeaturesTableSeeder::class,
            RateSeeder::class,
            ReferenceMarketSeeder::class,
            MeltedGoldCardSeeder::class,
        ]);
    }
}
