<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\MenuItemBranch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    protected XenditService $xenditService;

    public function __construct(XenditService $xenditService)
    {
        $this->xenditService = $xenditService;
    }

    /**
     * Buat order baru.
     *
     * @param User $user
     * @param int $branchId
     * @param array $items  [['menu_item_id' => x, 'quantity' => y, 'notes' => '...']]
     * @param string $paymentMethod  'xendit' | 'cash'
     * @param string|null $notes
     * @return Order
     */
    public function createOrder(?User $user, int $branchId, array $items, string $paymentMethod, ?string $notes = null, string $guestName = null): Order
    {
        return DB::transaction(function () use ($user, $branchId, $items, $paymentMethod, $notes, $guestName) {
            $subtotal = '0.00';
            $discountTotal = '0.00';
            $orderItems = [];

            foreach ($items as $item) {
                // Ambil data menu di cabang
                $menuBranch = MenuItemBranch::where('menu_item_id', $item['menu_item_id'])
                    ->where('branch_id', $branchId)
                    ->where('is_available', true)
                    ->with('menuItem')
                    ->first();

                if (!$menuBranch) {
                    throw ValidationException::withMessages([
                        'items' => ["Menu item ID {$item['menu_item_id']} tidak tersedia di cabang ini."],
                    ]);
                }

                // Cek stok
                if ($menuBranch->stock !== null && $menuBranch->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok '{$menuBranch->menuItem->name}' tidak mencukupi. Sisa: {$menuBranch->stock}"],
                    ]);
                }

                // Hitung harga
                $basePrice = $menuBranch->menuItem->base_price;
                $unitPrice = $menuBranch->final_price; // sudah termasuk diskon jika ada
                $itemSubtotal = bcmul($unitPrice, (string) $item['quantity'], 2);
                $itemDiscount = bcmul(bcsub($basePrice, $unitPrice, 2), (string) $item['quantity'], 2);

                $subtotal = bcadd($subtotal, bcmul($basePrice, (string) $item['quantity'], 2), 2);
                $discountTotal = bcadd($discountTotal, $itemDiscount, 2);

                // Kurangi stok
                if ($menuBranch->stock !== null) {
                    $menuBranch->decrement('stock', $item['quantity']);
                }

                $orderItems[] = [
                    'menu_item_id' => $item['menu_item_id'],
                    'menu_item_name' => $menuBranch->menuItem->name,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                    'notes' => $item['notes'] ?? null,
                ];
            }

            $totalAmount = bcsub($subtotal, $discountTotal, 2);

            // Hitung loyalty points: 1 poin per Rp 10.000
            $loyaltyPoints = $user ? (int) bcdiv($totalAmount, '10000', 0) : 0;

            $order = Order::create([
                'user_id' => $user?->id,
                'guest_name' => $guestName ?? null,
                'branch_id' => $branchId,
                'order_number' => Order::generateOrderNumber(),
                'status' => 'pending',
                'payment_method' => $paymentMethod,
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'total_amount' => $totalAmount,
                'loyalty_points_earned' => $loyaltyPoints,
                'notes' => $notes,
            ]);

            foreach ($orderItems as $orderItem) {
                $order->items()->create($orderItem);
            }

            // Jika bayar via Xendit, buat invoice
            if ($paymentMethod === 'xendit') {
                $order->load('items');
                if ($user) {
                    $order->load('user');
                }
                try {
                    $invoice = $this->xenditService->createInvoice($order);
                    $order->update([
                        'xendit_invoice_id' => $invoice['invoice_id'],
                        'xendit_invoice_url' => $invoice['invoice_url'],
                    ]);
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                    if (method_exists($e, 'getResponseBody')) {
                        $responseBody = $e->getResponseBody();
                        if (is_string($responseBody)) {
                            $decoded = json_decode($responseBody, true);
                            if (isset($decoded['message'])) {
                                $errorMessage = $decoded['message'];
                            }
                        }
                    }
                    
                    // Rollback stok jika gagal buat invoice
                    throw ValidationException::withMessages([
                        'payment' => ['Gagal membuat invoice pembayaran: ' . $e->getMessage()],
                    ]);
                }
            }

            $order->load('items');

            return $order;
        });
    }

    /**
     * Konfirmasi pembayaran tunai oleh admin.
     */
    public function confirmCashPayment(Order $order): Order
    {
        if ($order->payment_method !== 'cash') {
            throw ValidationException::withMessages([
                'payment_method' => ['Pesanan ini bukan pembayaran tunai.'],
            ]);
        }

        if ($order->payment_status === 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => ['Pesanan ini sudah dibayar.'],
            ]);
        }

        $order->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        // Tambah loyalty points
        if ($order->user_id) {
            $order->user->increment('loyalty_points', $order->loyalty_points_earned);
        }

        return $order->fresh(['items', 'user', 'branch']);
    }

    /**
     * Handle callback webhook dari Xendit.
     */
    public function handleXenditCallback(array $payload): ?Order
    {
        $externalId = $payload['external_id'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$externalId || !$status) {
            return null;
        }

        $order = Order::where('order_number', $externalId)->first();

        if (!$order) {
            return null;
        }

        match ($status) {
            'PAID', 'SETTLED' => $this->markAsPaid($order),
            'EXPIRED' => $this->markAsExpired($order),
            default => null,
        };

        return $order->fresh();
    }

    /**
     * Tandai order sebagai sudah dibayar (dari Xendit).
     */
    protected function markAsPaid(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return; // Idempotent
        }

        $order->update([
            'payment_status' => 'paid',
            'status' => 'confirmed',
            'paid_at' => now(),
        ]);

        // Tambah loyalty points
        if ($order->user_id) {
            $order->user->increment('loyalty_points', $order->loyalty_points_earned);
        }
    }

    /**
     * Tandai order sebagai expired (Xendit timeout).
     */
    protected function markAsExpired(Order $order): void
    {
        $order->update([
            'payment_status' => 'expired',
            'status' => 'cancelled',
        ]);

        // Kembalikan stok
        $this->restoreStock($order);
    }

    /**
     * Cancel order oleh customer.
     */
    public function cancelOrder(Order $order): Order
    {
        if (!$order->isCancellable()) {
            throw ValidationException::withMessages([
                'status' => ['Pesanan tidak bisa dibatalkan karena statusnya sudah ' . $order->status . '.'],
            ]);
        }

        $order->update([
            'status' => 'cancelled',
            'payment_status' => $order->payment_status === 'unpaid' ? 'failed' : $order->payment_status,
        ]);

        // Kembalikan stok
        $this->restoreStock($order);

        return $order->fresh(['items']);
    }

    /**
     * Update status pesanan oleh admin.
     */
    public function updateStatus(Order $order, string $newStatus): Order
    {
        $allowedTransitions = [
            'confirmed' => ['preparing'],
            'preparing' => ['ready'],
            'ready' => ['completed'],
        ];

        $currentStatus = $order->status;

        if (!isset($allowedTransitions[$currentStatus]) || !in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw ValidationException::withMessages([
                'status' => ["Tidak bisa mengubah status dari '{$currentStatus}' ke '{$newStatus}'."],
            ]);
        }

        $order->update(['status' => $newStatus]);

        return $order->fresh(['items', 'user', 'branch']);
    }

    /**
     * Kembalikan stok saat order dibatalkan.
     */
    protected function restoreStock(Order $order): void
    {
        $order->load('items');

        foreach ($order->items as $item) {
            $menuBranch = MenuItemBranch::where('menu_item_id', $item->menu_item_id)
                ->where('branch_id', $order->branch_id)
                ->first();

            if ($menuBranch && $menuBranch->stock !== null) {
                $menuBranch->increment('stock', $item->quantity);
            }
        }
    }
}
