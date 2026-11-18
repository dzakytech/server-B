-- ==========================================================
-- SISTEM INFORMASI RUMAH SAKIT BERBASIS SOA (SERVICE ORIENTED ARCHITECTURE)
-- Database : db_rumah_sakit
-- Server B (API & Database Server)
-- ==========================================================

CREATE DATABASE IF NOT EXISTS db_rumah_sakit CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_rumah_sakit;

-- 1. Tabel Poliklinik
DROP TABLE IF EXISTS token_akses;
DROP TABLE IF EXISTS kunjungan;
DROP TABLE IF EXISTS pasien;
DROP TABLE IF EXISTS dokter;
DROP TABLE IF EXISTS pengguna;
DROP TABLE IF EXISTS asuransi;
DROP TABLE IF EXISTS poliklinik;

CREATE TABLE poliklinik (
  id_poli INT AUTO_INCREMENT PRIMARY KEY,
  nama_poli VARCHAR(100) NOT NULL,
  lokasi VARCHAR(100),
  keterangan TEXT
) ENGINE=InnoDB;

-- 2. Tabel Asuransi
CREATE TABLE asuransi (
  id_asuransi INT AUTO_INCREMENT PRIMARY KEY,
  nama_asuransi VARCHAR(100) NOT NULL,
  jenis VARCHAR(50),
  no_kontak VARCHAR(20),
  keterangan TEXT
) ENGINE=InnoDB;

-- 3. Tabel Pengguna (RBAC: admin, dokter, pasien)
CREATE TABLE pengguna (
  id_pengguna INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nama_lengkap VARCHAR(100) NOT NULL,
  role ENUM('admin','dokter','pasien') NOT NULL,
  status ENUM('aktif','nonaktif') NOT NULL DEFAULT 'aktif',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 4. Tabel Dokter (Relasi ke pengguna dan poliklinik)
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

-- 5. Tabel Pasien (Relasi ke pengguna dan asuransi)
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

-- 6. Tabel Kunjungan (Pendaftaran, Rekam Medis & Diagnosis)
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

-- 7. Tabel Token Akses (Autentikasi Bearer Token SOA)
CREATE TABLE token_akses (
  id_token INT AUTO_INCREMENT PRIMARY KEY,
  id_pengguna INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  expired_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==========================================================
-- DATA AWAL (SEEDING)
-- Default Password:
-- admin   : admin123  ($2y$10$dmkq0MEKoPUzqvVT6xauCu3efu5Fkd8uZ/OR2BtcDzDUlnFmcDqPO)
-- dokter  : dokter123 ($2y$10$jm94XIX7ujxrcHqnuh.QteDZOHYMcH9L.NxHMY57d0H.9YegCJWV2)
-- pasien  : pasien123 ($2y$10$Zk0rvd2HyjHYM3.Q3PDIuupT0Zau7aUiJKcxzkWc7HoTnsMcqmTpi)
-- ==========================================================

-- Seed Poliklinik
INSERT INTO poliklinik (id_poli, nama_poli, lokasi, keterangan) VALUES
(1, 'Poli Penyakit Dalam', 'Gedung A Lantai 1', 'Pelayanan diagnosis & terapi organ internal dewasa'),
(2, 'Poli Gigi & Mulut', 'Gedung A Lantai 2', 'Pelayanan kesehatan gigi, scaling, penambalan dan bedah mulut'),
(3, 'Poli Anak (Pediatri)', 'Gedung B Lantai 1', 'Konsultasi tumbuh kembang anak, imunisasi, dan pengobatan spesifik'),
(4, 'Poli Umum', 'Gedung A Lantai 1', 'Pemeriksaan kesehatan dasar dan rujukan lanjutan');

-- Seed Asuransi
INSERT INTO asuransi (id_asuransi, nama_asuransi, jenis, no_kontak, keterangan) VALUES
(1, 'BPJS Kesehatan', 'Pemerintah', '165', 'Jaminan Kesehatan Nasional kelas 1, 2, 3'),
(2, 'Prudential Life Assurance', 'Swasta', '1500085', 'Klaim cashless dan reimbursement rawat jalan/inap'),
(3, 'Mandiri Inhealth', 'Swasta', '021-5250900', 'Asuransi kesehatan korporasi & individu');

-- Seed Pengguna
INSERT INTO pengguna (id_pengguna, username, password, nama_lengkap, role, status) VALUES
-- 1 Admin
(1, 'admin', '$2y$10$dmkq0MEKoPUzqvVT6xauCu3efu5Fkd8uZ/OR2BtcDzDUlnFmcDqPO', 'Administrator Utama', 'admin', 'aktif'),
-- 3 Dokter
(2, 'dr_andi', '$2y$10$jm94XIX7ujxrcHqnuh.QteDZOHYMcH9L.NxHMY57d0H.9YegCJWV2', 'dr. Andi Wijaya, Sp.PD', 'dokter', 'aktif'),
(3, 'dr_siti', '$2y$10$jm94XIX7ujxrcHqnuh.QteDZOHYMcH9L.NxHMY57d0H.9YegCJWV2', 'drg. Siti Aminah, Sp.KG', 'dokter', 'aktif'),
(4, 'dr_budi', '$2y$10$jm94XIX7ujxrcHqnuh.QteDZOHYMcH9L.NxHMY57d0H.9YegCJWV2', 'dr. Budi Santoso, Sp.A', 'dokter', 'aktif'),
-- 3 Pasien dengan akun
(5, 'ahmad', '$2y$10$Zk0rvd2HyjHYM3.Q3PDIuupT0Zau7aUiJKcxzkWc7HoTnsMcqmTpi', 'Ahmad Fauzi', 'pasien', 'aktif'),
(6, 'dewi', '$2y$10$Zk0rvd2HyjHYM3.Q3PDIuupT0Zau7aUiJKcxzkWc7HoTnsMcqmTpi', 'Dewi Sartika', 'pasien', 'aktif'),
(7, 'rian', '$2y$10$Zk0rvd2HyjHYM3.Q3PDIuupT0Zau7aUiJKcxzkWc7HoTnsMcqmTpi', 'Rian Pratama', 'pasien', 'aktif');

-- Seed Dokter
INSERT INTO dokter (id_dokter, id_pengguna, id_poli, nama_dokter, spesialisasi, no_str, no_hp) VALUES
(1, 2, 1, 'dr. Andi Wijaya, Sp.PD', 'Spesialis Penyakit Dalam', 'STR-PD-2023-001', '081234567801'),
(2, 3, 2, 'drg. Siti Aminah, Sp.KG', 'Spesialis Konservasi Gigi', 'STR-KG-2022-045', '081234567802'),
(3, 4, 3, 'dr. Budi Santoso, Sp.A', 'Spesialis Anak', 'STR-AN-2021-089', '081234567803');

-- Seed Pasien (5 Pasien: 3 berakun pengguna, 1 ber-asuransi tanpa akun, 1 umum tanpa akun)
INSERT INTO pasien (id_pasien, id_pengguna, id_asuransi, no_rm, nik, nama_pasien, jenis_kelamin, tanggal_lahir, alamat, no_hp, no_kartu_asuransi) VALUES
(1, 5, 1, 'RM-2026-0001', '3201011204900001', 'Ahmad Fauzi', 'L', '1990-04-12', 'Jl. Merdeka No. 45, Jakarta', '081311112222', '0001234567890'),
(2, 6, 2, 'RM-2026-0002', '3201015508950002', 'Dewi Sartika', 'P', '1995-08-15', 'Jl. Sudirman No. 12, Bandung', '081322223333', 'PRU-99887766'),
(3, 7, 3, 'RM-2026-0003', '3201012301880003', 'Rian Pratama', 'L', '1988-01-23', 'Jl. Diponegoro No. 88, Bogor', '081333334444', 'INH-55443322'),
(4, NULL, NULL, 'RM-2026-0004', '3201016711920004', 'Ratna Sari', 'P', '1992-11-27', 'Jl. Melati No. 10, Depok', '081344445555', NULL),
(5, NULL, 1, 'RM-2026-0005', '3201011006850005', 'Bambang Pamungkas', 'L', '1985-06-10', 'Jl. Gatot Subroto No. 04, Bekasi', '081355556666', '0009876543210');

-- Seed Kunjungan (10 Kunjungan sampel rekam medis & status)
INSERT INTO kunjungan (id_kunjungan, id_pasien, id_dokter, id_poli, tanggal_kunjungan, keluhan, diagnosis, tindakan, status) VALUES
(1, 1, 1, 1, '2026-11-10', 'Nyeri lambung akut dan mual selama 3 hari', 'Gastritis Akut Erosif', 'Resep Omeprazole 20mg 2x1, Antasida syrup 3x1', 'selesai'),
(2, 2, 2, 2, '2026-11-12', 'Gigi geraham bawah kiri ngilu saat minum dingin', 'Pulpitis Reversibel Gigi 36', 'Pembersihan kavitas, penambalan komposit sinar', 'selesai'),
(3, 3, 1, 1, '2026-11-14', 'Tekanan darah tinggi dan pusing di tengkuk', 'Hipertensi Grade 1', 'Edukasi diet rendah garam, Amlodipine 5mg 1x1', 'selesai'),
(4, 4, 1, 1, '2026-11-15', 'Demam 39C hari ke-3, menggigil dan lemas', 'Observasi Febris ec Suspect DHF', 'Cek darah lengkap, infus RL 20 tpm, Paracetamol drip', 'selesai'),
(5, 5, 2, 2, '2026-11-16', 'Karang gigi menumpuk dan gusi berdarah', 'Gingivitis Marginalis Kronis', 'Scaling supra dan subgingiva rahang atas dan bawah', 'selesai'),
(6, 1, 1, 1, '2026-11-17', 'Kontrol rutin lambung setelah terapi 1 minggu', 'Gastritis dalam perbaikan', 'Lanjutkan Omeprazole 1x1 pagi sebelum makan', 'selesai'),
(7, 2, 2, 2, '2026-11-18', 'Pemeriksaan rutin gigi 6 bulanan', 'Karies superfisial gigi 24', 'Aplikasi fluorida dan penambalan komposit', 'diperiksa'),
(8, 3, 1, 1, '2026-11-18', 'Kontrol tensi bulanan dan cek lab kolesterol', 'Hipertensi terkontrol', 'Cek laboratorium profil lipid, resep rutin', 'diperiksa'),
(9, 1, 1, 1, '2026-11-18', 'Pusing berputar dan mual pagi hari', 'Vertigo Perifer', 'Pemeriksaan fisik nistagmus, Betahistine 6mg 3x1', 'menunggu'),
(10, 4, 3, 3, '2026-11-18', 'Anak batuk pilek 2 hari dan nafsu makan turun', NULL, NULL, 'menunggu');
