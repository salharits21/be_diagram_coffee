<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Branch;
use App\Models\MenuItemBranch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', 'customer')->get();
        $branch = Branch::first();
        $menuItemBranches = MenuItemBranch::with('menuItem')->where('branch_id', $branch->id)->get();

        if ($users->isEmpty() || !$branch || $menuItemBranches->isEmpty()) {
            return;
        }

        // Generate 50 completed orders for training data
        for ($i = 1; $i <= 50; $i++) {
            $user = $users->random();
            
            $order = Order::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'order_number' => 'ORD-SEED-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'order_type' => 'take_away',
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'status' => 'completed',
                'subtotal' => 0,
                'discount_total' => 0,
                'admin_fee' => 2000,
                'total_amount' => 0,
                'loyalty_points_earned' => 0,
                'paid_at' => now()->subDays(rand(1, 30)), // Random date in last 30 days
            ]);

            // Add 1 to 3 random items
            $numItems = rand(1, 3);
            $subtotal = 0;
            
            for ($j = 0; $j < $numItems; $j++) {
                $menuBranch = $menuItemBranches->random();
                $qty = rand(1, 2);
                $price = $menuBranch->menuItem->base_price;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $menuBranch->menu_item_id,
                    'menu_item_name' => $menuBranch->menuItem->name,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'subtotal' => $price * $qty,
                ]);
                
                $subtotal += ($price * $qty);
            }
            
            $totalAmount = $subtotal + 2000; // + admin fee
            $order->update([
                'subtotal' => $subtotal,
                'total_amount' => $totalAmount,
                'loyalty_points_earned' => floor($totalAmount / 10000)
            ]);
        }
    }
}
