<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // ==========================================
        // 1. Buat Cabang
        // ==========================================
        $branches = [
            Branch::create([
                'name' => 'Diagram Coffee Dago',
                'address' => 'Jl. Ir. H. Juanda No.123, Dago, Bandung',
                'phone' => '022-1234567',
                'status' => 'active',
                'opening_time' => '08:00',
                'closing_time' => '22:00',
            ]),
            Branch::create([
                'name' => 'Diagram Coffee Braga',
                'address' => 'Jl. Braga No.45, Braga, Bandung',
                'phone' => '022-7654321',
                'status' => 'active',
                'opening_time' => '07:00',
                'closing_time' => '23:00',
            ]),
            Branch::create([
                'name' => 'Diagram Coffee Paskal',
                'address' => 'Jl. Pasir Kaliki No.78, Paskal, Bandung',
                'phone' => '022-9876543',
                'status' => 'active',
                'opening_time' => '09:00',
                'closing_time' => '21:00',
            ]),
        ];

        // ==========================================
        // 2. Buat Users (Super Admin, Admin, Customer)
        // ==========================================
        User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@diagramcoffee.com',
            'password' => Hash::make('@Password123'),
            'role' => 'super_admin',
            'branch_id' => null,
            'email_verified_at' => now(),
        ]);

        // Admin per cabang
        User::create([
            'name' => 'Admin Dago',
            'email' => 'admin.dago@diagramcoffee.com',
            'password' => Hash::make('@Password123'),
            'role' => 'admin',
            'branch_id' => $branches[0]->id,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Admin Braga',
            'email' => 'admin.braga@diagramcoffee.com',
            'password' => Hash::make('@Password123'),
            'role' => 'admin',
            'branch_id' => $branches[1]->id,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Admin Paskal',
            'email' => 'admin.paskal@diagramcoffee.com',
            'password' => Hash::make('@Password123'),
            'role' => 'admin',
            'branch_id' => $branches[2]->id,
            'email_verified_at' => now(),
        ]);

        // Customer
        User::create([
            'name' => 'John Customer',
            'email' => 'customer@example.com',
            'password' => Hash::make('@Password123'),
            'role' => 'customer',
            'loyalty_points' => 150,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
            'password' => Hash::make('@Password123'),
            'role' => 'customer',
            'loyalty_points' => 50,
            'email_verified_at' => now(),
        ]);

        // ==========================================
        // 3. Buat Kategori
        // ==========================================
        $categories = [
            Category::create([
                'name' => 'Coffee',
                'slug' => 'coffee',
                'description' => 'Berbagai pilihan kopi berkualitas',
                'sort_order' => 1,
            ]),
            Category::create([
                'name' => 'Non-Coffee',
                'slug' => 'non-coffee',
                'description' => 'Minuman non-kopi yang menyegarkan',
                'sort_order' => 2,
            ]),
            Category::create([
                'name' => 'Pastry',
                'slug' => 'pastry',
                'description' => 'Aneka pastry dan roti segar',
                'sort_order' => 3,
            ]),
            Category::create([
                'name' => 'Snacks',
                'slug' => 'snacks',
                'description' => 'Camilan pendamping kopi',
                'sort_order' => 4,
            ]),
        ];

        // ==========================================
        // 4. Buat Menu Items
        // ==========================================
        $menuItems = [];

        // Coffee
        $coffeeMenus = [
            ['name' => 'Espresso', 'base_price' => 22000, 'description' => 'Kopi espresso murni dengan crema sempurna'],
            ['name' => 'Americano', 'base_price' => 25000, 'description' => 'Espresso dengan air panas'],
            ['name' => 'Cappuccino', 'base_price' => 30000, 'description' => 'Espresso dengan steamed milk dan foam'],
            ['name' => 'Caffe Latte', 'base_price' => 32000, 'description' => 'Espresso dengan susu steamed yang lembut'],
            ['name' => 'Flat White', 'base_price' => 33000, 'description' => 'Double shot espresso dengan microfoam'],
            ['name' => 'Mocha', 'base_price' => 35000, 'description' => 'Espresso, cokelat, dan steamed milk'],
            ['name' => 'Affogato', 'base_price' => 35000, 'description' => 'Espresso dituang di atas gelato vanilla'],
            ['name' => 'Cold Brew', 'base_price' => 28000, 'description' => 'Kopi seduh dingin 18 jam'],
            ['name' => 'Vietnamese Drip', 'base_price' => 27000, 'description' => 'Kopi drip gaya Vietnam dengan susu kental'],
            ['name' => 'Manual Brew V60', 'base_price' => 38000, 'description' => 'Single origin hand-brewed V60'],
        ];

        foreach ($coffeeMenus as $menu) {
            $menuItems[] = MenuItem::create([
                'category_id' => $categories[0]->id,
                'name' => $menu['name'],
                'slug' => Str::slug($menu['name']),
                'description' => $menu['description'],
                'base_price' => $menu['base_price'],
                'is_active' => true,
            ]);
        }

        // Non-Coffee
        $nonCoffeeMenus = [
            ['name' => 'Matcha Latte', 'base_price' => 30000, 'description' => 'Matcha premium dari Uji, Jepang'],
            ['name' => 'Chocolate', 'base_price' => 28000, 'description' => 'Cokelat hangat atau dingin'],
            ['name' => 'Fresh Orange Juice', 'base_price' => 25000, 'description' => 'Jus jeruk segar'],
            ['name' => 'Taro Latte', 'base_price' => 28000, 'description' => 'Minuman taro creamy'],
            ['name' => 'Lemon Tea', 'base_price' => 22000, 'description' => 'Teh lemon segar'],
        ];

        foreach ($nonCoffeeMenus as $menu) {
            $menuItems[] = MenuItem::create([
                'category_id' => $categories[1]->id,
                'name' => $menu['name'],
                'slug' => Str::slug($menu['name']),
                'description' => $menu['description'],
                'base_price' => $menu['base_price'],
                'is_active' => true,
            ]);
        }

        // Pastry
        $pastryMenus = [
            ['name' => 'Croissant', 'base_price' => 18000, 'description' => 'Croissant butter renyah berlapis'],
            ['name' => 'Cinnamon Roll', 'base_price' => 20000, 'description' => 'Roti kayu manis dengan cream cheese glaze'],
            ['name' => 'Banana Bread', 'base_price' => 22000, 'description' => 'Roti pisang homemade'],
        ];

        foreach ($pastryMenus as $menu) {
            $menuItems[] = MenuItem::create([
                'category_id' => $categories[2]->id,
                'name' => $menu['name'],
                'slug' => Str::slug($menu['name']),
                'description' => $menu['description'],
                'base_price' => $menu['base_price'],
                'is_active' => true,
            ]);
        }

        // Snacks
        $snackMenus = [
            ['name' => 'French Fries', 'base_price' => 20000, 'description' => 'Kentang goreng renyah'],
            ['name' => 'Chicken Wings', 'base_price' => 28000, 'description' => 'Sayap ayam goreng crispy'],
        ];

        foreach ($snackMenus as $menu) {
            $menuItems[] = MenuItem::create([
                'category_id' => $categories[3]->id,
                'name' => $menu['name'],
                'slug' => Str::slug($menu['name']),
                'description' => $menu['description'],
                'base_price' => $menu['base_price'],
                'is_active' => true,
            ]);
        }

        // ==========================================
        // 5. Assign Menu ke Cabang (dengan stok & promo)
        // ==========================================
        foreach ($branches as $branch) {
            foreach ($menuItems as $index => $menuItem) {
                $data = [
                    'menu_item_id' => $menuItem->id,
                    'branch_id' => $branch->id,
                    'is_available' => true,
                    'stock' => rand(10, 50),
                    'discount_type' => null,
                    'discount_percentage' => null,
                    'discount_amount' => null,
                    'is_promo_active' => false,
                ];

                // Beberapa menu punya promo di cabang tertentu
                // Cabang Dago: diskon persentase untuk Cappuccino & Latte
                if ($branch->id === $branches[0]->id && in_array($menuItem->name, ['Cappuccino', 'Caffe Latte'])) {
                    $data['discount_type'] = 'percentage';
                    $data['discount_percentage'] = 15.00;
                    $data['is_promo_active'] = true;
                }

                // Cabang Braga: diskon potongan langsung untuk Matcha & Mocha
                if ($branch->id === $branches[1]->id && in_array($menuItem->name, ['Matcha Latte', 'Mocha'])) {
                    $data['discount_type'] = 'fixed';
                    $data['discount_amount'] = 5000.00;
                    $data['is_promo_active'] = true;
                }

                // Beberapa menu habis stoknya di cabang Paskal
                if ($branch->id === $branches[2]->id && in_array($menuItem->name, ['Affogato', 'Manual Brew V60'])) {
                    $data['is_available'] = false;
                    $data['stock'] = 0;
                }

                MenuItemBranch::create($data);
            }
        }

        $this->call([
            VoucherSeeder::class,
            OrderSeeder::class,
        ]);
    }
}
