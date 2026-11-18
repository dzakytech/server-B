<?php
/**
 * Resource: Poliklinik (/poliklinik/index.php)
 * Mendukung GET, POST, PUT, DELETE
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/response.php';
require_once __DIR__ . '/../helpers/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Bisa diakses oleh semua pengguna (bahkan landing page)
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            $stmt = $conn->prepare("SELECT * FROM poliklinik WHERE id_poli = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                send_json(false, 'Data poliklinik tidak ditemukan.', null, 404);
            }
            send_json(true, 'Detail poliklinik berhasil diambil.', $res->fetch_assoc());
        } else {
            $query = "SELECT p.*, COUNT(d.id_dokter) AS total_dokter 
                      FROM poliklinik p 
                      LEFT JOIN dokter d ON p.id_poli = d.id_poli 
                      GROUP BY p.id_poli ORDER BY p.nama_poli ASC";
            $res = $conn->query($query);
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            send_json(true, 'Daftar poliklinik berhasil diambil.', $data);
        }
        break;

    case 'POST':
        require_auth(['admin']);
        $input = get_json_input();
        $nama_poli  = trim($input['nama_poli'] ?? '');
        $lokasi     = trim($input['lokasi'] ?? '');
        $keterangan = trim($input['keterangan'] ?? '');

        if (empty($nama_poli)) {
            send_json(false, 'Nama poliklinik wajib diisi.', null, 400);
        }

        $stmt = $conn->prepare("INSERT INTO poliklinik (nama_poli, lokasi, keterangan) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nama_poli, $lokasi, $keterangan);
        if ($stmt->execute()) {
            send_json(true, 'Poliklinik berhasil ditambahkan.', ['id_poli' => $stmt->insert_id], 201);
        } else {
            send_json(false, 'Gagal menambahkan poliklinik: ' . $conn->error, null, 500);
        }
        break;

    case 'PUT':
        require_auth(['admin']);
        $input = get_json_input();
        $id_poli    = (int)($input['id_poli'] ?? ($_GET['id'] ?? 0));
        $nama_poli  = trim($input['nama_poli'] ?? '');
        $lokasi     = trim($input['lokasi'] ?? '');
        $keterangan = trim($input['keterangan'] ?? '');

        if (!$id_poli || empty($nama_poli)) {
            send_json(false, 'ID Poliklinik dan Nama Poliklinik wajib diisi.', null, 400);
        }

        $stmt = $conn->prepare("UPDATE poliklinik SET nama_poli = ?, lokasi = ?, keterangan = ? WHERE id_poli = ?");
        $stmt->bind_param("sssi", $nama_poli, $lokasi, $keterangan, $id_poli);
        if ($stmt->execute()) {
            send_json(true, 'Poliklinik berhasil diperbarui.', null);
        } else {
            send_json(false, 'Gagal memperbarui poliklinik: ' . $conn->error, null, 500);
        }
        break;

    case 'DELETE':
        require_auth(['admin']);
        $input = get_json_input();
        $id_poli = (int)($input['id_poli'] ?? ($_GET['id'] ?? 0));

        if (!$id_poli) {
            send_json(false, 'ID Poliklinik wajib disertakan.', null, 400);
        }

        // Cek relasi dokter
        $check = $conn->prepare("SELECT COUNT(*) AS total FROM dokter WHERE id_poli = ?");
        $check->bind_param("i", $id_poli);
        $check->execute();
        $total_dokter = $check->get_result()->fetch_assoc()['total'];

        if ($total_dokter > 0) {
            send_json(false, "Tidak dapat menghapus poliklinik karena masih ada $total_dokter dokter yang terdaftar di poli ini.", null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM poliklinik WHERE id_poli = ?");
        $stmt->bind_param("i", $id_poli);
        if ($stmt->execute()) {
            send_json(true, 'Poliklinik berhasil dihapus.', null);
        } else {
            send_json(false, 'Gagal menghapus poliklinik: ' . $conn->error, null, 500);
        }
        break;

    default:
        send_json(false, 'Metode HTTP tidak didukung.', null, 405);
        break;
}
