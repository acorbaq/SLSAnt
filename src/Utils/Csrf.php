<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Class Csrf
 *
 * Helper minimal para protección CSRF basado en un token guardado en la sesión.
 *
 * Objetivos:
 *  - Generar un token aleatorio seguro por sesión.
 *  - Validar tokens enviados en formularios POST para evitar CSRF.
 *  - Mantener API simple adecuada para entornos locales y formularios tradicionales.
 *
 * Comportamiento clave y notas:
 *  - El token se guarda en $_SESSION['csrf_token'] y se reutiliza mientras dure la sesión.
 *  - La generación usa random_bytes() + bin2hex() para entropía criptográfica.
 *  - La validación usa hash_equals() para evitar timing attacks.
 *  - El helper no consume/rota automáticamente el token tras validación (para UX local).
 *    Si se desea mayor seguridad, implementar rotación o tokens por formulario.
 *
 * Uso:
 *  - En formularios: <input type="hidden" name="csrf" value="<?= Csrf::generateToken() ?>">
 *  - En el procesamiento: Csrf::validateToken($_POST['csrf'])
 *
 * @package SLSAnt\Utils
 */
class Csrf
{
    /** Nombre de la variable de sesión usada para almacenar el token */
    private const SESSION_KEY = 'csrf_token';

    /**
     * Inicializa la sesión si no está activa.
     *
     * Detalle de flujo:
     *  - Comprueba session_status() para evitar warnings si la sesión ya está iniciada.
     *  - Llamar a este método antes de leer/escribir $_SESSION garantiza entorno consistente.
     *
     * @return void
     */
    public static function init(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // session_start() crea la cookie de sesión si no existe.
            session_start();
        }
    }

    /**
     * Genera o devuelve el token CSRF asociado a la sesión actual.
     *
     * Lógica paso a paso:
     *  1. Asegura que la sesión está iniciada (self::init()).
     *  2. Si no existe un token en la sesión, genera 32 bytes aleatorios con random_bytes().
     *     - random_bytes() proporciona entropía criptográfica adecuada.
     *  3. Convierte los bytes a texto hexadecimal con bin2hex() para uso en formularios/headers.
     *  4. Devuelve el token (se almacena en sesión para futuras validaciones).
     *
     * Consideraciones:
     *  - El token es por sesión; no por formulario. Para máxima seguridad usar token por formulario.
     *  - Longitud resultante: 64 caracteres hex (32 bytes * 2).
     *
     * @return string Token CSRF (hex)
     * @throws \Exception Si random_bytes falla
     */
    public static function generateToken(): string
    {
        self::init();

        // Si ya hay token en la sesión, devolverlo (evita regenerar en cada petición).
        if (!empty($_SESSION[self::SESSION_KEY])) {
            return (string) $_SESSION[self::SESSION_KEY];
        }

        // Generar token criptográfico y almacenarlo en sesión.
        // random_bytes puede lanzar Exception si no hay suficiente entropía.
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * Valida un token CSRF recibido frente al token guardado en sesión.
     *
     * Flujo de validación:
     *  1. Inicia sesión si es necesario.
     *  2. Comprueba que tanto el token recibido como el token en sesión existan.
     *  3. Usa hash_equals() para comparar de forma segura (mitiga timing attacks).
     *  4. Retorna true si coinciden, false en cualquier otro caso.
     *
     * Comportamiento adicional:
     *  - El token NO se elimina ni rota tras la validación en esta implementación.
     *    Si prefieres consumir tokens (one‑time), hacer unset($_SESSION[self::SESSION_KEY])
     *    tras una validación exitosa.
     *
     * @param string|null $token Token recibido desde formulario/header
     * @return bool True si el token es válido; false si no
     */
    public static function validateToken(?string $token): bool
    {
        self::init();

        // Si falta cualquiera de los tokens, la validación falla rápidamente.
        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        // hash_equals evita comparaciones vulnerables a timing attacks.
        return hash_equals($_SESSION[self::SESSION_KEY], (string) $token);
    }
}
