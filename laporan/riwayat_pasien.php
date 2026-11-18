<?php
/**
 * Laporan: Riwayat Rekam Medis & Kunjungan Pasien
 * Role: Admin, Dokter, Pasien (milik sendiri)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$auth_user = require_auth(['admin', 'dokter', 'pasien']);

$id_pasien = isset($_GET['id_pasien']) ? (int)$_GET['id_pasien'] : 0;

if ($auth_user['role'] === 'pasien') {
    $id_pasien = (int)($auth_user['id_pasien'] ?? 0);
}

if (!$id_pasien) {
    send_json(false, 'Parameter id_pasien wajib disertakan.', null, 400);
}

// Ambil info pasien
$stmt_p = $conn->prepare("
    SELECT ps.*, COALESCE(a.nama_asuransi, 'Umum') AS nama_asuransi 
    FROM pasien ps
    LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
    WHERE ps.id_pasien = ?
");
$stmt_p->bind_param("i", $id_pasien);
$stmt_p->execute();
$pasien_res = $stmt_p->get_result();

if ($pasien_res->num_rows === 0) {
    send_json(false, 'Data pasien tidak ditemukan.', null, 404);
}
$pasien = $pasien_res->fetch_assoc();

// Ambil riwayat kunjungan
$stmt_k = $conn->prepare("
    SELECT k.*, d.nama_dokter, d.spesialisasi, p.nama_poli, p.lokasi AS lokasi_poli
    FROM kunjungan k
    JOIN dokter d ON k.id_dokter = d.id_dokter
    JOIN poliklinik p ON k.id_poli = p.id_poli
    WHERE k.id_pasien = ?
    ORDER BY k.tanggal_kunjungan DESC, k.id_kunjungan DESC
");
$stmt_k->bind_param("i", $id_pasien);
$stmt_k->execute();
$res_k = $stmt_k->get_result();

$riwayat = [];
while ($row = $res_k->fetch_assoc()) {
    $riwayat[] = $row;
}

send_json(true, 'Riwayat kunjungan pasien berhasil diambil.', [
    'pasien'  => $pasien,
    'riwayat' => $riwayat
]);
