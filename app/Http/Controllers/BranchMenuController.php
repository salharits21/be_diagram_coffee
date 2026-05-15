<?php
// app/Http/Controllers/BranchMenuController.php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MenuItem;
use Illuminate\Http\Request;

class BranchMenuController extends Controller
{
    public function index(Request $request, Branch $branch)
    {
        // Ambil menu yang aktif di sistem DAN tersedia di cabang ini
        $menuItems = $branch->menuItems()
            ->with('category')
            ->where('menu_items.is_active', true)
            ->wherePivot('is_available', true)
            ->get();

        $data = $menuItems->map(function ($item) {
            $pivot = $item->pivot;
            
            // Hitung harga final
            $basePrice = (float) $item->base_price;
            $finalPrice = $basePrice;

            if ($pivot->is_promo_active) {
                if ($pivot->discount_type === 'percentage') {
                    $finalPrice = $basePrice - ($basePrice * ($pivot->discount_percentage / 100));
                } elseif ($pivot->discount_type === 'fixed') {
                    $finalPrice = $basePrice - $pivot->discount_amount;
                }
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'description' => $item->description,
                'image_url' => $item->image_url,
                'category' => $item->category->name ?? null,
                'base_price' => $basePrice,
                'final_price' => max(0, $finalPrice),
                'stock' => $pivot->stock,
                'is_promo_active' => (bool) $pivot->is_promo_active,
                'discount_type' => $pivot->discount_type,
                'discount_percentage' => $pivot->discount_percentage,
                'discount_amount' => $pivot->discount_amount,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
    
    public function show(Branch $branch, MenuItem $menuItem)
    {
        // Cari menu item yang spesifik di cabang ini dan pastikan tersedia
        $item = $branch->menuItems()
            ->with('category')
            ->where('menu_items.id', $menuItem->id)
            ->where('menu_items.is_active', true)
            ->wherePivot('is_available', true)
            ->first();

        // Jika menu tidak ditemukan di cabang tersebut
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Menu tidak tersedia di cabang ini',
            ], 404);
        }

        $pivot = $item->pivot;
        $basePrice = (float) $item->base_price;
        $finalPrice = $basePrice;

        // Hitung harga final berdasarkan promo di cabang tersebut
        if ($pivot->is_promo_active) {
            if ($pivot->discount_type === 'percentage') {
                $finalPrice = $basePrice - ($basePrice * ($pivot->discount_percentage / 100));
            } elseif ($pivot->discount_type === 'fixed') {
                $finalPrice = $basePrice - $pivot->discount_amount;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail menu cabang berhasil diambil',
            'data' => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
                'description' => $item->description,
                'image_url' => $item->image_url,
                'category' => $item->category->name ?? null,
                'base_price' => $basePrice,
                'final_price' => max(0, $finalPrice),
                'stock' => $pivot->stock,
                'is_promo_active' => (bool) $pivot->is_promo_active,
                'discount_type' => $pivot->discount_type,
                'discount_percentage' => $pivot->discount_percentage,
                'discount_amount' => $pivot->discount_amount,
            ]
        ]);
    }
}