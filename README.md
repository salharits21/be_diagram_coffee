# Coffeeshop Backend API (TA Capstone)

Repositori ini berisi *source code* untuk Backend API dari aplikasi e-commerce Coffeeshop dengan fitur *Personified Recommendation*. Backend ini bertindak sebagai API Gateway utama yang melayani aplikasi Frontend (React) dan berkomunikasi dengan Service Rekomendasi (Python FastAPI).

## 🚀 Tech Stack
* **Framework:** Laravel 12
* **Database:** MySQL
* **Authentication:** Laravel Sanctum (Token-based)

---

## 📋 Prerequisites
Sebelum memulai instalasi, pastikan sistem kamu sudah terpasang:
* **PHP** >= 8.2
* **Composer**
* **MySQL** / MariaDB
* **Git**

---

## 🛠️ Cara Setup di Local
Ikuti langkah-langkah berikut untuk menjalankan aplikasi di mesin lokal kamu:

1. **Clone Repositori**
   ```bash
   git clone <URL_REPO_GITHUB_KALIAN>
   cd coffeeshop-api

2. **Install Dependencies**
   ```bash
   composer install

3. **Setup Environment Variables**
   ```bash
   cp .env.example .env

   ```cuplikan
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=coffeeshop_db
   DB_USERNAME=root
   DB_PASSWORD=

4. **Generate Application Key**
   ```bash
   php artisan key:generate

5. **Database Migration**
   ```bash
   php artisan migrate

5. **Jalankan Development Server**
   ```bash
   php artisan serve

