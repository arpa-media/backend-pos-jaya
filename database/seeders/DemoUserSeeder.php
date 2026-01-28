<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $outletSht = Outlet::query()->where('code', 'SHT')->first();
        $outletDpn = Outlet::query()->where('code', 'DPN')->first();

        // 1) Global admin
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@tokokopijaya.com'],
            [
                'name' => 'Admin',
                'nisj' => '10012501000',
                'password' => Hash::make('password123'),
            ]
        );
        $admin->nisj = '10012501000';
        $admin->outlet_id = null;
        $admin->password = Hash::make('password123');
        $admin->save();
        if (!$admin->hasRole('admin')) {
            $admin->syncRoles(['admin']);
        }


        // 1) Global admin2
        $admin2 = User::query()->firstOrCreate(
            ['email' => 'admin2@tokokopijaya.com'],
            [
                'name' => 'Admin',
                'nisj' => '10012501003',
                'password' => Hash::make('password123'),
            ]
        );
        $admin2->nisj = '10012501003';
        $admin2->outlet_id = null;
        $admin2->password = Hash::make('password123');
        $admin2->save();
        if (!$admin2->hasRole('admin')) {
            $admin2->syncRoles(['admin']);
        }


        // 2) Soehat (Malang) user
        $sht = User::query()->firstOrCreate(
            ['email' => 'soehat@tokokopijaya.com'],
            [
                'name' => 'Soehat (SHT)',
                'nisj' => '10012501001',
                'password' => Hash::make('password123'),
            ]
        );
        $sht->nisj = '10012501001';
        $sht->outlet_id = $outletSht?->id; // must exist from AuthSeeder
        $sht->password = Hash::make('password123');
        $sht->save();
        if (!$sht->hasRole('cashier')) {
            $sht->syncRoles(['cashier']);
        }

        // 3) Bali (Denpasar) user
        $dpn = User::query()->firstOrCreate(
            ['email' => 'bali@tokokopijaya.com'],
            [
                'name' => 'Bali (DPN)',
                'nisj' => '10012501002',
                'password' => Hash::make('password123'),
            ]
        );
        $dpn->nisj = '10012501002';
        $dpn->outlet_id = $outletDpn?->id; // must exist from AuthSeeder
        $dpn->password = Hash::make('password123');
        $dpn->save();
        if (!$dpn->hasRole('cashier')) {
            $dpn->syncRoles(['cashier']);
        }
    }
}
