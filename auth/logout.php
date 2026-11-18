<?php
/**
 * Endpoint Logout: POST /auth/logout.php
 * Menghapus token akses yang sedang aktif
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(false, 'Metode HTTP tidak diizinkan. Gunakan POST.', null, 405);
}

$token = get_bearer_token();
if (!$token) {
    send_json(false, 'Token tidak disertakan.', null, 400);
}

$stmt = $conn->prepare("DELETE FROM token_akses WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();

send_json(true, 'Logout berhasil. Sesi telah diakhiri.', null, 200);
