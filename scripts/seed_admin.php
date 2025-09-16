<?php
declare(strict_types=1);
/**
 * scripts/seed_admin.php
 *
 * Script CLI para crear un usuario administrador localmente.
 *
 * Comportamiento:
 *  - Usa src/bootstrap.php para obtener una conexión PDO (SQLite).
 *  - Inserta un registro en `users` si no existe (INSERT OR IGNORE).
 *  - Crea el rol "admin" si no existe (tabla roles solo tiene name).
 *  - Asigna el rol al usuario en `users_roles`.
 *
 * Uso:
 *   php scripts/seed_admin.php <username> <password> [email]
 *
 * Notas de seguridad:
 *  - Las contraseñas se almacenan usando password_hash().
 *  - Todas las operaciones críticas se ejecutan dentro de una transacción.
 *
 * @return void
 */

$root = dirname(__DIR__);

// Obtener PDO desde bootstrap (bootstrap debe devolver una instancia PDO)
$pdo = require $root . '/src/bootstrap.php';
if (!($pdo instanceof PDO)) {
    fwrite(STDERR, "Error: src/bootstrap.php no devolvió una instancia PDO válida.\n");
    exit(1);
}

// Validación básica de argumentos CLI
if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/seed_admin.php <username> <password> [email]\n");
    exit(1);
}

// Obtener y normalizar parámetros
$username = trim((string) $argv[1]);
$password = (string) $argv[2];
$email = isset($argv[3]) ? trim((string) $argv[3]) : null;

// Validaciones simples
if ($username === '') {
    fwrite(STDERR, "Error: username vacío.\n");
    exit(1);
}
if ($password === '' || strlen($password) < 6) {
    fwrite(STDERR, "Error: password inválida (mín. 6 caracteres recomendado).\n");
    exit(1);
}
if ($email !== null && $email === '') {
    $email = null;
}

// Generar hash seguro de la contraseña
$hash = password_hash($password, PASSWORD_DEFAULT);
if ($hash === false) {
    fwrite(STDERR, "Error: no se pudo generar el hash de la contraseña.\n");
    exit(1);
}

try {
    // Iniciar transacción para que la operación sea atómica
    $pdo->beginTransaction();

    // 1) Insertar usuario si no existe (evita duplicados)
    $insertUser = $pdo->prepare(
        "INSERT OR IGNORE INTO users (username, email, password) VALUES (:username, :email, :password)"
    );
    $insertUser->execute([
        ':username' => $username,
        ':email' => $email,
        ':password' => $hash,
    ]);

    // 2) Obtener el id del usuario (tanto si se insertó ahora como si ya existía)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $userId = (int) $stmt->fetchColumn();
    if ($userId <= 0) {
        throw new RuntimeException("No se pudo obtener el id del usuario '{$username}'.");
    }

    // 3) Crear rol 'admin' si no existe
    // Nota: la tabla roles según migración solo tiene (id, name)
    $insertRole = $pdo->prepare("INSERT OR IGNORE INTO roles (name) VALUES (:name)");
    $insertRole->execute([':name' => 'admin']);

    // 4) Obtener id del rol 'admin'
    $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = :name LIMIT 1");
    $roleStmt->execute([':name' => 'admin']);
    $roleId = (int) $roleStmt->fetchColumn();
    if ($roleId <= 0) {
        throw new RuntimeException("No se pudo obtener el id del rol 'admin'.");
    }

    // 5) Asignar el rol al usuario (INSERT OR IGNORE para evitar duplicados)
    $assign = $pdo->prepare("INSERT OR IGNORE INTO users_roles (user_id, role_id) VALUES (:user_id, :role_id)");
    $assign->execute([
        ':user_id' => $userId,
        ':role_id' => $roleId,
    ]);

    // Confirmar transacción
    $pdo->commit();

    // Salida informativa por STDOUT
    fwrite(STDOUT, "Admin creado/asignado correctamente: {$username} (id: {$userId})\n");
    exit(0);

} catch (Throwable $e) {
    // Rollback si hay transacción abierta
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Mensaje por STDERR y código de error
    fwrite(STDERR, "Error creando admin: " . $e->getMessage() . PHP_EOL);
    exit(1);
}