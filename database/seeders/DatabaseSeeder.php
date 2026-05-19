<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
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
        ]);

        User::factory()->create([
            'name' => 'Company User',
            'email' => 'company@orca-med.test',
            'password' => Hash::make('password'),
            'role' => UserRole::Company,
            'company_id' => $company->id,
        ]);
    }
}
