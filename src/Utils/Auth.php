<?php
declare(strict_types=1);

namespace App\Utils;

use PDO;
use RuntimeException;

/**
 * Class Auth
 *
 * Helper mínimo para autenticación y gestión de sesión en entorno local.
 *
 * Objetivos:
 *  - Proveer operaciones básicas de login (attempt), obtener usuario actual (user) y logout.
 *  - Centralizar el manejo de sesión (configuración de cookies y regeneración de id).
 *  - Usar prácticas seguras: password_hash()/password_verify(), prepared statements y session_regenerate_id().
 *
 * Comportamiento general y flujo:
 *  - Todas las funciones llaman a initSession() para asegurar que la sesión PHP está activa
 *    y configurada con parámetros seguros para entorno local (httponly, samesite).
 *  - attempt():
 *      1) busca al usuario por username o email usando una consulta preparada.
 *      2) verifica que el usuario existe y está activo.
 *      3) compara la contraseña usando password_verify().
 *      4) si la autenticación es correcta guarda user_id en la sesión y regenera el id de sesión.
 *      5) retorna un array con los datos del usuario (sin el password) o null si falla.
 *  - user():
 *      - Si hay user_id en la sesión consulta la tabla users y devuelve sus datos.
 *  - logout():
 *      - Borra user_id de la sesión y regenera el id de sesión para mitigar secuestro de sesión.
 *
 * Notas de diseño y seguridad:
 *  - No se almacenan contraseñas en memoria o logs; sólo hashes en la BD y comparaciones con password_verify().
 *  - session_regenerate_id(true) se usa tras login y tras logout parcial para reducir ventana de session fixation.
 *  - is_active permite deshabilitar cuentas sin borrarlas.
 *  - En producción ajustar session_set_cookie_params() para usar 'secure' => true y cookies sobre HTTPS.
 *
 * @package SLSAnt\Utils
 */
class Auth
{
    /**
     * Inicia la sesión si no está activa y aplica parámetros de cookie seguros por defecto.
     *
     * Detalles:
     *  - Comprueba session_status() para evitar warnings si la sesión ya está iniciada.
     *  - En desarrollo se pone 'secure' => false para permitir uso sobre HTTP local.
     *    Cambiar a true en producción con HTTPS.
     *
     * @return void
     */
    public static function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuración de cookies: HttpOnly para evitar acceso desde JS,
            // SameSite=Lax reduce riesgo CSRF en peticiones normales, secure depende del entorno.
            session_set_cookie_params([
                'httponly' => true,
                'secure' => false, // cambiar a true en producción con HTTPS
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    /**
     * Intenta autenticar un usuario por username o email y contraseña.
     *
     * Flujo detallado:
     *  - Asegura la sesión (initSession).
     *  - Consulta preparada para buscar usuario por username o email.
     *  - Si no existe o is_active == 0 devuelve null.
     *  - Verifica la contraseña con password_verify().
     *  - Si es correcta:
     *      * guarda $_SESSION['user_id'] con el id del usuario (int)
     *      * llama session_regenerate_id(true) para mitigar session fixation
     *      * elimina el campo password del array devuelto
     *
     * Retorno:
     *  - array asociativo (id, username, email, is_active) en caso de éxito
     *  - null en caso de fallo (usuario no encontrado / inactivo / password incorrecta)
     *
     * @param PDO $pdo Conexión PDO a la base de datos (SQLite)
     * @param string $userOrEmail Username o email
     * @param string $password Contraseña en texto plano
     * @return array|null Datos del usuario (sin password) o null si la autenticación falla
     */
    public static function attempt(PDO $pdo, string $userOrEmail, string $password): ?array
    {
        self::initSession();

        // Consulta parametrizada: evita inyección SQL.
        $sql = "SELECT id, username, email, password, is_active FROM users WHERE username = :u OR email = :u LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':u' => $userOrEmail]);

        // Obtener fila; fetch devuelve false si no hay resultado.
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            // Usuario no encontrado
            return null;
        }

        // Comprobar que la cuenta está activa (flag is_active)
        if (!(int)$row['is_active']) {
            return null;
        }

        // Verificar contraseña usando password_verify() contra el hash almacenado.
        if (!password_verify($password, $row['password'])) {
            return null;
        }

        // Autenticación correcta: persistir en sesión y regenerar id de sesión.
        $_SESSION['user_id'] = (int) $row['id'];
        session_regenerate_id(true);

        // No devolver el hash de la contraseña al llamador.
        unset($row['password']);

        return $row;
    }

    /**
     * Devuelve el usuario actualmente autenticado según $_SESSION['user_id'].
     *
     * Flujo:
     *  - Asegura la sesión.
     *  - Si no existe user_id en la sesión devuelve null.
     *  - Consulta la tabla users por id y devuelve columnas seguras al consumidor.
     *
     * Uso típico: mostrar nombre en la UI o comprobar permisos en controladores.
     *
     * @param PDO $pdo Conexión PDO
     * @return array|null Datos del usuario o null si no hay sesión válida
     */
    public static function user(PDO $pdo): ?array
    {
        self::initSession();

        if (empty($_SESSION['user_id'])) {
            return null;
        }

        // Recuperar datos públicos del usuario (sin password)
        $stmt = $pdo->prepare(
            "SELECT id, username, email, is_active, created_at, last_login FROM users WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => (int) $_SESSION['user_id']]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        return $u === false ? null : $u;
    }

    /**
     * Cierra la sesión de autenticación.
     *
     * Flujo:
     *  - Asegura la sesión.
     *  - Elimina la identificación de usuario de la sesión.
     *  - Regenera el id de sesión para invalidar la sesión previa en el cliente.
     *
     * Nota:
     *  - Este método no destruye completamente todos los datos de sesión. Si se requiere
     *    limpieza total, llamar session_unset() y session_destroy() según la lógica de la app.
     *
     * @return void
     */
    public static function logout(): void
    {
        self::initSession();

        // Quitar la referencia al usuario en la sesión
        unset($_SESSION['user_id']);

        // Regenerar id de sesión para reducir riesgo de reutilización de session id.
        session_regenerate_id(true);
    }
}