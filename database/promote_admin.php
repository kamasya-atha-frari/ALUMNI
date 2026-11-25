<?php
declare(strict_types=1);

/**
 * AlumniIstü Admin Role Promotion CLI Utility
 *
 * Promotes an existing registered account (by email or username) to role = 'admin',
 * or creates the owner admin account if --create is supplied.
 *
 * Usage:
 *   php database/promote_admin.php kamasyaatha.frari@ogr.iu.edu.tr
 *   php database/promote_admin.php kamasya
 *   php database/promote_admin.php --from-env
 */

spl_autoload_register(function (string $class): void {
    $prefix = 'AlumniIstü\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $file = $baseDir . str_replace('\\', '/', substr($class, $len)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use AlumniIstü\Core\Database;
use AlumniIstü\Core\Env;

Env::load();

$arg = $argv[1] ?? '--from-env';
$identifier = $arg === '--from-env'
    ? (string)Env::get('ADMIN_EMAIL', 'kamasyaatha.frari@ogr.iu.edu.tr')
    : trim($arg);

if ($identifier === '') {
    fwrite(STDERR, "Usage: php database/promote_admin.php <email-or-username|--from-env>\n");
    exit(1);
}

$pdo = Database::getConnection();
if (!$pdo) {
    fwrite(STDERR, "[ERROR] Unable to connect to MySQL: " . (Database::getLastError() ?? 'unknown') . "\n");
    exit(1);
}

$stmt = $pdo->prepare('SELECT id, username, email, role, is_active FROM users WHERE LOWER(email) = :id OR LOWER(username) = :id LIMIT 1');
$stmt->execute(['id' => strtolower($identifier)]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Auto-create from .env if promoting the configured owner admin
    $ownerEmail = strtolower((string)Env::get('ADMIN_EMAIL', 'kamasyaatha.frari@ogr.iu.edu.tr'));
    $ownerUsername = strtolower((string)Env::get('ADMIN_USERNAME', 'kamasya'));
    if (strtolower($identifier) === $ownerEmail || strtolower($identifier) === $ownerUsername) {
        $ownerName = (string)Env::get('ADMIN_FULL_NAME', 'Kamasya Atha Frari');
        $ownerPass = (string)Env::get('ADMIN_PASSWORD', 'AdminPass2026!');
        $ins = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, auth_provider, full_name, role, department_id, is_active)
            VALUES (:username, :email, :password_hash, 'local', :full_name, 'admin', 1, 1)
        ");
        $ins->execute([
            'username' => $ownerUsername,
            'email' => $ownerEmail,
            'password_hash' => password_hash($ownerPass, PASSWORD_DEFAULT),
            'full_name' => $ownerName,
        ]);
        echo "\033[32m✔ Created and assigned admin role to {$ownerName} (@{$ownerUsername} / {$ownerEmail}).\033[0m\n";
        exit(0);
    }

    fwrite(STDERR, "\033[31m✖ No user found matching '{$identifier}'. Register the account first or pass --from-env.\033[0m\n");
    exit(1);
}

$upd = $pdo->prepare("UPDATE users SET role = 'admin', is_active = 1 WHERE id = :id");
$upd->execute(['id' => (int)$user['id']]);

echo "\033[32m✔ Promoted user #{$user['id']} (@{$user['username']} / {$user['email']}) from '{$user['role']}' to 'admin'.\033[0m\n";
exit(0);
