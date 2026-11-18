<?php
/**
 * Resource: Asuransi (/asuransi/index.php)
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
            $stmt = $conn->prepare("SELECT * FROM asuransi WHERE id_asuransi = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                send_json(false, 'Data asuransi tidak ditemukan.', null, 404);
            }
            send_json(true, 'Detail asuransi berhasil diambil.', $res->fetch_assoc());
        } else {
            $query = "SELECT a.*, COUNT(p.id_pasien) AS total_pasien 
                      FROM asuransi a 
                      LEFT JOIN pasien p ON a.id_asuransi = p.id_asuransi 
                      GROUP BY a.id_asuransi ORDER BY a.nama_asuransi ASC";
            $res = $conn->query($query);
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            send_json(true, 'Daftar asuransi berhasil diambil.', $data);
        }
        break;

    case 'POST':
        require_auth(['admin']);
        $input = get_json_input();
        $nama_asuransi = trim($input['nama_asuransi'] ?? '');
        $jenis         = trim($input['jenis'] ?? '');
        $no_kontak     = trim($input['no_kontak'] ?? '');
        $keterangan    = trim($input['keterangan'] ?? '');

        if (empty($nama_asuransi)) {
            send_json(false, 'Nama asuransi wajib diisi.', null, 400);
        }

        $stmt = $conn->prepare("INSERT INTO asuransi (nama_asuransi, jenis, no_kontak, keterangan) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $nama_asuransi, $jenis, $no_kontak, $keterangan);
        if ($stmt->execute()) {
            send_json(true, 'Asuransi berhasil ditambahkan.', ['id_asuransi' => $stmt->insert_id], 201);
        } else {
            send_json(false, 'Gagal menambahkan asuransi: ' . $conn->error, null, 500);
        }
        break;

    case 'PUT':
        require_auth(['admin']);
        $input = get_json_input();
        $id_asuransi   = (int)($input['id_asuransi'] ?? ($_GET['id'] ?? 0));
        $nama_asuransi = trim($input['nama_asuransi'] ?? '');
        $jenis         = trim($input['jenis'] ?? '');
        $no_kontak     = trim($input['no_kontak'] ?? '');
        $keterangan    = trim($input['keterangan'] ?? '');

        if (!$id_asuransi || empty($nama_asuransi)) {
            send_json(false, 'ID Asuransi dan Nama Asuransi wajib diisi.', null, 400);
        }

        $stmt = $conn->prepare("UPDATE asuransi SET nama_asuransi = ?, jenis = ?, no_kontak = ?, keterangan = ? WHERE id_asuransi = ?");
        $stmt->bind_param("ssssi", $nama_asuransi, $jenis, $no_kontak, $keterangan, $id_asuransi);
        if ($stmt->execute()) {
            send_json(true, 'Asuransi berhasil diperbarui.', null);
        } else {
            send_json(false, 'Gagal memperbarui asuransi: ' . $conn->error, null, 500);
        }
        break;

    case 'DELETE':
        require_auth(['admin']);
        $input = get_json_input();
        $id_asuransi = (int)($input['id_asuransi'] ?? ($_GET['id'] ?? 0));

        if (!$id_asuransi) {
            send_json(false, 'ID Asuransi wajib disertakan.', null, 400);
        }

        // Cek relasi pasien
        $check = $conn->prepare("SELECT COUNT(*) AS total FROM pasien WHERE id_asuransi = ?");
        $check->bind_param("i", $id_asuransi);
        $check->execute();
        $total_pasien = $check->get_result()->fetch_assoc()['total'];

        if ($total_pasien > 0) {
            send_json(false, "Tidak dapat menghapus asuransi karena masih dipakai oleh $total_pasien pasien.", null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM asuransi WHERE id_asuransi = ?");
        $stmt->bind_param("i", $id_asuransi);
        if ($stmt->execute()) {
            send_json(true, 'Asuransi berhasil dihapus.', null);
        } else {
            send_json(false, 'Gagal menghapus asuransi: ' . $conn->error, null, 500);
        }
        break;

    default:
        send_json(false, 'Metode HTTP tidak didukung.', null, 405);
        break;
}
