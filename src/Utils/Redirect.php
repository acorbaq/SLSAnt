<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;

/**
 * Class Redirect
 *
 * Utilidad ligera para forzar redirecciones basadas en autenticación y permisos.
 *
 * Propósito:
 *  - Centralizar redirecciones comunes (forzar login, forzar permiso).
 *  - Evitar duplicación de la lógica de verificación de sesión/permiso en front controllers.
 *
 * Comportamiento general:
 *  - Los métodos son estáticos para uso directo desde front controllers y controladores.
 *  - No renderiza HTML ni muestra errores; envía cabeceras Location y termina la ejecución (exit).
 *  - Depende de App\Utils\Auth para acceder al usuario actual y App\Utils\Access para comprobar permisos.
 *
 * Recomendaciones de uso:
 *  - Llamar a Auth::initSession() temprano (o dejar que requireLogin lo haga) para asegurar $_SESSION configurado.
 *  - Siempre llamar a estos métodos antes de emitir cualquier output (cabeceras ya enviadas causan fallo).
 *  - Aunque Redirect::requirePermission ayuda en el front, cada endpoint debe validar permisos server-side.
 *
 * @package App\Utils
 */
class Redirect
{
    /**
     * Comprueba si hay un usuario autenticado; si no lo hay, redirige a $loginPath y termina.
     *
     * Flujo interno:
     *  1) Asegura parámetros de sesión llamando Auth::initSession() para configurar cookies/flags.
     *  2) Obtiene el usuario actual con Auth::user($pdo). Si no existe, realiza redirección a login.
     *  3) Si existe usuario, no hace nada y el caller continúa.
     *
     * Notas:
     *  - Este método no valida permisos; solo comprueba existencia de sesión.
     *  - Está pensado para front controllers que requieren autenticación mínima.
     *
     * @param PDO $pdo Conexión a la BD (se pasa a Auth::user()).
     * @param string $loginPath Ruta a la que redirigir si no hay sesión (por defecto '/login.php').
     * @return void No retorna si redirige (exit).
     */
    public static function requireLogin(PDO $pdo, string $loginPath = '/login.php'): void
    {
        // 1) Asegurar que la sesión está inicializada y configurada de forma segura.
        //    Auth::initSession() puede setear cookie params y llamar session_start() si procede.
        Auth::initSession();

        // 2) Obtener el usuario actual desde el helper Auth (puede retornar array|null).
        $user = Auth::user($pdo);

        // 3) Si no hay usuario autenticado, redirigir al login y detener ejecución.
        //    Este comportamiento evita que el resto del front controller se ejecute.
        if (!$user) {
            self::to($loginPath);
        }
        // Si hay usuario, continuar normalmente (no retorna nada).
    }

    /**
     * Comprueba que el usuario autenticado tiene permiso para el menú indicado.
     *
     * Flujo interno:
     *  1) Llama a self::requireLogin() — si no hay sesión, el usuario ya será redirigido a login.
     *  2) Usa Access::check($pdo, $menuKey) para comprobar permiso server-side.
     *  3) Si la comprobación falla, redirige a $fallbackPath y termina.
     *
     * Diseño:
     *  - Útil en front controllers para impedir acceso a secciones completas (p. ej. usuarios.php).
     *  - No debe sustituir las comprobaciones dentro de controladores/acciones concretas: cada acción
     *    debe volver a validar permisos antes de modificar datos.
     *
     * @param PDO $pdo Conexión a la BD.
     * @param string $menuKey Clave de menú según App\Utils\Access (ej. Access::M_USUARIOS).
     * @param string $fallbackPath Ruta a la que redirigir si falta permiso (por defecto '/index.php').
     * @return void
     */
    public static function requirePermission(PDO $pdo, string $menuKey, string $fallbackPath = '/index.php'): void
    {
        // 1) Garantizar sesión y usuario: si no hay sesión, requireLogin hará la redirección a login.
        self::requireLogin($pdo);

        // 2) Validar permiso server-side usando el helper centralizado Access.
        //    Access::check() toma el usuario actual y comprueba si tiene acceso al menuKey.
        if (!Access::check($pdo, $menuKey)) {
            // 3) Si no está autorizado, redirigir a una ruta segura (panel principal por defecto).
            self::to($fallbackPath);
        }
        // Si tiene permiso, continuar la ejecución normalmente.
    }

    /**
     * Envía una cabecera Location y termina la ejecución.
     *
     * Detalles implementativos y consideraciones:
     *  - Se utiliza header('Location: ...') con código 302 implícito.
     *  - Se llama a exit inmediatamente para asegurarse de que no se ejecute código adicional.
     *  - Debe invocarse antes de que se hayan enviado datos al cliente (headers already sent).
     *  - Si se requiere un código HTTP distinto (ej. 303 o 301), la llamada se puede adaptar aquí.
     *
     * @param string $path URL o ruta donde redirigir.
     * @return void No retorna.
     */
    public static function to(string $path): void
    {
        // Emitir header de redirección.
        header('Location: ' . $path);
        // Terminar ejecución para evitar efectos laterales.
        exit;
    }
}