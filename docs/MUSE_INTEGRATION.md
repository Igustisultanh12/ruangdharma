# Integrasi Muse — Ruang Dharma Studio

## Cara Baru: Kelola Token via Menu Admin (Disarankan)

Tidak perlu edit `.env` manual. Login sebagai **admin** → **Admin → Integrasi Muse (Token)** (`/admin/muse-config`).

- **Lihat token:** `MUSE_API_TOKEN` & `MUSE_WEBHOOK_SECRET` tampil ter-mask, klik **Tampilkan** / **Salin**.
- **Generate token baru:** klik **Generate Token Baru** (konfirmasi dulu). Token baru = 64 karakter hex (`bin2hex(random_bytes(32))`), disimpan terenkripsi di tabel `settings`. Token lama langsung tidak berlaku.
- **Generate webhook secret baru:** tombol **Generate Secret Baru** — selalu beda dari token.
- **Atur Base URL & Timeout:** form `MUSE_BASE_URL` (kosong = generator lokal) + `MUSE_TIMEOUT` 5–60 detik → **Simpan**.
- **Tes koneksi:** tombol **Periksa Koneksi** / **Test Koneksi Muse** / **Test Generate via Muse**, plus contoh curl ter-mask.

### Endpoint Admin (auth Sanctum + role admin)
| Method | Endpoint | Fungsi |
|---|---|---|
| `GET` | `/api/admin/muse-config?show=1` | Lihat config (full token hanya jika `show=1`) |
| `POST` | `/api/admin/muse-config/generate-token` | Buat `MUSE_API_TOKEN` baru |
| `POST` | `/api/admin/muse-config/generate-webhook-secret` | Buat `MUSE_WEBHOOK_SECRET` baru |
| `PUT` | `/api/admin/muse-config` | Update `muse_base_url`, `muse_timeout` |
| `POST` | `/api/admin/muse-config/test` | Tes koneksi (eksternal jika Base URL diisi, internal jika kosong) |

### Prioritas token
1. Database (`settings.muse_api_token`, terenkripsi) — hasil generate di menu Admin.
2. `.env` (`MUSE_API_TOKEN`) — fallback jika database kosong (cara lama tetap didukung).
3. Validasi di `POST /api/muse/*` menerima token dari **keduanya** (DB atau env).

## Cara Lama: Token via `.env` (Fallback)


## Gambaran
Laravel ini adalah **server** untuk Ruang Dharma Studio. Muse (agen AI) bisa:
1. **Mengecek koneksi** via `GET /api/muse/status` / `/health`
2. **Meminta generate** via `POST /api/muse/generate` (dengan token)
3. **Mengecek job** via `GET /api/muse/jobs/{id}`
4. **Mengirim hasil** via `POST /api/muse/webhook` (callback dari Muse setelah video/naskah selesai)

Sebaliknya, Laravel juga bisa memanggil Muse jika Muse menyediakan endpoint (lihat bagian "Laravel → Muse").

## Auth
Semua endpoint `/api/muse/*` kecuali `/health` butuh token:
- Header: `X-Muse-Token: <MUSE_API_TOKEN>` ATAU `Authorization: Bearer <MUSE_API_TOKEN>`
- **Cara baru (disarankan):** generate & lihat token di menu Admin → Integrasi Muse. Token disimpan terenkripsi di database.
- **Cara lama (fallback):** token di `.env`: `MUSE_API_TOKEN=...` (min 32 karakter, ganti di produksi). Dipakai hanya jika database kosong.
- Webhook memakai `MUSE_WEBHOOK_SECRET` via header `X-Muse-Signature` (HMAC SHA256 dari body) atau `X-Muse-Token`.

## Endpoints

### 1. GET /api/muse/health (public)
Health check untuk load balancer / panel admin.
```bash
curl http://localhost:8000/api/muse/health
```
Response:
```json
{"status":"ok","service":"ruang-dharma-studio","time":"2026-10-17T10:41:00+07:00","db":"ok","version":"1.0.0"}
```

### 2. GET /api/muse/status (butuh token)
```bash
curl -H "X-Muse-Token: TOKEN" http://localhost:8000/api/muse/status
```
Response:
```json
{"connected":true,"last_active":"2026-10-17T10:41:00+07:00","version":"1.0.0","latency_ms":12,"queue_pending":2,"daily_generations":6}
```

### 3. POST /api/muse/generate (butuh token)
Muse meminta pembuatan job generate. Body:
```json
{"keyword":"Rwa Bhineda","durasi":60,"model":"narasi","user_id":2,"callback_url":"https://muse.example.com/callback"}
```
- `keyword` (string, wajib), `durasi` (30|60|90), `model` (narasi|ngobrol|gabungan)
- Validasi: kata diblokir → 422, kuota habis → 429
Response 201:
```json
{"job_id":12,"status":"queued","keyword":"Rwa Bhineda","durasi":60,"model":"narasi","estimated_seconds":45}
```
Contoh curl:
```bash
curl -X POST -H "X-Muse-Token: TOKEN" -H "Content-Type: application/json" \
  -d '{"keyword":"Rwa Bhineda","durasi":60,"model":"narasi","user_id":2}' \
  http://localhost:8000/api/muse/generate
```

### 4. GET /api/muse/jobs/{id} (butuh token)
```bash
curl -H "X-Muse-Token: TOKEN" http://localhost:8000/api/muse/jobs/12
```
Response:
```json
{"id":12,"status":"completed","keyword":"Rwa Bhineda","durasi":60,"model":"narasi","result":{"naskah":[...],"caption":"...","prompts":[...]},"created_at":"...","completed_at":"..."}
```
Status: `queued` | `processing` | `completed` | `failed`

### 5. POST /api/muse/webhook (butuh signature/token)
Muse kirim hasil setelah selesai generate (misal video sudah jadi di Muse).
```bash
curl -X POST -H "X-Muse-Token: TOKEN" -H "Content-Type: application/json" \
  -d '{"job_id":12,"status":"completed","result":{"naskah":[...],"caption":"...","video_url":"https://..."}}' \
  http://localhost:8000/api/muse/webhook
```
Response: `{"ok":true,"job_id":12,"status":"completed"}`

## Laravel → Muse (opsional, jika Muse punya endpoint)
Jika Muse menyediakan endpoint HTTP (misal `MUSE_BASE_URL`), Laravel bisa memanggilnya via `MuseService::requestGenerationFromMuse()`:
- `POST {MUSE_BASE_URL}/generate` dengan header `Authorization: Bearer {MUSE_API_TOKEN}` dan body sama seperti di atas.
- Atur `MUSE_BASE_URL` di menu Admin (atau `.env` sebagai fallback). Jika tidak diisi, Laravel memakai generator lokal (`ScriptGeneratorService`) dan menandai job `source=local`.

## Panel Admin Vue
- `Admin/MuseConfig.vue` (route `/admin/muse-config`) — halaman Integrasi Muse lengkap: card token & webhook secret (Show/Hide, Copy, Generate), form Base URL + Timeout, status koneksi, contoh curl. `Admin/MuseStatus.vue` tetap ada sebagai wrapper ke halaman ini (route `/admin/muse`).
- `services/muse.js` menyediakan `getMuseConfig`, `generateMuseToken`, `generateMuseWebhookSecret`, `updateMuseConfig`, `testMuseConfig`.
- Jika status gagal, tampilkan pesan error + cara cek token di menu Admin (atau `.env` fallback).

## Keamanan
- Jangan commit `.env` (token) ke git.
- Rotasi token: ganti `MUSE_API_TOKEN` di server + di Muse secara bersamaan.
- Rate limit: `throttle:60,1` pada grup API Muse (lihat `routes/api.php`).
