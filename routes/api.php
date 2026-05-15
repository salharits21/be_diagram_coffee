<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\MenuItemBranchController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AdminOrderController;
use App\Http\Controllers\XenditWebhookController;
use App\Http\Controllers\BranchMenuController;

// Public Routes (Tidak perlu login)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public: Daftar cabang aktif
Route::get('/branches', [BranchController::class, 'index']);
Route::get('/branches/{branch}', [BranchController::class, 'show']);

// Public: Daftar kategori & menu
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);
Route::get('/menu-items', [MenuItemController::class, 'index']);
Route::get('/menu-items/{menuItem}', [MenuItemController::class, 'show']);
Route::get('/branches/{branch}/menus', [BranchMenuController::class, 'index']);
Route::get('/branches/{branch}/menus/{menuItem}', [BranchMenuController::class, 'show']);
Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/status/{orderNumber}', [OrderController::class, 'guestStatus']);

// Xendit Webhook (public, tapi diverifikasi oleh token)
Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle']);

// Protected Routes (Harus membawa Bearer Token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Endpoint untuk mendapatkan data profile user yang sedang login
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diambil',
            'data' => $request->user()
        ]);
    });

    // ==========================================
    // Customer Order Routes
    // ==========================================
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // ==========================================
    // Super Admin Routes
    // ==========================================
    Route::middleware('role:super_admin')->group(function () {
        // Manajemen Cabang
        Route::get('/admin/branches', [BranchController::class, 'all']);
        Route::post('/admin/branches', [BranchController::class, 'store']);
        Route::put('/admin/branches/{branch}', [BranchController::class, 'update']);
        Route::delete('/admin/branches/{branch}', [BranchController::class, 'destroy']);

        // Manajemen Kategori
        Route::post('/admin/categories', [CategoryController::class, 'store']);
        Route::put('/admin/categories/{category}', [CategoryController::class, 'update']);
        Route::delete('/admin/categories/{category}', [CategoryController::class, 'destroy']);

        // Manajemen Menu (CRUD penuh)
        Route::post('/admin/menu-items', [MenuItemController::class, 'store']);
        Route::put('/admin/menu-items/{menuItem}', [MenuItemController::class, 'update']);
        Route::delete('/admin/menu-items/{menuItem}', [MenuItemController::class, 'destroy']);

        // Assign/Unassign menu ke cabang
        Route::post('/admin/branches/{branch}/menu-items/{menuItem}', [MenuItemBranchController::class, 'assign']);
        Route::delete('/admin/branches/{branch}/menu-items/{menuItem}', [MenuItemBranchController::class, 'unassign']);

        // Manajemen Admin
        Route::get('/admin/admins', [AdminController::class, 'index']);
        Route::get('/admin/admins/{admin}', [AdminController::class, 'show']);
        Route::post('/admin/admins', [AdminController::class, 'store']);
        Route::put('/admin/admins/{admin}', [AdminController::class, 'update']);
        Route::delete('/admin/admins/{admin}', [AdminController::class, 'destroy']);
    });

    // ==========================================
    // Super Admin & Admin Routes
    // ==========================================
    Route::middleware('role:super_admin,admin')->group(function () {
        // Lihat & update stok menu per cabang
        Route::get('/admin/branches/{branch}/stock', [MenuItemBranchController::class, 'index']);
        Route::put('/admin/branches/{branch}/menu-items/{menuItem}/stock', [MenuItemBranchController::class, 'update']);

        // Manajemen Pesanan
        Route::get('/admin/orders', [AdminOrderController::class, 'index']);
        Route::get('/admin/orders/{order}', [AdminOrderController::class, 'show']);
        Route::put('/admin/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);
        Route::post('/admin/orders/{order}/confirm-cash', [AdminOrderController::class, 'confirmCash']);
    });
});
