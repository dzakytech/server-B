<?php
/**
 * Laporan: Jumlah Pasien Per Poliklinik
 * Role: Admin
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

require_auth(['admin']);

$sql = "
    SELECT p.id_poli, p.nama_poli, p.lokasi,
           COUNT(DISTINCT k.id_pasien) AS total_pasien_unik,
           COUNT(k.id_kunjungan) AS total_kunjungan
    FROM poliklinik p
    LEFT JOIN kunjungan k ON p.id_poli = k.id_poli
    GROUP BY p.id_poli
    ORDER BY total_kunjungan DESC, p.nama_poli ASC
";

$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

send_json(true, 'Laporan jumlah pasien per poliklinik berhasil diambil.', $data);
