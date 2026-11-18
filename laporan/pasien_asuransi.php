<?php
/**
 * Laporan: Daftar Pasien Berdasarkan Asuransi
 * Role: Admin
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

require_auth(['admin']);

$id_asuransi = isset($_GET['id_asuransi']) ? (int)$_GET['id_asuransi'] : null;

$sql = "
    SELECT ps.id_pasien, ps.no_rm, ps.nik, ps.nama_pasien, ps.jenis_kelamin, 
           ps.tanggal_lahir, ps.no_hp, ps.no_kartu_asuransi,
           COALESCE(a.nama_asuransi, 'Umum / Mandiri') AS nama_asuransi,
           COALESCE(a.jenis, 'Non-Asuransi') AS jenis_asuransi
    FROM pasien ps
    LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
";

if ($id_asuransi !== null) {
    if ($id_asuransi === 0) {
        $sql .= " WHERE ps.id_asuransi IS NULL ";
    } else {
        $sql .= " WHERE ps.id_asuransi = " . (int)$id_asuransi;
    }
}

$sql .= " ORDER BY ps.id_asuransi DESC, ps.nama_pasien ASC ";

$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

send_json(true, 'Laporan pasien asuransi berhasil diambil.', $data);
