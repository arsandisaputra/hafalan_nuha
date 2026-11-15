# HafalanNuha — Aplikasi Tahfidz & Muraja'ah Mushaf Madinah (15 Baris)

**HafalanNuha** adalah aplikasi web personal bebas distraksi (*PWA-ready*) untuk menghafal Al-Qur'an (*Tahfidz*) dan menjaga hafalan (*Muraja'ah*) berbasis halaman **Mushaf Madinah (15 baris / 20 halaman per Juz / 604 halaman)**.

---

## Arsitektur & Tech Stack

- **Backend:** PHP 8.3+ dengan **Laravel 11/12** (*Service & Repository Pattern*)
- **Database:** MySQL / MariaDB (`quran_pages_cache`, `page_progress`, `page_notes`)
- **Frontend:** Blade + **Tailwind CSS** + **Alpine.js**
- **Data Eksternal:** Integrasi **Quran.com API v4** (`https://api.quran.com/api/v4`) untuk teks Rasm Utsmani, Terjemahan Kemenag RI (Resource ID `33`), serta audio murottal per ayat berdasarkan `page_number` (`1–604`).

---

## Cara Menjalankan Proyek

1. **Instalasi Dependensi & Konfigurasi Environment**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

2. **Konfigurasi Database MySQL / MariaDB di `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=hafalan_nuha
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. **Migrasi Database & Seeder Awal**
   ```bash
   php artisan migrate --seed
   ```

4. **Pre-cache Halaman Mushaf dari Quran.com API v4 (Opsional untuk Offline Penuh)**
   ```bash
   # Mengunduh dan menyimpan cache lokal halaman 1 s/d 604 (atau rentang tertentu)
   php artisan quran:cache-pages --from=1 --to=21
   ```

5. **Menjalankan Server Aplikasi**
   ```bash
   php artisan serve
   ```
   Buka `http://localhost:8000` di browser.
