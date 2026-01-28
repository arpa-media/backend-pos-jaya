<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // IMPORTANT: Spatie permissions are cached.
        // When adding new permissions in a patch, the cache MUST be reset
        // otherwise hasPermissionTo()/can() may throw PermissionDoesNotExist.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = 'web';
        $roles = [
            'admin',
            'manager',
            'cashier',
        ];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => $guard]);
        }

        $permissions = [
            // Auth
            'auth.me',

            // Admin
            'admin.access',

            'dashboard.view',

            'outlet.view',
            'outlet.update',

            'category.view',
            'category.create',
            'category.update',
            'category.delete',

            'product.view',
            'product.create',
            'product.update',
            'product.delete',

            'pos.checkout',
            'sale.view',

            // Patch-8: Cancel bill request flow
            'sale.cancel.request',
            'sale.cancel.approve',

            // Reports
            'report.view',

            'customer.view',
'customer.create',

            'payment_method.view',
            'payment_method.create',
            'payment_method.update',
            'payment_method.delete',

            'discount.view',
            'discount.create',
            'discount.update',
            'discount.delete',

            // Taxes (global)
            'taxes.view',
            'taxes.create',
            'taxes.update',
            'taxes.delete',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => $guard]);
        }

        $admin = Role::where('name', 'admin')->where('guard_name', $guard)->firstOrFail();
        $manager = Role::where('name', 'manager')->where('guard_name', $guard)->firstOrFail();
        $cashier = Role::where('name', 'cashier')->where('guard_name', $guard)->firstOrFail();

        $admin->syncPermissions($permissions);

        $manager->syncPermissions([
            'auth.me',
            'dashboard.view',
            'outlet.view', 'outlet.update',
            'category.view', 'category.create', 'category.update', 'category.delete',
            'product.view', 'product.create', 'product.update', 'product.delete',
            'payment_method.view', 'payment_method.create', 'payment_method.update', 'payment_method.delete',
            'discount.view', 'discount.create', 'discount.update', 'discount.delete',

            'discount.view',
            'discount.create',
            'discount.update',
            'discount.delete',
            'taxes.view', 'taxes.create', 'taxes.update', 'taxes.delete',
            'pos.checkout', 'sale.view',
            'sale.cancel.request',
            'sale.cancel.approve',
'customer.view', 'customer.create',

            // Reports
            'report.view',

        ]);

        $cashier->syncPermissions([
            'auth.me',
            'dashboard.view',
            'outlet.view',
            'category.view',
            'product.view',
            'payment_method.view',
            'discount.view',
            'pos.checkout', 'sale.view',
            'sale.cancel.request',
'customer.view', 'customer.create',
        ]);

        // ------------------------------------------------------------------
        // Outlets (Patch: update OUTLET-001/002 to real branches)
        // We keep the seeder idempotent even if old codes already exist.
        // ------------------------------------------------------------------

        // OUTLET-001 => SHT (Soehat - Malang)
        $outletSht = Outlet::query()
            ->where('code', 'SHT')
            ->first();
        if (!$outletSht) {
            $outletSht = Outlet::query()->where('code', 'OUTLET-001')->first();
        }
        if (!$outletSht) {
            $outletSht = new Outlet();
        }
        $outletSht->code = 'SHT';
        $outletSht->name = 'Soehat';
        $outletSht->address = 'Jl. Puncak Borobudur No.H 430/431, RT.1/RW.15, Mojolangu, Kec. Lowokwaru, Kota Malang';
        $outletSht->timezone = 'Asia/Jakarta';
        $outletSht->phone = $outletSht->phone ?? null;
        $outletSht->save();

        // OUTLET-002 => DPN (Soehat - Denpasar)
        $outletDpn = Outlet::query()
            ->where('code', 'DPN')
            ->first();
        if (!$outletDpn) {
            $outletDpn = Outlet::query()->where('code', 'OUTLET-002')->first();
        }
        if (!$outletDpn) {
            $outletDpn = new Outlet();
        }
        $outletDpn->code = 'DPN';
        $outletDpn->name = 'Bali';
        $outletDpn->address = 'Jl. Hayam Wuruk No.171, Sumerta Kelod, Denpasar Selatan, Kota Denpasar, Bali 80232';
        $outletDpn->timezone = 'Asia/Makassar';
        $outletDpn->phone = $outletDpn->phone ?? null;
        $outletDpn->save();

        // Keep OUTLET-003 as a spare demo outlet (optional)
        Outlet::query()->firstOrCreate(
            ['code' => 'OUTLET-003'],
            [
                'name' => 'Outlet Cabang (Demo)',
                'timezone' => 'Asia/Jakarta',
                'address' => null,
                'phone' => null,
            ]
        );

        // NOTE: Demo users are seeded in DemoUserSeeder (so AuthSeeder focuses on roles/perms/outlets).
    }
}
