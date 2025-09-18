<?php
declare(strict_types=1);
/**
 * head.php
 *
 * ParciaL <head> reutilizable.
 *
 * Variables esperadas antes de incluir:
 *  - string $titleSection   : Título de la página (opcional, por defecto "SLSAnt").
 *  - string|array $extraHead: (Opcional) HTML extra que se inyecta dentro del <head>,
 *                            puede ser un string con tags <link>/<script> o un array de strings.
 *
 * Seguridad:
 *  - $titleSection se escapa con htmlentities para evitar XSS.
 */
$title = $titleSection ?? 'SLSAnt';
$extraHead = $extraHead ?? '';

// Normalizar $debug para compatibilidad con vistas que usan $debug variable
if (!isset($debug)) {
    if (defined('APP_DEBUG')) {
        $debug = APP_DEBUG;
    } else {
        $debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" type="image/png" href="SLSAnt.png">
  <link rel="stylesheet" href="/assets/css/app.css">
  <title><?php echo htmlentities((string)$title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></title>

  <?php
  // Inyectar CSS/JS extra si se pasó $extraHead
  if (is_array($extraHead)) {
      foreach ($extraHead as $tag) {
          echo $tag . "\n";
      }
  } else {
      echo (string)$extraHead;
  }
  ?>
</head>