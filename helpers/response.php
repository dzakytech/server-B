<?php
/**
 * Helper Format Response JSON & CORS
 * Standar respon PRD:
 * Sukses: { "status": true, "message": "...", "data": [...] }
 * Gagal:  { "status": false, "message": "...", "data": null }
 */

// Aktifkan CORS untuk mengizinkan request dari Server A
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Authorization, X-Token, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

// Tangani Preflight OPTIONS Request
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function send_json($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'status'  => (bool)$status,
        'message' => $message,
        'data'    => $data
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function get_json_input() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
        return $data;
    }
    // Fallback jika dikirim lewat x-www-form-urlencoded atau POST biasa
    return $_POST;
}
