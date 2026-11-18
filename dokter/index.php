<?php
/**
 * Resource: Dokter (/dokter/index.php)
 * Mendukung GET, POST, PUT, DELETE
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            $stmt = $conn->prepare("
                SELECT d.*, p.nama_poli, p.lokasi AS lokasi_poli, u.username, u.status AS status_akun
                FROM dokter d
                JOIN poliklinik p ON d.id_poli = p.id_poli
                LEFT JOIN pengguna u ON d.id_pengguna = u.id_pengguna
                WHERE d.id_dokter = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                send_json(false, 'Data dokter tidak ditemukan.', null, 404);
            }
            send_json(true, 'Detail dokter berhasil diambil.', $res->fetch_assoc());
        } else {
            $sql = "SELECT d.*, p.nama_poli, p.lokasi AS lokasi_poli, u.username, u.status AS status_akun
                    FROM dokter d
                    JOIN poliklinik p ON d.id_poli = p.id_poli
                    LEFT JOIN pengguna u ON d.id_pengguna = u.id_pengguna
                    ORDER BY d.nama_dokter ASC";
            $res = $conn->query($sql);
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            send_json(true, 'Daftar dokter berhasil diambil.', $data);
        }
        break;

    case 'POST':
        require_auth(['admin']);
        $input = get_json_input();

        $nama_dokter  = trim($input['nama_dokter'] ?? '');
        $id_poli      = (int)($input['id_poli'] ?? 0);
        $spesialisasi = trim($input['spesialisasi'] ?? '');
        $no_str       = trim($input['no_str'] ?? '');
        $no_hp        = trim($input['no_hp'] ?? '');
        $id_pengguna  = !empty($input['id_pengguna']) ? (int)$input['id_pengguna'] : null;

        // Opsi pembuatan akun pengguna otomatis jika username & password disediakan
        $create_account = !empty($input['username']) && !empty($input['password']);

        if (empty($nama_dokter) || !$id_poli) {
            send_json(false, 'Nama dokter dan poliklinik wajib diisi.', null, 400);
        }

        $conn->begin_transaction();
        try {
            if ($create_account) {
                $username = trim($input['username']);
                $password = trim($input['password']);
                // Cek username
                $chk = $conn->prepare("SELECT id_pengguna FROM pengguna WHERE username = ?");
                $chk->bind_param("s", $username);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    throw new Exception('Username untuk akun dokter sudah digunakan.');
                }
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins_u = $conn->prepare("INSERT INTO pengguna (username, password, nama_lengkap, role, status) VALUES (?, ?, ?, 'dokter', 'aktif')");
                $ins_u->bind_param("sss", $username, $hash, $nama_dokter);
                $ins_u->execute();
                $id_pengguna = $ins_u->insert_id;
            }

            $stmt = $conn->prepare("INSERT INTO dokter (id_pengguna, id_poli, nama_dokter, spesialisasi, no_str, no_hp) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $id_pengguna, $id_poli, $nama_dokter, $spesialisasi, $no_str, $no_hp);
            $stmt->execute();
            $id_dokter = $stmt->insert_id;

            $conn->commit();
            send_json(true, 'Dokter berhasil ditambahkan.', ['id_dokter' => $id_dokter, 'id_pengguna' => $id_pengguna], 201);
        } catch (Exception $e) {
            $conn->rollback();
            send_json(false, 'Gagal menambahkan dokter: ' . $e->getMessage(), null, 500);
        }
        break;

    case 'PUT':
        $auth_user = require_auth(['admin', 'dokter']);
        $input = get_json_input();
        $id_dokter = (int)($input['id_dokter'] ?? ($_GET['id'] ?? 0));

        if (!$id_dokter) {
            send_json(false, 'ID Dokter wajib diisi.', null, 400);
        }

        // Jika role dokter, hanya boleh edit profil dirinya sendiri
        if ($auth_user['role'] === 'dokter') {
            if ((int)$auth_user['id_dokter'] !== $id_dokter) {
                send_json(false, 'Akses ditolak: Dokter hanya dapat mengubah profil miliknya sendiri.', null, 403);
            }
        }

        $nama_dokter  = trim($input['nama_dokter'] ?? '');
        $id_poli      = (int)($input['id_poli'] ?? 0);
        $spesialisasi = trim($input['spesialisasi'] ?? '');
        $no_str       = trim($input['no_str'] ?? '');
        $no_hp        = trim($input['no_hp'] ?? '');

        if (empty($nama_dokter) || !$id_poli) {
            send_json(false, 'Nama dokter dan poliklinik wajib diisi.', null, 400);
        }

        $stmt = $conn->prepare("UPDATE dokter SET id_poli = ?, nama_dokter = ?, spesialisasi = ?, no_str = ?, no_hp = ? WHERE id_dokter = ?");
        $stmt->bind_param("issssi", $id_poli, $nama_dokter, $spesialisasi, $no_str, $no_hp, $id_dokter);
        if ($stmt->execute()) {
            send_json(true, 'Data dokter berhasil diperbarui.', null);
        } else {
            send_json(false, 'Gagal memperbarui data dokter: ' . $conn->error, null, 500);
        }
        break;

    case 'DELETE':
        require_auth(['admin']);
        $input = get_json_input();
        $id_dokter = (int)($input['id_dokter'] ?? ($_GET['id'] ?? 0));

        if (!$id_dokter) {
            send_json(false, 'ID Dokter wajib disertakan.', null, 400);
        }

        // Cek apakah dokter memiliki riwayat kunjungan
        $check = $conn->prepare("SELECT COUNT(*) AS total FROM kunjungan WHERE id_dokter = ?");
        $check->bind_param("i", $id_dokter);
        $check->execute();
        $total = $check->get_result()->fetch_assoc()['total'];

        if ($total > 0) {
            send_json(false, "Tidak dapat menghapus dokter karena masih memiliki $total catatan kunjungan pasien.", null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM dokter WHERE id_dokter = ?");
        $stmt->bind_param("i", $id_dokter);
        if ($stmt->execute()) {
            send_json(true, 'Dokter berhasil dihapus.', null);
        } else {
            send_json(false, 'Gagal menghapus dokter: ' . $conn->error, null, 500);
        }
        break;

    default:
        send_json(false, 'Metode HTTP tidak didukung.', null, 405);
        break;
}
