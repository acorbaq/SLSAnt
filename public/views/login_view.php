<?php
declare(strict_types=1);

/**
 * login_view.php
 *
 * Plantilla de presentación para el formulario de acceso (login).
 *
 * Contracto / variables esperadas (inyectadas por el controlador):
 *  - array  $errors   => lista de mensajes de error a mostrar (puede estar vacía).
 *  - string $oldUser  => valor previo del campo "user" para repoblar el formulario.
 *  - string $csrf     => token CSRF generado por Csrf::generateToken().
 *
 * Responsabilidad:
 *  - Renderizar la interfaz (HTML) sin realizar lógica de negocio ni accesos a la BD.
 *  - Escapar toda salida procedente del usuario para prevenir XSS.
 *  - Incluir el token CSRF en un campo oculto; el controlador validará dicho token.
 *
 * Notas de seguridad y accesibilidad:
 *  - No imprimir campos sensibles (password).
 *  - Usar htmlentities() con ENT_QUOTES y codificación UTF-8 para escapar salidas.
 *  - Mantener atributos HTML5 (required) para mejora UX, la validación real es servidor.
 *  - El formulario usa POST (no GET) porque cambia estado (autenticación).
 *
 * Tailwind:
 *  - Esta plantilla aplica utilidades Tailwind para el layout y estilos.
 *  - El CSS resultante debe compilarse a public/assets/css/app.css (npm / tailwind).
 *
 * @package SLSAnt\View
 */

 // Normalizar variables para evitar "undefined variable" en caso de llamadas directas.
$errors  = $errors  ?? [];
$oldUser = $oldUser ?? '';
$csrf    = $csrf    ?? '';

$titleSection = 'Acceso a SLSAnt';
include_once __DIR__ . '/layouts/head.php';
?>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <main class="w-full max-w-md mx-auto p-6">
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
      <div class="p-6">
        <h1 class="text-2xl font-semibold text-gray-800 mb-4">Acceso a SLSAnt</h1>

        <?php if ($errors): ?>
          <!--
            Presentación de errores:
            - El controlador debe poblar $errors con mensajes amigables.
            - Cada mensaje se escapa para evitar inyección de HTML.
            - Se muestra un contenedor visual accesible (contraste y estructura de lista).
          -->
          <div class="mb-4 bg-red-50 border border-red-200 text-red-700 p-3 rounded" role="alert" aria-live="polite">
            <ul class="list-disc list-inside text-sm">
              <?php foreach ($errors as $e): ?>
                <li><?php echo htmlentities((string)$e, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <!--
          Formulario de login:
          - method="post" porque es una operación que modifica estado (inicio de sesión).
          - action apunta al front controller /login.php; el controlador realiza la validación.
          - autocomplete="off" reduce autocompletado en contextos locales; no sustituye validación.
        -->
        <form method="post" action="/login.php" autocomplete="off" class="space-y-4" novalidate>
          <!-- Campo oculto CSRF: imprescindible para prevenir CSRF -->
          <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

          <div>
            <label for="user" class="block text-sm font-medium text-gray-700 mb-1">Usuario o email</label>
            <!--
              Campo "user":
              - Se repuebla con $oldUser para mantener la entrada del usuario en caso de error.
              - El valor se escapa para evitar XSS reflejado.
              - required para validación básica del navegador; la validación definitiva es servidor.
            -->
            <input id="user" name="user" type="text" required
                   value="<?php echo htmlentities($oldUser, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent"
                   aria-label="Usuario o email" />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
            <!--
              Campo "password":
              - Tipo password para ocultar la entrada en la UI.
              - Nunca se repuebla desde el servidor por razones de seguridad.
            -->
            <input id="password" name="password" type="password" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-transparent"
                   aria-label="Contraseña" />
          </div>

          <div class="flex items-center justify-between">
            <!-- Placeholder para enlaces adicionales (recuperar contraseña, recuerdame) si se añaden -->
            <div class="text-sm text-gray-600" aria-hidden="true"></div>

            <!-- Botón principal -->
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-teal-500 hover:bg-teal-600 text-white text-sm font-medium rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-400"
                    aria-pressed="false">
              Entrar
            </button>
          </div>
        </form>
      </div>
    </div>

    <footer class="text-center text-xs text-gray-500 mt-4" role="contentinfo" aria-hidden="false">
      <p>SLSAnt — Entorno local</p>
    </footer>
  </main>
</body>
</html>