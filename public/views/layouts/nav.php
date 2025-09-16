<?php
declare(strict_types=1);

use App\Utils\Auth;
use App\Utils\Csrf;
use App\Utils\Access;

/**
 * nav.php
 *
 * Parcial de navegación reutilizable que muestra el estado de autenticación
 * y el control de logout (por POST con token CSRF).
 *
 * Contracto / variables y dependencias:
 *  - Requiere que src/bootstrap.php se haya cargado anteriormente o que se incluya aquí.
 *    bootstrap debe ejecutar el autoload de Composer y dejar disponible una instancia PDO
 *    en la variable $pdo (documentada con /** @var PDO $pdo *\/).
 *  - Usa App\Utils\Auth para consultar el usuario actual y gestionar sesión.
 *  - Usa App\Utils\Csrf para generar/validar tokens CSRF.
 *
 * Responsabilidades:
 *  - Mostrar marca/nombre de la aplicación.
 *  - Si hay usuario autenticado mostrar: saludo + botón/form de logout seguro (POST + CSRF).
 *  - Si no hay sesión mostrar enlace a login.
 *
 * Diseño de seguridad:
 *  - Logout se realiza mediante un formulario POST que incluye token CSRF.
 *    Esto evita que enlaces GET remotos puedan forzar el cierre de sesión (CSRF).
 *  - Todas las salidas que provienen del usuario se escapan con htmlentities() para prevenir XSS.
 *
 * Accesibilidad / UX:
 *  - El formulario de logout es un botón sencillo; se puede convertir en un enlace con JS
 *    pero el HTML puro sin JS es más accesible.
 *
 * Ejemplo de inclusión:
 *  - En una plantilla: <?php require __DIR__ . '/layouts/nav.php'; ?>
 *
 * @package SLSAnt\View\Layouts
 */

 // Incluir bootstrap relativo a esta vista para garantizar $pdo/autoload si no se cargó ya.
 // NOTA: si el caller ya incluyó bootstrap, esta línea puede duplicarse sin problemas.
require_once __DIR__ . '/../../../src/bootstrap.php';
/** @var PDO $pdo */

 // Inicializar CSRF y sesión de forma segura antes de usar Auth/Csrf.
 // - Csrf::init() garantiza que $_SESSION existe para almacenar/recuperar el token.
 // - Auth::initSession() aplica parámetros de cookie seguros y llama session_start() si hace falta.
Csrf::init();
Auth::initSession();

/**
 * Obtener usuario actual y token CSRF.
 *
 * Flujo:
 *  1) Auth::user($pdo) lee $_SESSION['user_id'] y, si existe, recupera datos públicos del usuario.
 *  2) Csrf::generateToken() crea o devuelve el token de sesión usado por formularios.
 *
 * Importante:
 *  - El controlador que procese /logout.php debe validar el token mediante Csrf::validateToken().
 */
$user = Auth::user($pdo);
$csrf = Csrf::generateToken();
$menus = [
  Access::M_INGREDIENTES => ['label' => 'Ingredientes', 'href' => '/ingredientes.php'],
  Access::M_ELABORADOS   => ['label' => 'Elaborados',   'href' => '/elaborados.php'],
  Access::M_LOTES        => ['label' => 'Lotes',        'href' => '/lotes.php'],
  Access::M_IMPRESION    => ['label' => 'Impresión',    'href' => '/impresion.php'],
  Access::M_APPCC        => ['label' => 'APPCC',        'href' => '/appcc.php'],
  Access::M_CALIDAD      => ['label' => 'Calidad',      'href' => '/calidad.php'],
  Access::M_USUARIOS     => ['label' => 'Usuarios',     'href' => '/usuarios.php'],
];

// Obtener menús permitidos para el usuario actual consultando la BD.
// Si no hay usuario autenticado, $allowed será vacío.
$allowed = [];
if ($user) {
    $allowed = Access::menusForUser($pdo, (int)$user['id']);
}
?>
<nav class="p-4 bg-white border-b">
  <div class="max-w-6xl mx-auto flex justify-between items-center">
    <div class="flex items-center space-x-4">
    <!-- Marca / Identidad -->
    <div class="text-sm font-semibold">SLSAnt</div>

      <?php if ($allowed): ?>
        <?php foreach ($allowed as $key):
            if (!isset($menus[$key])) continue;
            $m = $menus[$key];
        ?>
          <a href="<?php echo htmlentities($m['href']); ?>" class="text-sm text-gray-700 hover:text-teal-600"><?php echo htmlentities($m['label']); ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div>
      <?php if ($user): ?>
        <?php
        // Escapar output del nombre de usuario para evitar XSS.
        $username = htmlentities((string)$user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        ?>
        <span class="text-sm mr-4" aria-live="polite">Hola, <?php echo $username; ?></span>

        <!--
          Logout seguro:
          - Se usa un formulario POST para la acción de logout.
          - Incluye un campo oculto 'csrf' con el token de sesión.
          - El front controller /logout.php debe validar CSRF y método POST antes de destruir sesión.
          - El estilo inline permite que el botón aparezca en línea junto al saludo sin contenedor adicional.
        -->
        <form method="post" action="/logout.php" style="display:inline" aria-label="Cerrar sesión">
          <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          <button type="submit" class="text-sm text-teal-600 hover:underline">Salir</button>
        </form>

      <?php else: ?>
        <!-- Usuario no autenticado: enlace a la página de login -->
        <a href="/login.php" class="text-sm text-teal-600 hover:underline">Entrar</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php
/**
 * Notas operativas adicionales (no renderizadas):
 *
 * - Inclusión en múltiples vistas:
 *     Incluir esta parcial en cualquier plantilla/top layout permite mostrar el control de sesión
 *     de forma consistente. Asegúrate de que la vista que incluye nav.php no redefina la sesión
 *     ni el $pdo de forma incompatible.
 *
 * - Validación CSRF en logout:
 *     El controlador (AuthController::logout o el front controller /logout.php) debe:
 *       1) Verificar que $_SERVER['REQUEST_METHOD'] === 'POST'.
 *       2) Leer $_POST['csrf'] y llamar Csrf::validateToken($token).
 *       3) Si válido, llamar Auth::logout(), limpiar sesión y redirigir.
 *       4) Si no válido, devolver 400/403 o redirigir con mensaje de error.
 *
 * - Alternativas:
 *     Si prefieres un enlace en lugar de un formulario, puedes usar JavaScript para hacer POST
 *     con fetch() incluyendo el token CSRF; sin embargo, la versión de formulario HTML funciona
 *     sin JS y es más robusta en entornos degradados.
 */