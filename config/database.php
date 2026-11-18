<?php
/**
 * Konfigurasi Database MySQLi
 * Server B (API & Database)
 */

// Pastikan zona waktu sinkron ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'db_rumah_sakit';

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'status'  => false,
        'message' => 'Gagal terkoneksi ke database Server B: ' . $conn->connect_error,
        'data'    => null
    ]);
    exit;
}

$conn->set_charset('utf8mb4');
// Sinkronkan timezone MySQL ke +07:00 (WIB)
$conn->query("SET time_zone = '+07:00'");
