<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;

/**
 * Simple Role based access helper.
 *
 * Ahora soporta roles almacenados en tablas relacionadas:
 *  - roles (id, name)
 *  - users_roles (user_id, role_id)
 *
 * Se añade la capacidad de leer roles desde la BD y calcular el menú
 * permitido para un usuario concreto.
 */
class Access
{
    public const ROLE_ADMIN   = 'admin';
    public const ROLE_GESTOR  = 'gestor';
    public const ROLE_CALIDAD = 'calidad';
    public const ROLE_OPERADOR= 'operador';

    // Menu keys
    public const M_INGREDIENTES = 'ingredientes';
    public const M_ELABORADOS   = 'elaborados';
    public const M_LOTES        = 'lotes';
    public const M_IMPRESION    = 'impresion';
    public const M_APPCC        = 'appcc';
    public const M_CALIDAD      = 'calidad';
    public const M_USUARIOS     = 'usuarios';

    // ...existing allowedMenus(), canRoleAccess(), menusForRole()...

    // ...existing code...
    public static function allowedMenus(): array
    {
        return [
            self::ROLE_ADMIN => [
                self::M_USUARIOS,
                self::M_INGREDIENTES,
                self::M_ELABORADOS,
                self::M_LOTES,
                self::M_IMPRESION,
                self::M_APPCC,
                self::M_CALIDAD,
            ],
            self::ROLE_GESTOR => [
                self::M_USUARIOS,
                self::M_INGREDIENTES,
                self::M_ELABORADOS,
                self::M_LOTES,
                self::M_IMPRESION,
                self::M_APPCC,
                self::M_CALIDAD,
            ],
            self::ROLE_CALIDAD => [
                self::M_INGREDIENTES,
                self::M_ELABORADOS,
                self::M_LOTES,
                self::M_IMPRESION,
                self::M_APPCC,
                self::M_CALIDAD,
            ],
            self::ROLE_OPERADOR => [
                self::M_INGREDIENTES,
                self::M_ELABORADOS,
                self::M_LOTES,
                self::M_IMPRESION,
            ],
        ];
    }

    public static function canRoleAccess(string $role, string $menuKey): bool
    {
        $map = self::allowedMenus();
        return isset($map[$role]) && in_array($menuKey, $map[$role], true);
    }

    /**
     * Recupera los roles asociados a un usuario desde la BD.
     *
     * Asume existencia de tablas `roles` (id, name) y `users_roles` (user_id, role_id).
     * Ajustar nombres de columnas/tabla si tu esquema es distinto.
     *
     * @param PDO $pdo
     * @param int $userId
     * @return string[] lista de nombres de rol (ej. ['admin'])
     */
    public static function getUserRoles(PDO $pdo, int $userId): array
    {
        $sql = "SELECT r.name AS role_name
                FROM roles r
                JOIN users_roles ur ON ur.role_id = r.id
                WHERE ur.user_id = :uid";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        return $rows === false ? [] : $rows;
    }

    /**
     * Si un usuario tiene varios roles, elegir uno por prioridad.
     * Orden de prioridad: admin > gestor > calidad > operador
     *
     * @param string[] $roles
     * @return string|null
     */
    public static function highestRole(array $roles): ?string
    {
        $priority = [
            self::ROLE_ADMIN,
            self::ROLE_GESTOR,
            self::ROLE_CALIDAD,
            self::ROLE_OPERADOR,
        ];
        foreach ($priority as $p) {
            if (in_array($p, $roles, true)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Devuelve los menús permitidos para un usuario (consultando la BD).
     *
     * @param PDO $pdo
     * @param int $userId
     * @return string[] lista de keys de menú permitidos
     */
    public static function menusForUser(PDO $pdo, int $userId): array
    {
        $roles = self::getUserRoles($pdo, $userId);
        if (empty($roles)) {
            return [];
        }
        $role = self::highestRole($roles);
        if ($role === null) {
            return [];
        }
        return self::menusForRole($role);
    }

    /**
     * Comprueba si el usuario actual (vía Auth::user) puede acceder a un menú.
     *
     * @param PDO $pdo
     * @param string $menuKey
     * @return bool
     */
    public static function check(PDO $pdo, string $menuKey): bool
    {
        $user = Auth::user($pdo);
        if (!$user) return false;
        $userId = (int)$user['id'];
        $allowed = self::menusForUser($pdo, $userId);
        return in_array($menuKey, $allowed, true);
    }
    /**
     * Devuelve los menús permitidos para un rol dado.
     *
     * @param string $role
     * @return string[] lista de keys de menú permitidos
     */
    public static function menusForRole(string $role): array
    {
        $map = self::allowedMenus();
        return $map[$role] ?? [];
    }
}