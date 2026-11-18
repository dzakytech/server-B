<?php
/**
 * Resource: Pasien (/pasien/index.php)
 * Mendukung GET, POST, PUT, DELETE
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $auth_user = require_auth(['admin', 'dokter', 'pasien']);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

        if ($id) {
            // Jika pasien, hanya boleh melihat datanya sendiri
            if ($auth_user['role'] === 'pasien' && (int)$auth_user['id_pasien'] !== $id) {
                send_json(false, 'Akses ditolak: Anda hanya dapat mengakses profil pasien Anda sendiri.', null, 403);
            }

            $stmt = $conn->prepare("
                SELECT ps.*, a.nama_asuransi, a.jenis AS jenis_asuransi, u.username, u.status AS status_akun
                FROM pasien ps
                LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
                LEFT JOIN pengguna u ON ps.id_pengguna = u.id_pengguna
                WHERE ps.id_pasien = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                send_json(false, 'Data pasien tidak ditemukan.', null, 404);
            }
            send_json(true, 'Detail pasien berhasil diambil.', $res->fetch_assoc());
        } else {
            // Pasien biasa tidak boleh menarik semua daftar pasien
            if ($auth_user['role'] === 'pasien') {
                send_json(false, 'Akses ditolak: Pasien tidak memiliki izin melihat daftar seluruh pasien.', null, 403);
            }

            $sql = "SELECT ps.*, a.nama_asuransi, a.jenis AS jenis_asuransi, u.username
                    FROM pasien ps
                    LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
                    LEFT JOIN pengguna u ON ps.id_pengguna = u.id_pengguna
                    ORDER BY ps.id_pasien DESC";
            $res = $conn->query($sql);
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            send_json(true, 'Daftar pasien berhasil diambil.', $data);
        }
        break;

    case 'POST':
        require_auth(['admin']);
        $input = get_json_input();

        $nama_pasien       = trim($input['nama_pasien'] ?? '');
        $nik               = trim($input['nik'] ?? '');
        $jenis_kelamin     = trim($input['jenis_kelamin'] ?? 'L');
        $tanggal_lahir     = trim($input['tanggal_lahir'] ?? null);
        $alamat            = trim($input['alamat'] ?? '');
        $no_hp             = trim($input['no_hp'] ?? '');
        $id_asuransi       = !empty($input['id_asuransi']) ? (int)$input['id_asuransi'] : null;
        $no_kartu_asuransi = trim($input['no_kartu_asuransi'] ?? '');
        $no_rm             = trim($input['no_rm'] ?? '');
        $id_pengguna       = !empty($input['id_pengguna']) ? (int)$input['id_pengguna'] : null;

        if (empty($nama_pasien)) {
            send_json(false, 'Nama pasien wajib diisi.', null, 400);
        }

        // Generate No. Rekam Medis otomatis jika kosong
        if (empty($no_rm)) {
            $year = date('Y');
            $cnt_res = $conn->query("SELECT COUNT(*) AS total FROM pasien");
            $next_no = (int)$cnt_res->fetch_assoc()['total'] + 1;
            $no_rm = sprintf("RM-%s-%04d", $year, $next_no);
        } else {
            // Cek No. RM unik
            $chk_rm = $conn->prepare("SELECT id_pasien FROM pasien WHERE no_rm = ?");
            $chk_rm->bind_param("s", $no_rm);
            $chk_rm->execute();
            if ($chk_rm->get_result()->num_rows > 0) {
                send_json(false, 'Nomor Rekam Medis (No RM) sudah digunakan.', null, 400);
            }
        }

        // Opsi pembuatan akun pengguna otomatis
        $create_account = !empty($input['username']) && !empty($input['password']);

        $conn->begin_transaction();
        try {
            if ($create_account) {
                $username = trim($input['username']);
                $password = trim($input['password']);

                $chk_u = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ?");
                $chk_u->bind_param("s", $username);
                $chk_u->execute();
                if ($chk_u->get_result()->num_rows > 0) {
                    throw new Exception('Username untuk akun pasien sudah digunakan.');
                }

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins_u = $conn->prepare("INSERT INTO pengguna (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, 'pasien', 'aktif')");
                $ins_u->bind_param("sss", $username, $hash, $nama_pasien);
                $ins_u->execute();
                $id_pengguna = $ins_u->insert_id;
            }

            $stmt = $conn->prepare("
                INSERT INTO pasien (id_pengguna, id_asuransi, no_rm, nik, nama_pasien, jenis_kelamin, tanggal_lahir, alamat, no_hp, no_kartu_asuransi) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iissssssss", $id_pengguna, $id_asuransi, $no_rm, $nik, $nama_pasien, $jenis_kelamin, $tanggal_lahir, $alamat, $no_hp, $no_kartu_asuransi);
            $stmt->execute();
            $id_pasien = $stmt->insert_id;

            $conn->commit();
            send_json(true, 'Pasien baru berhasil didaftarkan.', ['id_pasien' => $id_pasien, 'no_rm' => $no_rm], 201);
        } catch (Exception $e) {
            $conn->rollback();
            send_json(false, 'Gagal mendaftarkan pasien: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'PUT':
        $auth_user = require_auth(['admin', 'pasien']);
        $input = get_json_input();
        $id_pasien = (int)($input['id_pasien'] ?? ($_GET['id'] ?? 0));

        if (!$id_pasien) {
            send_json(false, 'ID Pasien wajib disertakan.', null, 400);
        }

        // Pasien hanya boleh mengedit datanya sendiri
        if ($auth_user['role'] === 'pasien') {
            if ((int)$auth_user['id_pasien'] !== $id_pasien) {
                send_json(false, 'Akses ditolak: Pasien hanya dapat memperbarui data dirinya sendiri.', null, 403);
            }
        }

        $nama_pasien       = trim($input['nama_pasien'] ?? '');
        $nik               = trim($input['nik'] ?? '');
        $jenis_kelamin     = trim($input['jenis_kelamin'] ?? 'L');
        $tanggal_lahir     = trim($input['tanggal_lahir'] ?? null);
        $alamat            = trim($input['alamat'] ?? '');
        $no_hp             = trim($input['no_hp'] ?? '');
        $id_asuransi       = !empty($input['id_asuransi']) ? (int)$input['id_asuransi'] : null;
        $no_kartu_asuransi = trim($input['no_kartu_asuransi'] ?? '');

        if (empty($nama_pasien)) {
            send_json(false, 'Nama pasien wajib diisi.', null, 400);
        }

        $stmt = $conn->prepare("
            UPDATE pasien SET id_asuransi = ?, nik = ?, nama_pasien = ?, jenis_kelamin = ?, 
                              tanggal_lahir = ?, alamat = ?, no_hp = ?, no_kartu_asuransi = ? 
            WHERE id_pasien = ?
        ");
        $stmt->bind_param("isssssssi", $id_asuransi, $nik, $nama_pasien, $jenis_kelamin, $tanggal_lahir, $alamat, $no_hp, $no_kartu_asuransi, $id_pasien);
        if ($stmt->execute()) {
            send_json(true, 'Data pasien berhasil diperbarui.', null);
        } else {
            send_json(false, 'Gagal memperbarui data pasien: ' . $conn->error, null, 500);
        }
        break;

    case 'DELETE':
        require_auth(['admin']);
        $input = get_json_input();
        $id_pasien = (int)($input['id_pasien'] ?? ($_GET['id'] ?? 0));

        if (!$id_pasien) {
            send_json(false, 'ID Pasien wajib disertakan.', null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM pasien WHERE id_pasien = ?");
        $stmt->bind_param("i", $id_pasien);
        if ($stmt->execute()) {
            send_json(true, 'Pasien berhasil dihapus.', null);
        } else {
            send_json(false, 'Gagal menghapus data pasien: ' . $conn->error, null, 500);
        }
        break;

    default:
        send_json(false, 'Metode HTTP tidak didukung.', null, 405);
        break;
}
