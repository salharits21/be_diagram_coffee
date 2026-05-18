<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemBranch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use App\Services\XenditService;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->category = Category::factory()->create();

    $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'branch_id' => $this->branch->id,
    ]);
    $this->customer = User::factory()->create(['role' => 'customer', 'loyalty_points' => 0]);

    // Buat 2 menu dan assign ke cabang
    $this->menu1 = MenuItem::factory()->create(['category_id' => $this->category->id, 'name' => 'Espresso', 'base_price' => 25000]);
    $this->menu2 = MenuItem::factory()->create(['category_id' => $this->category->id, 'name' => 'Latte', 'base_price' => 35000]);

    $this->menuBranch1 = MenuItemBranch::factory()->create([
        'menu_item_id' => $this->menu1->id,
        'branch_id' => $this->branch->id,
        'is_available' => true,
        'stock' => 20,
    ]);
    $this->menuBranch2 = MenuItemBranch::factory()->create([
        'menu_item_id' => $this->menu2->id,
        'branch_id' => $this->branch->id,
        'is_available' => true,
        'stock' => 15,
    ]);
});

/**
 * Helper: mock XenditService supaya tidak panggil API asli.
 */
function mockXenditService(): void
{
    $mock = Mockery::mock(XenditService::class);
    $mock->shouldReceive('createInvoice')->andReturn([
        'invoice_id' => 'inv_test_123',
        'invoice_url' => 'https://checkout-staging.xendit.co/web/test123',
    ]);
    $mock->shouldReceive('verifyWebhookToken')->andReturn(true);
    app()->instance(XenditService::class, $mock);
}

// ==========================================
// Customer: Buat Order
// ==========================================

describe('Customer Create Order', function () {
    test('customer can create cash order', function () {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 2],
                    ['menu_item_id' => $this->menu2->id, 'quantity' => 1],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.status', 'pending');

        // Cek order di database
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 2);

        // Cek harga: 25000*2 + 35000*1 = 85000
        $order = Order::first();
        expect($order->total_amount)->toBe('85000.00');

        // Cek loyalty: 85000 / 10000 = 8 poin
        expect($order->loyalty_points_earned)->toBe(8);
    });

    test('customer can create xendit order', function () {
        mockXenditService();

        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'xendit',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_method', 'xendit')
            ->assertJsonPath('data.xendit_invoice_id', 'inv_test_123')
            ->assertJsonPath('data.xendit_invoice_url', 'https://checkout-staging.xendit.co/web/test123');
    });

    test('stock is reduced when order is created', function () {
        $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 3],
                ],
            ]);

        $this->menuBranch1->refresh();
        expect($this->menuBranch1->stock)->toBe(17); // 20 - 3
    });

    test('order with promo calculates discount', function () {
        // Set diskon 10% untuk menu1
        $this->menuBranch1->update([
            'discount_type' => 'percentage',
            'discount_percentage' => 10.00,
            'is_promo_active' => true,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 2],
                ],
            ]);

        $response->assertCreated();

        $order = Order::first();
        // base: 25000*2 = 50000, discount: 2500*2 = 5000, total: 45000
        expect($order->subtotal)->toBe('50000.00')
            ->and($order->discount_total)->toBe('5000.00')
            ->and($order->total_amount)->toBe('45000.00');
    });

    test('cannot order unavailable menu', function () {
        $this->menuBranch1->update(['is_available' => false]);

        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });

    test('cannot order more than available stock', function () {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 999],
                ],
            ]);

        $response->assertUnprocessable();
    });

    test('order requires items', function () {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });

    test('order requires valid branch', function () {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => 9999,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);
    });

    test('guest without guest_name gets validation error', function () {
        $response = $this->postJson('/api/orders', [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'items' => [['menu_item_id' => $this->menu1->id, 'quantity' => 1]],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['guest_name']);
    });
});

// ==========================================
// Guest Ordering (Tanpa Login)
// ==========================================

describe('Guest Ordering', function () {
    test('guest can create cash order with guest_name', function () {
        $response = $this->postJson('/api/orders', [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'guest_name' => 'Tamu Budi',
            'items' => [
                ['menu_item_id' => $this->menu1->id, 'quantity' => 2],
                ['menu_item_id' => $this->menu2->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.guest_name', 'Tamu Budi')
            ->assertJsonPath('data.payment_method', 'cash')
            ->assertJsonPath('data.status', 'pending');

        // user_id harus null
        $order = Order::first();
        expect($order->user_id)->toBeNull()
            ->and($order->guest_name)->toBe('Tamu Budi')
            ->and($order->total_amount)->toBe('85000.00');
    });

    test('guest can create xendit order', function () {
        mockXenditService();

        $response = $this->postJson('/api/orders', [
            'branch_id' => $this->branch->id,
            'payment_method' => 'xendit',
            'guest_name' => 'Tamu Ani',
            'items' => [
                ['menu_item_id' => $this->menu1->id, 'quantity' => 1],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_method', 'xendit')
            ->assertJsonPath('data.guest_name', 'Tamu Ani')
            ->assertJsonPath('data.xendit_invoice_id', 'inv_test_123');
    });

    test('guest does not earn loyalty points', function () {
        $this->postJson('/api/orders', [
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'guest_name' => 'Tamu No Points',
            'items' => [
                ['menu_item_id' => $this->menu1->id, 'quantity' => 4],
            ],
        ]);

        $order = Order::first();
        // 25000*4 = 100000 → 10 poin, tapi guest = 0
        expect($order->loyalty_points_earned)->toBe(0);
    });

    test('guest can check order status by order number', function () {
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => null,
            'guest_name' => 'Tamu Check',
        ]);

        $response = $this->getJson("/api/orders/status/{$order->order_number}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.order_number', $order->order_number);
    });

    test('guest status check returns 404 for invalid order number', function () {
        $response = $this->getJson('/api/orders/status/ORD-INVALID-99999');

        $response->assertNotFound();
    });

    test('logged-in customer does not need guest_name', function () {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 1],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.user_id', $this->customer->id);

        $order = Order::first();
        expect($order->user_id)->toBe($this->customer->id)
            ->and($order->guest_name)->toBeNull();
    });
});

// ==========================================
// Customer: Riwayat & Detail
// ==========================================

describe('Customer Order History', function () {
    test('customer can view own order history', function () {
        Order::factory()->count(3)->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
        ]);
        // Order milik user lain
        Order::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->customer)
            ->getJson('/api/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('customer can view own order detail', function () {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id);
    });

    test('customer cannot view other user order', function () {
        $otherOrder = Order::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/orders/{$otherOrder->id}");

        $response->assertNotFound();
    });
});

// ==========================================
// Customer: Cancel Order
// ==========================================

describe('Customer Cancel Order', function () {
    test('customer can cancel pending order', function () {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'pending',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $this->menu1->id,
            'quantity' => 2,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    });

    test('stock is restored when order is cancelled', function () {
        // Reduce stock via order creation first
        $this->actingAs($this->customer)
            ->postJson('/api/orders', [
                'branch_id' => $this->branch->id,
                'payment_method' => 'cash',
                'items' => [
                    ['menu_item_id' => $this->menu1->id, 'quantity' => 5],
                ],
            ]);

        $this->menuBranch1->refresh();
        expect($this->menuBranch1->stock)->toBe(15); // 20 - 5

        $order = Order::first();
        $this->actingAs($this->customer)
            ->postJson("/api/orders/{$order->id}/cancel");

        $this->menuBranch1->refresh();
        expect($this->menuBranch1->stock)->toBe(20); // restored
    });

    test('customer cannot cancel confirmed order', function () {
        $order = Order::factory()->confirmed()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/orders/{$order->id}/cancel");

        $response->assertUnprocessable();
    });

    test('customer cannot cancel other user order', function () {
        $otherOrder = Order::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->customer)
            ->postJson("/api/orders/{$otherOrder->id}/cancel");

        $response->assertNotFound();
    });
});

// ==========================================
// Admin: Daftar Pesanan
// ==========================================

describe('Admin Order Management', function () {
    test('super admin can view all orders', function () {
        $otherBranch = Branch::factory()->create();
        Order::factory()->count(2)->create(['branch_id' => $this->branch->id]);
        Order::factory()->create(['branch_id' => $otherBranch->id]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    test('admin can only view orders from own branch', function () {
        $otherBranch = Branch::factory()->create();
        Order::factory()->count(2)->create(['branch_id' => $this->branch->id]);
        Order::factory()->create(['branch_id' => $otherBranch->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/orders');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    test('admin can filter orders by status', function () {
        Order::factory()->create(['branch_id' => $this->branch->id, 'status' => 'pending']);
        Order::factory()->confirmed()->create(['branch_id' => $this->branch->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/orders?status=pending');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    test('customer cannot access admin orders', function () {
        $response = $this->actingAs($this->customer)
            ->getJson('/api/admin/orders');

        $response->assertForbidden();
    });
});

// ==========================================
// Admin: Update Status
// ==========================================

describe('Admin Update Order Status', function () {
    test('admin can update confirmed to preparing', function () {
        $order = Order::factory()->confirmed()->create([
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'preparing',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'preparing');
    });

    test('admin can update preparing to ready', function () {
        $order = Order::factory()->preparing()->create([
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'ready',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'ready');
    });

    test('admin can update ready to completed', function () {
        $order = Order::factory()->ready()->create([
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'completed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');
    });

    test('cannot skip status (pending to ready)', function () {
        $order = Order::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'ready',
            ]);

        $response->assertUnprocessable();
    });

    test('admin cannot update orders from other branch', function () {
        $otherBranch = Branch::factory()->create();
        $order = Order::factory()->confirmed()->create([
            'branch_id' => $otherBranch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/admin/orders/{$order->id}/status", [
                'status' => 'preparing',
            ]);

        $response->assertNotFound();
    });
});

// ==========================================
// Admin: Konfirmasi Tunai
// ==========================================

describe('Admin Confirm Cash Payment', function () {
    test('admin can confirm cash payment', function () {
        $order = Order::factory()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'loyalty_points_earned' => 8,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->id}/confirm-cash");

        $response->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'confirmed');

        // Cek loyalty points
        $this->customer->refresh();
        expect($this->customer->loyalty_points)->toBe(8);
    });

    test('cannot confirm non-cash payment as cash', function () {
        $order = Order::factory()->xendit()->create([
            'branch_id' => $this->branch->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->id}/confirm-cash");

        $response->assertUnprocessable();
    });

    test('cannot confirm already paid order', function () {
        $order = Order::factory()->paid()->create([
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/admin/orders/{$order->id}/confirm-cash");

        $response->assertUnprocessable();
    });
});

// ==========================================
// Xendit Webhook
// ==========================================

describe('Xendit Webhook', function () {
    test('webhook marks order as paid on PAID status', function () {
        mockXenditService();

        $order = Order::factory()->xendit()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-TEST-001',
            'loyalty_points_earned' => 5,
        ]);

        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'ORD-TEST-001',
            'status' => 'PAID',
        ], [
            'x-callback-token' => 'test-token',
        ]);

        $response->assertOk();

        $order->refresh();
        expect($order->payment_status)->toBe('paid')
            ->and($order->status)->toBe('confirmed')
            ->and($order->paid_at)->not->toBeNull();

        // Cek loyalty points
        $this->customer->refresh();
        expect($this->customer->loyalty_points)->toBe(5);
    });

    test('webhook marks order as expired', function () {
        mockXenditService();

        $order = Order::factory()->xendit()->create([
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-TEST-002',
        ]);
        OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $this->menu1->id,
            'quantity' => 3,
        ]);

        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'ORD-TEST-002',
            'status' => 'EXPIRED',
        ], [
            'x-callback-token' => 'test-token',
        ]);

        $response->assertOk();

        $order->refresh();
        expect($order->payment_status)->toBe('expired')
            ->and($order->status)->toBe('cancelled');
    });

    test('webhook with invalid token is rejected', function () {
        // Don't mock - use real service which will fail token check
        $response = $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'ORD-TEST-003',
            'status' => 'PAID',
        ], [
            'x-callback-token' => 'wrong-token',
        ]);

        $response->assertForbidden();
    });

    test('webhook is idempotent (double PAID callback)', function () {
        mockXenditService();

        $order = Order::factory()->xendit()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'order_number' => 'ORD-TEST-004',
            'loyalty_points_earned' => 3,
        ]);

        // First callback
        $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'ORD-TEST-004',
            'status' => 'PAID',
        ], ['x-callback-token' => 'test-token']);

        // Second callback (duplicate)
        $this->postJson('/api/webhooks/xendit', [
            'external_id' => 'ORD-TEST-004',
            'status' => 'PAID',
        ], ['x-callback-token' => 'test-token']);

        // Loyalty points hanya ditambahkan sekali
        $this->customer->refresh();
        expect($this->customer->loyalty_points)->toBe(3);
    });
});
