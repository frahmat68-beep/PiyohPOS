<?php

namespace Database\Seeders;

use App\Models\User;
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
        // Create roles
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin']);
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'cashier']);
        Role::firstOrCreate(['name' => 'kitchen']);

        // Create default super admin user
        $superAdmin = User::firstOrCreate([
            'email' => 'superadmin@piyohkopi.com',
        ], [
            'name' => 'Super Admin Piyoh',
            'password' => Hash::make('PiyohSuperAdmin2026!'),
        ]);

        $superAdmin->assignRole($superAdminRole);
    }
}
