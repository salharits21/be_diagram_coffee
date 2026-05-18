<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Menampilkan semua admin.
     * Akses: Super Admin only
     */
    public function index()
    {
        $admins = User::where('role', 'admin')
            ->with('branch')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar admin berhasil diambil',
            'data' => $admins,
        ]);
    }

    /**
     * Menampilkan daftar admin berdasarkan cabang (branch_id).
     */
    public function getByBranch(int $branchId)
    {
        $admins = User::where('role', 'admin')
            ->where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar admin untuk cabang tersebut berhasil diambil',
            'data' => $admins,
        ]);
    }

    /**
     * Menampilkan detail admin.
     */
    public function show(int $admin)
    {
        $admin = User::where('role', 'admin')
            ->with('branch')
            ->findOrFail($admin);

        return response()->json([
            'success' => true,
            'message' => 'Detail admin berhasil diambil',
            'data' => $admin,
        ]);
    }

    /**
     * Membuat admin baru dan assign ke cabang.
     */
    public function store(StoreAdminRequest $request)
    {
        $data = $request->validated();

        $admin = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'admin',
            'branch_id' => $data['branch_id'],
            'email_verified_at' => now(),
        ]);

        $admin->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil ditambahkan',
            'data' => $admin,
        ], 201);
    }

    /**
     * Mengupdate data admin (nama, email, password, cabang).
     */
    public function update(UpdateAdminRequest $request, int $admin)
    {
        $adminUser = User::where('role', 'admin')->findOrFail($admin);

        $data = $request->validated();

        // Hash password jika diubah
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $adminUser->update($data);
        $adminUser->load('branch');

        return response()->json([
            'success' => true,
            'message' => 'Data admin berhasil diperbarui',
            'data' => $adminUser,
        ]);
    }

    /**
     * Menghapus akun admin (hard delete).
     */
    public function destroy(int $admin)
    {
        $adminUser = User::where('role', 'admin')->findOrFail($admin);

        $adminUser->tokens()->delete(); // Revoke semua token
        $adminUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'Admin berhasil dihapus',
        ]);
    }
}
