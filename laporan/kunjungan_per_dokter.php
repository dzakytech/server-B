<?php
/**
 * Laporan: Jumlah Kunjungan Per Dokter
 * Role: Admin
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

require_auth(['admin']);

$sql = "
    SELECT d.id_dokter, d.nama_dokter, d.spesialisasi, p.nama_poli,
           COUNT(k.id_kunjungan) AS total_kunjungan,
           SUM(CASE WHEN k.status = 'selesai' THEN 1 ELSE 0 END) AS selesai,
           SUM(CASE WHEN k.status = 'menunggu' THEN 1 ELSE 0 END) AS menunggu,
           SUM(CASE WHEN k.status = 'diperiksa' THEN 1 ELSE 0 END) AS diperiksa,
           SUM(CASE WHEN k.status = 'batal' THEN 1 ELSE 0 END) AS batal
    FROM dokter d
    JOIN poliklinik p ON d.id_poli = p.id_poli
    LEFT JOIN kunjungan k ON d.id_dokter = k.id_dokter
    GROUP BY d.id_dokter
    ORDER BY total_kunjungan DESC, d.nama_dokter ASC
";

$res = $conn->query($sql);
$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

send_json(true, 'Laporan jumlah kunjungan per dokter berhasil diambil.', $data);
