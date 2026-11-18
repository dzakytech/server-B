<?php
/**
 * Skrip utilitas untuk menghasilkan hash password bcrypt
 * Jalankan lewat CLI: C:\xampp\php\php.exe generate_hash.php
 */
$passwords = [
    'admin123'  => password_hash('admin123', PASSWORD_BCRYPT),
    'dokter123' => password_hash('dokter123', PASSWORD_BCRYPT),
    'pasien123' => password_hash('pasien123', PASSWORD_BCRYPT),
];

echo "=== HASH PASSWORD SEED ===\n";
foreach ($passwords as $plain => $hash) {
    echo "Password: $plain => Hash: $hash\n";
}
