# PRD: Sistem Informasi Rumah Sakit Berbasis SOA

Tugas Praktikum SOA (Service-Oriented Architecture). Dokumen ini adalah spesifikasi lengkap untuk dibuat menjadi kode oleh AI atau developer lain.

## Instruksi untuk AI yang Membaca Dokumen Ini

Kamu diminta membantu membangun aplikasi sesuai PRD ini. Kerjakan **bertahap**, dalam urutan: (1) SQL database, (2) web service di Server B, (3) UI di Server A, (4) pengujian. Setelah tiap tahap selesai, berikan **source code lengkap per file** (dengan nama file dan path), cara menjalankan, dan cara mengujinya. Jangan mengubah IP, nama folder, nama tabel, atau format respons yang sudah ditetapkan di dokumen ini. Mahasiswa pemilik tugas ingin memahami kodenya, jadi sertakan komentar singkat dalam bahasa Indonesia pada bagian penting.

## 1. Ringkasan Proyek

| Item | Isi |
|---|---|
| Mata kuliah | Service-Oriented Architecture (SOA) |
| Studi kasus | Pasien di Rumah Sakit |
| Tujuan | Membangun aplikasi dua server: Server A (UI) dan Server B (database + web service) dengan RBAC |
| Bahasa | PHP Native + MySQLi (pilihan utama, sesuai tugas) |
| Pengumpulan | Laporan (NPM, nama, kelas) berisi jawaban Soal 1 sampai 4, diunggah ke Classroom |

### Soal Dosen (yang harus terpenuhi)

1. Tulis IP address atau nama folder dan screenshot Server A (server utama aplikasi, berisi semua UI untuk user) dan Server B (server basis data dan web service).
2. Buat database dan tabel di Server B, tuliskan kode SQL-nya. Minimal tabel: Poliklinik, Pengguna, Dokter (bisa menjadi Pengguna), Pasien (bisa menjadi Pengguna aplikasi), Asuransi (bila pasien memakai asuransi).
3. Buat semua web service untuk melayani Server A: Login, CRUD semua tabel, dan laporan yang dibutuhkan. Tuliskan source code.
4. Buat semua UI dari landing page hingga dashboard/menu utama dan modul program, berbasis RBAC dan web service. Tuliskan source code tiap file.

## 2. Lingkungan dan Arsitektur

### 2.1 Infrastruktur (sudah ditetapkan)

| | Server A (Klien/UI) | Server B (API + Database) |
|---|---|---|
| IP Address | **192.168.1.2** | **192.168.1.10** |
| Perangkat | Komputer 1, XAMPP | Komputer 2, XAMPP |
| Layanan aktif | Apache + PHP (MySQL tidak dipakai) | Apache + PHP + MySQL (MariaDB) |
| Folder | `C:\xampp\htdocs\rs_client` | `C:\xampp\htdocs\rs_api` |
| URL dasar | `http://192.168.1.2/rs_client/` | `http://192.168.1.10/rs_api/` |

Kedua komputer berada di jaringan 192.168.1.x yang sama. Firewall Windows harus mengizinkan Apache (port 80) di kedua komputer. Nama database di Server B: `db_rumah_sakit`.

### 2.2 Alur Arsitektur

`Browser user -> Server A (UI PHP, session, RBAC) -> HTTP/cURL JSON -> Server B (web service PHP) -> MySQL`

Aturan penting:

- **Server A tidak boleh mengakses database langsung.** Semua data lewat web service Server B.
- Server B hanya mengembalikan **JSON**.
- Alamat API disimpan di satu file konfigurasi di Server A, supaya mudah diganti bila IP berubah.

## 3. Role dan RBAC

Tiga role: **admin**, **dokter**, **pasien**. Dokter dan pasien dapat memiliki akun pengguna (relasi ke tabel `pengguna`).

### 3.1 Matriks Hak Akses

| Modul | Admin | Dokter | Pasien |
|---|---|---|---|
| Dashboard | Statistik seluruh sistem | Statistik pasiennya | Ringkasan kunjungan sendiri |
| Kelola Pengguna | CRUD | - | - |
| Kelola Poliklinik | CRUD | Lihat | Lihat |
| Kelola Dokter | CRUD | Lihat dan ubah profil sendiri | Lihat daftar dokter |
| Kelola Pasien | CRUD | Lihat pasien yang ditangani | Lihat dan ubah profil sendiri |
| Kelola Asuransi | CRUD | Lihat | Lihat |
| Kunjungan | CRUD semua | Lihat dan isi diagnosis/tindakan untuk pasiennya | Daftar kunjungan baru dan lihat riwayat sendiri |
| Laporan | Semua laporan | Laporan pasien sendiri | - |

### 3.2 Mekanisme Autentikasi

1. Server A mengirim `username` dan `password` ke endpoint login di Server B.
2. Server B memverifikasi dengan `password_verify()` (password disimpan dengan `password_hash()`), lalu membuat **token acak** (`bin2hex(random_bytes(32))`), menyimpannya di tabel `token_akses` dengan masa berlaku (contoh 2 jam), dan mengembalikan token, role, dan data pengguna.
3. Server A menyimpan token, role, id pengguna, dan nama di `$_SESSION`.
4. Setiap request berikutnya dari Server A membawa header `Authorization: Bearer <token>`.
5. Server B memeriksa token (valid, belum kedaluwarsa) dan memeriksa **role** untuk tiap endpoint. Hasil gagal: HTTP 401 (belum login) atau 403 (role tidak berhak).
6. Server A juga memeriksa role di setiap halaman (menyembunyikan menu dan memblokir akses URL langsung). Pemeriksaan di Server B tetap yang utama.

## 4. Rancangan Database (Soal 2)

Database: `db_rumah_sakit`, engine InnoDB, charset `utf8mb4`.

### 4.1 Tabel dan Relasi

- `poliklinik` (1) ke (N) `dokter`
- `asuransi` (1) ke (N) `pasien` (kolom `id_asuransi` boleh NULL bila pasien umum)
- `pengguna` (1) ke (0..1) `dokter`, dan (1) ke (0..1) `pasien`
- `pasien` (1) ke (N) `kunjungan`; `dokter` (1) ke (N) `kunjungan`; `poliklinik` (1) ke (N) `kunjungan`
- `pengguna` (1) ke (N) `token_akses`

### 4.2 Skrip SQL

```sql
CREATE DATABASE IF NOT EXISTS db_rumah_sakit CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_rumah_sakit;

CREATE TABLE poliklinik (
  id_poli INT AUTO_INCREMENT PRIMARY KEY,
  nama_poli VARCHAR(100) NOT NULL,
  lokasi VARCHAR(100),
  keterangan TEXT
) ENGINE=InnoDB;

CREATE TABLE asuransi (
  id_asuransi INT AUTO_INCREMENT PRIMARY KEY,
  nama_asuransi VARCHAR(100) NOT NULL,
  jenis VARCHAR(50),
  no_kontak VARCHAR(20),
  keterangan TEXT
) ENGINE=InnoDB;

CREATE TABLE pengguna (
  id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role ENUM('admin','dokter','pasien') NOT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE dokter (
  id_dokter INT AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT NULL UNIQUE,
  id_poli INT NOT NULL,
  nama_dokter VARCHAR(100) NOT NULL,
  spesialisasi VARCHAR(100),
  no_str VARCHAR(50),
  no_hp VARCHAR(20),
  FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL,
  FOREIGN KEY (id_poli) REFERENCES poliklinik(id_poli)
) ENGINE=InnoDB;

CREATE TABLE pasien (
  id_pasien INT AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT NULL UNIQUE,
  id_asuransi INT NULL,
  no_rm VARCHAR(20) NOT NULL UNIQUE,
  nik VARCHAR(20),
  nama_pasien VARCHAR(100) NOT NULL,
  jenis_kelamin ENUM('L','P') NOT NULL,
  tanggal_lahir DATE,
  alamat TEXT,
  no_hp VARCHAR(20),
  no_kartu_asuransi VARCHAR(50),
  FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL,
  FOREIGN KEY (id_asuransi) REFERENCES asuransi(id_asuransi) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE kunjungan (
  id_kunjungan INT AUTO_INCREMENT PRIMARY KEY,
  id_pasien INT NOT NULL,
  id_dokter INT NOT NULL,
  id_poli INT NOT NULL,
  tanggal_kunjungan DATE NOT NULL,
  keluhan TEXT,
  diagnosis TEXT,
  tindakan TEXT,
  status ENUM('menunggu','diperiksa','selesai','batal') NOT NULL DEFAULT 'menunggu',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_pasien) REFERENCES pasien(id_pasien) ON DELETE CASCADE,
  FOREIGN KEY (id_dokter) REFERENCES dokter(id_dokter),
  FOREIGN KEY (id_poli) REFERENCES poliklinik(id_poli)
) ENGINE=InnoDB;

CREATE TABLE token_akses (
  id_token INT AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expired_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE CASCADE
) ENGINE=InnoDB;
```

### 4.3 Data Awal (seed)

AI wajib membuatkan `INSERT` contoh: minimal 4 poliklinik (Umum, Gigi, Anak, Penyakit Dalam), 3 asuransi (BPJS Kesehatan, Prudential, Mandiri Inhealth), 1 akun admin (`admin` / `admin123`), 3 dokter beserta akun, 5 pasien (sebagian dengan akun, sebagian dengan asuransi, sebagian umum), dan sekitar 10 kunjungan. **Password di seed harus berupa hash** hasil `password_hash()` (buat lewat skrip PHP kecil `generate_hash.php`, jangan teks biasa).

## 5. Spesifikasi Web Service (Soal 3, Server B)

### 5.1 Struktur Folder `rs_api`

```
rs_api/
  config/database.php      (koneksi MySQLi)
  helpers/response.php     (fungsi kirim JSON)
  helpers/auth.php         (cek token dan role)
  auth/login.php
  auth/logout.php
  poliklinik/  (index, detail, tambah, ubah, hapus)
  dokter/      (idem)
  pasien/      (idem)
  asuransi/    (idem)
  pengguna/    (idem)
  kunjungan/   (idem)
  laporan/     (file per laporan)
  index.php    (info API)
```

Boleh juga memakai satu file per resource dengan routing berdasarkan metode HTTP (GET, POST, PUT, DELETE), asalkan konsisten dan terdokumentasi.

### 5.2 Format Respons Standar

Sukses:

```json
{ "status": true, "message": "Data berhasil diambil", "data": [ ... ] }
```

Gagal:

```json
{ "status": false, "message": "Penjelasan error", "data": null }
```

Header wajib: `Content-Type: application/json; charset=utf-8`. Aktifkan CORS sederhana (`Access-Control-Allow-Origin: *`) dan tangani `OPTIONS`. Kode HTTP: 200, 201, 400, 401, 403, 404, 405, 500.

### 5.3 Daftar Endpoint

Base URL: `http://192.168.1.10/rs_api`

| Resource | Metode dan Path | Fungsi | Role |
|---|---|---|---|
| Auth | POST `/auth/login.php` | Login, kembalikan token dan role | publik |
| Auth | POST `/auth/logout.php` | Hapus token | semua |
| Poliklinik | GET `/poliklinik` | Daftar | semua |
| Poliklinik | GET `/poliklinik?id=` | Detail | semua |
| Poliklinik | POST / PUT / DELETE | Tambah / ubah / hapus | admin |
| Asuransi | GET (daftar, detail) | Baca | semua |
| Asuransi | POST / PUT / DELETE | Tulis | admin |
| Dokter | GET (daftar, detail) | Baca, sertakan nama poli | semua |
| Dokter | POST / DELETE | Tambah / hapus | admin |
| Dokter | PUT | Ubah | admin, dokter (profil sendiri) |
| Pasien | GET daftar | Daftar pasien beserta asuransi | admin, dokter |
| Pasien | GET detail / PUT | Lihat / ubah | admin, pasien (data sendiri), dokter (lihat) |
| Pasien | POST / DELETE | Tambah / hapus | admin |
| Pengguna | GET, POST, PUT, DELETE | Kelola akun, reset password | admin |
| Kunjungan | GET | Daftar sesuai role (admin: semua, dokter: miliknya, pasien: miliknya) | semua |
| Kunjungan | POST | Buat kunjungan | admin, pasien |
| Kunjungan | PUT | Ubah data; dokter mengisi diagnosis, tindakan, status | admin, dokter |
| Kunjungan | DELETE | Hapus | admin |
| Laporan | GET `/laporan/pasien_per_poli.php` | Jumlah pasien per poliklinik | admin |
| Laporan | GET `/laporan/pasien_asuransi.php` | Daftar pasien beserta asuransi | admin |
| Laporan | GET `/laporan/kunjungan_periode.php?dari=&sampai=` | Kunjungan per rentang tanggal | admin, dokter |
| Laporan | GET `/laporan/kunjungan_per_dokter.php` | Jumlah kunjungan per dokter | admin |
| Laporan | GET `/laporan/riwayat_pasien.php?id_pasien=` | Riwayat kunjungan satu pasien | admin, dokter, pasien (sendiri) |
| Laporan | GET `/laporan/statistik_dashboard.php` | Total pasien, dokter, poli, kunjungan hari ini | semua (isi sesuai role) |

Ketentuan teknis web service:

- Gunakan **prepared statements** MySQLi (`bind_param`) di semua query. Dilarang menggabungkan input langsung ke SQL.
- Validasi input: field wajib, tipe data, format tanggal, username unik.
- Hapus data yang masih dipakai relasi (misalnya poliklinik yang punya dokter) harus mengembalikan pesan error yang jelas, bukan error SQL mentah.
- Saat membuat dokter atau pasien baru, admin boleh sekaligus membuat akun pengguna (opsional), dalam satu transaksi.
- Jangan pernah mengembalikan kolom `password` pada respons.
- Token kedaluwarsa dibersihkan saat login.

## 6. Spesifikasi UI (Soal 4, Server A)

### 6.1 Struktur Folder `rs_client`

```
rs_client/
  config/config.php        (BASE_API = 'http://192.168.1.10/rs_api')
  helpers/api.php          (fungsi cURL: GET, POST, PUT, DELETE + header Bearer)
  helpers/auth.php         (cek session dan role, redirect)
  includes/header.php, sidebar.php, footer.php
  assets/css/, assets/js/
  index.php                (landing page)
  login.php, logout.php
  dashboard.php
  modules/
    poliklinik/ dokter/ pasien/ asuransi/ pengguna/ kunjungan/ laporan/
```

### 6.2 Halaman dan Fitur

1. **Landing page** (`index.php`): profil singkat rumah sakit, daftar poliklinik (diambil dari API), daftar dokter, tombol Login. Bisa diakses tanpa login.
2. **Login** (`login.php`): form username dan password, tampilkan pesan error dari API, simpan session, arahkan ke dashboard.
3. **Dashboard** (`dashboard.php`): kartu statistik dan menu sidebar yang berbeda per role (lihat matriks RBAC). Boleh memakai grafik sederhana (Chart.js dari CDN).
4. **Modul CRUD** untuk setiap resource: tabel daftar dengan pencarian, form tambah, form ubah, tombol hapus dengan konfirmasi, dan pesan sukses/gagal. Tombol dan menu yang tidak berhak **tidak ditampilkan**, dan akses URL langsung oleh role yang salah diarahkan ke halaman "Akses ditolak".
5. **Modul Kunjungan**: pasien mendaftar kunjungan (pilih poli, dokter, tanggal, keluhan); dokter melihat antrean dan mengisi diagnosis dan tindakan; admin mengelola semua.
6. **Modul Laporan**: tampilkan hasil laporan dalam tabel, dengan filter tanggal, dan tombol cetak (`window.print()`).
7. **Logout**: panggil API logout, hancurkan session.

### 6.3 Ketentuan UI

- PHP native, sesi dengan `session_start()`. Jangan menyimpan password di session.
- Gunakan Bootstrap 5 dari CDN agar tampilan rapi dan responsif. Jika komputer tidak ada internet, simpan file Bootstrap lokal di `assets/`.
- Semua output dari API di-escape dengan `htmlspecialchars()` untuk mencegah XSS.
- Jika token ditolak API (401), hancurkan session dan arahkan ke login.
- Satu fungsi pemanggil API terpusat di `helpers/api.php`, tidak menulis cURL berulang di setiap halaman.

## 7. Kebutuhan Non-Fungsional

- **Keamanan:** password di-hash, prepared statements, validasi input, pembatasan role di server dan UI, token berkedaluwarsa.
- **Kompatibilitas:** XAMPP versi terbaru (PHP 8.x, MariaDB), Windows.
- **Keterbacaan kode:** nama variabel jelas, komentar Indonesia, struktur folder konsisten, kode mudah dijelaskan saat ditanya dosen.
- **Ketahanan:** error koneksi ke Server B ditangani dengan pesan yang ramah (misalnya "Server layanan tidak dapat dihubungi").

## 8. Rencana Pengerjaan (Urutan Wajib)

| Tahap | Pekerjaan | Hasil |
|---|---|---|
| 1 | Verifikasi jaringan, ping dua arah, Apache aktif | Bahan Soal 1 |
| 2 | Buat database, tabel, dan seed | SQL untuk Soal 2 |
| 3 | Buat `config`, `helpers`, lalu endpoint login dan logout | Login API berfungsi (uji Postman) |
| 4 | CRUD poliklinik, asuransi, pengguna, dokter, pasien, kunjungan | Soal 3 |
| 5 | Endpoint laporan dan statistik dashboard | Soal 3 |
| 6 | Server A: config, helper cURL, landing page, login, dashboard | Soal 4 |
| 7 | Server A: modul CRUD dan laporan dengan RBAC | Soal 4 |
| 8 | Pengujian menyeluruh dan perbaikan bug | Aplikasi final |
| 9 | Screenshot, rangkum source code, susun laporan | Laporan siap unggah |

## 9. Kriteria Penerimaan dan Pengujian

Aplikasi dianggap selesai bila semua poin berikut lolos:

- [ ] Server A dapat membuka halaman Server B lewat browser dan cURL.
- [ ] Login benar menghasilkan token; login salah ditolak dengan pesan jelas.
- [ ] Akses endpoint tanpa token mengembalikan 401; role yang salah mengembalikan 403.
- [ ] CRUD berfungsi untuk poliklinik, asuransi, pengguna, dokter, pasien, dan kunjungan.
- [ ] Pasien hanya bisa melihat data dan kunjungan miliknya sendiri.
- [ ] Dokter hanya bisa melihat dan mengisi kunjungan pasiennya.
- [ ] Menu sidebar berbeda untuk admin, dokter, dan pasien.
- [ ] Semua laporan menampilkan data yang benar.
- [ ] Tidak ada query yang menggabungkan input langsung; password tidak muncul di respons API.
- [ ] Aplikasi tetap berjalan setelah browser ditutup dan dibuka kembali (session baru, login ulang).

Skenario uji minimal: login sebagai admin, dokter, dan pasien; tambah, ubah, hapus data; coba buka URL admin sebagai pasien (harus ditolak); hapus poliklinik yang masih punya dokter (harus gagal dengan pesan jelas).

## 10. Panduan Penyusunan Laporan

Struktur laporan (dokumen Word/PDF), diawali **NPM, Nama, Kelas** di bagian atas:

- **Soal 1:** tabel IP dan folder Server A dan B, screenshot `ipconfig`, `ping`, folder `htdocs`, XAMPP Control Panel.
- **Soal 2:** penjelasan singkat rancangan, ERD, dan kode SQL lengkap (Bagian 4.2 beserta seed).
- **Soal 3:** daftar endpoint, source code semua file web service per file (nama file lalu kode), contoh hasil uji Postman.
- **Soal 4:** source code semua file UI per file, screenshot tiap halaman untuk tiap role (landing, login, dashboard, tiap modul, laporan).

## 11. Perintah Awal untuk AI Lain

Salin dan tempel pesan berikut bersama dokumen ini:

> Berdasarkan PRD di atas, mulai dari **Tahap 2**: buatkan skrip SQL lengkap (struktur tabel dan data awal dengan password ter-hash) untuk `db_rumah_sakit`, beserta skrip `generate_hash.php`. Setelah itu saya akan meminta tahap berikutnya satu per satu. Server A: 192.168.1.2 (`rs_client`), Server B: 192.168.1.10 (`rs_api`), PHP native + MySQLi.

## 12. Status Saat Ini

- Server A: 192.168.1.2, Server B: 192.168.1.10 (sudah ditetapkan).
- Tahap 1 (jaringan) sedang disiapkan oleh mahasiswa; ping dua arah perlu dikonfirmasi.
- Tahap 2 sampai 9 belum dikerjakan.
