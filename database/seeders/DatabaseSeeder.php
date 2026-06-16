<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Outlet;
use App\Models\Table;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Role::firstOrCreate(['name' => 'kitchen']);

        // 2. Create Outlets
        $outletGalaxy = Outlet::firstOrCreate([
            'slug' => 'piyoh-galaxy',
        ], [
            'name' => 'Piyoh Galaxy',
            'address' => 'Galaxy, Bekasi',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        $outletBekasi = Outlet::firstOrCreate([
            'slug' => 'piyoh-bekasi',
        ], [
            'name' => 'Piyoh Bekasi',
            'address' => 'Bekasi Kota, Bekasi',
            'phone' => '081234567891',
            'is_active' => true,
        ]);

        // 3. Create 20 tables per outlet
        for ($i = 1; $i <= 20; $i++) {
            $tableNum = sprintf('%02d', $i);
            Table::firstOrCreate([
                'outlet_id' => $outletGalaxy->id,
                'number' => $tableNum,
            ], [
                'seating_capacity' => 4,
                'status' => 'vacant',
            ]);

            Table::firstOrCreate([
                'outlet_id' => $outletBekasi->id,
                'number' => $tableNum,
            ], [
                'seating_capacity' => 4,
                'status' => 'vacant',
            ]);
        }

        // 4. Create default super admin user
        $superAdmin = User::firstOrCreate([
            'email' => 'superadmin@piyohkopi.com',
        ], [
            'name' => 'Super Admin Piyoh',
            'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'password')),
        ]);

        $superAdmin->assignRole($superAdminRole);
    }
}
