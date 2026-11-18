<?php
/**
 * Resource: Kunjungan (/kunjungan/index.php)
 * Mendukung pendaftaran kunjungan, pemeriksaan dokter, dan rekam medis
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
            $stmt = $conn->prepare("
                SELECT k.*, 
                       ps.nama_pasien, ps.no_rm, ps.nik, ps.jenis_kelamin, ps.tanggal_lahir, ps.no_hp AS no_hp_pasien, ps.no_kartu_asuransi,
                       a.nama_asuransi,
                       d.nama_dokter, d.spesialisasi,
                       p.nama_poli, p.lokasi AS lokasi_poli
                FROM kunjungan k
                JOIN pasien ps ON k.id_pasien = ps.id_pasien
                LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
                JOIN dokter d ON k.id_dokter = d.id_dokter
                JOIN poliklinik p ON k.id_poli = p.id_poli
                WHERE k.id_kunjungan = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 0) {
                send_json(false, 'Data kunjungan tidak ditemukan.', null, 404);
            }
            $row = $res->fetch_assoc();

            // Validasi kepemilikan untuk dokter dan pasien
            if ($auth_user['role'] === 'dokter' && (int)$auth_user['id_dokter'] !== (int)$row['id_dokter']) {
                send_json(false, 'Akses ditolak: Anda hanya dapat melihat kunjungan pasien yang ditugaskan kepada Anda.', null, 403);
            }
            if ($auth_user['role'] === 'pasien' && (int)$auth_user['id_pasien'] !== (int)$row['id_pasien']) {
                send_json(false, 'Akses ditolak: Anda hanya dapat melihat riwayat kunjungan Anda sendiri.', null, 403);
            }

            send_json(true, 'Detail kunjungan berhasil diambil.', $row);
        } else {
            // Filter berdasarkan role
            $sql = "
                SELECT k.*, 
                       ps.nama_pasien, ps.no_rm,
                       a.nama_asuransi,
                       d.nama_dokter, d.spesialisasi,
                       p.nama_poli
                FROM kunjungan k
                JOIN pasien ps ON k.id_pasien = ps.id_pasien
                LEFT JOIN asuransi a ON ps.id_asuransi = a.id_asuransi
                JOIN dokter d ON k.id_dokter = d.id_dokter
                JOIN poliklinik p ON k.id_poli = p.id_poli
            ";

            if ($auth_user['role'] === 'dokter') {
                $id_dok = (int)($auth_user['id_dokter'] ?? 0);
                $sql .= " WHERE k.id_dokter = $id_dok ";
            } elseif ($auth_user['role'] === 'pasien') {
                $id_pas = (int)($auth_user['id_pasien'] ?? 0);
                $sql .= " WHERE k.id_pasien = $id_pas ";
            }

            $sql .= " ORDER BY k.tanggal_kunjungan DESC, k.id_kunjungan DESC ";

            $res = $conn->query($sql);
            $data = [];
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
            send_json(true, 'Daftar kunjungan berhasil diambil.', $data);
        }
        break;

    case 'POST':
        $auth_user = require_auth(['admin', 'pasien']);
        $input = get_json_input();

        $id_dokter         = (int)($input['id_dokter'] ?? 0);
        $id_poli           = (int)($input['id_poli'] ?? 0);
        $tanggal_kunjungan = trim($input['tanggal_kunjungan'] ?? date('Y-m-d'));
        $keluhan           = trim($input['keluhan'] ?? '');

        // Tentukan id_pasien
        if ($auth_user['role'] === 'pasien') {
            $id_pasien = (int)($auth_user['id_pasien'] ?? 0);
            if (!$id_pasien) {
                send_json(false, 'Akun Anda belum terhubung dengan data identitas pasien.', null, 400);
            }
        } else {
            // Admin bebas menentukan pasien
            $id_pasien = (int)($input['id_pasien'] ?? 0);
        }

        if (!$id_pasien || !$id_dokter || !$id_poli) {
            send_json(false, 'Pasien, dokter, dan poliklinik wajib dipilih.', null, 400);
        }

        $status = 'menunggu';
        $stmt = $conn->prepare("
            INSERT INTO kunjungan (id_pasien, id_dokter, id_poli, tanggal_kunjungan, keluhan, status) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiisss", $id_pasien, $id_dokter, $id_poli, $tanggal_kunjungan, $keluhan, $status);
        if ($stmt->execute()) {
            send_json(true, 'Pendaftaran kunjungan berhasil dibuat.', ['id_kunjungan' => $stmt->insert_id], 201);
        } else {
            send_json(false, 'Gagal mendaftarkan kunjungan: ' . $conn->error, null, 500);
        }
        break;

    case 'PUT':
        $auth_user = require_auth(['admin', 'dokter']);
        $input = get_json_input();
        $id_kunjungan = (int)($input['id_kunjungan'] ?? ($_GET['id'] ?? 0));

        if (!$id_kunjungan) {
            send_json(false, 'ID Kunjungan wajib disertakan.', null, 400);
        }

        // Ambil data kunjungan saat ini
        $chk = $conn->prepare("SELECT id_dokter FROM kunjungan WHERE id_kunjungan = ?");
        $chk->bind_param("i", $id_kunjungan);
        $chk->execute();
        $cur_kunjungan = $chk->get_result()->fetch_assoc();

        if (!$cur_kunjungan) {
            send_json(false, 'Data kunjungan tidak ditemukan.', null, 404);
        }

        if ($auth_user['role'] === 'dokter') {
            // Dokter hanya boleh ubah kunjungan miliknya
            if ((int)$auth_user['id_dokter'] !== (int)$cur_kunjungan['id_dokter']) {
                send_json(false, 'Akses ditolak: Dokter hanya dapat mengubah rekam medis kunjungan pasiennya sendiri.', null, 403);
            }

            // Dokter mengupdate diagnosis, tindakan, dan status pemeriksaan
            $diagnosis = trim($input['diagnosis'] ?? '');
            $tindakan  = trim($input['tindakan'] ?? '');
            $status    = trim($input['status'] ?? 'selesai');

            $stmt = $conn->prepare("UPDATE kunjungan SET diagnosis = ?, tindakan = ?, status = ? WHERE id_kunjungan = ?");
            $stmt->bind_param("sssi", $diagnosis, $tindakan, $status, $id_kunjungan);
        } else {
            // Admin memiliki hak penuh mengubah jadwal/status
            $id_pasien          = (int)($input['id_pasien'] ?? 0);
            $id_dokter          = (int)($input['id_dokter'] ?? 0);
            $id_poli            = (int)($input['id_poli'] ?? 0);
            $tanggal_kunjungan  = trim($input['tanggal_kunjungan'] ?? '');
            $keluhan            = trim($input['keluhan'] ?? '');
            $diagnosis          = trim($input['diagnosis'] ?? '');
            $tindakan           = trim($input['tindakan'] ?? '');
            $status             = trim($input['status'] ?? 'menunggu');

            if (!$id_pasien || !$id_dokter || !$id_poli) {
                // Jika hanya update status/rekam medis
                $stmt = $conn->prepare("UPDATE kunjungan SET diagnosis = ?, tindakan = ?, status = ? WHERE id_kunjungan = ?");
                $stmt->bind_param("sssi", $diagnosis, $tindakan, $status, $id_kunjungan);
            } else {
                $stmt = $conn->prepare("
                    UPDATE kunjungan 
                    SET id_pasien = ?, id_dokter = ?, id_poli = ?, tanggal_kunjungan = ?, keluhan = ?, diagnosis = ?, tindakan = ?, status = ? 
                    WHERE id_kunjungan = ?
                ");
                $stmt->bind_param("iiisssssi", $id_pasien, $id_dokter, $id_poli, $tanggal_kunjungan, $keluhan, $diagnosis, $tindakan, $status, $id_kunjungan);
            }
        }

        if ($stmt->execute()) {
            send_json(true, 'Data kunjungan berhasil diperbarui.', null);
        } else {
            send_json(false, 'Gagal memperbarui kunjungan: ' . $conn->error, null, 500);
        }
        break;

    case 'DELETE':
        require_auth(['admin']);
        $input = get_json_input();
        $id_kunjungan = (int)($input['id_kunjungan'] ?? ($_GET['id'] ?? 0));

        if (!$id_kunjungan) {
            send_json(false, 'ID Kunjungan wajib disertakan.', null, 400);
        }

        $stmt = $conn->prepare("DELETE FROM kunjungan WHERE id_kunjungan = ?");
        $stmt->bind_param("i", $id_kunjungan);
        if ($stmt->execute()) {
            send_json(true, 'Kunjungan berhasil dihapus.', null);
        } else {
            send_json(false, 'Gagal menghapus kunjungan: ' . $conn->error, null, 500);
        }
        break;

    default:
        send_json(false, 'Metode HTTP tidak didukung.', null, 405);
        break;
}
