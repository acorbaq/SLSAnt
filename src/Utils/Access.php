<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;
use App\Utils\Auth;

/**
 * Class Access
 *
 * Helper centralizado para autorización basada en roles.
 *
 * Propósito:
 *  - Definir roles y claves de menú (constantes).
 *  - Mapear qué menús están permitidos por cada rol.
 *  - Proveer utilidades para:
 *      * obtener roles de la BD para un usuario,
 *      * normalizar / filtrar roles conocidos por la app,
 *      * elegir el rol "principal" si un usuario tiene varios,
 *      * obtener los menús permitidos para un rol o para un usuario,
 *      * comprobar permiso server-side para el usuario actual.
 *
 * Diseño / decisiones importantes:
 *  - La "fuente de verdad" de los roles usados en la UI/logic son las constantes ROLE_* y definedRoles().
 *    Los nombres en la BD se filtran/normalizan para evitar valores inesperados.
 *  - Menús se configuran en allowedMenus() y se consultan mediante menusForRole()/menusForUser().
 *  - menusForUser() lee roles desde la BD y aplica prioridad con highestRole().
 *
 * Seguridad:
 *  - Todas las comprobaciones UI deben repetirse en endpoints server-side usando Access::check() o Access::canRoleAccess().
 *
 * @package App\Utils
 */
class Access
{
    // Roles de la aplicación (valores canonical)
    public const ROLE_ADMIN   = 'admin';
    public const ROLE_GESTOR  = 'gestor';
    public const ROLE_CALIDAD = 'calidad';
    public const ROLE_OPERADOR= 'operador';

    // Claves de menú (usadas para mapear vistas/rutas)
    public const M_INGREDIENTES = 'ingredientes';
    public const M_ELABORADOS   = 'elaborados';
    public const M_LOTES        = 'lotes';
    public const M_IMPRESION    = 'impresion';
    public const M_APPCC        = 'appcc';
    public const M_CALIDAD      = 'calidad';
    public const M_USUARIOS     = 'usuarios';

    /**
     * allowedMenus
     *
     * Mapa declarativo que asocia cada rol conocido a la lista de claves de menú a las que puede acceder.
     * Mantener esta estructura como la política de autorización de la UI.
     *
     * @return array<string,string[]>
     */
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

    /**
     * canRoleAccess
     *
     * Comprobación rápida: dado un nombre de rol y una clave de menú,
     * devuelve true si el rol tiene acceso según allowedMenus().
     *
     * Uso típico: validar reglas estáticas sin consultar BD.
     *
     * @param string $role
     * @param string $menuKey
     * @return bool
     */
    public static function canRoleAccess(string $role, string $menuKey): bool
    {
        $map = self::allowedMenus();
        return isset($map[$role]) && in_array($menuKey, $map[$role], true);
    }

    /**
     * getUserRoles
     *
     * Recupera los nombres de rol asociados a $userId desde la BD.
     * - Se asume tablas roles(id,name) y users_roles(user_id, role_id).
     * - Devuelve los nombres tal cual están en la BD; la normalización / filtrado
     *   se aplica después si se desea (ver getUserRolesFiltered).
     *
     * @param PDO $pdo
     * @param int $userId
     * @return string[] lista de nombres de rol (o vacío)
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
     * highestRole
     *
     * Política simple para resolver conflictos cuando un usuario tiene múltiples roles.
     * Devuelve el rol con mayor prioridad según la lista definida aquí.
     *
     * Prioridad: admin > gestor > calidad > operador
     *
     * @param string[] $roles lista de nombres de rol
     * @return string|null rol escogido o null si ninguno coincide
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
     * knownRoleNames
     *
     * Lista interna de roles "conocidos" por la aplicación. Usar para filtrar/normalizar
     * valores que provienen de la BD y así evitar roles inesperados en la UI.
     *
     * @return string[]
     */
    private static function knownRoleNames(): array
    {
        return [
            self::ROLE_ADMIN,
            self::ROLE_GESTOR,
            self::ROLE_CALIDAD,
            self::ROLE_OPERADOR,
        ];
    }

    /**
     * definedRoles
     *
     * API pública para obtener los roles que la aplicación considera válidos
     * (útil para poblar select/checkbox en la UI).
     *
     * @return string[]
     */
    public static function definedRoles(): array
    {
        return array_values(self::knownRoleNames());
    }

    /**
     * menusForUser
     *
     * Flujo:
     *  - Obtener roles del usuario desde la BD (getUserRoles).
     *  - Si no hay roles devolver [].
     *  - Resolver rol "principal" con highestRole.
     *  - Devolver los menús permitidos para ese rol con menusForRole.
     *
     * Comentario de diseño:
     *  - Si se desea una política más compleja (combinar permisos de varios roles)
     *    aquí es donde implementarla (por ahora se usa el rol de mayor prioridad).
     *
     * @param PDO $pdo
     * @param int $userId
     * @return string[] keys de menú permitidas para el usuario
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
     * check
     *
     * Comprobación server-side orientada al usuario actual:
     *  - Usa Auth::user($pdo) para obtener el usuario autenticado.
     *  - Si no hay usuario devuelve false.
     *  - Obtiene menús permitidos y comprueba la existencia de $menuKey.
     *
     * Uso recomendado:
     *  - Invocar en cada endpoint sensible (controllers/front controllers) para
     *    impedir acceso aunque la UI muestre/oculte enlaces.
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
     * menusForRole
     *
     * Devuelve la lista de menús permitidos para un rol dado usando allowedMenus().
     * Método utilitario simple.
     *
     * @param string $role
     * @return string[]
     */
    public static function menusForRole(string $role): array
    {
        $map = self::allowedMenus();
        return $map[$role] ?? [];
    }
}