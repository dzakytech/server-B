<?php
/**
 * Resource: Pengguna (/pengguna/index.php)
 * Role: Admin
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $auth_user = require_auth(['admin']);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            $stmt = $conn->prepare("SELECT id_pengguna, username, nama_lengkap, role, status, created_at FROM pengguna WHERE id_pengguna = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                send_json(false, 'Data pengguna tidak ditemukan.', null, 404);
            }
            send_json(true, 'Detail pengguna berhasil diambil.', $res->fetch_assoc());
        } else {
            $res = $conn->query("SELECT id_pengguna, username, nama_lengkap, role, status, created_at FROM pengguna ORDER BY id_pengguna DESC");
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            send_json(true, 'Daftar pengguna berhasil diambil.', $data);
        }
        break;

    case 'POST':
        require_auth(['admin']);
        $input = get_json_input();
        $username     = trim($input['username'] ?? '');
        $password     = trim($input['password'] ?? '');
        $nama_lengkap = trim($input['nama_lengkap'] ?? '');
        $role         = trim($input['role'] ?? 'pasien');
        $status       = trim($input['status'] ?? 'aktif');

        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            send_json(false, 'Username, password, dan nama lengkap wajib diisi.', null, 400);
        }

        if (!in_array($role, ['admin', 'dokter', 'pasien'])) {
            send_json(false, 'Role tidak valid. Harus admin, dokter, atau pasien.', null, 400);
        }

        // Cek username unik
        $check = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            send_json(false, 'Username sudah digunakan, silakan pilih username lain.', null, 400);
        }

        $hash_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO pengguna (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $hash_password, $nama_lengkap, $role, $status);
        if ($stmt->execute()) {
            send_json(true, 'Pengguna baru berhasil ditambahkan.', ['id_pengguna' => $stmt->insert_id], 201);
        } else {
            send_json(false, 'Gagal menambahkan pengguna: ' . $conn->error, null, 500);
        }
        break;

    case 'PUT':
        require_auth(['admin']);
        $input = get_json_input();
        $id_pengguna  = (int)($input['id_pengguna'] ?? ($_GET['id'] ?? 0));
        $nama_lengkap = trim($input['nama_lengkap'] ?? '');
        $role         = trim($input['role'] ?? '');
        $status       = trim($input['status'] ?? 'aktif');
        $new_password = trim($input['password'] ?? '');

        if (!$id_pengguna || empty($nama_lengkap) || empty($role)) {
            send_json(false, 'ID Pengguna, nama lengkap, dan role wajib diisi.', null, 400);
        }

        if (!in_array($role, ['admin', 'dokter', 'pasien'])) {
            send_json(false, 'Role tidak valid.', null, 400);
        }

        if (!empty($new_password)) {
            $hash = password_hash($new_password, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE pengguna SET nama_lengkap = ?, role = ?, status = ?, password = ? WHERE id_pengguna = ?");
            $stmt->bind_param("ssssi", $nama_lengkap, $role, $status, $hash, $id_pengguna);
        } else {
            $stmt = $conn->prepare("UPDATE pengguna SET nama_lengkap = ?, role = ?, status = ? WHERE id_pengguna = ?");
            $stmt->bind_param("sssi", $nama_lengkap, $role, $status, $id_pengguna);
        }

        if ($stmt->execute()) {
            send_json(true, 'Data pengguna berhasil diperbarui.', null);
        } else {
            send_json(false, 'Gagal memperbarui pengguna: ' . $conn->error, null, 500);
        }
        break;

    case 'DELETE':
        $auth_user = require_auth(['admin']);
        $input = get_json_input();
        $id_pengguna = (int)($input['id_pengguna'] ?? ($_GET['id'] ?? 0));

        if (!$id_pengguna) {
            send_json(false, 'ID Pengguna wajib disertakan.', null, 400);
        }

        if ($id_pengguna === (int)$auth_user['id_pengguna']) {
            send_json(false, 'Anda tidak dapat menghapus akun admin yang sedang Anda gunakan.', null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM pengguna WHERE id_pengguna = ?");
        $stmt->bind_param("i", $id_pengguna);
        if ($stmt->execute()) {
            send_json(true, 'Pengguna berhasil dihapus.', null);
        } else {
            send_json(false, 'Gagal menghapus pengguna: ' . $conn->error, null, 500);
        }
        break;

    default:
        send_json(false, 'Metode HTTP tidak didukung.', null, 405);
        break;
}
