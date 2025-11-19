<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Menu;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();
        // Bersihkan tabel (opsional, hati-hati di production)
        // TransactionItem hapus otomatis karena cascade delete dari Transaction
        TransactionItem::truncate();
        Transaction::truncate(); 
        Menu::truncate();
        User::truncate();

        Schema::enableForeignKeyConstraints();

        $this->command->info('🌱 Creating users...');
        
        // 1. Buat Users
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.com',
            'password' => Hash::make('123'), // Hash password bawaan Laravel
            'role' => 'OWNER'
        ]);

        $cashier = User::create([
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password' => Hash::make('123'),
            'role' => 'CASHIER'
        ]);

        $this->command->info('🌱 Creating menus...');

        // 2. Data Menu (Simpan bobot 'weight' dalam array lokal untuk logika random)
        $menuList = [
            ['name' => "Nasi Goreng", 'price' => 20000, 'weight' => 40],
            ['name' => "Mie Ayam", 'price' => 15000, 'weight' => 25],
            ['name' => "Ayam Geprek", 'price' => 22000, 'weight' => 35],
            ['name' => "Soto Ayam", 'price' => 18000, 'weight' => 18],
            ['name' => "Es Teh", 'price' => 5000, 'weight' => 50],
            ['name' => "Es Jeruk", 'price' => 7000, 'weight' => 35],
            ['name' => "Kopi Hitam", 'price' => 8000, 'weight' => 15],
        ];

        // Insert ke DB (hanya kolom yang ada di tabel)
        foreach ($menuList as $m) {
            Menu::create([
                'name' => $m['name'],
                'price' => $m['price']
            ]);
        }

        // Ambil balik dari DB untuk dapat ID-nya
        $menusInDb = Menu::all();

        $this->command->info('🌱 Generating 6 months of transactions (might take a while)...');

        $today = Carbon::now();

        // Loop 180 hari ke belakang
        for ($i = 0; $i < 180; $i++) {
            $currentDate = $today->copy()->subDays($i);
            $dayOfWeek = $currentDate->dayOfWeek; // 0 (Minggu) - 6 (Sabtu)
            $dayOfMonth = $currentDate->day;

            // Logika Jumlah Pesanan Harian (Base Orders)
            $baseOrders = match ($dayOfWeek) {
                0 => rand(60, 100),  // Minggu ramai
                1 => rand(30, 60),   // Senin sepi
                2 => rand(35, 70),
                3 => rand(40, 75),
                4 => rand(50, 90),
                5 => rand(80, 130),  // Jumat ramai
                6 => rand(90, 150),  // Sabtu sangat ramai
                default => 50,
            };

            // Boost Tanggal Gajian (Tgl 1 & 25)
            if ($dayOfMonth === 1 || $dayOfMonth === 25) {
                $baseOrders = floor($baseOrders * 1.3);
            }

            // Generate transaksi per hari
            for ($j = 0; $j < $baseOrders; $j++) {
                
                // Logika Jam Makan (Lunch & Dinner Peaks)
                $roll = mt_rand(0, 100) / 100; // 0.0 - 1.0
                
                if ($roll < 0.4) $hour = rand(11, 14);       // Lunch
                elseif ($roll < 0.75) $hour = rand(17, 20);  // Dinner
                else $hour = rand(8, 22);                    // Random

                $minute = rand(0, 59);
                
                // Set waktu transaksi
                $txTime = $currentDate->copy()->setTime($hour, $minute);

                // Pilih Menu (1-3 item per transaksi)
                $itemCount = rand(1, 3);
                $total = 0;
                $transactionItems = [];

                for ($k = 0; $k < $itemCount; $k++) {
                    // Weighted Random Selection
                    $rollWeight = rand(1, 100); // Total bobot menuList sekitar ~218, disederhanakan 100 scale
                    
                    // Cari menu berdasarkan bobot
                    // (Logika sederhana: iterasi bobot kumulatif)
                    $cumulative = 0;
                    $selectedMenu = null;
                    
                    // Kita ambil random dari array $menuList, lalu cocokkan dengan DB
                    // Supaya akurat, kita kocok array lokal saja
                    $targetWeight = rand(1, 218); // Total sum bobot manual di atas
                    $currentWeight = 0;
                    
                    foreach ($menuList as $mItem) {
                        $currentWeight += $mItem['weight'];
                        if ($targetWeight <= $currentWeight) {
                            $selectedMenu = $menusInDb->firstWhere('name', $mItem['name']);
                            break;
                        }
                    }
                    // Fallback jika null
                    if (!$selectedMenu) $selectedMenu = $menusInDb->first();

                    $qty = rand(1, 3);
                    $subtotal = $qty * $selectedMenu->price;
                    $total += $subtotal;

                    $transactionItems[] = [
                        'menu_id' => $selectedMenu->id,
                        'quantity' => $qty,
                        'subtotal' => $subtotal
                    ];
                }

                // Simpan Header Transaksi
                $transaction = Transaction::create([
                    'user_id' => $cashier->id, // Selalu cashier yg input
                    'total' => $total,
                    'date' => $txTime,
                    'created_at' => $txTime,
                    'updated_at' => $txTime
                ]);

                // Simpan Detail Item (Eloquent Relationship hasMany)
                // Pastikan di Model Transaction ada relasi items()
                $transaction->items()->createMany($transactionItems);
            }
        }

        $this->command->info('🎉 Seed complete! Database populated with realistic data.');
    }
}