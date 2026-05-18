<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemBranch;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->category = Category::factory()->create();
    $this->customer = User::factory()->create(['role' => 'customer']);

    $this->menu1 = MenuItem::factory()->create(['category_id' => $this->category->id, 'name' => 'Espresso']);
    $this->menu2 = MenuItem::factory()->create(['category_id' => $this->category->id, 'name' => 'Latte']);
    $this->menu3 = MenuItem::factory()->create(['category_id' => $this->category->id, 'name' => 'Mocha']);

    MenuItemBranch::factory()->create(['menu_item_id' => $this->menu1->id, 'branch_id' => $this->branch->id, 'is_available' => true, 'stock' => 10]);
    MenuItemBranch::factory()->create(['menu_item_id' => $this->menu2->id, 'branch_id' => $this->branch->id, 'is_available' => true, 'stock' => 10]);
    MenuItemBranch::factory()->create(['menu_item_id' => $this->menu3->id, 'branch_id' => $this->branch->id, 'is_available' => false, 'stock' => 10]); // Unavailable
});

describe('Internal Transaction Export API', function () {
    test('internal api requires valid api key', function () {
        $response = $this->getJson('/api/internal/transactions', [
            'X-API-KEY' => 'wrong_key',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    });

    test('internal api exports completed transactions', function () {
        $order = \App\Models\Order::factory()->create([
            'user_id' => $this->customer->id,
            'branch_id' => $this->branch->id,
            'status' => 'completed',
        ]);
        
        \App\Models\OrderItem::factory()->create([
            'order_id' => $order->id,
            'menu_item_id' => $this->menu1->id,
            'quantity' => 2,
        ]);

        $response = $this->getJson('/api/internal/transactions', [
            'X-API-KEY' => config('services.internal.api_key'),
        ]);

        $response->assertOk()
            ->assertJsonCount(1);
            
        $data = $response->json()[0];
        expect($data['menu_name'])->toBe('Espresso')
            ->and($data['quantity'])->toBe(2)
            ->and($data['customer_id'])->toBe($this->customer->id);
    });
});

describe('Recommendation API', function () {
    test('guest gets popularity recommendations and filters unavailable items', function () {
        Http::fake([
            '*/popularity' => Http::response([
                ['menu_name' => 'Espresso'],
                ['menu_name' => 'Mocha'], // This one is unavailable in DB
                ['menu_name' => 'Latte'],
            ], 200)
        ]);

        $response = $this->getJson("/api/recommendations?branch_id={$this->branch->id}");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.popularity') // Only Espresso and Latte
            ->assertJsonMissingPath('data.ibcf') // Guest shouldn't get ibcf
            ->assertJsonMissingPath('data.hybrid'); // Guest shouldn't get hybrid

        $data = $response->json('data.popularity');
        expect($data[0]['name'])->toBe('Espresso')
            ->and($data[1]['name'])->toBe('Latte');
    });

    test('authenticated user gets popularity, ibcf, and hybrid recommendations', function () {
        Http::fake([
            "*/popularity" => Http::response([
                ['menu_name' => 'Espresso'],
            ], 200),
            "*/ibcf/{$this->customer->id}" => Http::response([
                ['menu_name' => 'Latte'],
            ], 200),
            "*/hybrid/{$this->customer->id}" => Http::response([
                ['menu_name' => 'Espresso'],
                ['menu_name' => 'Latte'],
            ], 200),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/recommendations?branch_id={$this->branch->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.popularity')
            ->assertJsonCount(1, 'data.ibcf')
            ->assertJsonCount(2, 'data.hybrid');

        expect($response->json('data.popularity.0.name'))->toBe('Espresso')
            ->and($response->json('data.ibcf.0.name'))->toBe('Latte')
            ->and($response->json('data.hybrid.0.name'))->toBe('Espresso');
    });

    test('ibcf and hybrid return empty array if customer not found', function () {
        Http::fake([
            "*/popularity" => Http::response([
                ['menu_name' => 'Espresso'],
            ], 200),
            "*/ibcf/{$this->customer->id}" => Http::response([
                'message' => 'Customer tidak ditemukan'
            ], 200),
            "*/hybrid/{$this->customer->id}" => Http::response([
                'message' => 'Customer tidak ditemukan'
            ], 200),
        ]);

        $response = $this->actingAs($this->customer)
            ->getJson("/api/recommendations?branch_id={$this->branch->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data.popularity')
            ->assertJsonCount(0, 'data.ibcf')
            ->assertJsonCount(0, 'data.hybrid');
    });
});
