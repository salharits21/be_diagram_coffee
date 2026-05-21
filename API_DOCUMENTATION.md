# Diagram Coffee Backend — API Documentation

> **Base URL:** `http://localhost:8000/api`
> **Auth Method:** Laravel Sanctum (Bearer Token)

---

## Daftar Isi

1. [Autentikasi](#1-autentikasi)
2. [Profil User](#2-profil-user)
3. [Cabang (Branch)](#3-cabang-branch)
4. [Kategori](#4-kategori)
5. [Menu Item](#5-menu-item)
6. [Menu per Cabang (Branch Menu)](#6-menu-per-cabang-branch-menu)
7. [Pesanan (Order)](#7-pesanan-order)
8. [Voucher & Loyalty](#8-voucher--loyalty)
9. [Banner Promo](#9-banner-promo)
10. [Manajemen Admin](#10-manajemen-admin)
11. [Manajemen Stok Cabang](#11-manajemen-stok-cabang)
12. [Manajemen Pesanan (Admin)](#12-manajemen-pesanan-admin)
13. [Statistik Penjualan](#13-statistik-penjualan)
14. [Rekomendasi Menu](#14-rekomendasi-menu)
15. [Webhook Xendit](#15-webhook-xendit)
16. [Internal API (Python ML)](#16-internal-api-python-ml)

---

## Legenda Role

| Simbol | Role | Keterangan |
|--------|------|------------|
| 🌐 | Public | Tanpa autentikasi |
| 👤 | Customer | Customer yang login |
| 🔧 | Admin | Admin cabang |
| 👑 | Super Admin | Super Admin |

---

## 1. Autentikasi

### `POST /register` 🌐

Registrasi akun customer baru.

**Body:**
```json
{
  "name": "string, required, max:255",
  "email": "string, required, email, unique",
  "password": "string, required, min:8, confirmed",
  "password_confirmation": "string, required"
}
```

> **Aturan Password:** Minimal 1 huruf kecil, 1 huruf besar, 1 angka, dan 1 simbol (`!@#$%^&*`).

**Response `201`:**
```json
{
  "success": true,
  "message": "Registrasi berhasil. Silakan login dengan Bearer token.",
  "data": {
    "user": { ... },
    "access_token": "1|abc...",
    "token_type": "Bearer"
  }
}
```

---

### `POST /login` 🌐

Login menggunakan email dan password. Mengembalikan Bearer token.

**Body:**
```json
{
  "email": "string, required",
  "password": "string, required"
}
```

**Response `200`:**
```json
{
  "success": true,
  "message": "Login berhasil",
  "data": {
    "user": { "id": 1, "name": "...", "email": "...", "role": "customer", ... },
    "access_token": "1|abc...",
    "token_type": "Bearer"
  }
}
```

**Response `401`:**
```json
{ "success": false, "message": "Email atau password salah" }
```

---

### `POST /logout` 👤🔧👑

Logout dan revoke access token.

**Headers:** `Authorization: Bearer {access_token}`

**Response `200`:**
```json
{ "success": true, "message": "Logout berhasil" }
```

---

### `POST /forgot-password` 🌐

Kirim link reset password ke email.

**Body:**
```json
{ "email": "string, required, email" }
```

**Response `200`:**
```json
{ "success": true, "message": "Link reset password berhasil dikirim ke email Anda" }
```

---

### `POST /reset-password` 🌐

Reset password menggunakan token dari email.

**Body:**
```json
{
  "token": "string, required",
  "email": "string, required, email",
  "password": "string, required, min:8, confirmed",
  "password_confirmation": "string, required"
}
```

---

### `GET /email/verify/{id}/{hash}` 🌐

Verifikasi email (diakses dari link di email). Redirect ke frontend setelah berhasil.

---

### `POST /email/resend` 👤🔧👑

Kirim ulang email verifikasi.

---

## 2. Profil User

### `GET /user` 👤🔧👑

Mendapatkan data profil user yang sedang login.

**Response `200`:**
```json
{
  "success": true,
  "message": "Data user berhasil diambil",
  "data": { "id": 1, "name": "...", "email": "...", "role": "customer", "loyalty_points": 150, ... }
}
```

---

### `PUT /user/profile` 👤🔧👑

Edit nama profil.

**Body:**
```json
{ "name": "string, required, max:255" }
```

---

## 3. Cabang (Branch)

### `GET /branches` 🌐

Daftar cabang yang aktif.

**Response `200`:**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Diagram Coffee Dago", "address": "...", "phone": "...", "status": "active", "opening_time": "08:00", "closing_time": "22:00" }
  ]
}
```

---

### `GET /branches/{id}` 🌐

Detail satu cabang.

---

### `GET /admin/branches` 👑

Semua cabang termasuk yang `inactive` (untuk panel admin).

---

### `POST /admin/branches` 👑

Buat cabang baru.

**Body:**
```json
{
  "name": "string, required, max:255",
  "address": "string, required",
  "phone": "string, nullable, numeric, min:7, unique",
  "status": "active|inactive, optional (default: active)",
  "opening_time": "HH:mm, nullable",
  "closing_time": "HH:mm, nullable"
}
```

---

### `PUT /admin/branches/{id}` 👑

Update cabang.

---

### `DELETE /admin/branches/{id}` 👑

Hapus cabang (soft delete).

---

## 4. Kategori

### `GET /categories` 🌐

Daftar kategori beserta jumlah menu aktif (`menu_items_count`).

---

### `GET /categories/{id}` 🌐

Detail kategori beserta daftar menu aktif di dalamnya.

---

### `POST /admin/categories` 👑

Buat kategori baru. Slug di-generate otomatis dari `name`.

**Body:**
```json
{
  "name": "string, required, max:255",
  "description": "string, nullable",
  "sort_order": "integer, nullable"
}
```

---

### `PUT /admin/categories/{id}` 👑

Update kategori.

---

### `DELETE /admin/categories/{id}` 👑

Hapus kategori (soft delete).

---

## 5. Menu Item

### `GET /menu-items` 🌐

Daftar semua menu (hanya menu aktif untuk customer/guest).

**Query Params:**
| Param | Type | Keterangan |
|-------|------|------------|
| `category_id` | int | Filter by kategori |
| `search` | string | Cari berdasarkan nama |

---

### `GET /menu-items/{id}` 🌐

Detail menu beserta ketersediaan per cabang.

---

### `POST /admin/menu-items` 👑

Buat menu baru. **Form-data** (karena upload gambar).

**Body (multipart/form-data):**
| Field | Type | Keterangan |
|-------|------|------------|
| `category_id` | int | required, exists |
| `name` | string | required, unique, max:100 |
| `description` | string | nullable |
| `base_price` | numeric | required, min:1 |
| `image_url` | file | nullable, jpeg/png/jpg/webp, max:2MB |
| `is_active` | boolean | optional |

---

### `PUT /admin/menu-items/{id}` 👑

Update menu (form-data jika ada gambar baru).

---

### `DELETE /admin/menu-items/{id}` 👑

Hapus menu (soft delete).

---

## 6. Menu per Cabang (Branch Menu)

### `GET /branches/{branch_id}/menus` 🌐

Daftar menu yang tersedia di cabang tertentu, termasuk harga final setelah diskon.

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Cappuccino",
      "slug": "cappuccino",
      "description": "...",
      "image_url": "...",
      "category": "Coffee",
      "base_price": 30000,
      "final_price": 25500,
      "stock": 25,
      "is_promo_active": true,
      "discount_type": "percentage",
      "discount_percentage": 15,
      "discount_amount": null
    }
  ]
}
```

---

### `GET /branches/{branch_id}/menus/{menu_item_id}` 🌐

Detail satu menu di cabang tertentu.

---

## 7. Pesanan (Order)

### `POST /orders` 🌐👤

Buat pesanan baru. Bisa sebagai guest atau customer yang login.

**Body:**
```json
{
  "branch_id": "int, required",
  "order_type": "dine_in | take_away, required",
  "table_number": "string, required jika dine_in, max:10",
  "payment_method": "xendit | cash, required",
  "notes": "string, nullable, max:500",
  "guest_name": "string, required jika tidak login",
  "voucher_id": "int, nullable (ID dari tabel user_vouchers)",
  "items": [
    {
      "menu_item_id": "int, required",
      "quantity": "int, required, min:1, max:100",
      "notes": "string, nullable, max:255"
    }
  ]
}
```

> **Biaya Admin:** Rp 2.000 ditambahkan otomatis ke semua transaksi.
>
> **Loyalty Points:** 1 poin per Rp 10.000 (hanya untuk customer yang login).
>
> **Voucher:** Hanya bisa dipakai oleh customer yang login. Harus belum expired dan belum digunakan.

**Response `201`:**
```json
{
  "success": true,
  "message": "Pesanan berhasil dibuat",
  "data": {
    "id": 1,
    "order_number": "ORD-20260519-A1B2C",
    "order_type": "dine_in",
    "table_number": "5",
    "status": "pending",
    "payment_method": "xendit",
    "payment_status": "unpaid",
    "subtotal": "60000.00",
    "discount_total": "0.00",
    "admin_fee": "2000.00",
    "total_amount": "62000.00",
    "loyalty_points_earned": 6,
    "xendit_invoice_url": "https://checkout.xendit.co/...",
    "items": [ ... ],
    "branch": { ... }
  }
}
```

---

### `GET /orders` 👤

Riwayat pesanan milik customer yang sedang login.

---

### `GET /orders/{id}` 👤

Detail pesanan (customer hanya bisa melihat miliknya).

---

### `POST /orders/{id}/cancel` 👤

Batalkan pesanan (hanya saat status masih `pending`).

---

### `GET /orders/status/{order_number}` 🌐

Cek status pesanan secara publik berdasarkan nomor order.

---

## 8. Voucher & Loyalty

### `GET /vouchers` 👤

Daftar semua voucher yang aktif dan bisa ditukar.

**Response `200`:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Diskon 10rb",
      "code": "DISC10K",
      "discount_amount": 10000,
      "min_transaction_amount": 50000,
      "points_required": 10,
      "is_active": true
    }
  ]
}
```

---

### `POST /vouchers/exchange` 👤

Tukar poin loyalty dengan voucher. Poin dikurangi, voucher berlaku 30 hari.

**Body:**
```json
{ "voucher_id": "int, required, exists" }
```

---

### `GET /vouchers/my-vouchers` 👤

Daftar voucher yang dimiliki user (diurutkan: belum dipakai dulu, lalu yang segera expired).

---

### `POST /admin/vouchers` 👑

Buat voucher baru.

**Body:**
```json
{
  "name": "string, required, max:255",
  "code": "string, required, unique, max:50",
  "discount_amount": "numeric, required, min:0",
  "min_transaction_amount": "numeric, required, min:0",
  "points_required": "int, required, min:0",
  "is_active": "boolean, optional"
}
```

---

### `PUT /admin/vouchers/{id}` 👑

Update voucher.

---

### `DELETE /admin/vouchers/{id}` 👑

Hapus voucher.

---

## 9. Banner Promo

### `GET /banners` 🌐

Daftar banner promo yang aktif.

---

### `GET /admin/banners` 👑

Semua banner (termasuk nonaktif).

---

### `POST /admin/banners` 👑

Buat banner baru. **Form-data** (karena upload gambar).

**Body (multipart/form-data):**
| Field | Type | Keterangan |
|-------|------|------------|
| `title` | string | required |
| `description` | string | nullable |
| `image` | file | required, jpeg/png/jpg/webp, max:2MB |
| `is_active` | boolean | optional |
| `sort_order` | int | optional |

---

### `PUT /admin/banners/{id}` 👑

Update banner.

---

### `DELETE /admin/banners/{id}` 👑

Hapus banner (beserta file gambar).

---

## 10. Manajemen Admin

### `GET /admin/admins` 👑

Daftar semua akun admin beserta cabangnya.

---

### `GET /admin/admins/{id}` 👑

Detail admin.

---

### `GET /admin/branches/{branch_id}/admins` 👑

Daftar admin yang ditugaskan di cabang tertentu.

---

### `POST /admin/admins` 👑

Buat akun admin baru.

**Body:**
```json
{
  "name": "string, required",
  "email": "string, required, unique",
  "password": "string, required, min:8, confirmed",
  "password_confirmation": "string, required",
  "branch_id": "int, required, exists"
}
```

---

### `PUT /admin/admins/{id}` 👑

Update data admin (nama, email, password, cabang).

---

### `DELETE /admin/admins/{id}` 👑

Hapus akun admin (hard delete + revoke tokens).

---

## 11. Manajemen Stok Cabang

### `GET /admin/branches/{branch_id}/stock` 👑🔧

Lihat stok & promo semua menu di cabang tertentu. Admin hanya bisa melihat cabangnya sendiri.

---

### `PUT /admin/branches/{branch_id}/menu-items/{menu_item_id}/stock` 👑🔧

Update stok, ketersediaan, dan promo menu di cabang.

**Body:**
```json
{
  "is_available": "boolean, optional",
  "stock": "int, nullable, min:0",
  "discount_type": "percentage | fixed | null",
  "discount_percentage": "numeric, min:0, max:100 (wajib jika discount_type=percentage)",
  "discount_amount": "numeric, min:0 (wajib jika discount_type=fixed)",
  "is_promo_active": "boolean, optional"
}
```

---

### `POST /admin/branches/{branch_id}/menu-items/{menu_item_id}` 👑

Assign menu ke cabang (buat record pivot baru).

---

### `DELETE /admin/branches/{branch_id}/menu-items/{menu_item_id}` 👑

Unassign (hapus) menu dari cabang.

---

## 12. Manajemen Pesanan (Admin)

### `GET /admin/orders` 👑🔧

Daftar pesanan. Admin hanya melihat pesanan di cabangnya.

**Query Params:**
| Param | Type | Keterangan |
|-------|------|------------|
| `status` | string | Filter: `pending`, `confirmed`, `preparing`, `ready`, `completed`, `cancelled` |
| `payment_status` | string | Filter: `unpaid`, `paid`, `expired` |
| `branch_id` | int | Filter cabang (Super Admin only) |

---

### `GET /admin/orders/{id}` 👑🔧

Detail pesanan.

---

### `PUT /admin/orders/{id}/status` 👑🔧

Update status pesanan secara bertahap.

**Body:**
```json
{ "status": "preparing | ready | completed" }
```

> **Alur Status:** `pending` → `confirmed` → `preparing` → `ready` → `completed`

---

### `POST /admin/orders/{id}/confirm-cash` 👑🔧

Konfirmasi pembayaran tunai (mengubah `payment_status` menjadi `paid`).

---

## 13. Statistik Penjualan

### `GET /admin/statistics` 👑🔧

Mendapatkan statistik penjualan.

**Query Params:**
| Param | Type | Keterangan |
|-------|------|------------|
| `days` | int | Jumlah hari terakhir (default: 7, max: 30) |
| `branch_id` | int | Filter cabang (Super Admin only) |

> **Admin:** Otomatis di-scope ke cabangnya sendiri.
> **Super Admin:** Melihat semua cabang, bisa filter per cabang.

**Response `200`:**
```json
{
  "success": true,
  "data": {
    "today_transactions": 5,
    "today_revenue": 250000,
    "daily_revenue": [
      { "date": "2026-05-13", "revenue": 120000, "transaction_count": 3 },
      { "date": "2026-05-14", "revenue": 85000, "transaction_count": 2 }
    ],
    "top_menus": [
      { "menu_item_id": 1, "menu_item_name": "Espresso", "total_sold": 15, "total_sales": 330000 },
      { "menu_item_id": 4, "menu_item_name": "Caffe Latte", "total_sold": 12, "total_sales": 384000 }
    ]
  }
}
```

---

## 14. Rekomendasi Menu

### `GET /recommendations` 🌐👤

Mendapatkan rekomendasi menu berdasarkan algoritma ML. Mendukung guest dan customer.

**Query Params:**
| Param | Type | Keterangan |
|-------|------|------------|
| `branch_id` | int | **Required**. Cabang untuk filter ketersediaan stok. |
| `limit` | int | Jumlah rekomendasi per kategori (default: 5, max: 20) |

> **Guest:** Mengembalikan `popularity` saja.
> **Customer:** Mengembalikan `popularity`, `ibcf`, dan `hybrid` (dipanggil secara paralel).

**Response `200` (Guest):**
```json
{
  "success": true,
  "data": {
    "popularity": [
      { "id": 1, "name": "Espresso", "base_price": "22000.00", ... }
    ]
  }
}
```

**Response `200` (Customer):**
```json
{
  "success": true,
  "data": {
    "popularity": [ ... ],
    "ibcf": [ ... ],
    "hybrid": [ ... ]
  }
}
```

> Jika IBCF/Hybrid gagal menemukan data customer, key tersebut akan berisi array kosong `[]`.

---

## 15. Webhook Xendit

### `POST /webhooks/xendit` 🌐

Endpoint callback untuk Xendit Payment Gateway. Diverifikasi menggunakan header `x-callback-token`.

> Endpoint ini dipanggil otomatis oleh Xendit saat status pembayaran berubah (`PAID`, `EXPIRED`, dll).

---

## 16. Internal API (Python ML)

### `GET /internal/transactions` 🔒

Export data transaksi yang sudah completed untuk dijadikan dataset training ML.

**Headers:**
```
X-API-KEY: secret_key_123
```

**Response `200`:**
```json
[
  {
    "transaction_date": "2026-05-18",
    "transaction_id": "ORD-20260518-A1B2C",
    "customer_id": 5,
    "menu_id": 1,
    "menu_name": "Espresso",
    "category": "Coffee",
    "quantity": 2,
    "price": 22000,
    "total_price": 44000
  }
]
```

**Response `401`:**
```json
{ "success": false, "message": "Unauthorized access to internal API." }
```

---

## Format Response Umum

Semua response mengikuti pola konsisten:

```json
{
  "success": true | false,
  "message": "Deskripsi hasil",
  "data": { ... } | [ ... ]
}
```

### Error Codes

| Status | Keterangan |
|--------|------------|
| `200` | OK |
| `201` | Created |
| `401` | Unauthorized (belum login / token salah) |
| `403` | Forbidden (tidak punya akses) |
| `404` | Not Found |
| `409` | Conflict (data sudah ada) |
| `422` | Validation Error |
| `500` | Server Error |

### Validation Error Response `422`:
```json
{
  "message": "The branch id field is required.",
  "errors": {
    "branch_id": ["The branch id field is required."]
  }
}
```
