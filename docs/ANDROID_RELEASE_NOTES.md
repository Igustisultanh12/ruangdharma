# RUANGDHARMA Android v1.2.0 (versionCode 4)

## Baru: notifikasi HP seperti SISINDEN 🔔
- Notifikasi muncul di bilah status HP (bersuara & bergetar) saat **video Muse selesai / gagal / terunggah, naskah perlu review / revisi / disetujui, dan ada komentar baru**.
- Tiga kanal yang bisa diatur di Pengaturan Android: *Status video*, *Review & komentar*, *Notifikasi studio*.
- **Push instan (Firebase)** walau aplikasi ditutup — aktif setelah admin mengunggah `google-services.json` + service account di web: **Pengaturan → Push HP**. Tidak perlu build ulang APK.
- Tanpa Firebase pun notifikasi tetap muncul: dicek otomatis saat aplikasi terbuka dan ±15 menit di latar belakang (WorkManager).
- Ikon lonceng dengan jumlah belum dibaca di atas; layar **Notifikasi** (Semua / Belum dibaca, tandai dibaca).
- Ketuk notifikasi di bilah status → langsung membuka layar Notifikasi.
- Profil → **Notifikasi HP**: izin, aktif/nonaktif, suara & getar, kirim notifikasi tes.
- Android 13+: aplikasi meminta izin notifikasi setelah login.

## Server (wajib setelah pull)
```
php artisan migrate --force   # tabel device_tokens
npm run build                 # halaman Pengaturan → Push HP
```

---

## Dari v1.1.1

## Perbaikan
- **Riwayat / Beranda gagal dimuat** dengan pesan `NumberFormatException: Expected an int but was 3.1 … estimasi_detik`. Data dari Muse kadang berisi angka desimal, angka sebagai teks, 1/0 untuk ya/tidak, atau objek di kolom teks — sekarang semuanya dibaca dengan toleran, sehingga satu nilai aneh tidak lagi menggagalkan seluruh daftar.
- Pesan error teknis diganti pesan yang mudah dipahami.

---

## Dari v1.1.0

Login diperbaiki dan seluruh tampilan aplikasi dirombak agar **sama persis dengan UI web** Ruang Dharma Studio.

## Login & sesi
- Login membaca token dari respons server yang benar (`token` di level atas, `data.token` sebagai cadangan).
- Sesi **tersimpan**: buka aplikasi lagi tanpa perlu login ulang; token divalidasi lewat `/api/me`.
- Token dicabut / kedaluwarsa (HTTP 401) → otomatis kembali ke layar login dengan pesan jelas.
- Pesan error yang jelas: password salah, akun nonaktif, terlalu banyak percobaan, server error, atau tidak ada koneksi.

## UI baru — sama dengan web
- Palet PT Kreatif Sky: gradasi violet → fuchsia → oranye, aksen kuning "brutal" (border tinta + bayangan offset), font Plus Jakarta Sans & Space Grotesk.
- Logo canang sari asli (5 kelopak) digambar ulang di aplikasi + ikon launcher canang.
- **Login**: panel gradasi, eyebrow "Studio internal", "Om Swastyastu 🙏", input bergaya web.
- **Beranda**: hero gradasi + sapaan, 4 kartu statistik (dibuat hari ini, video aktif, favorit, terbit), terakhir dibuat, perlu revisi, ide untuk dicoba.
- **Buat video**: chip saran tema, pilihan durasi & gaya penyampaian ala web, toggle Muse, backsound, panel progres Muse dengan loader canang 5 kelopak.
- **Riwayat**: pencarian + tab Semua / Favorit / Arsip dengan jumlah, kartu video, bintang favorit.
- **Detail video**: pemutar video asli (Media3), tab Naskah / Caption / Audio / Publish, salin caption, bagikan.
- **Medsos**: statistik IG/FB, post & komen terbaru, upload via Muse, riwayat upload, TikTok manual.
- **Profil**: kartu identitas, kuota harian, mode gelap, Integrasi Muse (admin), konfirmasi keluar.
- Mode gelap mengikuti token warna gelap web.

## Realtime
Semua menu terbarui otomatis tanpa refresh (memantau `/api/realtime/pulse` tiap 5 detik, sama seperti web).

## Server
Tidak ada migrasi. Aplikasi memakai API yang sudah ada di `https://ruangdharma.my.id/api`.

## Install
Unduh `RUANGDHARMA-1.2.0.apk` di bawah, izinkan "Instal aplikasi tidak dikenal", lalu pasang (menimpa versi sebelumnya). Login dengan akun Ruang Dharma.
