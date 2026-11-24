# EDUSMART ERP — Fase 1

Platform SaaS manajemen sekolah multi-tenant: PHP 8.4 + MySQLi, Bootstrap 5,
Docker Compose, dan AI Gateway Python (FastAPI). Implementasi mengikuti blueprint
 

Dokumen ini mencakup **Fase 1: fondasi SaaS dan tenant** — struktur project, database
hybrid (core + database per sekolah), autentikasi, tenant resolver, RBAC, license
engine, tenant provisioning, dashboard Bootstrap, seed demo, dan scaffold AI gateway.

---

## 1. Arsitektur singkat

```
Browser ──HTTPS──► Apache/PHP 8.4 (modular monolith)
                      ├── Auth, Session, CSRF
                      ├── TenantResolver + RBAC + LicenseGuard
                      ├── SaaS Core (tenant, paket, lisensi, subscription, audit)
                      ├── Modul sekolah (fondasi untuk PPDB → kelulusan)
                      └── Core DB (MySQL)  +  Tenant DB per sekolah
                                              └── Python AI Gateway (FastAPI)
```

* **Database hybrid.** Satu database core menyimpan identitas platform
  (`tenants`, `plans`, `subscriptions`, `licenses`, `invoices`, `platform_users`,
  `audit_events`). Setiap sekolah mendapat database sendiri
  (`edusmart_t_<slug>`) berisi data operasional.
* **Tenant isolation.** Nama database **hanya** berasal dari record tenant yang
  sudah tervalidasi, tidak pernah dari input pengguna. Slug divalidasi dengan pola
  `^[a-z0-9][a-z0-9-]{0,61}[a-z0-9]$` dan daftar slug terlarang.
* **RBAC.** Role = kumpulan permission berpola `resource.action`
  (`students.view`, `finance.payment.post`, `certificates.issue`, …). Pemeriksaan
  selalu di sisi server; wildcard `resource.*` dan `*` didukung.
* **License engine.** Status: `trial`, `active`, `grace_period`, `suspended`,
  `expired`, `cancelled`. Setelah masa tenggang, akses menjadi *read-only* dan
  halaman tenant diblokir. Kunci lisensi disimpan sebagai hash.
* **Audit.** Login (sukses/gagal), provisioning, suspend/activate tercatat dengan
  actor, tenant, IP, user agent, dan metadata (nilai sensitif dibuang).
* **Proteksi dasar.** Prepared statement MySQLi, escaping output (`e()`), CSRF per
  form POST, session `HttpOnly`/`SameSite=Lax` + regenerasi saat login, throttle
  login 5 percobaan / 15 menit, cookie `Secure` dapat diaktifkan via env.

## 2. Struktur project

```
app/
  Core/         Auth, Config, Container, Database, Router, Session, Csrf, View,
                TenantResolver, RBAC, LicenseEngine, AuditLog, TenantConnector
  Controllers/  Home, Saas/*, Tenant/*
  Services/     MigrationRunner, TenantProvisioner, TenantSeeder, DemoDataSeeder
  bootstrap.php, routes.php
database/
  migrations/core/    skema platform SaaS
  migrations/tenant/  skema database sekolah
  seeds/              role, permission, paket
  init/               inisialisasi database Docker
resources/views/      layouts, saas/, tenant/, errors/
public/               front controller (index.php), aset, .htaccess, router.php
python-services/ai_gateway/  FastAPI gateway + tests
scripts/              migrate.php, provision_tenant.php, seed_demo.php, selftest.php
tests/                Unit (PHPUnit) + Fixtures
Dockerfile, docker-compose.yml, composer.json, phpunit.xml, .env.example
```

## 3. Menjalankan dengan Docker

Prasyarat: Docker Desktop dengan Docker Compose.

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php scripts/migrate.php core
docker compose exec app php scripts/seed_demo.php
```

Layanan:

| Layanan | URL |
|---|---|
| Beranda | http://localhost:8080/ |
| SaaS Super Admin | http://localhost:8080/saas/login |
| Login sekolah (demo) | http://localhost:8080/t/demo/login |
| Health check aplikasi | http://localhost:8080/health |
| AI Gateway health | http://localhost:8001/health |

Kredensial demo hasil `seed_demo.php` (ubah sebelum dipakai di lingkungan nyata):

| Peran | Email | Kata sandi |
|---|---|---|
| Super Admin SaaS | `admin@edusmart.local` | `ChangeMe!123456` |
| Admin sekolah demo | `sekolah@demo.edusmart.local` | `ChangeMe!123456` |
| Guru / Bendahara / TU | `guru@demo.edusmart.local`, `bendahara@demo.edusmart.local`, `tu@demo.edusmart.local` | `DemoPass!123456` |

Akun database dibuat otomatis oleh `database/init/01-databases.sql`:

* `edusmart_app` — akun runtime, hanya DML pada core dan `edusmart\_t\_%`.
* `edusmart_provisioner` — akun provisioning, boleh DDL dan `CREATE DATABASE`.
  Di produksi batasi host/jaringan akun ini dan gunakan secret manager.

## 4. Menjalankan tanpa Docker

Prasyarat: PHP 8.4 dengan ekstensi `mysqli`, MySQL 8.

```bash
cp .env.example .env          # sesuaikan kredensial database
mysql -u root -p < database/init/01-databases.sql
php scripts/migrate.php core
php scripts/seed_demo.php
php -S 127.0.0.1:8080 -t public public/router.php
```

`public/router.php` hanya untuk server pengembangan. Produksi memakai Apache
(`Dockerfile`) atau Nginx + PHP-FPM dengan document root `public/`.

## 5. Perintah CLI

| Perintah | Fungsi |
|---|---|
| `php scripts/migrate.php core` | Menjalankan migrasi database platform |
| `php scripts/migrate.php tenant --slug=demo` | Migrasi satu database sekolah |
| `php scripts/migrate.php tenant --all` | Migrasi seluruh tenant |
| `php scripts/seed_demo.php` | Paket, super admin, tenant demo + data sintetis |
| `php scripts/provision_tenant.php --name="..." --slug=... --admin-name=... --admin-email=... --admin-password=...` | Onboarding sekolah via CLI |
| `php scripts/selftest.php` | Uji mandiri tanpa Composer/MySQL |
| `composer test` | PHPUnit (`tests/Unit`) |
| `python -m pytest tests` (di `python-services/ai_gateway`) | Uji AI Gateway |

## 6. Alur onboarding tenant

1. Super Admin mengisi wizard di `/saas/tenants/create` (atau CLI).
2. `TenantProvisioner` memvalidasi input dan keunikan slug.
3. Database `edusmart_t_<slug>` dibuat dengan akun provisioning.
4. `MigrationRunner` menjalankan seluruh migrasi tenant (tercatat di
   `schema_migrations`).
5. `TenantSeeder` menanam 40+ permission, 9 role default, profil sekolah, tahun
   ajaran awal, dan akun admin dengan role `school_super_admin`.
6. Tenant, lisensi (trial/aktif), dan subscription dicatat di database core.
7. Audit `tenant.provisioned` disimpan. Jika pembuatan skema gagal, database
   dibatalkan (drop) agar tidak ada tenant setengah jadi.

## 7. Status lisensi dan akses

| Status | Tulis data | Portal sekolah |
|---|---|---|
| `trial` / `active` (sebelum `ends_at`) | Ya | Normal |
| `grace_period` (setelah `ends_at`, dalam `grace_days`) | Ya | Normal + peringatan |
| `expired` (melewati masa tenggang) | Tidak (read-only) | Halaman lisensi |
| `suspended` / `cancelled` | Tidak | Halaman lisensi |
| Tenant `suspended` | Tidak | Halaman "ditangguhkan" |

Fitur per paket dikendalikan `licenses.features` (`ppdb`, `master_data`,
`academic`, `finance`, `letters`, `graduation`, `communication`, `analytics`, `ai`,
atau `*`) dan ditampilkan pada dashboard sekolah.

## 8. AI Gateway

`python-services/ai_gateway` menyediakan kontrak layanan:

* `POST /v1/chat` — wajib header `Authorization: Bearer <AI_GATEWAY_TOKEN>`.
  Payload membawa `tenant_id`, `tenant_slug`, `user {id, role, permissions}`,
  `messages`, dan `context` (dokumen tenant).
* `GET /health` — status layanan, provider, dan konfigurasi kuota.

Pengamanan yang sudah ada: autentikasi service-to-service, rate/quota harian per
tenant (in-memory, placeholder), deteksi prompt injection, penandaan permintaan
transaksional sebagai `requires_approval`, serta larangan mencatat isi pesan ke log.
Provider `mock` dipakai secara default; isi `AI_PROVIDER=openai_compatible`,
`AI_BASE_URL`, `AI_API_KEY`, dan `AI_MODEL` untuk provider nyata.

AI **bukan** otoritas untuk nilai, kelulusan, penagihan, atau perubahan data.
Adapter PHP → gateway dan RAG tenant-aware dijadwalkan pada Fase 6.

## 9. Pengujian

Yang sudah dijalankan pada lingkungan build ini (Windows, PHP 8.3 CLI):

* `php -l` untuk seluruh berkas PHP — lolos.
* `php scripts/selftest.php` — **28/28 pemeriksaan lolos** (router, slug & nama
  database tenant, RBAC, license engine, CSRF, integritas migrasi, seed).
* `python -m pytest tests` pada AI Gateway — **8/8 lolos** (auth token, kontrak,
  guardrail, kuota, validasi).
* **Uji integrasi MariaDB 11.4.5** (instance portable lokal, bukan Docker):
  migrasi core & tenant, `seed_demo.php`, provisioning tenant kedua lewat CLI,
  login SaaS dan login sekolah (HTTP + sesi + CSRF), isolasi lintas tenant,
  suspend/aktifkan tenant, dan RBAC platform. Hasilnya:
  - `/health` = `ok`; `/saas/login`, `/saas`, `/t/demo/login`,
    `/t/demo/dashboard`, `/t/smpn2/login`, `/t/smpn2/dashboard` = 200.
  - Kredensial sekolah demo ditolak pada tenant `smpn2` (redirect ke login,
    tanpa dashboard) — isolasi berjalan.
  - Role `support` dapat melihat tenant tetapi perubahan status ditolak **403**;
    `super_admin` dapat menangguhkan (dashboard sekolah 403) dan mengaktifkan
    kembali (dashboard 200), dengan audit `tenant.suspended`/`tenant.activated`.
  - Audit login tercatat di core dan di database tenant.
  - Dengan database dimatikan, `/saas/login` dan `/t/{slug}/login` tetap
    **200** dan menampilkan peringatan; POST login dijawab redirect + pesan,
    bukan 500.

Akun database dipisahkan: migrasi, provisioning, dan seeding memakai akun
`PROVISION_DB_*` (DDL), sedangkan request aplikasi memakai akun `TENANT_DB_*`
(DML saja).

Belum dijalankan di lingkungan build ini (tidak tersedia): Docker dan Composer,
sehingga **PHPUnit dan `docker compose up` belum dieksekusi**. Jalankan di
lingkungan target:

```bash
composer install && composer test
docker compose up -d --build && docker compose exec app php scripts/seed_demo.php
```

Skenario uji minimum mengikuti blueprint bagian 11: login valid/tidak valid,
isolasi lintas tenant, RBAC per role, provisioning, status lisensi, migrasi,
audit, backup/restore, dan AI (prompt injection + kuota).

## 10. Keamanan dan catatan produksi

* Wajib HTTPS + `SESSION_SECURE_COOKIE=true`; jangan commit `.env`.
* Pisahkan akun DB provisioning dan runtime; batasi host (hindari grant `%` di
  produksi) dan simpan secret di secret manager.
* `APP_DEBUG` hanya untuk pengembangan; pesan error ke pengguna bersifat generik.
* Simpan file privat di luar web root dan layani via endpoint ber-otorisasi.
* Audit sudah mencatat aksi sensitif; lengkapi retensi dan monitoring pada Fase 1.1.
* Data demo bersifat sintetis; jangan pernah memakai data siswa nyata untuk demo.

## 11. Roadmap (sesuai blueprint)

| Tahap | Fokus | Status |
|---|---|---|
| Phase 1 | Fondasi SaaS, tenant, auth, RBAC, lisensi, provisioning, dashboard | **Selesai (baseline ini)** |
| Phase 1.1 | Hardening, UI role/permission, billing, renewal, audit lengkap, backup | Belum |
| Phase 2 | PPDB & master data | Belum (skema awal tersedia) |
| Phase 3 | Akademik: jadwal, absensi, penilaian, rapor | Belum |
| Phase 4 | Keuangan: tagihan, pembayaran, kas, rekonsiliasi | Belum (skema awal tersedia) |
| Phase 5 | Tata usaha, persuratan, kelulusan, sertifikat QR | Belum |
| Phase 6 | AI Assistant: provider, RAG, drafting, safety | Scaffold gateway tersedia |
| Phase 7 | Portal orang tua/siswa, komunikasi, perpustakaan, inventaris | Belum |
| Phase 8 | Production readiness: load test, HA, observability, UAT | Belum |
