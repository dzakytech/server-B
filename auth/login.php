<?php
/**
 * Endpoint Login: POST /auth/login.php
 * Publik: Memverifikasi pengguna dan menghasilkan token akses
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(false, 'Metode HTTP tidak diizinkan. Gunakan POST.', null, 405);
}

$input = get_json_input();
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (empty($username) || empty($password)) {
    send_json(false, 'Username dan password wajib diisi.', null, 400);
}

// Bersihkan token kedaluwarsa secara otomatis
$conn->query("DELETE FROM token_akses WHERE expired_at <= NOW()");

// Cari data pengguna
$stmt = $conn->prepare("
    SELECT p.id_pengguna, p.username, p.password, p.nama_lengkap, p.role, p.status,
           d.id_dokter, ps.id_pasien
    FROM pengguna p
    LEFT JOIN dokter d ON p.id_pengguna = d.id_pengguna
    LEFT JOIN pasien ps ON p.id_pengguna = ps.id_pengguna
    WHERE p.username = ?
    LIMIT 1
");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_json(false, 'Username atau password salah.', null, 401);
}

$user = $result->fetch_assoc();

if ($user['status'] !== 'aktif') {
    send_json(false, 'Akun Anda dinonaktifkan. Hubungi administrator.', null, 403);
}

// Verifikasi password hash
if (!password_verify($password, $user['password'])) {
    send_json(false, 'Username atau password salah.', null, 401);
}

// Buat token akses baru (64 karakter hex)
$token = bin2hex(random_bytes(32));

// Gunakan DATE_ADD(NOW(), INTERVAL 2 HOUR) langsung di MySQL agar kebal terhadap perbedaan timezone PHP vs MySQL
$stmt_token = $conn->prepare("INSERT INTO token_akses (id_pengguna, token, expired_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))");
$stmt_token->bind_param("is", $user['id_pengguna'], $token);

if (!$stmt_token->execute()) {
    send_json(false, 'Gagal menyimpan sesi login token.', null, 500);
}

// Ambil tanggal expired_at yang dihitung oleh MySQL
$res_exp = $conn->query("SELECT expired_at FROM token_akses WHERE token = '$token' LIMIT 1");
$exp_row = $res_exp->fetch_assoc();
$expired_at = $exp_row['expired_at'] ?? date('Y-m-d H:i:s', time() + 7200);

// Hilangkan kolom password dari data respon
unset($user['password']);

$response_data = [
    'token'       => $token,
    'expired_at'  => $expired_at,
    'user'        => [
        'id_pengguna'  => (int)$user['id_pengguna'],
        'username'     => $user['username'],
        'nama_lengkap' => $user['nama_lengkap'],
        'role'         => $user['role'],
        'id_dokter'    => $user['id_dokter'] ? (int)$user['id_dokter'] : null,
        'id_pasien'    => $user['id_pasien'] ? (int)$user['id_pasien'] : null
    ]
];

send_json(true, 'Login berhasil. Selamat datang, ' . $user['nama_lengkap'] . '!', $response_data, 200);
