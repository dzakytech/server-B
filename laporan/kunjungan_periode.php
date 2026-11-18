<?php
/**
 * Laporan: Kunjungan Per Periode Tanggal
 * Role: Admin, Dokter
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$auth_user = require_auth(['admin', 'dokter']);

$dari   = trim($_GET['dari'] ?? date('Y-m-01')); // Default awal bulan ini
$sampai = trim($_GET['sampai'] ?? date('Y-m-d')); // Default hari ini

$sql = "
    SELECT k.*, 
           ps.nama_pasien, ps.no_rm, ps.jenis_kelamin,
           COALESCE(a.nama_asuransi, 'Umum') AS nama_asuransi,
           d.nama_dokter, d.spesialisasi,
           p.nama_poli
    FROM kunjungan k
    JOIN pasien ps ON k.id_pasien = ps.id_pasien
    LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
    JOIN dokter d ON k.id_dokter = d.id_dokter
    JOIN poliklinik p ON k.id_poli = p.id_poli
    WHERE k.tanggal_kunjungan BETWEEN ? AND ?
";

if ($auth_user['role'] === 'dokter') {
    $id_dok = (int)($auth_user['id_dokter'] ?? 0);
    $sql .= " AND k.id_dokter = $id_dok ";
}

$sql .= " ORDER BY k.tanggal_kunjungan ASC, k.id_kunjungan ASC ";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $dari, $sampai);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

send_json(true, "Laporan kunjungan periode $dari s/d $sampai berhasil diambil.", [
    'periode' => ['dari' => $dari, 'sampai' => $sampai],
    'total'   => count($data),
    'list'    => $data
]);
