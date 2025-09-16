<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

/**
 * Class User
 *
 * Modelo ligero para operaciones sobre la entidad `users` y las relaciones con `roles` / `users_roles`.
 *
 * Contrato / diseño:
 * - El constructor acepta una instancia PDO, pero la mayoría de métodos aceptan también un PDO
 *   como argumento para mantener compatibilidad con llamadas existentes en controllers.
 * - Los métodos usan prepared statements para evitar inyección SQL.
 * - Las operaciones que modifican múltiples tablas usan transacciones cuando procede.
 *
 * Responsabilidades principales:
 * - Proveer listados públicos de usuarios (sin password) y sus roles.
 * - Crear y eliminar usuarios.
 * - Obtener/actualizar asignaciones de roles (por nombre).
 *
 * Seguridad:
 * - Nunca devolver el campo password en las respuestas públicas.
 * - Hashear contraseñas con password_hash antes de persistir.
 * - Validar entradas en el controlador antes de invocar el modelo (el modelo proporciona validación mínima).
 *
 * Nota sobre firmas:
 * - Muchos controladores actuales llaman métodos pasando $pdo explícitamente (ej. $this->userModel->allUsers($pdo)).
 *   Por compatibilidad estos métodos aceptan PDO $pdo como primer parámetro; internamente puede usarse $this->pdo si se prefiere.
 */
class User
{
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PDO $pdo Conexión a la base de datos (inyección).
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Recupera un usuario por id (sin password).
     *
     * @param PDO $pdo
     * @param int $id
     * @return array<string,mixed>|null Array asociativo con campos públicos o null si no existe.
     */
    public function findById(PDO $pdo, int $id): ?array
    {
        $sql = "SELECT id, username, email, is_active, created_at, last_login FROM users WHERE id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        // Adjuntar roles
        $row['roles'] = $this->getUserRoleNames($pdo, $id);
        return $row;
    }

    /**
     * Recupera todos los usuarios con datos públicos (excluye password).
     * Devuelve además 'roles' => array de nombres de rol para cada usuario.
     *
     * Implementación en 2 pasos para evitar problemas con GROUP_CONCAT en distintos motores/encodings:
     *  1) Traer usuarios.
     *  2) Traer roles para todos los ids en una sola query y mapear.
     *
     * @param PDO $pdo
     * @return array<int,array<string,mixed>>
     */
    public function allUsers(PDO $pdo): array
    {
        // 1) Obtener usuarios
        $stmt = $pdo->query(
            "SELECT id, username, email, is_active, created_at, last_login
             FROM users
             ORDER BY username ASC"
        );
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($users === false || empty($users)) {
            return [];
        }

        // 2) Obtener roles de todos los usuarios obtenidos en una sola consulta
        $ids = array_column($users, 'id');
        // proteger caso borde
        if (empty($ids)) {
            // adjuntar roles vacíos por seguridad
            foreach ($users as &$u) { $u['roles'] = []; }
            return $users;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT ur.user_id, r.name
                FROM users_roles ur
                JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3) Mapear roles por usuario
        $rolesByUser = [];
        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            $rolesByUser[$uid][] = $r['name'];
        }

        // 4) Adjuntar roles a cada usuario
        foreach ($users as &$u) {
            $u['roles'] = $rolesByUser[(int)$u['id']] ?? [];
        }

        return $users;
    }

    /**
     * Recupera todos los roles (id, name) existentes en la BD.
     *
     * @param PDO $pdo
     * @return array<int,array{ id: string, name: string }>
     */
    public function allRoles(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows === false ? [] : $rows;
    }

    /**
     * Recupera los nombres de rol asignados a un usuario.
     *
     * @param PDO $pdo
     * @param int $userId
     * @return string[] lista de nombres de rol
     */
    public function getUserRoleNames(PDO $pdo, int $userId): array
    {
        $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN users_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid");
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        return $rows === false ? [] : $rows;
    }

    /**
     * Crea un nuevo usuario.
     *
     * - Hashea la contraseña con password_hash().
     * - Devuelve el id insertado.
     *
     * @param PDO $pdo
     * @param string $username
     * @param string $password Plain text; debe validarse antes en el controlador.
     * @return int id del nuevo usuario
     * @throws RuntimeException en error de inserción
     */
    public function createUser(PDO $pdo, string $username, string $password): int
    {
        // Validaciones mínimas (el controlador debe validar más a fondo)
        $username = trim($username);
        if ($username === '' || $password === '') {
            throw new RuntimeException('Username y password requeridos');
        }

        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (username, password, created_at, is_active) VALUES (:username, :password, datetime('now'), 1)";
        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([':username' => $username, ':password' => $hashed]);
        if (!$ok) {
            throw new RuntimeException('No se pudo crear usuario');
        }
        return (int)$pdo->lastInsertId();
    }

    /**
     * Elimina un usuario y sus relaciones (users_roles).
     *
     * @param PDO $pdo
     * @param int $id
     * @return void
     */
    public function deleteUser(PDO $pdo, int $id): void
    {
        $pdo->prepare("DELETE FROM users_roles WHERE user_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM users WHERE id = :id")->execute([':id' => $id]);
    }

    /**
     * Asigna roles a un usuario por nombres. Reemplaza asignaciones previas.
     *
     * Flujo:
     *  - Resuelve role_id para cada role name (SELECT id WHERE name IN (...)).
     *  - Borra entradas previas en users_roles para el usuario.
     *  - Inserta nuevas filas users_roles (user_id, role_id).
     *
     * Usa transacción para garantizar consistencia.
     *
     * @param PDO $pdo
     * @param int $userId
     * @param string[] $roleNames Lista de nombres de rol (ej. ['admin','gestor'])
     * @return void
     */
    public function assignRolesByNames(PDO $pdo, int $userId, array $roleNames): void
    {
        // Normalizar y filtrar strings vacíos
        $roleNames = array_values(array_filter(array_map('trim', $roleNames), fn($v) => $v !== ''));

        // Si no hay roles a asignar, simplemente limpiar las existentes
        $pdo->beginTransaction();
        try {
            // Resolver role ids
            if (!empty($roleNames)) {
                $in = implode(',', array_fill(0, count($roleNames), '?'));
                $sql = "SELECT id, name FROM roles WHERE name IN ($in)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($roleNames);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $nameToId = [];
                foreach ($rows as $r) {
                    $nameToId[$r['name']] = (int)$r['id'];
                }
            } else {
                $nameToId = [];
            }

            // Reemplazar roles: eliminar existentes
            $pdo->prepare("DELETE FROM users_roles WHERE user_id = :uid")->execute([':uid' => $userId]);

            // Insertar nuevas relaciones
            if (!empty($nameToId)) {
                $insert = $pdo->prepare("INSERT INTO users_roles (user_id, role_id) VALUES (:uid, :rid)");
                foreach ($roleNames as $rn) {
                    if (!isset($nameToId[$rn])) {
                        // saltar nombres desconocidos
                        continue;
                    }
                    $insert->execute([':uid' => $userId, ':rid' => $nameToId[$rn]]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}