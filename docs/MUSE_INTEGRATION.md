# Integrasi Muse — Ruang Dharma Studio

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
- Token di `.env`: `MUSE_API_TOKEN=...` (min 32 karakter, ganti di produksi)
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
- Atur `MUSE_BASE_URL` di `.env`. Jika tidak diisi, Laravel memakai generator lokal (`ScriptGeneratorService`) dan menandai job `source=local`.

## Panel Admin Vue
- `Admin/MuseStatus.vue` memanggil `GET /api/muse/status` via `services/muse.js`, tampilkan Terhubung/Tidak, terakhir aktif, latency, tombol "Periksa Koneksi" & "Test Generate via Muse".
- Jika status gagal, tampilkan pesan error + cara cek token di `.env`.

## Keamanan
- Jangan commit `.env` (token) ke git.
- Rotasi token: ganti `MUSE_API_TOKEN` di server + di Muse secara bersamaan.
- Rate limit: `throttle:60,1` pada grup API Muse (lihat `routes/api.php`).
