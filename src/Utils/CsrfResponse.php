<?php

declare(strict_types=1);

namespace App\Utils;

class CsrfResponse
{
    /**
     * Responder con error CSRF y terminar ejecución
     *
     * @param string $format 'json' o 'html'
     * @return void (nunca retorna)
     */
    public static function invalid(string $format = 'json'): void
    {
        http_response_code(403);
        
        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'csrf_invalid',
                'message' => 'Token CSRF inválido. Recargue la página e intente nuevamente.'
            ]);
        } else {
            echo '<!DOCTYPE html>
<html>
<head><title>Error 403</title></head>
<body>
    <h1>403 Forbidden</h1>
    <p>Token CSRF inválido. <a href="javascript:history.back()">Volver</a></p>
</body>
</html>';
        }
        exit;
    }

    /**
     * Validar y responder si es inválido
     *
     * @param string|null $token
     * @param string $format
     * @return void (solo retorna si es válido)
     */
    public static function validateOrDie(?string $token, string $format = 'json'): void
    {
        if (!Csrf::validateToken($token ?? '')) {
            self::invalid($format);
        }
    }
}