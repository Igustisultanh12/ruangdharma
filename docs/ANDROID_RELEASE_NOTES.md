# RUANGDHARMA Android v1.0.0 (versionCode 1)

Aplikasi native Android pertama Ruang Dharma Studio — sama seperti web, tapi di HP.

## Baru
- **Login** dengan token Sanctum (tersimpan aman di DataStore)
- **Beranda / Feed** ala Instagram: kartu video, thumbnail, caption, badge status (Menunggu Muse / Memproses / Selesai / Gagal), pull-to-refresh, shimmer loading
- **Buat Video**: keyword, durasi 30/60/90, model narasi/ngobrol/gabungan, checkbox via Muse, backsound (volume + trim)
- **Riwayat + Detail**: video player, naskah per bagian, caption, Editor Audio (volume + trim + simpan & mix ulang), tombol publish IG/FB/TikTok manual
- **Medsos**: tab Instagram / Facebook / TikTok, statistik (followers, like, komen, reach), publish, refresh
- **Admin Muse**: penggunaan token (total, bulan ini, sisa dari 22M, riwayat per video)
- **Profil**: info user, kuota, logout
- UI/UX ala Meta (Instagram/Facebook): bottom navigation, top bar Canang, feed card, dark mode

## Server
Tidak ada migrasi / build ulang web. Aplikasi memakai API yang sudah ada di `https://ruangdharma.my.id/api`.

## Install
Unduh `RUANGDHARMA-1.0.0.apk` di bawah, izinkan "Instal aplikasi tidak dikenal", lalu pasang. Login dengan akun Ruang Dharma (mis. `admin@ruangdharma.id`).
