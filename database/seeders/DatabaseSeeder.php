<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(['email' => 'superadmin@pospharmacy.test'], [
            'name' => 'System Owner',
            'role' => User::ROLE_SUPERADMIN,
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'admin@pospharmacy.test'], [
            'name' => 'Pharmacy Admin',
            'role' => User::ROLE_ADMIN,
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'pharmacist@pospharmacy.test'], [
            'name' => 'Rhea Villanueva',
            'role' => User::ROLE_PHARMACIST,
            'password' => 'password',
        ]);

        User::updateOrCreate(['email' => 'cashier@pospharmacy.test'], [
            'name' => 'Carlo Ramos',
            'role' => User::ROLE_CASHIER,
            'password' => 'password',
        ]);

        $this->call(PharmacySeeder::class);
    }
}
