# SILAS — Sistem Informasi Peminjaman Laptop Sekolah

Aplikasi web untuk mengelola peminjaman laptop sekolah. Siswa mengajukan peminjaman, petugas memprosesnya, dan admin mengelola seluruh data inventaris, pengguna, serta laporan.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-MySQL-003545?logo=mariadb&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-Vanilla-F7DF1E?logo=javascript&logoColor=black)
![XAMPP](https://img.shields.io/badge/XAMPP-ready-FB7A24?logo=xampp&logoColor=white)

<!-- Tambahkan screenshot di sini, misalnya:
![Dashboard Admin](docs/screenshots/dashboard-admin.png)
-->

## Fitur

### 👤 Semua pengguna
- Login satu form untuk semua role; sistem mengarahkan ke dashboard sesuai role akun.
- Pendaftaran akun mandiri. Akun baru **otomatis berperan siswa**; perubahan role hanya bisa dilakukan admin.
- Password disimpan dalam bentuk hash (bcrypt) dan pembatasan percobaan login.

### 🎓 Siswa
- **Dashboard:** ringkasan peminjaman aktif, pengajuan menunggu, riwayat, dan stok laptop tersedia.
- **Ajukan Pinjam:** pilih laptop yang tersedia dan tentukan tanggal pinjam serta kembali.
- **Peminjaman Saya:** pantau status dan sisa hari, batalkan pengajuan yang belum diproses.
- **Riwayat:** laptop yang sudah dikembalikan, keterlambatan, dan pengajuan yang ditolak.
- **Profil:** ubah email, no. HP, dan password.

### 🧑‍💼 Petugas
- **Dashboard:** antrean pengajuan, peminjaman berjalan, jatuh tempo hari ini, dan yang terlambat.
- **Peminjaman:** setujui atau tolak pengajuan, catat peminjaman langsung.
- **Pengembalian:** terima laptop kembali beserta kondisi dan keterangan.
- **Data Laptop** (hanya lihat) dan **Laporan**.

### 🛠 Admin
- **Dashboard:** statistik inventaris, grafik peminjaman 6 bulan terakhir, dan kondisi laptop.
- **Data Laptop:** tambah, ubah, hapus, cari, dan filter.
- **Pengguna:** kelola akun admin, petugas, dan siswa; reset password; ubah role.
- **Peminjaman:** pantau seluruh transaksi, setujui/tolak, terima pengembalian.
- **Laporan:** filter periode, ringkasan, unduh **CSV** (kompatibel Excel), dan cetak/PDF.
- **Pengaturan:** ubah profil dan password.

## Alur peminjaman

```
Siswa mengajukan ──► menunggu ──► Petugas/Admin setujui ──► dipinjam ──► diterima kembali ──► dikembalikan
                         │
                         └──► ditolak
```

- Laptop otomatis berstatus `dipinjam` saat disetujui dan kembali `tersedia` saat dikembalikan.
- Jika dikembalikan dengan kondisi **Rusak**, laptop masuk status `perbaikan`.
- Aturan siswa (ubah di `api/ajukanSiswa.php`): maksimal **7 hari** per peminjaman (`MAKS_HARI`), **1** peminjaman/pengajuan aktif sekaligus (`MAKS_AKTIF`), dan tidak bisa meminjam lagi jika ada laptop yang terlambat.

## Teknologi

| Bagian | Teknologi |
|---|---|
| Frontend | HTML, CSS, JavaScript (vanilla, tanpa framework) |
| Backend | PHP 8.1+ (PDO), API berformat JSON |
| Database | MariaDB / MySQL |
| Server lokal | XAMPP |

Frontend dan backend dipisah: halaman HTML hanya memanggil endpoint di folder `api/` lewat `fetch`.

## Struktur proyek

```
silas/
├── login.html
├── register.html
├── auth.css
├── admin/
│   ├── dashboard.html  laptop.html  pengguna.html
│   ├── peminjaman.html  laporan.html  pengaturan.html
│   └── admin.css
├── petugas/
│   ├── dashboard.html  peminjamanPetugas.html  pengembalianPetugas.html
│   ├── laptopPetugas.html  laporanPetugas.html
│   └── petugas.css
├── siswa/
│   ├── dashboard.html  ajukanPinjam.html  peminjamanSaya.html
│   ├── riwayatSiswa.html  profilSiswa.html
│   └── siswa.css
├── api/
│   ├── config.php  auth.php  login.php  logout.php  register.php
│   ├── dashboardAdm.php  dashboardPtgs.php  dashboardSiswa.php
│   ├── laptopAdm.php  laptopPtgs.php  penggunaAdm.php
│   ├── peminjamanAdm.php  laporanAdm.php  pengaturanAdm.php
│   └── ajukanSiswa.php  peminjamanSiswa.php  profilSiswa.php
└── database/
    ├── schema.sql
    ├── akun_uji_coba.sql
    └── migrasi_users_role.sql
```

## Instalasi (XAMPP)

1. **Salin proyek** ke folder `htdocs`, misalnya `C:\xampp\htdocs\silas`.
2. **Jalankan Apache dan MySQL** dari XAMPP Control Panel.
3. **Buat database.** Buka phpMyAdmin → tab *SQL* → jalankan isi `database/schema.sql`. Ini membuat database `db_peminjaman_laptop` beserta tabelnya.
4. **(Opsional) Buat akun uji coba** dengan menjalankan `database/akun_uji_coba.sql`.
5. **Sesuaikan koneksi** di `api/config.php` bila perlu:
   ```php
   const DB_HOST = 'localhost';
   const DB_NAME = 'db_peminjaman_laptop';
   const DB_USER = 'root';
   const DB_PASS = '';
   ```
6. **Buka** `http://localhost/silas/login.html`.

> **Sudah punya database versi lama** (tabel `admin` terpisah)? Backup dulu, lalu jalankan `database/migrasi_users_role.sql` untuk memindahkan akun ke tabel `users` dengan kolom `role`.

### Akun uji coba

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Petugas | `petugas` | `petugas123` |
| Siswa | `siswa` | `siswa123` |

> ⚠️ Akun ini hanya untuk pengembangan lokal. **Hapus atau ganti passwordnya** sebelum dipakai sungguhan.

## Struktur database

| Tabel | Isi |
|---|---|
| `users` | Seluruh akun: `nama`, `username`, `password` (hash), `role` (`admin`/`petugas`/`siswa`), `kelas`, `no_hp`, `email` |
| `laptops` | Inventaris: `kode_laptop`, `merk`, `tipe`, `kondisi`, `status` (`tersedia`/`dipinjam`/`perbaikan`) |
| `peminjaman` | Transaksi: peminjam, laptop, petugas pemroses, tanggal pinjam, tanggal harus kembali, `status` (`menunggu`/`dipinjam`/`dikembalikan`/`ditolak`) |
| `pengembalian` | Catatan pengembalian: tanggal kembali, kondisi saat kembali, keterangan, petugas penerima |

## Endpoint API

Semua endpoint membalas JSON dan memeriksa sesi serta role di sisi server.

| Endpoint | Role | Fungsi |
|---|---|---|
| `POST api/login.php` | publik | Login |
| `POST api/register.php` | publik | Daftar akun siswa |
| `GET api/logout.php` | semua | Keluar |
| `api/dashboardAdm.php` · `dashboardPtgs.php` · `dashboardSiswa.php` | admin · petugas · siswa | Data dashboard |
| `api/laptopAdm.php` | admin | CRUD data laptop |
| `api/laptopPtgs.php` | petugas | Daftar laptop (hanya baca) |
| `api/penggunaAdm.php` | admin | CRUD pengguna |
| `api/peminjamanAdm.php` | admin, petugas | Daftar transaksi, setujui, tolak, kembali, catat |
| `api/laporanAdm.php` | admin, petugas | Laporan dan unduh CSV |
| `api/pengaturanAdm.php` | admin | Profil dan password admin |
| `api/ajukanSiswa.php` | siswa | Daftar laptop tersedia dan ajukan pinjam |
| `api/peminjamanSiswa.php` | siswa | Peminjaman aktif, riwayat, batalkan |
| `api/profilSiswa.php` | siswa | Kontak dan password siswa |

## Keamanan

- Query memakai **prepared statement (PDO)** untuk mencegah SQL injection.
- Password di-hash dengan `password_hash()`; password teks biasa dari data lama otomatis di-hash saat login pertama.
- Hak akses diperiksa di server (`require_role`), bukan hanya di tampilan.
- Operasi peminjaman memakai **transaksi database** dengan penguncian baris agar tidak terjadi dua persetujuan untuk laptop yang sama.
- Ekspor CSV mengamankan sel yang diawali `=`, `+`, `-`, `@` dari formula injection.
- Role pada pendaftaran selalu `siswa`; input role dari klien diabaikan.

## Keterbatasan yang diketahui

- Halaman `.html` statis tetap bisa dibuka lewat URL tanpa login; **datanya** terlindungi karena API menolak sesi yang tidak valid. Untuk memproteksi halamannya juga, ubah menjadi `.php` dan panggil `require_role()` di awal.
- Pendaftaran terbuka tanpa verifikasi email atau persetujuan admin.
- Belum ada fitur lupa password dan notifikasi keterlambatan.

## Rencana pengembangan

- [ ] Proteksi halaman di sisi server
- [ ] Lupa/reset password
- [ ] Notifikasi atau pengingat keterlambatan
- [ ] Pengaturan sistem (durasi pinjam, batas aktif) dari antarmuka admin
- [ ] Halaman pengaturan untuk petugas

## Lisensi

Tentukan lisensi proyek Anda di sini (misalnya MIT) dan tambahkan file `LICENSE`.
