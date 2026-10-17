# Ruang Dharma Studio — Laravel 11 + Vue 3 (Vite + Tailwind CSS 4)

Studio web untuk membuat **naskah, prompt visual & caption** video cerita Hindu anak Ruang Dharma (@ruangdharma_).
Ketik kata kunci (mis. "Rwa Bhineda"), pilih durasi (30/60/90 detik) dan gaya (narasi / ngobrol / gabungan), tekan **Buat video** —
loader canang 5 kelopak menyala per tahap, lalu hasil lengkap tampil: naskah 6 bagian + estimasi detik per bagian, prompt visual 9:16,
caption Instagram, pratinjau story 9:16 yang bisa diputar, dan kuota berkurang otomatis.

## Yang baru di versi ini

**UI/UX dirombak total** — gaya PT Kreatif Sky (gradien violet → fuchsia → oranye, aksen kuning "brutal", font Plus Jakarta Sans + Space Grotesk)
di atas kerangka panel bersih ala SISFOPERS (sidebar berkelompok, kartu pengguna & kuota di bawah, breadcrumb, tabel rapi). Mode gelap, responsif
sampai layar HP, pencarian menu cepat **Ctrl/⌘ + K**, toast & dialog konfirmasi sendiri, grafik SVG interaktif, drag & drop di kalender & media.

**Semua menu realtime tanpa refresh.** Frontend memanggil `GET /api/realtime/pulse` tiap 4 detik (30 detik saat tab tidak aktif, langsung saat tab
dibuka lagi). Server mengirim "stamp" per resource (video, review, notifikasi, pengguna, log, pengaturan, dll). Bila berubah, hanya halaman yang
memakai data itu yang dimuat ulang diam-diam; notifikasi baru muncul sebagai toast, badge sidebar & kuota ikut berubah. Tidak butuh server
WebSocket (Reverb/Pusher), jadi aman di aaPanel / shared hosting.

**Semua fitur memakai API asli.** Versi lama banyak menyimpan favorit, arsip, versi, komentar, review, paket, API key, webhook, media, jadwal,
role, notifikasi & log hanya di `localStorage` browser (hilang saat ganti perangkat). Sekarang semuanya tersimpan di database.

### Error & kekurangan yang diperbaiki
| Masalah | Dampak | Perbaikan |
|---|---|---|
| Migrasi `000005` & `000009` memakai `information_schema` | `php artisan migrate` **gagal di SQLite** (instalasi lokal default) | Pakai `Schema::getForeignKeys()` (portabel MySQL/MariaDB/SQLite/PostgreSQL) |
| `created_at` tidak fillable di log, notifikasi, versi, webhook log, riwayat login | Semua tanggal tersimpan **NULL** | Model memakai `UPDATED_AT = null` sehingga `created_at` terisi otomatis |
| `env()` dipanggil saat runtime (MuseService, MuseConfig) | Token dari `.env` **hilang setelah `config:cache`** | Dibaca lewat `config('services.muse.*')` |
| Placeholder `MUSE_API_TOKEN=change-me-...` di `.env.example` | Siapa pun bisa memakai token contoh itu | Nilai kosong/placeholder diabaikan otomatis |
| Middleware stateful Sanctum pada API berbasis token | Login bisa kena **419 CSRF mismatch** | Dihapus; API murni Bearer token |
| Admin ubah role hanya mengubah kolom `role` | Admin yang diturunkan **tetap punya akses admin** lewat pivot | Role kolom + pivot selalu disinkronkan; tidak bisa menonaktifkan/menurunkan diri sendiri; user nonaktif langsung dipaksa logout |
| Edit naskah memakai `validated()` dengan aturan wildcard | Kunci `suara`, `prompt_visual`, `bagian` **terbuang** saat menyimpan | Memakai input penuh + normalisasi |
| Upload avatar via `PUT` multipart | PHP tidak membaca body → **avatar tidak tersimpan** | Tambah `POST /api/profile` (multipart) |
| Komentar & detail video bisa diakses siapa saja | Kebocoran data antar pengguna | Hanya pemilik atau admin/editor |
| Webhook Muse mengabaikan `MUSE_WEBHOOK_SECRET` | Tidak sesuai dokumentasi | Mendukung `X-Muse-Signature` (HMAC-SHA256), `X-Muse-Webhook-Secret`, atau `X-Muse-Token` |
| Editor tidak punya halaman antrean review | Alur review tidak jalan untuk editor | `GET /api/review-queue` untuk admin/super admin/editor |
| Guard frontend hanya mengenal role `admin`; `/me` disimpan salah (`{success,user}`) | Super admin tertolak, data user rusak setelah refresh | Router & store baru berbasis role dari server |
| Durasi tidak memengaruhi naskah; kalimat gabungan tanpa titik | Naskah 30/60/90 detik sama panjang, teks "Ahimsa Merawat…" | 30 dtk diringkas, 90 dtk diperkaya, estimasi detik per bagian, penggabungan kalimat diperbaiki |
| Pengaturan keamanan & template notifikasi tidak dipakai | Hanya tampilan | Panjang minimal password dipakai di validasi; logout otomatis setelah tidak aktif; template notifikasi dipakai saat kirim notifikasi |
| Test memakai database utama & butuh `mockery` yang tidak terpasang | `phpunit` gagal / bisa menghapus data | `phpunit.xml` memaksa SQLite in-memory; `mockery/mockery` ditambahkan; 19 test baru (173 assertion) |
| Grup query `groupBy('date')` alias | Rawan error di MySQL `ONLY_FULL_GROUP_BY` | Group by ekspresi `DATE(created_at)` |
| Akun demo tampil di halaman login produksi | Risiko keamanan | Hanya tampil saat `APP_ENV=local` |

## Fitur
- Login token Sanctum, role `super_admin`, `admin`, `editor`, `pengguna`, `viewer` + role kustom & matriks izin
- **Buat video** dengan template, kuota per paket / per user / global, kata kunci terblokir, aturan wajib Om Swastyastu / Om Santi Santi Santi Om
- **Riwayat** (tabel / kartu, filter, favorit, arsip), **detail video**: pratinjau story 9:16, edit naskah + versi & pulihkan, prompt, caption, komentar, jadwal tayang, unduh .txt
- **Alur review**: draf → menunggu review → perlu revisi / disetujui → terbit, dengan notifikasi ke pemilik & reviewer
- **Kalender konten** (drag & drop untuk menjadwalkan), **Media** (drag & drop upload gambar/video), **Notifikasi**
- **Admin**: ringkasan, analitik (7/14/30/90 hari, tabel & grafik, ekspor CSV), semua video (ekspor CSV / cetak PDF), pengguna, role & izin, paket kuota,
  pengaturan (umum, generate otomatis, keamanan, template notifikasi), integrasi Muse (token & webhook secret terenkripsi), API key, webhook + log, log aktivitas, riwayat login
- **Buat video beneran via Muse (Cara 2)** — centang opsi di halaman Buat video; job dikirim ke Muse, statusnya (menunggu → diproses → selesai/gagal) terbarui realtime, video & klip tampil di detail video. Lihat `docs/MUSE_INTEGRATION.md`.
- **Auto generate harian** `app:generate-daily` (scheduler)
- Integrasi Muse: `GET /api/muse/status`, `POST /api/muse/generate`, `GET /api/muse/jobs/{id}`, `POST /api/muse/webhook`, `GET /api/muse/health`. Lihat `docs/MUSE_INTEGRATION.md`.

## Akun Demo (Seeder)
- Admin: `admin@ruangdharma.id` / `admin123`
- Pengguna: `user@ruangdharma.id` / `user123`

> ⚠️ Ganti password demo ini setelah deploy ke server produksi. Tombol isi-otomatis akun demo hanya tampil saat `APP_ENV=local`.

## Install Lokal
Butuh PHP 8.2+, Composer, Node.js **20+**.
```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan storage:link
npm install
npm run dev          # atau: npm run build
php artisan serve
# buka http://localhost:8000
```
Jalankan test: `composer test` (atau `php artisan config:clear && vendor/bin/phpunit`). Test selalu memakai SQLite in-memory.

> Catatan: `package-lock.json` sengaja dihapus karena dependency frontend berganti (Tailwind 4, Pinia 3, Vite 6). `npm install` pertama kali akan membuat lock file baru — silakan di-commit.

## Install di Server (VPS / Shared Hosting)
### VPS (Ubuntu + Nginx + PHP 8.2)
```bash
sudo apt update && sudo apt install -y php8.2 php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl php8.2-sqlite3 php8.2-mysql unzip nodejs npm
git clone <repo> /var/www/ruang-dharma-studio
cd /var/www/ruang-dharma-studio
composer install --optimize-autoloader --no-dev
cp .env.example .env
# edit .env: APP_URL, DB_*, MUSE_API_TOKEN
php artisan key:generate
php artisan migrate --seed --force
npm install && npm run build
php artisan config:cache && php artisan route:cache
# Nginx: root -> /var/www/ruang-dharma-studio/public, try_files $uri $uri/ /index.php?$query_string;
# chown -R www-data:www-data storage bootstrap/cache
```

### Shared Hosting (cPanel)
1. Upload semua file ke `ruang-dharma-studio/` di luar `public_html`, pindahkan isi `public/` ke `public_html/` (atau symlink).
2. Edit `public_html/index.php`: `require __DIR__.'/../ruang-dharma-studio/vendor/autoload.php'; $app = require __DIR__.'/../ruang-dharma-studio/bootstrap/app.php';`
3. Buat DB MySQL di cPanel, isi .env DB_*, jalankan `php artisan migrate --seed --force` via Terminal cPanel / cron sekali.
4. `npm run build` di lokal lalu upload hasil `public/build/` ke server.

---

# Deploy ke aaPanel (Langkah demi Langkah, Dari Nol)

Tutorial ini untuk VPS yang memakai **aaPanel** (panel gratis, mirip cPanel tapi untuk VPS). Diasumsikan VPS masih kosong / baru install Ubuntu 20.04 / 22.04 / Debian 11/12.

## 0. Gambaran Alur

```
VPS kosong
  → Install aaPanel
  → Install PHP 8.2 + MySQL + NodeJS + Composer lewat aaPanel
  → Buat Website (domain → /www/wwwroot/ruangdharma/public)
  → Buat Database MySQL
  → Clone / upload project dari GitHub
  → Setting .env (APP_URL, DB, MUSE_API_TOKEN)
  → composer install + migrate --seed + npm run build
  → Permission + SSL + cek web jalan
```

## 1. Install aaPanel

1. Login ke VPS via SSH (pakai Terminal / PuTTY / Termius).
2. Jalankan installer resmi aaPanel:
   ```bash
   # Ubuntu / Debian
   wget -O install.sh http://www.aapanel.com/script/install-ubuntu_6.0_en.sh && bash install.sh aapanel
   # CentOS
   # yum install -y wget && wget -O install.sh http://www.aapanel.com/script/install_6.0_en.sh && bash install.sh aapanel
   ```
3. Tunggu sampai selesai. Catat URL login, username, dan password yang ditampilkan di akhir install, contoh:
   ```
   ==================================================================
   aaPanel Internet Address: http://IP_VPS:8888/xxxxxx
   username: xxxxxxxx
   password: xxxxxxxx
   ==================================================================
   ```
4. Buka URL tersebut di browser, login. Saat pertama kali, aaPanel akan menawarkan install **LNMP / LAMP** — pilih **LNMP** (Nginx + MySQL + PHP), atau klik **Skip / Lewati** dan install manual di langkah berikutnya (lebih aman agar versi bisa dipilih).

> 🔒 Setelah login, segera ganti password aaPanel dan aktifkan 2FA jika tersedia di **Settings → Security**.

## 2. Install PHP 8.2 + Ekstensi yang Dibutuhkan

1. Di aaPanel, buka menu **App Store**.
2. Cari **PHP 8.2**, klik **Install**. Tunggu sampai status jadi **Installed**.
3. Klik **Settings / Set** pada PHP 8.2 → tab **Install Extensions**, install satu per satu:
   - `mbstring`
   - `xml`
   - `curl`
   - `sqlite3`
   - `mysqli` (untuk MySQL)
   - `zip`
   - `bcmath`
   - `fileinfo`
   - `gd` (opsional, untuk gambar/avatar)
   - `opcache` (opsional, untuk performa)
4. Di tab **Configuration** PHP 8.2, pastikan:
   ```
   memory_limit = 512M
   upload_max_filesize = 64M
   post_max_size = 64M
   max_execution_time = 300
   ```
   Klik **Save / Reload** setelah mengubah.
5. Di aaPanel → **Website → PHP Version** (nanti) pastikan website memakai **PHP 8.2**. Laravel 11 butuh PHP **>= 8.2**.

## 3. Install MySQL

1. Di **App Store**, cari **MySQL 8.0** (atau 5.7 jika VPS RAM kecil), klik **Install**.
2. Setelah terinstall, buka **Database → MySQL → Set / Settings** untuk cek status berjalan.
3. Catat password **root MySQL** dari aaPanel (biasanya ditampilkan di halaman MySQL). Jangan samakan dengan password website.

## 4. Install NodeJS + Composer

### NodeJS (untuk build Vue 3)
1. Di **App Store**, cari **NodeJS**, install versi **20.x LTS** (atau 18.x minimal).
2. Cek via Terminal aaPanel (lihat Langkah 8):
   ```bash
   node -v
   npm -v
   # harus tampil v20.x dan npm 10.x
   ```

### Composer
1. Composer biasanya belum ada di aaPanel. Install lewat Terminal:
   ```bash
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php --install-dir=/usr/local/bin --filename=composer
   php -r "unlink('composer-setup.php');"
   composer --version
   ```
2. Jika `composer` tidak ditemukan, pastikan `/usr/local/bin` ada di PATH, atau pakai path lengkap `/usr/local/bin/composer`.

## 5. Buat Website di aaPanel

1. Di aaPanel, buka **Website → Add Site**.
2. Isi:
   - **Domain:** `studio.ruangdharma.id` (ganti dengan domain kamu. Jika belum ada domain, bisa pakai IP + port dulu untuk testing, tapi SSL & APP_URL nanti perlu domain.)
   - **Root directory:** biarkan default dulu, misal `/www/wwwroot/studio.ruangdharma.id` — **nanti kita ubah ke `/www/wwwroot/studio.ruangdharma.id/public`**.
   - **PHP Version:** pilih **PHP 8.2**.
   - **Database:** centang **Create Database** jika mau langsung dibuat di sini, atau buat manual di Langkah 6.
   - Klik **Submit / Add**.
3. Setelah website dibuat, klik **Set / Settings** pada website tersebut → tab **Site Directory**:
   - **Running Directory:** isi `/public`
   - **Effective Directory:** akan menjadi `/www/wwwroot/studio.ruangdharma.id/public`
   - Klik **Save**.
4. Di tab yang sama, pastikan **Anti-XSS / open_basedir** tidak menghalangi Laravel. Jika muncul error `open_basedir restriction`, matikan sementara opsi tersebut untuk website ini, lalu **Reload Nginx**.

> 📁 Untuk tutorial ini, kita pakai contoh path: `/www/wwwroot/ruangdharma` sebagai folder project, dan `/www/wwwroot/ruangdharma/public` sebagai document root. Sesuaikan dengan nama domain kamu.

## 6. Buat Database MySQL di aaPanel

1. Buka **Database → Add Database**.
2. Isi:
   - **Database Name:** `ruang_dharma_studio`
   - **Username:** `rd_studio` (bebas, jangan pakai `root`)
   - **Password:** klik **Random** / buat password kuat, **catat password ini**.
   - **Access Permission:** `Localhost` (jika DB di server yang sama).
3. Klik **Submit**. Catat: nama DB, username, password.

## 7. Clone Repo / Upload File Project

### Cara A — Clone dari GitHub (disarankan)
1. Buka **Terminal** di aaPanel (menu **Terminal** di kiri) atau SSH ke VPS.
2. Masuk ke folder web:
   ```bash
   cd /www/wwwroot
   # Hapus folder bawaan website jika sudah ada file default
   rm -rf ruangdharma
   git clone https://github.com/Igustisultanh12/ruangdharma.git ruangdharma
   cd ruangdharma
   git branch --show-current
   # harus tampil: main
   ```
3. Jika repo private, GitHub akan minta login. Pakai **Personal Access Token (PAT)** sebagai password, bukan password GitHub. Cara buat PAT: GitHub → Settings → Developer settings → Personal access tokens → Generate (scope `repo`).

### Cara B — Upload ZIP (jika tidak bisa git)
1. Di laptop, download ZIP dari GitHub repo → **Code → Download ZIP**.
2. Di aaPanel → **Files** → masuk `/www/wwwroot/` → **Upload** ZIP → **Extract** ke folder `ruangdharma`.
3. Pastikan isi folder `ruangdharma` langsung berisi `composer.json`, `artisan`, `app/`, `resources/`, dll (bukan folder ganda `ruangdharma/ruangdharma/`).

## 8. Setting File `.env`

1. Di Terminal aaPanel / SSH:
   ```bash
   cd /www/wwwroot/ruangdharma
   cp .env.example .env
   nano .env
   ```
   Atau edit lewat aaPanel → **Files** → buka `/www/wwwroot/ruangdharma/.env` → **Edit**.
2. Isi minimal ini (sesuaikan dengan domain & database kamu):
   ```env
   APP_NAME="Ruang Dharma Studio"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://studio.ruangdharma.id
   APP_TIMEZONE=Asia/Jakarta

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ruang_dharma_studio
   DB_USERNAME=rd_studio
   DB_PASSWORD=password_database_yang_dibuat_di_Langkah_6

   # === Integrasi Muse (lihat bagian "Cara Buat Token Muse" di bawah) ===
   MUSE_API_TOKEN=token_yang_sudah_dibuat_64_karakter_hex
   MUSE_WEBHOOK_SECRET=secret_webhook_yang_sudah_dibuat_beda_dari_token
   MUSE_BASE_URL=
   MUSE_TIMEOUT=15

   DEFAULT_DAILY_LIMIT=5
   ```
3. Simpan (`Ctrl+O`, `Enter`, `Ctrl+X` jika pakai nano).
4. Pastikan `.env` **tidak** ter-commit ke GitHub. File `.gitignore` project sudah mengecualikan `.env`.

## 9. Install Dependency + Generate Key + Migrate

Jalankan berurutan di Terminal aaPanel / SSH, di dalam folder project:

```bash
cd /www/wwwroot/ruangdharma

# 1. Install dependency PHP (produksi, tanpa dev)
composer install --optimize-autoloader --no-dev

# 2. Generate APP_KEY (wajib, sekali saja)
php artisan key:generate

# 3. Buat tabel + isi data awal (admin demo + settings)
php artisan migrate --seed --force

# 4. Buat symlink storage (untuk upload avatar)
php artisan storage:link

# 5. Build frontend Vue 3
npm install
npm run build

# 6. Cache config/route untuk performa (opsional, setelah .env final)
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> ⚠️ Jika `npm install` gagal karena RAM kecil, tambahkan swap di VPS atau jalankan `npm install --production` / build di laptop lalu upload hasil `public/build/` saja.

## 10. Setting Permission `storage` & `bootstrap/cache`

Laravel harus bisa menulis ke `storage/` dan `bootstrap/cache/`. Di aaPanel, user web biasanya `www`.

```bash
cd /www/wwwroot/ruangdharma
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
# Pastikan folder project bisa dibaca web server
chown -R www:www /www/wwwroot/ruangdharma
```

Jika setelah ini masih error `Permission denied`, cek di aaPanel → **Files** → klik kanan folder `storage` → **Permissions** → pastikan owner `www`.

## 11. Setting Nginx / Apache di aaPanel

### Jika pakai Nginx (default aaPanel LNMP)
1. Website → **Set** → tab **Configuration** (Nginx).
2. Pastikan blok `server` berisi:
   ```nginx
   server {
       listen 80;
       server_name studio.ruangdharma.id;
       root /www/wwwroot/ruangdharma/public;
       index index.php index.html;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           include enable-php.conf;
           # aaPanel biasanya sudah mengisi fastcgi_pass ke PHP 8.2
       }

       location ~ /\.(?!well-known).* {
           deny all;
       }
   }
   ```
3. Klik **Save** → **Reload** Nginx.

### Jika pakai Apache (LAMP)
1. File `.htaccess` Laravel di `public/` sudah menangani rewrite. Pastikan di aaPanel → Website → **Set** → **Configuration** Apache, `AllowOverride All` aktif untuk folder `public/`.
2. Jika pakai Apache, pastikan modul `mod_rewrite` aktif.

> ✅ Cek cepat: buka `https://studio.ruangdharma.id/` — harus tampil halaman login Vue. Jika tampil listing file / `403`, berarti Running Directory belum `/public` atau permission salah.

## 12. Setting SSL (Let's Encrypt) di aaPanel

1. Website → **Set** → tab **SSL** → pilih **Let's Encrypt**.
2. Centang domain kamu, klik **Apply / Request**.
3. Tunggu sampai status **SSL Enabled**. aaPanel akan otomatis set redirect HTTP → HTTPS jika diaktifkan.
4. Setelah SSL aktif, pastikan `.env`:
   ```
   APP_URL=https://studio.ruangdharma.id
   ```
   Lalu jalankan:
   ```bash
   php artisan config:clear && php artisan config:cache
   ```

## 13. Setting PHP Version di aaPanel

1. Website → **Set** → **PHP Version** → pilih **PHP 8.2**.
2. Jika website masih memakai PHP lain, jalankan:
   ```bash
   php -v
   # harus tampil PHP 8.2.x
   ```
3. Jika `php -v` masih versi lama, pakai path PHP 8.2 aaPanel, contoh:
   ```bash
   /www/server/php/82/bin/php -v
   /www/server/php/82/bin/php artisan --version
   ```
   Untuk perintah `composer` / `artisan`, gunakan path lengkap tersebut jika perlu.

## 14. Cron untuk Queue (Jika Perlu)

Jika nanti generate video memakai queue (`QUEUE_CONNECTION=database`), aktifkan worker:

1. aaPanel → **Cron** → **Add Cron**.
2. Isi:
   - **Type:** Shell Script
   - **Cycle:** Setiap 1 menit (`* * * * *`)
   - **Script:**
     ```bash
     cd /www/wwwroot/ruangdharma && /www/server/php/82/bin/php artisan queue:work --once --tries=3 --timeout=120 >> /www/wwwroot/ruangdharma/storage/logs/queue.log 2>&1
     ```
3. Atau jalankan worker permanen dengan Supervisor (aaPanel → **App Store → Supervisor**):
   ```
   Command: /www/server/php/82/bin/php /www/wwwroot/ruangdharma/artisan queue:work --sleep=3 --tries=3 --timeout=120
   Directory: /www/wwwroot/ruangdharma
   User: www
   ```

## 15. Cara Cek Log Jika Error 500 / Web Tidak Jalan

Jangan panik. Cek berurutan:

```bash
# 1. Log Laravel (paling penting)
tail -n 100 /www/wwwroot/ruangdharma/storage/logs/laravel.log

# 2. Log Nginx aaPanel
tail -n 100 /www/wwwlogs/studio.ruangdharma.id-error.log
# atau /www/wwwlogs/nginx_error.log

# 3. Log PHP
tail -n 100 /www/server/php/82/var/log/php-fpm.log
```

Penyebab error 500 yang paling sering:

| Gejala di log | Penyebab | Perbaikan |
|---|---|---|
| `No application encryption key` | `APP_KEY` kosong | `php artisan key:generate` |
| `SQLSTATE Access denied` | DB user/pass salah | Cek `DB_*` di `.env`, cek password di aaPanel → Database |
| `Permission denied` `storage/` | Permission salah | `chown -R www:www storage bootstrap/cache` + `chmod -R 775` |
| `Class not found` / `vendor/autoload.php` | `composer install` belum jalan | `composer install --no-dev` |
| `Vite manifest not found` | Frontend belum di-build | `npm install && npm run build` |
| `open_basedir restriction` | aaPanel blokir path | Matikan open_basedir di Website → Site Directory |
| `404 semua route` | Running Directory salah | Set Running Directory ke `/public`, cek `try_files` Nginx |

Setelah memperbaiki, selalu jalankan:
```bash
php artisan config:clear
php artisan cache:clear
# lalu reload website di aaPanel
```

### Troubleshooting: `Please provide a valid cache path.` (HTTP 500, `InvalidArgumentException`)

Error ini muncul setelah `git clone` karena folder `storage/framework/{sessions,views,cache/data}`, `storage/logs`, dan `bootstrap/cache` kosong / tidak ada. Folder-folder itu diabaikan git (`.gitignore`), jadi clone baru tidak membawanya — Laravel lalu gagal di `Compiler.php` (Blade compiler tidak menemukan cache path yang valid).

**Perbaikan cepat di aaPanel (Terminal / SSH):**

```bash
cd /www/wwwroot/ruangdharma

# 1. Buat folder yang hilang (jika belum ada)
mkdir -p storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/framework/testing \
  storage/logs \
  bootstrap/cache

# 2. Perbaiki kepemilikan & permission (aaPanel: user www)
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 3. Bersihkan cache Laravel
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# 4. (Opsional) cache ulang untuk produksi
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 5. Reload website di aaPanel, lalu buka lagi domainnya
```

**Kenapa ini tidak terulang lagi:** repo sekarang menyertakan file `.gitkeep` di setiap folder wajib (`storage/app/.gitkeep`, `storage/framework/cache/data/.gitkeep`, `storage/framework/sessions/.gitkeep`, `storage/framework/views/.gitkeep`, `storage/logs/.gitkeep`, `bootstrap/cache/.gitkeep`) plus `.gitignore` di `storage/` dan `bootstrap/cache/` yang mengabaikan isi tapi TIDAK mengabaikan `.gitkeep` (`!.gitkeep` / `!**/.gitkeep`). Jadi `git clone` / `git pull` berikutnya akan membuat folder-folder itu otomatis.

**Jika masih error setelah langkah di atas:** cek `storage/logs/laravel.log` — pastikan `APP_KEY` sudah ter-generate (`php artisan key:generate`) dan `storage/framework/views` bisa ditulis (`ls -ld storage/framework/views` harus `www:www` + `775`).

---


---

# Cara Buat & Kelola Token Muse via Menu Admin (Cara Baru — Disarankan)

Tidak perlu edit `.env` manual lagi. Setelah web jalan dan kamu login sebagai **admin**, buka:

**Admin → Integrasi Muse (Token)** — atau langsung `/admin/muse-config`

Di halaman itu kamu bisa:

| Yang bisa dilakukan | Caranya |
|---|---|
| Lihat `MUSE_API_TOKEN` | Token tampil ter-mask `a1b2c3••••••••e7f8` → klik **Tampilkan** untuk lihat full, **Salin** untuk copy |
| Buat token baru | Klik **Generate Token Baru** → konfirmasi → token baru 64 karakter hex langsung dibuat (`bin2hex(random_bytes(32))`) dan tersimpan terenkripsi di database. Token lama langsung tidak berlaku. |
| Lihat / buat `MUSE_WEBHOOK_SECRET` | Sama seperti token, tapi tombol **Generate Secret Baru**. Secret selalu dibuat beda dari token. |
| Atur `MUSE_BASE_URL` & `MUSE_TIMEOUT` | Isi form (Base URL kosong = pakai generator lokal; Timeout 5–60 detik) → klik **Simpan Pengaturan** |
| Cek koneksi | Klik **Periksa Koneksi** / **Test Koneksi Muse** / **Test Generate via Muse** |
| Contoh curl | Tersedia di bawah halaman, dengan token ter-mask. Klik **Salin curl** (tampilkan token dulu untuk curl lengkap). |

**Catatan penting:**
- Token disimpan terenkripsi (`encrypt()` Laravel) di tabel `settings` (`muse_api_token`, `muse_webhook_secret`). Tanpa `APP_KEY` yang benar, token tidak bisa di-decrypt.
- Banner kuning setelah generate: "Simpan token ini..." — token tetap bisa dilihat lagi lewat tombol **Tampilkan** karena tersimpan di database, tapi tetap jangan dibagikan sembarangan.
- Jika token sudah pernah dibuat lewat menu Admin, token di database yang dipakai (prioritas utama). Token di `.env` hanya fallback jika database kosong.
- Setelah `git pull` update ini di aaPanel, jalankan `php artisan migrate --force` (untuk migrasi `2026_10_17_000004_add_muse_config_to_settings_table`) lalu `npm run build`.

---

# Cara Buat Token Muse via .env (Cara Lama — Tetap Didukung sebagai Fallback)

## Apa Itu `MUSE_API_TOKEN`? (Cara Lama via .env)

`MUSE_API_TOKEN` adalah **password rahasia** antara web Laravel kamu dan Muse.
- Web kamu hanya menerima request dari Muse jika request itu membawa token yang benar di header `X-Muse-Token`.
- Tanpa token yang benar, endpoint `/api/muse/*` akan menolak (401 Unauthorized).
- Token ini **bukan** token yang dikasih Muse secara otomatis. **Kamu yang bikin sendiri** di server, lalu kamu beritahu Muse token tersebut lewat jalur aman (bukan chat biasa).

Ada 2 token yang perlu dibedakan:

| Variabel | Fungsi | Harus Beda? |
|---|---|---|
| `MUSE_API_TOKEN` | Auth untuk Muse memanggil API web (`/api/muse/status`, `/generate`, `/jobs`) | Ya |
| `MUSE_WEBHOOK_SECRET` | Auth untuk webhook (Muse kirim hasil balik ke `/api/muse/webhook`) | Ya, beda dari `MUSE_API_TOKEN` |

## Kenapa Harus 32+ Karakter?

- Token yang pendek / mudah ditebak (misal `12345`, `ruangdharma`) bisa ditebak orang dan dipakai untuk mengakses API kamu.
- Minimal **32 karakter**, idealnya **64 karakter hex** (hasil `openssl rand -hex 32` = 64 karakter). Semakin panjang & acak, semakin aman.
- Jangan pakai nama, tanggal lahir, atau kata kamus sebagai token.

## Cara 1 — Via Terminal aaPanel (Paling Disarankan)

1. Di aaPanel, buka menu **Terminal** (di sidebar kiri).
2. Jalankan untuk token utama:
   ```bash
   openssl rand -hex 32
   ```
   Contoh hasil (punya kamu akan beda):
   ```
   9f8a2b1c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f708192a3b4c5d6e7f80
   ```
3. **Copy** hasil tersebut.
4. Jalankan lagi sekali untuk webhook secret (harus beda):
   ```bash
   openssl rand -hex 32
   ```
5. Copy hasil kedua.

## Cara 2 — Via SSH (VPS / Terminal Laptop)

Sama seperti Cara 1, tapi lewat SSH:

```bash
ssh root@IP_VPS_KAMU
cd /www/wwwroot/ruangdharma
openssl rand -hex 32
# copy hasilnya
openssl rand -hex 32
# copy hasil kedua untuk webhook secret
```

Di macOS / Linux, perintah di atas langsung jalan. Di Windows, pakai PowerShell (lihat Cara 3) atau Git Bash.

## Cara 3 — Via PowerShell Windows

Jika kamu di laptop Windows dan tidak punya `openssl`:

```powershell
# Buat 32 byte acak → 64 karakter hex
[Convert]::ToHexString((1..32 | ForEach-Object { Get-Random -Maximum 256 }))
```

Atau versi yang lebih singkat:

```powershell
-join ((48..57)+(65..90)+(97..122) | Get-Random -Count 64 | ForEach-Object { [char]$_ })
```

Copy hasilnya.

## Cara 4 — Via PHP (Jika `openssl` Tidak Tersedia)

Jalankan di Terminal aaPanel / SSH:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
# jalankan 2x: sekali untuk MUSE_API_TOKEN, sekali untuk MUSE_WEBHOOK_SECRET
```

Atau buat file sementara `token.php`:
```php
<?php
echo "MUSE_API_TOKEN=" . bin2hex(random_bytes(32)) . PHP_EOL;
echo "MUSE_WEBHOOK_SECRET=" . bin2hex(random_bytes(32)) . PHP_EOL;
```
Jalankan `php token.php`, copy hasilnya, lalu **hapus file `token.php`** setelah selesai.

## Cara Paste Token ke `.env`

1. Buka file `.env` di `/www/wwwroot/ruangdharma/.env` (via aaPanel → **Files** → Edit, atau `nano .env` di Terminal).
2. Ganti 2 baris ini:
   ```env
   MUSE_API_TOKEN=9f8a2b1c4d5e6f708192a3b4c5d6e7f8091a2b3c4d5e6f708192a3b4c5d6e7f80
   MUSE_WEBHOOK_SECRET=hasil_kedua_yang_beda_dari_token_di_atas
   ```
   - Jangan ada spasi di sekitar `=`.
   - Jangan pakai tanda petik kecuali token mengandung karakter khusus (token hex tidak perlu petik).
   - Pastikan 2 token **beda**.
3. Simpan. Lalu clear cache:
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

## Cara Test Token dengan `curl`

Ganti `https://studio.ruangdharma.id` dengan domain kamu, dan `TOKEN_KAMU` dengan token asli (jangan share hasil curl yang berisi token asli ke orang lain).

### 1. Health check (tanpa token, harus selalu OK)
```bash
curl https://studio.ruangdharma.id/api/muse/health
```
Harus balas:
```json
{"status":"ok","service":"ruang-dharma-studio","time":"...","db":"ok","version":"1.0.0"}
```

### 2. Status dengan token (harus `connected: true`)
```bash
curl -H "X-Muse-Token: TOKEN_KAMU" https://studio.ruangdharma.id/api/muse/status
```
Harus balas:
```json
{"connected":true,"last_active":"...","version":"1.0.0","latency_ms":12,"queue_pending":0,"daily_generations":0}
```

### 3. Test generate (harus `201` + `job_id`)
```bash
curl -X POST -H "X-Muse-Token: TOKEN_KAMU" -H "Content-Type: application/json" \
  -d '{"keyword":"Rwa Bhineda","durasi":60,"model":"narasi"}' \
  https://studio.ruangdharma.id/api/muse/generate
```

### Jika Gagal

| Response | Artinya | Perbaikan |
|---|---|---|
| `401 Unauthorized` | Token salah / tidak terkirim | Cek `MUSE_API_TOKEN` di `.env` sama persis dengan yang dikirim, cek header `X-Muse-Token`, jalankan `php artisan config:clear` |
| `404` | Route tidak ketemu | Cek `APP_URL`, cek Running Directory `/public`, cek `php artisan route:list \| grep muse` |
| `500` | Error server | Cek `storage/logs/laravel.log` |
| `429` | Kuota habis / rate limit | Cek limit di Admin → Settings, tunggu besok / naikkan limit |

## Cara Rotasi Token (Ganti Token Lama)

Jika token bocor / ingin diganti:

1. Buat token baru dengan salah satu cara di atas (`openssl rand -hex 32`).
2. Ganti `MUSE_API_TOKEN` di `.env` server dengan token baru.
3. Jalankan `php artisan config:clear && php artisan config:cache`.
4. Beritahu Muse token baru lewat jalur aman (bukan chat biasa). Token lama langsung tidak berlaku.
5. Jika webhook juga ingin diganti, ulangi langkah yang sama untuk `MUSE_WEBHOOK_SECRET`.

## Peringatan Keamanan Token

- ❌ **Jangan commit `.env` ke GitHub.** File `.gitignore` sudah mengecualikan `.env`. Yang di-commit hanya `.env.example`.
- ❌ **Jangan kirim token asli di chat biasa, screenshot, atau grup.** Jika terlanjur terkirim, anggap bocor dan segera rotasi (lihat di atas).
- ❌ **Jangan pakai token yang sama untuk `MUSE_API_TOKEN` dan `MUSE_WEBHOOK_SECRET`.**
- ✅ Simpan token di password manager / Secure Vault, bukan di catatan biasa.
- ✅ Jika token dipakai di banyak tempat, rotasi serentak (server + Muse) agar tidak ada yang masih pakai token lama.

---

## Setting Token Muse (Ringkasan Singkat)

```bash
# .env
MUSE_API_TOKEN=token-rahasia-min-32-karakter
MUSE_WEBHOOK_SECRET=secret-webhook
```
Semua request Muse ke API harus kirim header `X-Muse-Token: <MUSE_API_TOKEN>` atau `Authorization: Bearer <token>`. Detail lengkap ada di bagian "Cara Buat Token Muse" di atas.

## Test Integrasi Muse
```bash
curl http://localhost:8000/api/muse/health
curl -H "X-Muse-Token: $MUSE_API_TOKEN" http://localhost:8000/api/muse/status
curl -X POST -H "X-Muse-Token: $MUSE_API_TOKEN" -H "Content-Type: application/json" \
  -d '{"keyword":"Rwa Bhineda","durasi":60,"model":"narasi"}' http://localhost:8000/api/muse/generate
```

## Cara Update Setelah Push Baru

Setiap ada push baru ke GitHub (`git push`), jalankan di server (Terminal aaPanel / SSH):

```bash
cd /www/wwwroot/ruangdharma

# 1. Ambil kode terbaru
git pull origin main

# 2. Update dependency PHP (jika ada package baru)
composer install --optimize-autoloader --no-dev

# 3. Jalankan migrasi baru (jika ada)
php artisan migrate --force

# 4. Rebuild frontend Vue (jika ada perubahan di resources/js)
#    (sekali saja setelah update UI baru ini: rm -rf node_modules agar paket lama Tailwind 3 bersih)
npm install
npm run build

# 5. Clear + cache ulang
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache

# 6. Perbaiki permission jika perlu
chown -R www:www storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

> 💡 Tips: jika hanya mengubah README / docs saja, tidak perlu langkah 2–5. Cukup `git pull` saja.

## Struktur
- `app/Services/ScriptGeneratorService.php` — generator naskah
- `app/Services/MuseService.php` — integrasi Muse
- `app/Services/QuotaService.php` — cek kuota
- `app/Http/Controllers/RealtimeController.php` — endpoint realtime pulse
- `resources/js/` — Vue 3 SPA: `views/` (halaman), `components/` (UI, grafik, brand canang, layout), `stores/realtime.js` (polling realtime), `composables/useList.js` & `useRealtime.js`
- `resources/css/app.css` — design tokens & komponen (Tailwind CSS 4)
- `docs/MUSE_INTEGRATION.md` — dokumentasi API Muse

## Auto-Generate Harian & Cron aaPanel

`app:generate-daily` membuat 3 video dari tema di settings (`auto_generate_themes`,
default: Rwa Bhineda, Canang Sari, Tri Sandhya) dengan rotasi model
narasi → ngobrol → gabungan, untuk user admin pertama yang aktif.
Aktifkan di Admin > Pengaturan tab `auto_generate`
(`auto_generate_enabled=1`, `auto_generate_time` HH:MM, default 07:00).

```bash
php artisan app:generate-daily                 # jalan manual
php artisan app:generate-daily --dry-run       # lihat rencana saja
php artisan app:generate-daily --themes="Karma" --themes="Nyepi"
```

Scheduler Laravel (`routes/console.php`) menjadwalkan command ini setiap hari
pada jam dari settings. Agar scheduler jalan, tambahkan **satu** cron di
aaPanel (Scheduled Tasks / Cron) yang memanggil scheduler tiap menit:

```bash
* * * * * cd /www/wwwroot/ruangdharma && php artisan schedule:run >> /dev/null 2>&1
```

Alternatif tanpa scheduler (langsung tiap hari jam 07:00):

```bash
0 7 * * * cd /www/wwwroot/ruangdharma && php artisan app:generate-daily >> storage/logs/generate-daily.log 2>&1
```

Setelah menambah migration baru, jalankan `php artisan migrate --force`.

## Endpoint Baru (ringkas)

- `GET /api/realtime/pulse` — stamp perubahan per resource + notifikasi belum dibaca + kuota (dipakai semua menu agar realtime).
- `GET /api/review-queue?status=review|revisi|approved|published|all` — antrean review (admin/super admin/editor).
- `GET /api/generations/counts` — jumlah semua / favorit / arsip / review / revisi / terbit milik user.
- `POST /api/profile` — update profil multipart (avatar).
- `GET /api/app-info` — info publik halaman login.

- `GET /api/templates` — katalog template/ide video.
- `POST /api/generations/{id}/toggle-favorite`, `POST /toggle-archive` — favorit & arsip.
- `GET /api/generations?search=&model=&date_from=&date_to=&sort=latest|oldest&favorites=1&archived=` — search & filter riwayat.
- `GET /api/admin/export/csv`, `GET /api/admin/export/pdf` (HTML siap print) — ekspor laporan.
- `GET /api/admin/activity-logs`, `GET /api/admin/login-histories` — log aktivitas & login.
- `GET /api/notifications`, `POST /api/notifications/{id}/read`, `POST /api/notifications/read-all`.
- `GET /api/generations/calendar?month=YYYY-MM`, `POST /api/generations/{id}/schedule-publish`.
- Workflow: `submit-review`, `approve`, `reject` (catatan), `publish`, `archive`.
- Versi: `PUT /api/generations/{id}/naskah` (snapshot otomatis), `GET .../versions`, `POST .../versions/{v}/restore`.
- Komentar: `GET|POST /api/generations/{id}/comments`, `DELETE .../comments/{commentId}`.
- Media: `GET|POST /api/media` (max 10MB), `DELETE /api/media/{id}`.
- Role & Permission: `GET|POST|PUT|DELETE /api/admin/roles`, `POST /api/admin/roles/{id}/permissions`,
  `POST /api/admin/users/{id}/roles`, `GET /api/admin/permissions`. Middleware `permission:xxx`.
- Kuota & Paket: `GET|POST|PUT|DELETE /api/admin/quota-plans`, `POST /api/admin/users/{id}/quota-plan`.
- API Keys & Webhooks: `GET|POST|DELETE /api/api-keys`, `GET|POST|PUT|DELETE /api/webhooks`,
  `POST /api/webhooks/{id}/test`, `GET /api/webhooks/{id}/logs`. Eksternal: header `X-Api-Key`.
- Analitik: `GET /api/admin/analytics?days=30`, `GET /api/admin/analytics/export-csv`.
- Settings per tab: `GET|PUT /api/admin/settings/{tab}` (general, muse, auto_generate, security, notifications).
- Semua respons JSON konsisten: `{success, message, data, ...}` (kecuali MuseConfig yang
  mempertahankan bentuk lama agar frontend Admin/MuseConfig.vue tetap jalan, dan
  `download-txt` yang mengembalikan `text/plain`).

## Aplikasi Android Native

Aplikasi native Kotlin + Jetpack Compose + Material 3 di folder `android/` (applicationId `id.ruangdharma.studio`, minSdk 24, targetSdk 34).

Fitur: Login (Sanctum), Beranda/Feed ala Instagram, Buat Video (keyword, durasi, model, via Muse, backsound), Riwayat + Detail (video player, naskah, caption, Editor Audio, publish IG/FB/TikTok), Medsos (statistik + publish), Admin Muse (penggunaan token dari 22M + riwayat per video), Profil (kuota, logout). UI/UX ala Meta: bottom navigation, top bar Canang, feed card, dark mode, pull-to-refresh, shimmer loading.

### Unduh APK
Unduh dari **GitHub Releases**: https://github.com/Igustisultanh12/ruangdharma/releases/tag/v1.0.0 — file `RUANGDHARMA-1.0.0.apk` (+ `SHA256SUMS.txt` untuk verifikasi). Izinkan "Instal aplikasi tidak dikenal", lalu pasang & login dengan akun Ruang Dharma.

### Build lokal
```bash
cd android
./gradlew :app:assembleDebug   # APK debug
./gradlew :app:assembleRelease # APK release (butuh keystore, lihat android/app/build.gradle.kts)
./gradlew :app:bundleRelease   # AAB Play Store
```

### Rilis otomatis (CI)
Workflow `.github/workflows/android-release.yml` (pola [kantor](https://github.com/Igustisultanh12/kantor.git)): setiap push ke `main` yang lulus build + unit test + lint, bila rilis `v<versionName>` belum ada, akan menerbitkan GitHub Release otomatis — **hanya bila APK ditandatangani kunci rilis tetap**. Isi secrets `RUANGDHARMA_KEYSTORE_BASE64`, `RUANGDHARMA_KEYSTORE_PASSWORD`, `RUANGDHARMA_KEY_ALIAS`, `RUANGDHARMA_KEY_PASSWORD` di Settings → Secrets. Tanpa secrets, CI mengunggah APK debug-signed sebagai artifact saja dan menolak publish Release (agar pengguna tidak perlu uninstall). Naikkan `versionName` di `android/app/build.gradle.kts` untuk rilis baru.
