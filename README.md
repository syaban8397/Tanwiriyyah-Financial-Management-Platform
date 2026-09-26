# Tanwiriyyah Financial Management Platform

Platform keuangan terpusat untuk Yayasan Tanwiriyyah. Delapan unit awal (RA, MI, DTA, MTs, MA, Pondok Pesantren, Majelis Ta'lim, BLKK) dikelola dari satu jurnal. Unit baru ditambahkan lewat administrasi, tanpa mengubah kode sumber.

## Arsitektur

Backend Laravel menyimpan angka sebagai integer rupiah. Setiap transaksi yang diposting menghasilkan jurnal berpasangan (debit = kredit) di dalam transaksi database, dengan kunci baris pada akun kas atau bank. Transfer memindahkan aset dan tidak masuk pendapatan atau beban.

Alur persetujuan dibaca dari tabel `approval_workflows`, bukan dari cabang peran di controller:

`draf → diajukan → ditinjau → disetujui → diposting → direkonsiliasi`

Penolakan mengembalikan transaksi ke draf lewat revisi. Transaksi yang sudah diposting tidak diubah; koreksi memakai penyesuaian. Periode `open` menerima entri. Periode `closing` atau `locked` menolak entri biasa.

Isolasi unit ditegakkan di query backend (`visibleTo` / `canAccessUnit`). Pengguna dengan `unit_id` kosong melihat semua unit. Pengguna unit hanya melihat unitnya, termasuk lewat permintaan langsung.

## Stack

- PHP 8.3+ dan Laravel 13
- MySQL atau MariaDB untuk aplikasi; PHPUnit memakai SQLite memori
- Inertia 3, React 19, TypeScript, Tailwind CSS 4, Vite
- Dompdf untuk PDF, OpenSpout untuk Excel
- Antrean database untuk laporan
- Server-Sent Events untuk pembaruan sesi lain

## Kebutuhan

- PHP 8.3 atau lebih baru, dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`
- Composer 2
- Node.js 20 atau lebih baru dan npm
- MySQL 8 atau MariaDB

## Pemasangan

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Buat basis data, lalu sesuaikan `DB_*` di `.env`.

```bash
php artisan migrate --force
php artisan db:seed --force
npm install
npm run build
php artisan serve
```

Buka `http://127.0.0.1:8000`.

## Lingkungan

Salin `.env.example`. Wajib diisi:

| Variabel | Peran |
| --- | --- |
| `APP_NAME` | Nama aplikasi |
| `APP_KEY` | Kunci enkripsi, termasuk nomor rekening |
| `APP_URL` | URL yang dipakai sesi dan tautan |
| `APP_LOCALE` | `id` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Basis data aplikasi |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` agar laporan tidak memblokir permintaan |
| `FILESYSTEM_DISK` | `local` (penyimpanan privat, bukan publik) |

Jangan menyimpan `.env` berisi rahasia produksi ke repositori.

## Basis data, migrasi, dan data contoh

```bash
php artisan migrate
php artisan db:seed
```

`FinanceCatalogSeeder` membuat peran, izin, bagan akun, periode Juli–September 2026, alur persetujuan, anggaran, serta kas dan bank tiap unit. `DemoDataSeeder` memposting penerimaan dan beban yang melewati alur persetujuan, mengunci Juli dan Agustus 2026, lalu menyisakan draf, pengajuan, ambang anggaran, transfer, dan rekening koran pada September 2026.

Ulangi seeder pada basis data yang sudah berisi transaksi demo akan menambah transaksi baru. Untuk mulai bersih, kosongkan basis data aplikasi terlebih dahulu, lalu jalankan migrasi dan seeder lagi.

## Pengembangan

```bash
composer dev
```

Perintah itu menjalankan server, antrean, log, dan Vite. Atau jalankan terpisah:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

## Pengujian

```bash
php artisan test
npm run test:ui
```

PHPUnit memaksa SQLite `:memory:` dan antrean `sync`. Suite mencakup format rupiah, alur penerimaan sampai posting, isolasi unit lewat halaman dan API, beban dengan dokumen, transfer, ambang anggaran, periode terkunci, idempotensi, aliran realtime, posting ganda, kesesuaian laporan dengan buku besar, dan larangan menyetujui transaksi sendiri.

## Build frontend

```bash
npm run build
```

Hasilnya ada di `public/build`. `npm run dev` dipakai saat mengembangkan tampilan.

## Realtime

`GET /realtime/stream` adalah aliran SSE untuk sesi yang sudah masuk. Peristiwa domain (`transaction.submitted`, persetujuan, posting, peringatan anggaran, penutupan periode) ditulis ke `domain_events`. Klien yang berwenang memuat ulang data Inertia. Kegagalan aliran tidak mengubah jurnal. Pengguna unit hanya menerima peristiwa unitnya.

## Antrean

Ekspor Excel dan PDF dijalankan oleh `GenerateReportJob`. Dengan `QUEUE_CONNECTION=database`, jalankan `php artisan queue:work`. Tanpa worker, status laporan tetap `preparing`.

## Deploy

- `APP_ENV=production`, `APP_DEBUG=false`
- `php artisan key:generate` sekali, lalu simpan `APP_KEY`
- `php artisan migrate --force`
- `npm ci && npm run build`
- Proses web, `php artisan queue:work`, dan penyimpanan `storage/app/private` yang tidak dapat diunduh publik
- Jadwalkan `php artisan schedule:run` hanya jika nanti ada tugas terjadwal
- Dokumen transaksi dan berkas laporan tidak boleh dipindah ke disk `public`

## Akun demo

Kata sandi semua akun: `password`

| Email | Peran |
| --- | --- |
| `admin@tanwiriyyah.test` | Super Admin. Kelola pengguna, peran, unit, dan pengaturan. Tidak membuat atau menyetujui transaksi. |
| `yayasan@tanwiriyyah.test` | Bendahara Yayasan. Semua unit, tinjau, setujui, posting, anggaran, rekonsiliasi, tutup periode, audit. |
| `ra@tanwiriyyah.test` | Bendahara RA |
| `mi@tanwiriyyah.test` | Bendahara MI |
| `dta@tanwiriyyah.test` | Bendahara DTA |
| `mts@tanwiriyyah.test` | Bendahara MTs |
| `ma@tanwiriyyah.test` | Bendahara MA |
| `pesantren@tanwiriyyah.test` | Bendahara Pondok Pesantren |
| `majelis@tanwiriyyah.test` | Bendahara Majelis Ta'lim |
| `blkk@tanwiriyyah.test` | Bendahara BLKK |

## Peran dan izin

Izin diperiksa lewat Gate, bukan nama peran di controller.

- Super Admin: `USER_MANAGE`, `UNIT_MANAGE`, `SYSTEM_SETTINGS_MANAGE`, `AUDIT_VIEW`, `REPORT_VIEW`, `REPORT_EXPORT`, `FINANCE_VIEW`, `PERIOD_OPEN`, `PERIOD_CLOSE`, `BUDGET_VIEW`, `RECONCILIATION_VIEW`
- Bendahara Yayasan: seluruh izin keuangan termasuk `FINANCE_VERIFY`, `FINANCE_APPROVE`, `FINANCE_POST`, `FINANCE_ADJUST`, anggaran, rekonsiliasi, laporan, periode, dan `AUDIT_VIEW`
- Bendahara Unit: `FINANCE_VIEW`, `FINANCE_CREATE`, `FINANCE_EDIT`, `FINANCE_SUBMIT`, `FINANCE_ADJUST`, `BUDGET_VIEW`, `RECONCILIATION_VIEW`, `REPORT_VIEW`, `REPORT_EXPORT`. Penyesuaian diajukan oleh unit dan diposting oleh Bendahara Yayasan.

Pengaturan `prevent_self_approval` menolak pembuat transaksi untuk meninjau, menyetujui, menolak, atau memposting transaksinya sendiri.

## Pemecahan masalah

- Halaman kosong atau gaya tidak muncul: jalankan `npm run build` atau `npm run dev`.
- Laporan macet di "Disiapkan": jalankan `php artisan queue:work`.
- Nomor rekening gagal dibaca: `APP_KEY` berubah setelah data dienkripsi. Kunci harus tetap.
- `SQLSTATE` saat migrasi: pastikan basis data sudah dibuat dan `DB_*` benar.
- Masuk ditolak setelah lima percobaan: pembatas laju login 5 permintaan per menit.
- Bendahara unit mendapat 403 pada unit lain: itu perilaku yang diharapkan.
