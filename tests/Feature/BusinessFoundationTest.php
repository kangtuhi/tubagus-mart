<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Setting;
use App\Services\Business\BusinessSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_profile_can_be_created_as_the_single_business_profile(): void
    {
        $profile = BusinessProfile::create([
            'name' => 'Tubagus Mart',
            'country' => 'Indonesia',
            'timezone' => 'Asia/Jakarta',
            'currency_code' => 'IDR',
            'currency_locale' => 'id_ID',
        ]);

        $this->assertSame('Tubagus Mart', $profile->name);
        $this->assertSame('IDR', $profile->currency_code);
        $this->assertSame(1, BusinessProfile::query()->count());
    }

    public function test_settings_service_reads_typed_values(): void
    {
        $service = app(BusinessSettings::class);

        $service->put('inventory.low_stock_threshold', 10, 'integer', 'inventory');
        $service->put('system.maintenance_mode', true, 'boolean', 'system');
        $service->put('sales.receipt_options', ['show_logo' => true], 'json', 'sales');

        $this->assertSame(10, $service->setting('inventory.low_stock_threshold'));
        $this->assertTrue($service->setting('system.maintenance_mode'));
        $this->assertSame(['show_logo' => true], $service->setting('sales.receipt_options'));
        $this->assertSame(['inventory.low_stock_threshold' => 10], $service->settings('inventory'));
    }

    public function test_setting_is_updated_by_key_without_creating_duplicates(): void
    {
        $service = app(BusinessSettings::class);

        $service->put('business.operating_status', 'open', 'string', 'business');
        $service->put('business.operating_status', 'closed', 'string', 'business');

        $this->assertSame('closed', $service->setting('business.operating_status'));
        $this->assertSame(1, Setting::query()->where('key', 'business.operating_status')->count());
    }
}
