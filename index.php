<?php
/**
 * Root Info Endpoint Web Service
 * Server B (API & Database)
 */

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status'      => true,
    'message'     => 'API Sistem Informasi Rumah Sakit Berbasis SOA Berjalan Normal.',
    'server'      => 'Server B (Database & Web Service)',
    'ip_default'  => '192.168.1.10',
    'base_url'    => 'http://192.168.1.10/rs_api/',
    'version'     => '1.0.0',
    'endpoints'   => [
        'POST /auth/login.php'                     => 'Login & mendapatkan Bearer Token',
        'POST /auth/logout.php'                    => 'Logout & revoke token',
        'GET|POST|PUT|DELETE /poliklinik/index.php'=> 'CRUD Data Poliklinik',
        'GET|POST|PUT|DELETE /asuransi/index.php'  => 'CRUD Data Asuransi',
        'GET|POST|PUT|DELETE /dokter/index.php'    => 'CRUD Data Dokter & Akun',
        'GET|POST|PUT|DELETE /pasien/index.php'    => 'CRUD Data Pasien & Akun',
        'GET|POST|PUT|DELETE /pengguna/index.php'  => 'CRUD Data Pengguna / RBAC',
        'GET|POST|PUT|DELETE /kunjungan/index.php' => 'Pendaftaran Kunjungan & Rekam Medis',
        'GET /laporan/statistik_dashboard.php'     => 'Statistik dashboard per role',
        'GET /laporan/pasien_per_poli.php'         => 'Laporan pasien per poli',
        'GET /laporan/pasien_asuransi.php'         => 'Laporan pasien asuransi',
        'GET /laporan/kunjungan_periode.php'       => 'Laporan kunjungan per tanggal',
        'GET /laporan/kunjungan_per_dokter.php'    => 'Laporan kunjungan per dokter',
        'GET /laporan/riwayat_pasien.php'          => 'Riwayat rekam medis pasien'
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
