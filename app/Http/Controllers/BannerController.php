<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Http\Requests\Banner\StoreBannerRequest;
use App\Http\Requests\Banner\UpdateBannerRequest;
use Illuminate\Support\Facades\Storage;

class BannerController extends Controller
{
    /**
     * Public: List banner aktif (sorted).
     */
    public function index()
    {
        $banners = Banner::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar banner berhasil diambil',
            'data' => $banners,
        ]);
    }

    /**
     * Super Admin: List semua banner (termasuk nonaktif).
     */
    public function all()
    {
        $banners = Banner::ordered()->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar semua banner berhasil diambil',
            'data' => $banners,
        ]);
    }

    /**
     * Super Admin: Buat banner baru.
     */
    public function store(StoreBannerRequest $request)
    {
        $data = $request->validated();

        $data['image_url'] = Storage::disk('public')->put('banners', $request->file('image'));

        // Hapus field 'image' dari data (sudah diproses)
        unset($data['image']);

        $banner = Banner::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner berhasil ditambahkan',
            'data' => $banner->fresh(),
        ], 201);
    }

    /**
     * Super Admin: Update banner.
     */
    public function update(UpdateBannerRequest $request, Banner $banner)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            // Hapus gambar lama
            if ($banner->image_url) {
                Storage::disk('public')->delete($banner->image_url);
            }
            $data['image_url'] = Storage::disk('public')->put('banners', $request->file('image'));
        }

        // Hapus field 'image' dari data
        unset($data['image']);

        $banner->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Banner berhasil diperbarui',
            'data' => $banner,
        ]);
    }

    /**
     * Super Admin: Hapus banner.
     */
    public function destroy(Banner $banner)
    {
        // Hapus gambar
        if ($banner->image_url) {
            Storage::disk('public')->delete($banner->image_url);
        }

        $banner->delete();

        return response()->json([
            'success' => true,
            'message' => 'Banner berhasil dihapus',
        ]);
    }
}
