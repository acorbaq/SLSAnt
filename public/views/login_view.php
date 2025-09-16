<?php
declare(strict_types=1);
/**
 * View: login_view.php
 *
 * Plantilla responsable de renderizar el formulario de acceso.
 *
 * Variables esperadas (inyectadas por el controlador):
 *  - array  $errors   : Lista de mensajes de error a mostrar (puede estar vacía).
 *  - string $oldUser  : Valor previo del campo "user" para repoblar formulario.
 *  - string $csrf     : Token CSRF generado y almacenado en la sesión.
 *
 * Comportamiento / flujo:
 *  - La vista no realiza lógica de negocio ni accesos directos a la BD.
 *  - Escapa todas las salidas que provienen del usuario con htmlentities() para evitar XSS.
 *  - Incluye un campo oculto "csrf" que debe coincidir con el token almacenado en la sesión;
 *    el controlador valida este token antes de procesar el POST.
 *
 * Seguridad:
 *  - No imprime ni manipula contraseñas.
 *  - Usa atributos HTML5 (required) para validación básica en cliente; la validación
 *    real se realiza en el servidor (en el controlador).
 *
 * Responsabilidad: presentar datos y delegar validaciones y acciones al controlador.
 */

 // Normalizar variables para evitar "undefined variable" en la plantilla.
$errors  = $errors  ?? [];
$oldUser = $oldUser ?? '';
$csrf    = $csrf    ?? '';

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - SLSAnt</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="container" style="max-width:480px;margin:4rem auto;">
  <h1>Acceso</h1>

  <?php
  // Si el controlador ha pasado errores, los mostramos en una lista.
  // Cada mensaje se escapa con htmlentities() para prevenir XSS si contienen texto no confiable.
  if ($errors): ?>
    <div style="color:#b00020">
      <ul>
        <?php
        // Iteración segura: se asume $errors es un array de strings.
        foreach ($errors as $e) {
            echo "<li>" . htmlentities((string)$e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>";
        }
        ?>
      </ul>
    </div>
  <?php endif; ?>

  <!--
    Formulario de login:
    - method="post" porque cambia estado (autenticación).
    - action apunta a /login.php (front controller), que delega al AuthController.
    - autocomplete="off" para evitar que el navegador sugiera contraseñas en contextos locales,
      aunque esto es solo una ayuda de UX y no sustituye la seguridad del servidor.
  -->
  <form method="post" action="/login.php" autocomplete="off">
    <!--
      Campo oculto CSRF:
      - Debe contener exactamente el token generado por Csrf::generateToken().
      - El controlador usará Csrf::validateToken() para rechazar peticiones no autorizadas.
    -->
    <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

    <div>
      <label>Usuario o email</label>
      <!--
        Campo "user":
        - Se repuebla con $oldUser para mantener la entrada en caso de errores.
        - Se escapa con htmlentities() al renderizar.
      -->
      <input name="user" type="text" required value="<?php echo htmlentities($oldUser, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    </div>

    <div>
      <label>Contraseña</label>
      <!--
        Campo "password":
        - Tipo password para ocultar entrada en la UI.
        - Nunca se repuebla ni se muestra su valor.
      -->
      <input name="password" type="password" required>
    </div>

    <div style="margin-top:1rem">
      <button type="submit">Entrar</button>
    </div>
  </form>
</div>
</body>
</html>