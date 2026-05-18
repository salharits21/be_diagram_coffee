<?php

use App\Models\User;
use App\Models\Banner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->customer = User::factory()->create(['role' => 'customer']);
});

/**
 * Helper: Buat fake image dengan rasio 3:1.
 */
function bannerImage(int $width = 1200, int $height = 400): UploadedFile
{
    return UploadedFile::fake()->image('banner.jpg', $width, $height);
}

// ==========================================
// Public: List Banner Aktif
// ==========================================

describe('Public Banner Listing', function () {
    test('can list active banners', function () {
        Banner::factory()->count(3)->create(['is_active' => true]);
        Banner::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/banners');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    });

    test('inactive banners are not shown', function () {
        Banner::factory()->inactive()->count(3)->create();

        $response = $this->getJson('/api/banners');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });

    test('banners are sorted by sort_order', function () {
        Banner::factory()->create(['title' => 'Third', 'sort_order' => 3]);
        Banner::factory()->create(['title' => 'First', 'sort_order' => 1]);
        Banner::factory()->create(['title' => 'Second', 'sort_order' => 2]);

        $response = $this->getJson('/api/banners');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        expect($titles)->toBe(['First', 'Second', 'Third']);
    });

    test('returns empty array when no banners exist', function () {
        $response = $this->getJson('/api/banners');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    });
});

// ==========================================
// Super Admin: CRUD Banner
// ==========================================

describe('Super Admin Banner Management', function () {
    test('super admin can list all banners including inactive', function () {
        Banner::factory()->count(2)->create(['is_active' => true]);
        Banner::factory()->count(3)->create(['is_active' => false]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/banners');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    test('super admin can create banner with valid image', function () {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'title' => 'Promo Ramadhan',
                'image' => bannerImage(),
                'description' => 'Diskon 50% semua menu',
                'link_url' => 'https://example.com/promo',
                'sort_order' => 1,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Promo Ramadhan')
            ->assertJsonPath('data.description', 'Diskon 50% semua menu')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.sort_order', 1);

        // Pastikan file disimpan
        $banner = Banner::first();
        Storage::disk('public')->assertExists($banner->image_url);
    });

    test('super admin can create banner without optional fields', function () {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'title' => 'Banner Minimal',
                'image' => bannerImage(),
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Banner Minimal')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.link_url', null);
    });

    test('super admin can update banner title without changing image', function () {
        $banner = Banner::factory()->create(['title' => 'Old Title']);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/admin/banners/{$banner->id}", [
                'title' => 'New Title',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'New Title');
    });

    test('super admin can update banner image', function () {
        $banner = Banner::factory()->create([
            'image_url' => 'banners/old-image.jpg',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/admin/banners/{$banner->id}", [
                'image' => bannerImage(900, 300),
            ]);

        $response->assertOk();

        $banner->refresh();
        expect($banner->image_url)->not->toBe('banners/old-image.jpg');
        Storage::disk('public')->assertExists($banner->image_url);
    });

    test('super admin can toggle banner active status', function () {
        $banner = Banner::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/admin/banners/{$banner->id}", [
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false);

        $banner->refresh();
        expect($banner->is_active)->toBeFalse();
    });

    test('super admin can delete banner', function () {
        $banner = Banner::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/admin/banners/{$banner->id}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('banners', ['id' => $banner->id]);
    });
});

// ==========================================
// Validasi
// ==========================================

describe('Banner Validation', function () {
    test('title is required when creating', function () {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'image' => bannerImage(),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title']);
    });

    test('image is required when creating', function () {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'title' => 'Test Banner',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    });

    test('image must have 3:1 ratio', function () {
        // Gambar 1:1 (bukan 3:1)
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'title' => 'Bad Ratio',
                'image' => UploadedFile::fake()->image('square.jpg', 500, 500),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    });

    test('link_url must be a valid url', function () {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'title' => 'Invalid Link',
                'image' => bannerImage(),
                'link_url' => 'not-a-url',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['link_url']);
    });

    test('image max size is 2MB', function () {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/admin/banners', [
                'title' => 'Too Large',
                'image' => UploadedFile::fake()->image('big.jpg', 1200, 400)->size(3000),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['image']);
    });
});

// ==========================================
// Access Control
// ==========================================

describe('Banner Access Control', function () {
    test('admin cannot create banner', function () {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/banners', [
                'title' => 'Blocked',
                'image' => bannerImage(),
            ]);

        $response->assertForbidden();
    });

    test('customer cannot create banner', function () {
        $response = $this->actingAs($this->customer)
            ->postJson('/api/admin/banners', [
                'title' => 'Blocked',
                'image' => bannerImage(),
            ]);

        $response->assertForbidden();
    });

    test('admin cannot delete banner', function () {
        $banner = Banner::factory()->create();

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/admin/banners/{$banner->id}");

        $response->assertForbidden();
    });

    test('unauthenticated cannot access admin banners', function () {
        $response = $this->getJson('/api/admin/banners');

        $response->assertUnauthorized();
    });
});
