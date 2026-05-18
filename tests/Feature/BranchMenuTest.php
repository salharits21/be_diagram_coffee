<?php

use App\Models\User;
use App\Models\Branch;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\MenuItemBranch;

beforeEach(function () {
    $this->branch = Branch::factory()->create(['status' => 'active']);
    $this->category = Category::factory()->create(['name' => 'Coffee']);

    $this->menu1 = MenuItem::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Espresso',
        'base_price' => 25000,
        'is_active' => true,
    ]);
    $this->menu2 = MenuItem::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Latte',
        'base_price' => 35000,
        'is_active' => true,
    ]);
    $this->menuInactive = MenuItem::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Discontinued Coffee',
        'base_price' => 20000,
        'is_active' => false,
    ]);

    // Assign menu1 — tersedia
    $this->menuBranch1 = MenuItemBranch::factory()->create([
        'menu_item_id' => $this->menu1->id,
        'branch_id' => $this->branch->id,
        'is_available' => true,
        'stock' => 20,
    ]);

    // Assign menu2 — tersedia
    $this->menuBranch2 = MenuItemBranch::factory()->create([
        'menu_item_id' => $this->menu2->id,
        'branch_id' => $this->branch->id,
        'is_available' => true,
        'stock' => 15,
    ]);

    // Assign menuInactive — aktif di cabang tapi is_active=false di menu
    MenuItemBranch::factory()->create([
        'menu_item_id' => $this->menuInactive->id,
        'branch_id' => $this->branch->id,
        'is_available' => true,
        'stock' => 10,
    ]);
});

// ==========================================
// Index: Daftar Menu Cabang
// ==========================================

describe('Branch Menu Index', function () {
    test('can list available menu items for a branch', function () {
        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data'); // hanya 2, karena menuInactive is_active=false
    });

    test('response format contains expected fields', function () {
        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $response->assertOk();

        $firstItem = $response->json('data.0');
        expect($firstItem)->toHaveKeys([
            'id', 'name', 'slug', 'description', 'image_url',
            'category', 'base_price', 'final_price', 'stock',
            'is_promo_active', 'discount_type', 'discount_percentage', 'discount_amount',
        ]);
    });

    test('does not show unavailable menu items', function () {
        // Buat menu3 yang tidak tersedia
        $menu3 = MenuItem::factory()->create([
            'category_id' => $this->category->id,
            'name' => 'Sold Out Coffee',
            'is_active' => true,
        ]);
        MenuItemBranch::factory()->create([
            'menu_item_id' => $menu3->id,
            'branch_id' => $this->branch->id,
            'is_available' => false,
            'stock' => 0,
        ]);

        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $response->assertOk()
            ->assertJsonCount(2, 'data'); // tetap 2, Sold Out tidak muncul

        $names = collect($response->json('data'))->pluck('name')->toArray();
        expect($names)->not->toContain('Sold Out Coffee');
    });

    test('does not show inactive menu items', function () {
        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $names = collect($response->json('data'))->pluck('name')->toArray();
        expect($names)->not->toContain('Discontinued Coffee');
    });

    test('shows correct final_price without promo', function () {
        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $espresso = collect($response->json('data'))->firstWhere('name', 'Espresso');
        expect($espresso['base_price'])->toEqual(25000)
            ->and($espresso['final_price'])->toEqual(25000)
            ->and($espresso['is_promo_active'])->toBeFalse();
    });

    test('shows correct final_price with percentage promo', function () {
        $this->menuBranch1->update([
            'discount_type' => 'percentage',
            'discount_percentage' => 20.00,
            'is_promo_active' => true,
        ]);

        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $espresso = collect($response->json('data'))->firstWhere('name', 'Espresso');
        // 25000 - (25000 * 20/100) = 25000 - 5000 = 20000
        expect($espresso['final_price'])->toEqual(20000)
            ->and($espresso['is_promo_active'])->toBeTrue()
            ->and($espresso['discount_type'])->toBe('percentage');
    });

    test('shows correct final_price with fixed promo', function () {
        $this->menuBranch2->update([
            'discount_type' => 'fixed',
            'discount_amount' => 5000.00,
            'is_promo_active' => true,
        ]);

        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $latte = collect($response->json('data'))->firstWhere('name', 'Latte');
        // 35000 - 5000 = 30000
        expect($latte['final_price'])->toEqual(30000)
            ->and($latte['is_promo_active'])->toBeTrue()
            ->and($latte['discount_type'])->toBe('fixed');
    });

    test('final_price cannot be negative', function () {
        // Diskon lebih besar dari base price
        $this->menuBranch1->update([
            'discount_type' => 'fixed',
            'discount_amount' => 99999.00,
            'is_promo_active' => true,
        ]);

        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $espresso = collect($response->json('data'))->firstWhere('name', 'Espresso');
        expect($espresso['final_price'])->toEqual(0); // capped at 0
    });

    test('returns empty data for branch with no menu', function () {
        $emptyBranch = Branch::factory()->create(['status' => 'active']);

        $response = $this->getJson("/api/branches/{$emptyBranch->id}/menus");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('category name is returned correctly', function () {
        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $espresso = collect($response->json('data'))->firstWhere('name', 'Espresso');
        expect($espresso['category'])->toBe('Coffee');
    });

    test('stock is included in response', function () {
        $response = $this->getJson("/api/branches/{$this->branch->id}/menus");

        $espresso = collect($response->json('data'))->firstWhere('name', 'Espresso');
        expect($espresso['stock'])->toBe(20);
    });
});

// ==========================================
// Show: Detail Menu Cabang
// ==========================================

describe('Branch Menu Show', function () {
    test('can view detail of available menu in branch', function () {
        $response = $this->getJson(
            "/api/branches/{$this->branch->id}/menus/{$this->menu1->id}"
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->menu1->id)
            ->assertJsonPath('data.name', 'Espresso')
            ->assertJsonPath('data.base_price', 25000)
            ->assertJsonPath('data.stock', 20);
    });

    test('returns 404 for unavailable menu in branch', function () {
        // Buat menu yang tidak tersedia di cabang
        $unavailableMenu = MenuItem::factory()->create([
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
        MenuItemBranch::factory()->create([
            'menu_item_id' => $unavailableMenu->id,
            'branch_id' => $this->branch->id,
            'is_available' => false,
        ]);

        $response = $this->getJson(
            "/api/branches/{$this->branch->id}/menus/{$unavailableMenu->id}"
        );

        $response->assertNotFound()
            ->assertJsonPath('success', false);
    });

    test('returns 404 for inactive menu item', function () {
        $response = $this->getJson(
            "/api/branches/{$this->branch->id}/menus/{$this->menuInactive->id}"
        );

        $response->assertNotFound();
    });

    test('returns 404 for menu not assigned to branch', function () {
        $unassignedMenu = MenuItem::factory()->create([
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);
        // Tidak di-assign ke branch

        $response = $this->getJson(
            "/api/branches/{$this->branch->id}/menus/{$unassignedMenu->id}"
        );

        $response->assertNotFound();
    });

    test('show includes promo details', function () {
        $this->menuBranch1->update([
            'discount_type' => 'percentage',
            'discount_percentage' => 15.00,
            'is_promo_active' => true,
        ]);

        $response = $this->getJson(
            "/api/branches/{$this->branch->id}/menus/{$this->menu1->id}"
        );

        $response->assertOk()
            ->assertJsonPath('data.is_promo_active', true)
            ->assertJsonPath('data.discount_type', 'percentage');

        // 25000 - (25000 * 15/100) = 21250
        expect($response->json('data.final_price'))->toEqual(21250);
    });
});
