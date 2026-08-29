<?php

namespace Database\Seeders;

use App\Models\BusinessProfile;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class BusinessFoundationSeeder extends Seeder
{
    public function run(): void
    {
        BusinessProfile::query()->firstOrCreate([], [
            'name' => 'Tubagus Mart',
            'tagline' => 'Modern supermarket management system',
            'country' => 'Indonesia',
            'timezone' => 'Asia/Jakarta',
            'currency_code' => 'IDR',
            'currency_locale' => 'id_ID',
        ]);

        $settings = [
            ['key' => 'business.operating_status', 'value' => 'open', 'type' => 'string', 'group' => 'business'],
            ['key' => 'business.default_payment_method', 'value' => 'cash', 'type' => 'string', 'group' => 'business'],
            ['key' => 'sales.receipt_footer', 'value' => 'Terima kasih telah berbelanja di Tubagus Mart.', 'type' => 'string', 'group' => 'sales'],
            ['key' => 'inventory.low_stock_threshold', 'value' => '10', 'type' => 'integer', 'group' => 'inventory'],
            ['key' => 'system.maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'system'],
        ];

        foreach ($settings as $setting) {
            Setting::query()->updateOrCreate(
                ['key' => $setting['key']],
                $setting,
            );
        }
    }
}
