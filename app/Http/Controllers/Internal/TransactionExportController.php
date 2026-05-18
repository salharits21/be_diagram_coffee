<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class TransactionExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $orders = Order::where('status', 'completed')
            ->with(['items.menuItem.category', 'user'])
            ->get();

        $data = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                // Pastikan menuItem tidak null (misal jika sudah di-soft delete)
                if (!$item->menuItem) continue;

                $data[] = [
                    'transaction_date' => $order->created_at->format('Y-m-d'),
                    'transaction_id' => $order->order_number,
                    'customer_id' => $order->user_id ?? 'guest',
                    'menu_id' => $item->menu_item_id,
                    'menu_name' => $item->menuItem->name,
                    'category' => $item->menuItem->category ? $item->menuItem->category->name : 'Uncategorized',
                    'quantity' => $item->quantity,
                    'price' => $item->menuItem->base_price,
                    'total_price' => $item->subtotal,
                ];
            }
        }

        return response()->json($data);
    }
}
