<?php
/**
 * Laporan: Statistik Dashboard (Disesuaikan berdasarkan Role Pengguna)
 * Role: Admin, Dokter, Pasien
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$auth_user = require_auth(['admin', 'dokter', 'pasien']);

$role = $auth_user['role'];
$today = date('Y-m-d');
$stats = [];

if ($role === 'admin') {
    // Total Master Data
    $stats['total_pasien']   = (int)$conn->query("SELECT COUNT(*) AS c FROM pasien")->fetch_assoc()['c'];
    $stats['total_dokter']   = (int)$conn->query("SELECT COUNT(*) AS c FROM dokter")->fetch_assoc()['c'];
    $stats['total_poli']     = (int)$conn->query("SELECT COUNT(*) AS c FROM poliklinik")->fetch_assoc()['c'];
    $stats['total_asuransi'] = (int)$conn->query("SELECT COUNT(*) AS c FROM asuransi")->fetch_assoc()['c'];
    $stats['total_pengguna'] = (int)$conn->query("SELECT COUNT(*) AS c FROM pengguna")->fetch_assoc()['c'];

    // Kunjungan Hari Ini & Total
    $stats['kunjungan_hari_ini'] = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE tanggal_kunjungan = '$today'")->fetch_assoc()['c'];
    $stats['total_kunjungan']    = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan")->fetch_assoc()['c'];

    // Breakdown Status Kunjungan
    $status_res = $conn->query("SELECT status, COUNT(*) as c FROM kunjungan GROUP BY status");
    $by_status = ['menunggu' => 0, 'diperiksa' => 0, 'selesai' => 0, 'batal' => 0];
    while ($row = $status_res->fetch_assoc()) {
        $by_status[$row['status']] = (int)$row['c'];
    }
    $stats['status_kunjungan'] = $by_status;

    // Grafik 7 Hari Terakhir
    $grafik_res = $conn->query("
        SELECT tanggal_kunjungan, COUNT(*) as jumlah 
        FROM kunjungan 
        WHERE tanggal_kunjungan >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY tanggal_kunjungan 
        ORDER BY tanggal_kunjungan ASC
    ");
    $grafik = [];
    while ($row = $grafik_res->fetch_assoc()) {
        $grafik[] = $row;
    }
    $stats['grafik_kunjungan'] = $grafik;

    // Kunjungan Terbaru
    $recent_res = $conn->query("
        SELECT k.*, ps.nama_pasien, ps.no_rm, d.nama_dokter, p.nama_poli 
        FROM kunjungan k
        JOIN pasien ps ON k.id_pasien = ps.id_pasien
        JOIN dokter d ON k.id_dokter = d.id_dokter
        JOIN poliklinik p ON k.id_poli = p.id_poli
        ORDER BY k.id_kunjungan DESC LIMIT 5
    ");
    $recent = [];
    while ($row = $recent_res->fetch_assoc()) {
        $recent[] = $row;
    }
    $stats['kunjungan_terbaru'] = $recent;

} elseif ($role === 'dokter') {
    $id_dokter = (int)($auth_user['id_dokter'] ?? 0);

    $stats['id_dokter'] = $id_dokter;
    $stats['pasien_ditangani'] = (int)$conn->query("SELECT COUNT(DISTINCT id_pasien) AS c FROM kunjungan WHERE id_dokter = $id_dokter")->fetch_assoc()['c'];
    $stats['total_kunjungan']  = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_dokter = $id_dokter")->fetch_assoc()['c'];
    $stats['kunjungan_hari_ini'] = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_dokter = $id_dokter AND tanggal_kunjungan = '$today'")->fetch_assoc()['c'];
    $stats['kunjungan_menunggu'] = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_dokter = $id_dokter AND status = 'menunggu'")->fetch_assoc()['c'];
    $stats['kunjungan_selesai']  = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_dokter = $id_dokter AND status = 'selesai'")->fetch_assoc()['c'];

    // Antrean Hari Ini
    $antrean_res = $conn->query("
        SELECT k.*, ps.nama_pasien, ps.no_rm, ps.jenis_kelamin, p.nama_poli 
        FROM kunjungan k
        JOIN pasien ps ON k.id_pasien = ps.id_pasien
        JOIN poliklinik p ON k.id_poli = p.id_poli
        WHERE k.id_dokter = $id_dokter
        ORDER BY (k.status = 'menunggu') DESC, k.id_kunjungan DESC LIMIT 8
    ");
    $antrean = [];
    while ($row = $antrean_res->fetch_assoc()) {
        $antrean[] = $row;
    }
    $stats['antrean_pasien'] = $antrean;

} elseif ($role === 'pasien') {
    $id_pasien = (int)($auth_user['id_pasien'] ?? 0);

    $stats['id_pasien'] = $id_pasien;
    $stats['total_kunjungan'] = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_pasien = $id_pasien")->fetch_assoc()['c'];
    $stats['kunjungan_selesai'] = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_pasien = $id_pasien AND status = 'selesai'")->fetch_assoc()['c'];
    $stats['kunjungan_aktif'] = (int)$conn->query("SELECT COUNT(*) AS c FROM kunjungan WHERE id_pasien = $id_pasien AND status IN ('menunggu','diperiksa')")->fetch_assoc()['c'];

    // Info Pasien
    $p_info = $conn->query("SELECT ps.*, a.nama_asuransi FROM pasien ps LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi WHERE ps.id_pasien = $id_pasien")->fetch_assoc();
    $stats['profil_pasien'] = $p_info;

    // Riwayat Terakhir
    $hist_res = $conn->query("
        SELECT k.*, d.nama_dokter, p.nama_poli 
        FROM kunjungan k
        JOIN dokter d ON k.id_dokter = d.id_dokter
        JOIN poliklinik p ON k.id_poli = p.id_poli
        WHERE k.id_pasien = $id_pasien
        ORDER BY k.id_kunjungan DESC LIMIT 5
    ");
    $hist = [];
    while ($row = $hist_res->fetch_assoc()) {
        $hist[] = $row;
    }
    $stats['riwayat_terakhir'] = $hist;
}

send_json(true, 'Statistik dashboard berhasil dimuat.', $stats);
