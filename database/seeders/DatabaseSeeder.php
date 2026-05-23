<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\WarehouseType;
use App\Models\Company;
use App\Models\Province;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'شركة الأدوية التجريبية',
            'contact_email' => 'company@orca-med.test',
            'is_active' => true,
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@orca-med.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'company_id' => null,
            'warehouse_id' => null,
        ]);

        User::factory()->create([
            'name' => 'Company User',
            'email' => 'company@orca-med.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Company,
            'company_id' => $company->id,
            'warehouse_id' => null,
        ]);

        $this->call(DemoDataSeeder::class);

        $demoProvince = Province::query()->first();
        $warehouse = Warehouse::query()->firstOrCreate(
            ['name' => 'مخزن تجريبي للجملة'],
            [
                'type' => WarehouseType::Wholesale,
                'phone' => '0100000999',
                'address' => 'عنوان تجريبي',
                'province_id' => $demoProvince?->id,
            ]
        );
        app(WarehouseService::class)->ensureShadowSupplier($warehouse);

        User::factory()->warehouse()->create([
            'name' => 'مستخدم مخزن',
            'email' => 'warehouse@orca-med.test',
            'password' => Hash::make('password'),
            'warehouse_id' => $warehouse->id,
        ]);

        $company->update(['sensitive_view_password' => 'secret123']);
    }
}
