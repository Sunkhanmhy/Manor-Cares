<?php
/**
 * Manor Cares — create or promote the first admin account (CLI only)
 *
 * Usage:
 *   php php/seed-admin.php admin@manor-cares.com "Admin Name" "StrongPassword123!"
 *
 * If the email already exists, it is promoted to role=admin. Otherwise a
 * new admin account is created. Refuses to run outside the CLI SAPI so it
 * can never be reached over HTTP.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden.');
}

require __DIR__ . '/db.php';

$email = $argv[1] ?? null;
$name = $argv[2] ?? 'Administrator';
$password = $argv[3] ?? null;

if (!$email || !$password) {
    fwrite(STDERR, "Usage: php php/seed-admin.php <email> <name> <password>\n");
    exit(1);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email address.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "Password must be at least 8 characters.\n");
    exit(1);
}

$pdo = mc_db();
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$existing = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$existing->execute(['email' => $email]);
$row = $existing->fetch();

if ($row) {
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin', status = 'active' WHERE id = :id");
    $stmt->execute(['id' => $row['id']]);
    echo "Promoted existing user #{$row['id']} ({$email}) to admin.\n";
} else {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, plan, role, status) VALUES (:name, :email, :hash, 'essential', 'admin', 'active') RETURNING id"
    );
    $stmt->execute(['name' => $name, 'email' => $email, 'hash' => $hash]);
    $id = $stmt->fetchColumn();
    echo "Created new admin user #{$id} ({$email}).\n";
}
