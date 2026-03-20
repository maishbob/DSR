<?php

namespace Database\Seeders;

use App\Models\CreditCustomer;
use App\Models\Owner;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\PumpNozzle;
use App\Models\ShopProduct;
use App\Models\Station;
use App\Models\Tank;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ────────────────────────────────────────────────────────────
        $ownerUser = User::create([
            'name'     => 'John Kamau',
            'email'    => 'owner@dsr.test',
            'password' => Hash::make('password'),
            'role'     => 'owner',
        ]);

        $owner = Owner::create([
            'user_id'           => $ownerUser->id,
            'name'              => 'Kamau Petroleum Ltd',
            'email'             => 'owner@dsr.test',
            'phone'             => '+254700000001',
            'subscription_plan' => 'professional',
        ]);

        $station = Station::create([
            'owner_id'     => $owner->id,
            'station_name' => 'Shell Thika Road',
            'location'     => 'Thika Road, Nairobi',
            'timezone'     => 'Africa/Nairobi',
        ]);

        $ownerUser->update(['owner_id' => $owner->id, 'station_id' => $station->id]);

        User::create([
            'name'       => 'Mary Wanjiku',
            'email'      => 'operator@dsr.test',
            'password'   => Hash::make('password'),
            'role'       => 'operator',
            'owner_id'   => $owner->id,
            'station_id' => $station->id,
        ]);

        User::create([
            'name'       => 'Peter Mwangi',
            'email'      => 'manager@dsr.test',
            'password'   => Hash::make('password'),
            'role'       => 'manager',
            'owner_id'   => $owner->id,
            'station_id' => $station->id,
        ]);

        // ── Fuel Products ────────────────────────────────────────────────────
        $petrol  = Product::create(['station_id' => $station->id, 'product_name' => 'Unleaded']);
        $diesel  = Product::create(['station_id' => $station->id, 'product_name' => 'Diesel']);
        $vpower  = Product::create(['station_id' => $station->id, 'product_name' => 'V-Power']);
        $kero    = Product::create(['station_id' => $station->id, 'product_name' => 'Kerosene']);

        // ── Price History ────────────────────────────────────────────────────
        PriceHistory::create(['product_id' => $petrol->id, 'price_per_litre' => 198.50, 'effective_from' => '2024-01-01', 'created_by' => $ownerUser->id]);
        PriceHistory::create(['product_id' => $diesel->id, 'price_per_litre' => 186.50, 'effective_from' => '2024-01-01', 'created_by' => $ownerUser->id]);
        PriceHistory::create(['product_id' => $vpower->id, 'price_per_litre' => 215.00, 'effective_from' => '2024-01-01', 'created_by' => $ownerUser->id]);
        PriceHistory::create(['product_id' => $kero->id,   'price_per_litre' => 150.00, 'effective_from' => '2024-01-01', 'created_by' => $ownerUser->id]);

        // ── Tanks ────────────────────────────────────────────────────────────
        $tankDiesel1  = Tank::create(['station_id' => $station->id, 'product_id' => $diesel->id, 'tank_name' => 'DIESEL TANK 1',   'tank_capacity' => 45000]);
        $tankUnleaded = Tank::create(['station_id' => $station->id, 'product_id' => $petrol->id, 'tank_name' => 'UNLEADED TANK 1', 'tank_capacity' => 40000]);
        $tankVpower   = Tank::create(['station_id' => $station->id, 'product_id' => $vpower->id, 'tank_name' => 'V-POWER TANK 1',  'tank_capacity' => 10000]);
        $tankKero     = Tank::create(['station_id' => $station->id, 'product_id' => $kero->id,   'tank_name' => 'KEROSENE TANK 1', 'tank_capacity' => 10000]);

        // ── Pump Nozzles — each nozzle knows which tank it feeds from ─────────
        // main_pump = physical pump number, nozzle_no = position on that pump
        $nozzles = [
            ['product_id' => $diesel->id,  'tank_id' => $tankDiesel1->id,  'nozzle_ref' => 'DX1', 'nozzle_name' => 'DX1 DIESEL',   'main_pump' => 1, 'nozzle_no' => 1, 'sort_order' => 1],
            ['product_id' => $diesel->id,  'tank_id' => $tankDiesel1->id,  'nozzle_ref' => 'DX2', 'nozzle_name' => 'DX2 DIESEL',   'main_pump' => 1, 'nozzle_no' => 2, 'sort_order' => 2],
            ['product_id' => $diesel->id,  'tank_id' => $tankDiesel1->id,  'nozzle_ref' => 'DX3', 'nozzle_name' => 'DX3 DIESEL',   'main_pump' => 2, 'nozzle_no' => 1, 'sort_order' => 3],
            ['product_id' => $diesel->id,  'tank_id' => $tankDiesel1->id,  'nozzle_ref' => 'DX4', 'nozzle_name' => 'DX4 DIESEL',   'main_pump' => 2, 'nozzle_no' => 2, 'sort_order' => 4],
            ['product_id' => $diesel->id,  'tank_id' => $tankDiesel1->id,  'nozzle_ref' => 'DX5', 'nozzle_name' => 'DX5 DIESEL',   'main_pump' => 3, 'nozzle_no' => 1, 'sort_order' => 5],
            ['product_id' => $diesel->id,  'tank_id' => $tankDiesel1->id,  'nozzle_ref' => 'DX6', 'nozzle_name' => 'DX6 DIESEL',   'main_pump' => 3, 'nozzle_no' => 2, 'sort_order' => 6],
            ['product_id' => $petrol->id,  'tank_id' => $tankUnleaded->id, 'nozzle_ref' => 'UX1', 'nozzle_name' => 'UX1 UNLEADED', 'main_pump' => 4, 'nozzle_no' => 1, 'sort_order' => 7],
            ['product_id' => $petrol->id,  'tank_id' => $tankUnleaded->id, 'nozzle_ref' => 'UX2', 'nozzle_name' => 'UX2 UNLEADED', 'main_pump' => 4, 'nozzle_no' => 2, 'sort_order' => 8],
            ['product_id' => $petrol->id,  'tank_id' => $tankUnleaded->id, 'nozzle_ref' => 'UX3', 'nozzle_name' => 'UX3 UNLEADED', 'main_pump' => 5, 'nozzle_no' => 1, 'sort_order' => 9],
            ['product_id' => $petrol->id,  'tank_id' => $tankUnleaded->id, 'nozzle_ref' => 'UX4', 'nozzle_name' => 'UX4 UNLEADED', 'main_pump' => 5, 'nozzle_no' => 2, 'sort_order' => 10],
            ['product_id' => $petrol->id,  'tank_id' => $tankUnleaded->id, 'nozzle_ref' => 'UX5', 'nozzle_name' => 'UX5 UNLEADED', 'main_pump' => 6, 'nozzle_no' => 1, 'sort_order' => 11],
            ['product_id' => $petrol->id,  'tank_id' => $tankUnleaded->id, 'nozzle_ref' => 'UX6', 'nozzle_name' => 'UX6 UNLEADED', 'main_pump' => 6, 'nozzle_no' => 2, 'sort_order' => 12],
            ['product_id' => $vpower->id,  'tank_id' => $tankVpower->id,   'nozzle_ref' => 'VP1', 'nozzle_name' => 'VP1 V-POWER',  'main_pump' => 7, 'nozzle_no' => 1, 'sort_order' => 13],
        ];

        foreach ($nozzles as $n) {
            PumpNozzle::create(array_merge(['station_id' => $station->id], $n));
        }

        // ── Shop Products (lubricants / oils) ────────────────────────────────
        $shopProducts = [
            ['product_name' => 'R3 RIMULA TURBO 15W-40 1L', 'unit' => 'unit', 'current_price' => 565.00],
            ['product_name' => 'HX7 HELIX 10W-40 4L',       'unit' => 'unit', 'current_price' => 3245.00],
            ['product_name' => 'HX5 HELIX 15W-40 1LT',      'unit' => 'unit', 'current_price' => 590.00],
            ['product_name' => 'HX5 HELIX 15W-40 4L',       'unit' => 'unit', 'current_price' => 2280.00],
            ['product_name' => 'ATF SPIRAX S2 D2 1L',       'unit' => 'unit', 'current_price' => 640.00],
        ];

        foreach ($shopProducts as $sp) {
            ShopProduct::create(array_merge(['station_id' => $station->id], $sp));
        }

        // ── Credit Customers ─────────────────────────────────────────────────
        CreditCustomer::create(['station_id' => $station->id, 'customer_name' => 'MEHTA ELECTRICALS LTD',   'phone' => '+254722000001', 'credit_limit' => 500000]);
        CreditCustomer::create(['station_id' => $station->id, 'customer_name' => 'BIGFOOT ADVENTURES LTD',  'phone' => '+254733000002', 'credit_limit' => 300000]);
        CreditCustomer::create(['station_id' => $station->id, 'customer_name' => 'MULTI-LINE MOTORS K LTD', 'phone' => '+254711000003', 'credit_limit' => 400000]);
        CreditCustomer::create(['station_id' => $station->id, 'customer_name' => 'VIN CONSTRUCTION CO LTD', 'phone' => '+254744000004', 'credit_limit' => 600000]);
        CreditCustomer::create(['station_id' => $station->id, 'customer_name' => 'FINSBURY TRADING LTD',    'phone' => '+254755000005', 'credit_limit' => 250000]);
        CreditCustomer::create(['station_id' => $station->id, 'customer_name' => 'BAHIA AGENCIES',          'phone' => '+254766000006', 'credit_limit' => 200000]);

        $this->command->info('');
        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->info('   Owner:    owner@dsr.test    / password');
        $this->command->info('   Manager:  manager@dsr.test  / password');
        $this->command->info('   Operator: operator@dsr.test / password');
        $this->command->info('');
        $this->command->info('   Station:  Shell Thika Road');
        $this->command->info('   Nozzles:  6 Diesel, 5 Unleaded, 1 V-Power');
        $this->command->info('   Tanks:    Diesel, Unleaded, V-Power, Kerosene');
    }
}
