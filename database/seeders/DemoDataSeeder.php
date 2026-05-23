<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Province;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        $provinces = collect(['القاهرة', 'الجيزة', 'الإسكندرية', 'الدقهلية', 'الشرقية'])
            ->map(fn ($name) => Province::firstOrCreate(['name' => $name]));

        $products = [
            ['name' => 'باراسيتامول 500', 'code' => 'MED-001', 'price' => 25],
            ['name' => 'أموكسيسيلين 500', 'code' => 'MED-002', 'price' => 45],
            ['name' => 'إيبوبروفين 400', 'code' => 'MED-003', 'price' => 30],
            ['name' => 'أوميبرازول 20', 'code' => 'MED-004', 'price' => 55],
            ['name' => 'فيتامين سي', 'code' => 'MED-005', 'price' => 20],
        ];

        $productModels = collect($products)->map(fn ($p) => Product::firstOrCreate(
            ['code' => $p['code']],
            ['name' => $p['name'], 'price' => $p['price'], 'company_id' => $company->id]
        ));

        foreach ($provinces as $province) {
            for ($s = 1; $s <= 2; $s++) {
                $supplier = Supplier::firstOrCreate(
                    ['province_id' => $province->id, 'name' => "مورد {$province->name} {$s}"],
                    ['phone' => '0100000000'.$s, 'address' => 'عنوان المورد']
                );

                for ($p = 1; $p <= 3; $p++) {
                    $pharmacy = Pharmacy::firstOrCreate(
                        ['supplier_id' => $supplier->id, 'name' => "صيدلية {$province->name} {$s}-{$p}"],
                        ['province_id' => $province->id, 'phone' => '011000000'.$p]
                    );

                    foreach ($productModels->random(2) as $product) {
                        $soldAt = Carbon::now()->subDays(rand(1, 60))->format('Y-m-d');
                        $qty = rand(5, 50);
                        $hash = hash('sha256', "{$product->id}|{$pharmacy->id}|{$soldAt}|{$qty}");

                        Sale::firstOrCreate(
                            ['import_hash' => $hash],
                            [
                                'product_id' => $product->id,
                                'pharmacy_id' => $pharmacy->id,
                                'supplier_id' => $supplier->id,
                                'province_id' => $province->id,
                                'quantity' => $qty,
                                'sold_at' => $soldAt,
                            ]
                        );
                    }
                }
            }
        }
    }
}
