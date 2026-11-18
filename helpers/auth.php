<?php
/**
 * Helper Autentikasi Bearer Token & Role-Based Access Control (RBAC)
 * Mendeteksi Bearer Token dari berbagai header Apache / FastCGI / Query Param
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/response.php';

function get_bearer_token() {
    $raw_token = null;

    // 1. Cek dari variabel $_SERVER (termasuk variabel yang diteruskan oleh mod_rewrite Apache)
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $raw_token = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $raw_token = trim($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    } elseif (!empty($_SERVER['Authorization'])) {
        $raw_token = trim($_SERVER['Authorization']);
    } elseif (!empty($_SERVER['HTTP_X_AUTHORIZATION'])) {
        $raw_token = trim($_SERVER['HTTP_X_AUTHORIZATION']);
    } elseif (!empty($_SERVER['HTTP_X_TOKEN'])) {
        return trim($_SERVER['HTTP_X_TOKEN']);
    }

    // 2. Cek dari fungsi apache_request_headers() jika tersedia
    if (empty($raw_token) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        // Ubah key menjadi lowercase untuk pencarian case-insensitive
        $headers_lower = array_change_key_case($headers, CASE_LOWER);

        if (!empty($headers_lower['authorization'])) {
            $raw_token = trim($headers_lower['authorization']);
        } elseif (!empty($headers_lower['x-authorization'])) {
            $raw_token = trim($headers_lower['x-authorization']);
        } elseif (!empty($headers_lower['x-token'])) {
            return trim($headers_lower['x-token']);
        }
    }

    // Ekstrak string token jika ada format "Bearer <token>"
    if (!empty($raw_token)) {
        if (preg_match('/Bearer\s+(\S+)/i', $raw_token, $matches)) {
            return $matches[1];
        }
        return $raw_token;
    }

    // 3. Fallback parameter token dari URL GET atau body POST (misal ?token=...)
    if (!empty($_REQUEST['token'])) {
        return trim($_REQUEST['token']);
    }

    return null;
}

function require_auth($allowed_roles = []) {
    global $conn;

    $token = get_bearer_token();
    if (!$token) {
        send_json(false, 'Akses ditolak: Token autentikasi tidak ditemukan. Silakan login.', null, 401);
    }

    // Periksa token di tabel token_akses dan kaitkan dengan tabel pengguna
    $stmt = $conn->prepare("
        SELECT t.id_token, t.id_pengguna, t.expired_at,
               p.username, p.nama_lengkap, p.role, p.status,
               d.id_dokter, ps.id_pasien
        FROM token_akses t
        JOIN pengguna p ON t.id_pengguna = p.id_pengguna
        LEFT JOIN dokter d ON p.id_pengguna = d.id_pengguna
        LEFT JOIN pasien ps ON p.id_pengguna = ps.id_pengguna
        WHERE t.token = ? AND t.expired_at > NOW() AND p.status = 'aktif'
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        send_json(false, 'Token tidak valid atau sudah kedaluwarsa. Silakan login kembali.', null, 401);
    }

    $user = $result->fetch_assoc();

    // Periksa Role jika dispesifikasikan
    if (!empty($allowed_roles)) {
        if (!in_array($user['role'], $allowed_roles)) {
            send_json(false, 'Akses ditolak: Peran (' . $user['role'] . ') Anda tidak memiliki izin untuk tindakan ini.', null, 403);
        }
    }

    return $user;
}
