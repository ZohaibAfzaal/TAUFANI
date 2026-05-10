<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Test users ────────────────────────────────────────────────────
        $userData = [
            ['name' => 'Zohaib Afzaal',    'email' => 'zohaib@taufani.test'],
            ['name' => 'Sanaullah Saeed',   'email' => 'sana@taufani.test'],
            ['name' => 'Muhammad Abdullah', 'email' => 'abdullah@taufani.test'],
            ['name' => 'Abdul Wahab',       'email' => 'wahab@taufani.test'],
            ['name' => 'Abdul Qadir',       'email' => 'qadir@taufani.test'],
            ['name' => 'Mehdi Ali Jadoon',  'email' => 'mehdi@taufani.test'],
        ];

        $users = collect($userData)->map(fn($d) => User::firstOrCreate(
            ['email' => $d['email']],
            ['name' => $d['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()]
        ));

        [$zohaib, $sana, $abdullah, $wahab, $qadir, $mehdi] = $users->values()->all();

        // ── Group 1: House Mates (idempotent) ─────────────────────────────
        $house = Group::firstOrCreate(
            ['name' => 'House Mates', 'created_by' => $zohaib->id],
            ['emoji' => '🏠']
        );

        // Add test users + EVERY real user already registered in the app
        // so whoever logs in can immediately see the seeded data.
        $allUserIds = User::pluck('id')->toArray();
        $house->members()->syncWithoutDetaching($allUserIds);

        // Only seed expenses once
        if (Expense::where('group_id', $house->id)->count() === 0) {
            $houseExpenses = [
                ['description' => 'Monthly Rent',       'category' => 'home',          'amount' => 30000, 'paid_by' => $zohaib,   'days' => 30],
                ['description' => 'Grocery Shopping',   'category' => 'shopping-cart', 'amount' => 4500,  'paid_by' => $sana,     'days' => 27],
                ['description' => 'Electricity Bill',   'category' => 'zap',           'amount' => 2800,  'paid_by' => $wahab,    'days' => 25],
                ['description' => 'Internet Bill',      'category' => 'wifi',          'amount' => 1500,  'paid_by' => $qadir,    'days' => 22],
                ['description' => 'Dinner at BBQ',      'category' => 'utensils',      'amount' => 3200,  'paid_by' => $abdullah, 'days' => 20],
                ['description' => 'Sui Gas Bill',       'category' => 'flame',         'amount' => 900,   'paid_by' => $mehdi,    'days' => 18],
                ['description' => 'Sabzi & Fruit',      'category' => 'shopping-cart', 'amount' => 1100,  'paid_by' => $zohaib,   'days' => 15],
                ['description' => 'Careem Rides',       'category' => 'car',           'amount' => 650,   'paid_by' => $sana,     'days' => 13],
                ['description' => 'Gas Cylinder',       'category' => 'flame',         'amount' => 1200,  'paid_by' => $wahab,    'days' => 10],
                ['description' => 'Pizza Night',        'category' => 'utensils',      'amount' => 2400,  'paid_by' => $qadir,    'days' => 8],
                ['description' => 'Morning Chai',       'category' => 'coffee',        'amount' => 800,   'paid_by' => $mehdi,    'days' => 6],
                ['description' => 'Mineral Water',      'category' => 'droplets',      'amount' => 300,   'paid_by' => $abdullah, 'days' => 4],
                ['description' => 'Maintenance Repair', 'category' => 'home',          'amount' => 1800,  'paid_by' => $zohaib,   'days' => 2],
                ['description' => 'Shawarma Dinner',    'category' => 'utensils',      'amount' => 1400,  'paid_by' => $sana,     'days' => 1],
            ];

            foreach ($houseExpenses as $data) {
                $expense = Expense::create([
                    'group_id'    => $house->id,
                    'description' => $data['description'],
                    'category'    => $data['category'],
                    'amount'      => $data['amount'],
                    'paid_by'     => $data['paid_by']->id,
                    'split_type'  => 'equal',
                    'date'        => now()->subDays($data['days'])->toDateString(),
                ]);
                $expense->participants()->attach($allUserIds);
            }

            if (Settlement::where('group_id', $house->id)->count() === 0) {
                Settlement::create([
                    'group_id' => $house->id,
                    'from_id'  => $sana->id,
                    'to_id'    => $zohaib->id,
                    'amount'   => 5000,
                    'date'     => now()->subDays(3)->toDateString(),
                    'note'     => 'Partial rent payment',
                ]);
            }
        }

        // ── Group 2: Murree Trip ──────────────────────────────────────────
        $trip = Group::firstOrCreate(
            ['name' => 'Murree Trip', 'created_by' => $abdullah->id],
            ['emoji' => '🏔️']
        );

        $tripMemberIds = collect([$zohaib, $sana, $abdullah, $qadir])->pluck('id')->toArray();
        $trip->members()->syncWithoutDetaching($tripMemberIds);

        if (Expense::where('group_id', $trip->id)->count() === 0) {
            $tripExpenses = [
                ['description' => 'Hotel Booking',   'category' => 'home',     'amount' => 12000, 'paid_by' => $zohaib,   'days' => 14],
                ['description' => 'Fuel for Trip',   'category' => 'car',      'amount' => 3500,  'paid_by' => $abdullah, 'days' => 13],
                ['description' => 'Lunch on Road',   'category' => 'utensils', 'amount' => 2200,  'paid_by' => $sana,     'days' => 13],
                ['description' => 'Snacks & Drinks', 'category' => 'coffee',   'amount' => 900,   'paid_by' => $qadir,    'days' => 12],
                ['description' => 'Return Fuel',     'category' => 'car',      'amount' => 2800,  'paid_by' => $zohaib,   'days' => 11],
            ];

            foreach ($tripExpenses as $data) {
                $expense = Expense::create([
                    'group_id'    => $trip->id,
                    'description' => $data['description'],
                    'category'    => $data['category'],
                    'amount'      => $data['amount'],
                    'paid_by'     => $data['paid_by']->id,
                    'split_type'  => 'equal',
                    'date'        => now()->subDays($data['days'])->toDateString(),
                ]);
                $expense->participants()->attach($tripMemberIds);
            }
        }

        $this->command->info('✓ Seeded House Mates + Murree Trip groups');
        $this->command->info('✓ Added ALL registered users to House Mates group');
        $this->command->info('  Login with any test account: zohaib@taufani.test / password');
    }
}
