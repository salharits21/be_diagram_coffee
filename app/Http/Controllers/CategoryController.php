<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Menampilkan semua kategori (beserta jumlah menu aktif).
     */
    public function index()
    {
        $categories = Category::withCount(['menuItems' => function ($query) {
                $query->where('is_active', true);
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar kategori berhasil diambil',
            'data' => $categories,
        ]);
    }

    /**
     * Menampilkan detail kategori beserta menu-menunya.
     */
    public function show(Category $category)
    {
        $category->load(['menuItems' => function ($query) {
            $query->where('is_active', true)->orderBy('name');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Detail kategori berhasil diambil',
            'data' => $category,
        ]);
    }

    /**
     * Membuat kategori baru.
     * Akses: Super Admin only
     */
    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']);

        // Pastikan slug unik
        $originalSlug = $data['slug'];
        $counter = 1;
        while (Category::withTrashed()->where('slug', $data['slug'])->exists()) {
            $data['slug'] = $originalSlug . '-' . $counter++;
        }

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil ditambahkan',
            'data' => $category,
        ], 201);
    }

    /**
     * Mengupdate kategori.
     * Akses: Super Admin only
     */
    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $data = $request->validated();
        $oldSortOrder = $category->sort_order;

        // Regenerate slug jika nama berubah
        if (isset($data['name'])) {
            $slug = Str::slug($data['name']);
            $originalSlug = $slug;
            $counter = 1;
            while (Category::withTrashed()->where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                $slug = $originalSlug . '-' . $counter++;
            }
            $data['slug'] = $slug;
        }

        if (isset($data['sort_order'])) {
            $existingCategory = Category::where('sort_order', $data['sort_order'])->where('id', '!=', $category->id)->first();
            if($existingCategory){
                $existingCategory->update([
                    'sort_order' => $oldSortOrder
                ]);
            }
            $data['sort_order'] = $data['sort_order'];
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil diperbarui',
            'data' => $category,
        ]);
    }

    /**
     * Menghapus kategori (soft delete).
     * Akses: Super Admin only
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kategori berhasil dihapus',
        ]);
    }
}
