<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use App\Models\MenuItemBranch;
use App\Http\Requests\MenuItem\StoreMenuItemRequest;
use App\Http\Requests\MenuItem\UpdateMenuItemRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class MenuItemController extends Controller
{
    /**
     * Menampilkan semua menu (untuk customer: hanya yang aktif).
     */
    public function index(Request $request)
    {
        $query = MenuItem::with('category');

        // Customer hanya lihat menu aktif
        if (!$request->user() || $request->user()->isCustomer()) {
            $query->where('is_active', true);
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $menuItems = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar menu berhasil diambil',
            'data' => $menuItems,
        ]);
    }

    /**
     * Menampilkan detail menu beserta ketersediaan per cabang.
     */
    public function show(MenuItem $menuItem)
    {
        $menuItem->load(['category', 'branches']);

        return response()->json([
            'success' => true,
            'message' => 'Detail menu berhasil diambil',
            'data' => $menuItem,
        ]);
    }

    /**
     * Menampilkan menu yang tersedia di cabang tertentu.
     */
    public function byBranch(Request $request, int $branchId)
    {
        $menuItems = MenuItem::where('is_active', true)
            ->with('category')
            ->whereHas('branches', function ($query) use ($branchId) {
                $query->where('branches.id', $branchId)
                    ->where('menu_item_branch.is_available', true);
            })
            ->with(['branches' => function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            }])
            ->orderBy('name')
            ->get();

        // Append harga final ke setiap menu
        $menuItems->each(function ($menuItem) {
            $pivot = $menuItem->branches->first()?->pivot;
            if ($pivot) {
                $menuItem->setAttribute('stock', $pivot->stock);
                $menuItem->setAttribute('is_available', $pivot->is_available);
                $menuItem->setAttribute('is_promo_active', $pivot->is_promo_active);
                $menuItem->setAttribute('discount_type', $pivot->discount_type);
                $menuItem->setAttribute('discount_percentage', $pivot->discount_percentage);
                $menuItem->setAttribute('discount_amount', $pivot->discount_amount);
                $basePrice = $menuItem->base_price;
                $finalPrice = $basePrice;

                if ($pivot->is_promo_active && $pivot->discount_type) {
                    if ($pivot->discount_type === 'percentage') {
                        $finalPrice = $basePrice - ($basePrice * ($pivot->discount_percentage / 100));
                    } elseif ($pivot->discount_type === 'fixed') {
                        $finalPrice = $basePrice - $pivot->discount_amount;
                    }
                }
                
                $menuItem->setAttribute('final_price', max(0, $finalPrice));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Daftar menu cabang berhasil diambil',
            'data' => $menuItems,
        ]);
    }

    /**
     * Membuat menu baru.
     * Akses: Super Admin only
     */
    public function store(StoreMenuItemRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        // Pastikan slug unik
        $originalSlug = $data['slug'];
        $counter = 1;
        while (MenuItem::withTrashed()->where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter++;
        }

        $data['image_url'] = Storage::disk('public')->put('menu-images', $request->file('image_url'));

        $menuItem = MenuItem::create($data);
        $menuItem->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil ditambahkan',
            'data' => $menuItem,
        ], 201);
    }

    /**
     * Mengupdate menu.
     * Akses: Super Admin only
     */
    public function update(UpdateMenuItemRequest $request, MenuItem $menuItem)
    {
        $data = $request->validated();

        // Regenerate slug jika nama berubah
        if (isset($data['name'])) {
            $slug = Str::slug($data['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (MenuItem::withTrashed()->where('slug', $slug)->where('id', '!=', $menuItem->id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $data['slug'] = $slug;
        }

        if ($request->hasFile('image_url')) {
            if ($menuItem->image_url) {
                Storage::disk('public')->delete($menuItem->image_url);
            }
            $data['image_url'] = Storage::disk('public')->put('menu-images', $request->file('image_url'));
        }

        $menuItem->update($data);
        $menuItem->load('category');

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil diperbarui',
            'data' => $menuItem,
        ]);
    }

    /**
     * Menghapus menu (soft delete).
     * Akses: Super Admin only
     */
    public function destroy(MenuItem $menuItem)
    {
        $menuItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu berhasil dihapus',
        ]);
    }
}
