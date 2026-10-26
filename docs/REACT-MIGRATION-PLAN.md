# Rencana Migrasi ke React — Pradiya Portal

Status: **draft untuk direview**
Tanggal: 16 Oktober 2026
Strategi: **bertahap (strangler pattern)** — pilot **Family Financial Report**

---

## 1. Ringkasan

Aplikasi sekarang: PHP + MySQL, 69 file, ~8.475 baris, 13 controller (~40 route), 29 view (152 KB).
"React" yang sudah ada saat ini hanya di modul BAK, dan itu pun **dikompilasi di browser** memakai Babel
Standalone + React UMD dari CDN — bukan setup React sungguhan (tidak ada `package.json`/build step).

Rencana ini mengubah aplikasi menjadi React secara **bertahap**: aplikasi lama tetap jalan, modul dipindah
satu per satu, dan setiap modul baru bisa di-rollback tanpa mengganggu modul lain.

Dua hal yang membuat migrasi ini efisien:

1. **Logika bisnis sudah terpisah rapi di `services/`** (SchedulingEngine 25 KB, ScheduleValidator,
   Forecast/Leave/Shift/Staff/TlSchedule). Semua itu **dipakai ulang apa adanya** — yang ditambah hanya
   lapisan tipis "JSON adapter", bukan tulis ulang logika.
2. **BAK sudah membuktikan pola API JSON-nya** (`/api/bak/*` → `BAKController::api()`). Pola itu
   diperluas, bukan diciptakan dari nol.

---

## 2. Kondisi saat ini (fakta terverifikasi)

| Aspek | Kondisi |
|---|---|
| Skala kode | 69 file PHP, ~8.475 baris; `services/` 68,5 KB; `views/` 151,9 KB (29 file) |
| Routing | `public/index.php` — `?page=x&action=y` → controller method (map eksplisit) |
| Auth | Baru ditambahkan: session PHP, tabel `users`, CSRF via `FormHelper::csrfField()/verifyCsrf()` |
| API JSON | Hanya BAK: `/api/bak/*` (rewrite di `.htaccess` → `BAKController::api()`) |
| React | `BAKStepper.tsx` (122 KB) dikompilasi di browser via Babel Standalone + React UMD CDN |
| Toolchain | **Tidak ada** `package.json`, `node_modules`, bundler, atau linter |
| Hosting | Shared hosting + MySQL, deploy manual via FTP (SmartFTP), `uploadOnSave: true` |
| Dokumentasi | `research-plan.md` (sudah tertinggal — banyak controller baru belum tercatat) |

### Temuan yang perlu diperbaiki sekalian

| # | Temuan | Dampak |
|---|---|---|
| T1 | `BAKStepper.tsx` dikompilasi di browser + React/Babel dari CDN | Halaman BAK lambat & berat: **± 3,1 MB per kunjungan** (terukur: Babel Standalone 2.983.904 B + react-dom 131.835 B + React 10.751 B + TSX 122 KB) — jauh lebih besar dari seluruh aset aplikasi lain; rapuh kalau CDN diblokir/di-filter |
| T2 | API yang belum login membalas **302 redirect HTML**, bukan JSON | Tidak cocok untuk SPA — perlu `401 JSON` |
| T3 | Tidak ada test otomatis sama sekali | Migrasi tanpa jaring pengaman; prioritas menambah test untuk `services/` |
| T4 | `research-plan.md` tidak sinkron dengan kode | Membingungkan saat onboarding |
| T5 | Deploy manual per-file via FTP `uploadOnSave` | Rawan setengah-terupload; build React butuh alur unggah folder `dist/` |

---

## 3. Keputusan arsitektur (dua opsi)

### Opsi A — PHP+MySQL tetap jadi REST API, frontend React SPA (Vite)

```
Browser ──► React SPA (static, hasil build Vite)
              │  fetch + cookie sesi yang sama
              ▼
           /api/*  (PHP: controller API tipis → services → models → MySQL)
```

### Opsi B — Node.js / Next.js penuh

```
Browser ──► Next.js (SSR + API routes) ──► MySQL
```

### Perbandingan

| Kriteria | Opsi A (PHP API + React SPA) | Opsi B (Next.js/VPS) |
|---|---|---|
| Perubahan hosting | **Tidak ada** — tetap shared hosting + FTP | **Harus pindah** ke VPS/hosting Node (proses persist) |
| Logika bisnis | Dipakai ulang 100% (`services/`, `SchedulingEngine`) | Di-porting ulang ke TypeScript → risiko bug logika jadwal |
| Estimasi kerja | ± 8–11 minggu (1 orang, sambil jalan) | ± 14–20 minggu |
| Bahasa | PHP + TypeScript (dua bahasa) | TypeScript saja (satu bahasa) |
| Risiko utama | Dua bahasa & dua "cara" selama masa transisi | Registrasi ulang seluruh perilaku + biaya & operasional VPS |
| SEO / SSR | Tidak ada (SPA) — **tidak dibutuhkan**, aplikasi internal di balik login | Bisa SSR — tidak relevan untuk aplikasi internal |
| Upload file BAK | Tetap pakai PHP (sudah ada) | Perlu ditulis ulang |
| Backup/rollback | Mudah — modul PHP lama tetap ada | Sulit setelah cutover |

**Rekomendasi: Opsi A.**

Alasannya: aplikasi ini internal dan di balik login (SSR/SEO tidak memberi nilai), hosting sudah berjalan
dan berbayar, dan aset paling berharga — logika penjadwalan — sudah dalam bentuk PHP yang teruji dipakai
produksi. Opsi A mengubah **lapisan tampilan** saja; Opsi B mengubah tampilan **dan** mesinnya sekaligus.

**Pilih Opsi B hanya jika** salah satu benar: (a) kamu memang berencana pindah ke VPS dan ingin satu bahasa
untuk semua pengembangan berikutnya, atau (b) akan ada kebutuhan SSR/SEO publik, atau (c) akan ada realtime
(WebSocket) yang berat. Kalau tidak ada ketiganya → Opsi A.

> Catatan: plan di bawah ditulis untuk Opsi A. Perbedaan untuk Opsi B ada di **Lampiran B**.

---

## 4. Strategi bertahap (strangler pattern)

### Aturan koeksistensi

1. **Aplikasi lama tidak boleh rusak.** Semua halaman PHP lama tetap hidup sampai penggantinya
   diverifikasi di produksi.
2. **Satu modul, satu URL**: halaman React baru hidup di path-nya sendiri (mis. `/app/ffr/*`), sementara
   versi PHP tetap di `?page=...`. Tidak ada satu request yang dilayani dua-duanya.
3. **Rollback = satu baris**: setiap modul yang sudah dimigrasi punya flag. Kalau bermasalah, arahkan
   kartu/path kembali ke halaman PHP — tanpa deploy kode lama.
4. **API dulu, UI kemudian.** Untuk setiap modul: selesaikan + uji endpoint JSON-nya (bisa dites pakai
   `curl`), baru bangun UI React-nya. Jadi kalau UI tersendat, API-nya sudah berguna.
5. **Jangan sentuh `services/`** kecuali memang menemukan bug. API hanya memanggil service.

### Urutan modul (kecil & terisolasi → besar & rumit)

| Fase | Modul | Kenapa di urutan ini |
|---|---|---|
| 0 | Fondasi (Vite, UI kit, API client, deploy) | Menghasilkan "cetakan" yang dipakai semua fase berikutnya |
| 1 | **Family Financial Report** (pilot, greenfield) | Proyek baru → tidak ada warisan, pola dibuat dari nol dengan bersih |
| 2 | BAK | Sudah React + sudah ada API → hasil cepat, sekaligus membuang Babel-in-browser (T1) |
| 3 | Shift Master, Manage Staff, Leave | CRUD sederhana, pola berulang |
| 4 | Forecast, TL Schedule (termasuk Bulk Input) | Form kompleks + impor teks |
| 5 | Schedule generate, Schedule Overview, Backdate | Paling rumit (mesin jadwal + tabel besar) — dikerjakan terakhir |
| 6 | Portal + Login jadi SPA penuh, pensiunkan view PHP | Penutup: satu aplikasi React, PHP hanya API |

---

## 5. Struktur repo yang diusulkan

```text
AT SCH 2/
├── scheduling-system/          # PHP — tetap, makin lama makin hanya berisi API
│   ├── controllers/            # + Api/ (controller JSON tipis)
│   ├── services/               # TIDAK berubah (mesin bisnis)
│   ├── models/                 # TIDAK berubah
│   ├── views/                  # makin sedikit seiring migrasi
│   └── public/
│       ├── index.php           # front controller (route page + api)
│       └── app/                # hasil build React diunggah ke sini
├── apps/
│   ├── ffr/                    # pilot: Family Financial Report (Vite + React + TS)
│   └── scheduler-web/          # SPA Workforce Scheduler (mulai Fase 2)
├── packages/
│   ├── ui/                     # layout, navbar, sidebar, kartu portal, tabel
│   └── api-client/             # pembungkus fetch + tipe respons + penanganan 401/CSRF
├── docs/                       # dokumen ini
└── tools/
    └── deploy.ps1              # build + unggah dist via FTP
```

Catatan: `apps/ffr` sengaja dibuat sebagai app terpisah supaya nanti gampang dipisah jadi repo/hosting
sendiri ("laman terpisah" sesuai rencana project keuangan). Selama masih satu mesin, pakai npm workspaces.

---

## 6. Standar teknis

| Bidang | Pilihan | Alasan |
|---|---|---|
| Build | **Vite** | Paling ringan & cepat; hasil build statis → cocok untuk hosting tanpa Node |
| Bahasa | **TypeScript** | Sudah dipakai di BAKStepper; menangkap error sebelum deploy |
| Routing | **React Router** | Standar, mendukung nested layout |
| Data fetching | **TanStack Query** | Cache, retry, state loading/error otomatis — menghindari `useEffect` manual |
| UI | **Bootstrap 5 + react-bootstrap + bootstrap-icons** | **Sudah** dipakai aplikasi ini → tampilan konsisten, CSS tidak perlu ditulis ulang |
| Form | react-hook-form + zod | Validasi ganda (klien + server) dengan skema yang bisa dibagi |
| Tabel | TanStack Table | Untuk tabel besar (Schedule Overview, Staff) |
| Grafik | Recharts | Untuk laporan keuangan (FFR) |
| Test | Vitest + React Testing Library; PHPUnit untuk `services/` | Cepat, satu ekosistem dengan Vite |
| Lint/format | ESLint + Prettier | Konsistensi |
| Node (lokal) | Node 20 LTS | Hanya untuk build di komputer kamu — server tetap tanpa Node |

### Kontrak API JSON (dipakai semua modul)

Pola yang sudah dipakai BAK (`{"success":true,"data":{...}}`) dipertahankan:

```jsonc
// Sukses
{ "success": true, "data": { "items": [] } }

// Gagal validasi
{ "success": false, "error": "Shift code sudah dipakai.", "errors": { "shift_code": "..." } }
```

Aturan tambahan:

- **Status HTTP jujur**: 200 / 201 / 400 (validasi) / 401 (belum login) / 403 / 404 / 500.
- **401 JSON**, bukan redirect HTML (perbaikan T2) — hanya untuk path `/api/*`.
- **CSRF wajib** untuk POST/PUT/DELETE: header `X-CSRF-Token` (nilai dari `/api/auth/csrf`),
  diverifikasi dengan `hash_equals` yang sudah ada.
- **Auth**: cookie sesi yang sudah ada (`PHPSESSID`, HttpOnly + SameSite=Lax + Secure).
  SPA memanggil `GET /api/auth/me` → `{ id, username, name }` untuk penjaga route.
- Satu "envelope": semua respons lewat satu helper `ApiResponse::ok()/error()` supaya konsisten.

### Struktur controller API

```text
controllers/Api/
├── ApiController.php      # base: envelope, CSRF, 401 JSON, pemetaan exception
├── AuthApiController.php  # me, login, logout, csrf
├── ShiftApiController.php
├── StaffApiController.php
├── LeaveApiController.php
├── TlScheduleApiController.php
├── ForecastApiController.php
├── ScheduleApiController.php
└── FinanceApiController.php   # FFR
```

Controller API **tipis**: validasi input → panggil `services/` → bungkus jadi JSON. Tidak ada query SQL
langsung di controller.

---

## 7. Build & deploy (tanpa Node di server)

Karena server tidak punya Node, pola kerjanya:

```text
lokal:  npm run build  →  apps/ffr/dist/   →  tools/deploy.ps1  →  FTP  →  /scheduling-system/public/app/ffr/
```

Hal-hal yang harus diurus:

1. **Base path**: `base: '/scheduling-system/public/app/ffr/'` di `vite.config.ts` (atau `/app/ffr/`
   bila nanti dipindah ke root).
2. **SPA fallback**: semua path di bawah `/app/ffr/` yang bukan file harus dilayani `index.html`.
   Tambahkan di `.htaccess` root — **wajib ditulis SEBELUM** blok
   `RewriteRule ^(.*)$ index.php [QSA,L]` yang sekarang ada, karena blok itu menangkap semua URL dan
   akan mengirim deep link React ke front controller (berakhir 404):

   ```apache
   # SPA fallback untuk app React — taruh SEBELUM "Forward all requests to the dispatcher"
   RewriteCond %{REQUEST_URI} ^/scheduling-system/public/app/ffr/
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteCond %{REQUEST_FILENAME} !-d
   RewriteRule ^scheduling-system/public/app/ffr/.*$ scheduling-system/public/app/ffr/index.html [L,QSA]
   ```

   Ini termasuk hal yang paling gampang lupa dan gejalanya khas: halaman jalan saat diklik dari dalam
   aplikasi, tapi **404 saat di-refresh**. Uji khusus di Fase 0.

3. **Cache**: aset ber-hash dari Vite → `Cache-Control: immutable, max-age=1y`; `index.html` → `no-cache`.
4. **Deploy aman (perbaikan T5)**: `tools/deploy.ps1` melakukan build → unggah ke folder sementara
   `.../app/ffr_new/` → verifikasi → tukar nama folder (`ffr` ⇄ `ffr_prev`). Jadi tidak ada kondisi
   "setengah terunggah". `smartftp.json` ditambah `ignore` untuk `node_modules/**` dan `apps/**/dist/**`
   (yang diunggah hanya hasil akhir lewat script, bukan `uploadOnSave`).
5. **Alternatif tanpa FTP**: kalau hosting menyediakan Git atau SSH, pakai itu — jauh lebih aman.
   Perlu dicek ke provider.

---

## 8. Roadmap

Estimasi untuk **1 pengembang** (kamu), sambil aplikasi tetap dipakai. Anggap realistis, bukan optimis.

### Fase 0 — Fondasi (2–3 hari)

| Deliverable | Detail |
|---|---|
| npm workspaces + `apps/scheduler-web`, `packages/ui`, `packages/api-client` | `package.json`, tsconfig, ESLint/Prettier |
| Vite + React 18 + TS + Router + TanStack Query + Bootstrap 5 | Template jalan ("hello world" ter-style) |
| `packages/api-client` | `fetch` pembungkus: cookie, `X-CSRF-Token`, error → tipe `ApiError` |
| `packages/ui` | Navbar, sidebar, kartu portal, alert — replika 1:1 dari tampilan sekarang |
| `controllers/Api/ApiController.php` | Envelope, 401 JSON, CSRF, handler exception |
| `GET /api/auth/me`, `GET /api/auth/csrf` | Prasyarat semua modul |
| `tools/deploy.ps1` + `.htaccess` | Build & unggah atomik |
| Perlindungan route React | Belum login → halaman login PHP |

**Definition of done**: satu halaman React kosong (dengan navbar+sidebar identik) bisa dibuka di
`https://schedulewfm.iferror.web.id/scheduling-system/public/app/…`, membaca `/api/auth/me`, dan
menendang ke login kalau belum ada sesi.

### Fase 1 — Pilot: Family Financial Report (1–2 minggu)

Lihat rincian di §9. **Hasil sampingan wajib**: cetakan (template) yang dipakai berulang di fase
berikutnya — struktur folder fitur, contoh CRUD, contoh form + validasi zod, contoh tabel + filter.

**Definition of done**: FFR bisa dipakai harian untuk catat & lihat laporan keuangan; API-nya punya test;
deploy via `deploy.ps1`; kartu di Portal sudah mengarah ke app React FFR (menggantikan halaman penanda).

### Fase 2 — BAK (3–5 hari)

- Bikin ulang `BAKStepper.tsx` sebagai bagian dari build Vite (**bukan** Babel-in-browser) → perbaikan T1.
- Endpoint `/api/bak/*` sudah ada; rapikan saja envelope + 401 JSON.
- Hapus `bak-loader.js`, React UMD, dan Babel dari `views/bak/index.php`.
- Ukur: waktu muat halaman BAK sebelum vs sesudah (jadi bukti nyata perbaikan).

**DoD**: halaman BAK React menggantikan versi PHP; tidak ada lagi `@babel/standalone` di aplikasi.

### Fase 3 — Master data: Shift, Staff, Leave (1 minggu)

- API: `ShiftApiController`, `StaffApiController`, `LeaveApiController` (nyalakan CRUD + aksi khusus:
  `restoreDefaults`, `saveToggle`, `lookup`, `checkCompo`, `approve/reject`).
- UI: tabel + form + modal, mengikuti gaya halaman sekarang.
- Termasuk **Manajemen User** (tabel `users` sudah ada) — sekalian, karena bentuknya sama.

**DoD**: Shift Master & Manage Staff versi React bisa menggantikan versi PHP (diuji berdampingan).

### Fase 4 — Forecast & TL Schedule (1,5–2 minggu)

- Form kompleks + **Bulk Input** (tempel tabel dari Excel) — di React jadi jauh lebih enak
  (validasi per sel, preview sebelum simpan).
- Pertahankan perilaku impor yang ada; tambah test regresi untuk parser-nya.

### Fase 5 — Schedule generate, Overview, Backdate (2–3 minggu)

- `SchedulingEngine` & `ScheduleValidator` **tidak disentuh**; hanya UI-nya.
- Tabel besar (matriks staff × hari) → TanStack Table + virtualisasi.
- Halaman generate: tampilkan progres + hasil validasi (read-only view dulu, baru aksi tulis).
- Ini fase paling berisiko → wajib punya test perbandingan: hasil generate dari UI React harus **identik**
  dengan hasil dari halaman PHP untuk input yang sama (jalankan keduanya, bandingkan di DB).

### Fase 6 — Portal, Auth, dan pensiun view PHP (1 minggu)

- Portal + Login + Ganti Password jadi React; header/footer PHP dihapus untuk modul yang sudah pindah.
- `scheduling-system/public/index.php` menyusut jadi router API + satu halaman `index.php?page=redirect`.
- Dokumentasi: perbarui `research-plan.md` (perbaikan T4) atau tandai sebagai historis, dan tulis
  `docs/ARCHITECTURE.md` yang baru.

**Total: ± 8–11 minggu.**

### Gerbang kualitas (berlaku untuk semua fase)

- [ ] API diuji `curl` (termasuk kasus belum login → 401 JSON, CSRF salah → 400)
- [ ] Berjalan berdampingan dengan halaman PHP lama tanpa saling merusak
- [ ] Deploy atomik, versi lama masih bisa dibalik < 5 menit
- [ ] Tidak ada error di console browser & log PHP
- [ ] Sudah diuji di HP (aplikasi ini dipakai lewat browser HP juga)

---

## 9. Rincian pilot: Family Financial Report

FFR dipilih sebagai pilot karena **proyek baru** — tidak ada warisan, jadi pola yang dihasilkan bersih.

### Ruang lingkup awal (usulan, mohon dikoreksi)

| Layar | Isi |
|---|---|
| Dashboard | Saldo per dompet/rekening, pemasukan & pengeluaran bulan ini, grafik 6 bulan |
| Transaksi | Tabel + filter (tanggal, kategori, jenis), tambah/ubah/hapus, impor |
| Kategori | Master kategori pemasukan/pengeluaran (CRUD) |
| Rekening/Dompet | Master (tunai, bank, e-wallet) + saldo awal |
| Anggaran | Budget per kategori per bulan + indikator pemakaian |
| Laporan | Laporan bulanan/tahunan, ekspor CSV |
| Pengaturan | Mata uang, format angka (`Rp`), kategori default |

### Usulan tabel (baru, terpisah dari tabel Workforce)

```sql
CREATE TABLE wallets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  type ENUM('cash','bank','ewallet') NOT NULL DEFAULT 'cash',
  opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  kind ENUM('income','expense') NOT NULL,
  parent_id INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cat (name, kind),
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  wallet_id INT NOT NULL,
  category_id INT NOT NULL,
  kind ENUM('income','expense','transfer') NOT NULL,
  amount DECIMAL(18,2) NOT NULL,
  occurred_on DATE NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  transfer_group VARCHAR(36) DEFAULT NULL,   -- penanda pasangan transfer
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_tx_date (occurred_on),
  KEY idx_tx_cat (category_id),
  FOREIGN KEY (wallet_id) REFERENCES wallets(id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  period CHAR(7) NOT NULL,              -- 'YYYY-MM'
  amount DECIMAL(18,2) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_budget (category_id, period),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;
```

Prinsip yang dipegang: **tabel FFR tidak menyentuh tabel Workforce** (pemisahan yang kamu minta),
dan migrasi tabel dilakukan lewat pola yang sudah ada di `config/Database.php`.

### Endpoint FFR (Fase 1)

```text
GET    /api/finance/wallets              GET    /api/finance/categories
POST   /api/finance/wallets              POST   /api/finance/categories
PUT    /api/finance/wallets/{id}         PUT    /api/finance/categories/{id}
DELETE /api/finance/wallets/{id}         DELETE /api/finance/categories/{id}

GET    /api/finance/transactions?from=&to=&category=&wallet=&kind=&page=
POST   /api/finance/transactions
PUT    /api/finance/transactions/{id}
DELETE /api/finance/transactions/{id}
POST   /api/finance/transactions/import        # tempel/unggah CSV

GET    /api/finance/summary?period=YYYY-MM     # dashboard
GET    /api/finance/budgets?period=YYYY-MM
PUT    /api/finance/budgets/{categoryId}
GET    /api/finance/reports/monthly?year=
GET    /api/finance/reports/export.csv?...     # ekspor
```

Semua uang: `DECIMAL(18,2)`, **jangan** pakai float. Di sisi TS gunakan tipe `string` untuk nominal
(untuk mempertahankan presisi) dan format saat ditampilkan.

---

## 10. Pemetaan modul → API (ringkas)

| Modul | Route PHP sekarang | Endpoint API baru | Fase |
|---|---|---|---|
| Auth | `?page=auth` | `GET /api/auth/me`, `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/csrf` | 0 |
| Portal | `?page=portal` | (statis di SPA, tidak perlu API) | 6 |
| BAK | `?page=bak`, `/api/bak/*` | rapikan envelope + 401 JSON | 2 |
| Shift | `?page=shifts&action=*` | `/api/shifts`, `/api/shifts/{code}/active`, `/api/shifts/restore-defaults` | 3 |
| Staff | `?page=staff&action=*` | `/api/employees`, `/api/employees/import`, `/api/employees/{id}/compo` | 3 |
| Leave | `?page=leave&action=*` | `/api/leave`, `/api/leave/{id}/approve`, `/api/leave/{id}/reject` | 3 |
| Forecast | `?page=forecast&action=save` | `/api/forecast`, `PUT /api/forecast` | 4 |
| TL Schedule | `?page=tl_schedule&action=*` | `/api/tl-schedule`, `/api/tl-schedule/import`, `/api/tl-schedule/automation` | 4 |
| Schedule | `?page=schedule&action=generate` | `POST /api/schedule/generate`, `GET /api/schedule/{week}` | 5 |
| Schedule Overview | `?page=schedule_overview` | `/api/schedule/overview?week=` | 5 |
| Backdate | `?page=backdate` | `/api/backdate` | 5 |
| Finance (FFR) | — (baru) | lihat §9 | 1 |

---

## 11. Risiko & mitigasi

| # | Risiko | Dampak | Mitigasi |
|---|---|---|---|
| R1 | Migrasi mandek di tengah → dua aplikasi selamanya | Tinggi | Fase kecil & berdiri sendiri; tiap fase menghasilkan sesuatu yang bisa dipakai. Kalau berhenti setelah Fase 1 pun, FFR sudah jadi produk nyata |
| R2 | Regresi pada mesin penjadwalan | Tinggi | `services/` tidak disentuh; test perbandingan hasil generate PHP vs React (Fase 5) |
| R3 | Deploy FTP setengah jalan → aplikasi rusak | Tinggi | Deploy atomik (folder tukar nama) + panduan rollback |
| R4 | Sesi/login tidak nyambung antara PHP & SPA | Sedang | Satu domain, satu cookie; `GET /api/auth/me` sebagai penjaga; kalau FFR nanti beda subdomain → atur `session.cookie_domain = .iferror.web.id` |
| R5 | Kamu belum familiar React/TS | Sedang | Mulai dari pilot FFR yang bersih; Bootstrap dipakai supaya fokus ke React, bukan CSS; dokumentasikan pola sekali, pakai berulang |
| R6 | Tidak ada test → takut mengubah | Sedang | Tambah PHPUnit untuk `services/` **sebelum** Fase 5; Vitest sejak Fase 0 |
| R7 | Halaman React "hilang" saat refresh | Rendah | SPA fallback `.htaccess` diuji di Fase 0 |
| R8 | Dua bahasa (PHP & TS) membingungkan | Rendah | Batas tegas: PHP = data & aturan; TS = tampilan. Tidak ada logika bisnis di frontend |

---

## 12. Testing & QA

| Lapis | Alat | Cakupan |
|---|---|---|
| Service PHP | PHPUnit | SchedulingEngine, ScheduleValidator, ForecastService (deterministik, tanpa DB nyata kalau bisa) |
| API | PHPUnit (feature) / skrip `curl` | Status code, envelope, CSRF, 401 |
| Komponen | Vitest + RTL | Form validasi, tabel filter, perhitungan laporan |
| End-to-end | Playwright | Alur kritis: login → portal → modul; generate jadwal; catat transaksi |
| Manual | Checklist §8 gerbang kualitas | Termasuk uji di HP |

Aturan: setiap bug yang ditemukan di produksi → tambahkan test yang menangkapnya.

---

## 13. Rollback

| Situasi | Tindakan | Waktu |
|---|---|---|
| Modul React error di produksi | Arahkan kartu Portal/path kembali ke halaman PHP (flag) | < 5 menit |
| Build baru rusak | Tukar folder `ffr` ⇄ `ffr_prev` | < 5 menit |
| API bermasalah | Endpoint API di-disable; halaman PHP lama tetap berfungsi | < 15 menit |
| Migrasi dihentikan permanen | Hapus `apps/`, `packages/`, `public/app/`; aplikasi PHP utuh | < 1 jam |

Syarat agar rollback ini benar-benar mungkin: **jangan hapus view PHP** sampai fase berikutnya benar-benar
stabil (minimal 1–2 minggu pemakaian nyata).

---

## 14. Langkah berikutnya (minggu pertama)

1. **Konfirmasi arah**: setuju Opsi A? (kalau ragu, cukup jawab dan saya siapkan detail Opsi B)
2. **Pastikan Node 20 + Git terpasang di komputer** (lokal saja, bukan server).
3. **Cek ke provider hosting**: apakah ada SSH/Git deploy? Ini menyederhanakan §7 secara signifikan.
4. **Perbaikan kecil yang bisa langsung jalan** (independen dari migrasi):
   - API balas 401 JSON untuk `/api/*` (T2)
   - Sudah dijalankan: hapus duplikasi Bootstrap/jQuery di `views/layouts/footer.php`
5. **Tetapkan ruang lingkup FFR** (§9) — terutama: dompet/rekening dipakai berapa banyak, perlu multi-mata
   uang?, perlu lampiran bukti (foto struk)?
6. Mulai **Fase 0**: `npm init -w apps/scheduler-web`, pasang Vite + TS + Router + Query + Bootstrap,
   dan bikin `ApiController` + `/api/auth/me`.

---

## Lampiran A — Kenapa bukan "langsung semua React"?

Karena risiko terkonsentrasi di satu titik waktu: kalau ada satu bug di mesin penjadwalan, seluruh sistem
mati di saat yang sama, tanpa pembanding untuk mencari tahu mana yang salah. Dengan bertahap, versi PHP
yang sudah teruji produksi berfungsi sebagai **acuan pembanding** untuk versi React — ini yang membuat
migrasi modul rumit (Fase 5) bisa diverifikasi, bukan sekadar "kelihatannya jalan".

## Lampiran B — Bila akhirnya memilih Opsi B (Next.js di VPS)

Yang berubah dari plan di atas:

| Bagian | Perubahan |
|---|---|
| §5 struktur | `apps/ffr` & `scheduler-web` → `apps/web` (Next.js App Router); `scheduling-system/` dipertahankan sebagai referensi lalu diarsipkan |
| §6 API | Route handlers Next.js (`app/api/...`) + Prisma/Drizzle untuk MySQL |
| §7 deploy | Build di CI → deploy ke VPS (Docker + Nginx, atau PM2); FTP ditinggalkan |
| §9 FFR | Sama — pilot tetap FFR, tapi sekalian jadi acuan infrastruktur baru |
| Fase 3–5 | Semua service PHP ditulis ulang ke TypeScript → **naikkan estimasi 2–3×**, dan Fase 5 wajib punya test perbandingan yang lebih ketat |
| Auth | NextAuth (credentials) + sesi database; cookie lama tidak kompatibel → perlu masa transisi login ganda atau logout paksa |
| Tambahan fase | **Fase 0.5**: setup VPS, Docker, Nginx, SSL, backup otomatis, monitoring — pekerjaan baru yang tidak ada di Opsi A |

---

*Dokumen ini belum final — bagian yang paling perlu masukanmu: ruang lingkup FFR (§9) dan arah Opsi A/B (§3).*
