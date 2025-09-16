<?php
declare(strict_types=1);
/**
 * index.php
 *
 * Front controller para la página principal (panel).
 *
 * Responsabilidades añadidas:
 *  - Garantizar que el usuario está autenticado antes de renderizar el panel.
 *  - Comprobar que el usuario tiene acceso a al menos un menú; si no, devolver un 403
 *    o redirigir según la política definida.
 *
 * Flujo / lógica:
 *  1) Cargar bootstrap para disponer de autoload y $pdo.
 *  2) Inicializar la sesión segura mediante Auth::initSession().
 *  3) Forzar autenticación con Redirect::requireLogin($pdo) -> redirige a /login.php si no hay sesión.
 *  4) Consultar los menús permitidos para el usuario con Access::menusForUser().
 *  5) Si la lista de menús permitidos está vacía, terminar con 403 (o redirigir a una ruta segura).
 *  6) Incluir parcial de navegación y delegar renderizado a la vista index_view.php.
 *
 * Notas de seguridad:
 *  - Todas las comprobaciones de permisos deben repetirse servidor‑side en cada endpoint.
 *  - Evitar confiar únicamente en la visibilidad en la UI; proteger acciones en controllers.
 */

require_once __DIR__ . '/../src/bootstrap.php';
/** @var PDO $pdo */

use App\Utils\Auth;
use App\Utils\Redirect;
use App\Utils\Access;

// Asegurar que la sesión está inicializada y que Auth puede leer $_SESSION.
Auth::initSession();

// Forzar login: si no hay usuario autenticado Redirect::requireLogin() redirige a /login.php y termina.
Redirect::requireLogin($pdo);

// Obtener usuario actual (garantizado que existe tras requireLogin)
$user = Auth::user($pdo);

// Calcular los menús permitidos para el usuario actual consultando la BD (users_roles -> roles).
// Esto permite decidir si el usuario "puede estar en este menú" (panel principal).
$allowedMenus = [];
if ($user && isset($user['id'])) {
    $allowedMenus = Access::menusForUser($pdo, (int)$user['id']);
}

// Política: si el usuario no tiene ningún menú permitido considerarlo sin acceso al panel.
// Alternativas posibles: redirigir a una página de "sin permisos" distinta, o mostrar aviso en la vista.
if (empty($allowedMenus)) {
    http_response_code(403);
    // Respuesta mínima y segura: evitar renderizar el panel. (Se puede reemplazar por una vista dedicada).
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceso denegado</title></head><body style="font-family:system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;max-width:800px;margin:3rem auto;padding:1rem"><h1>Acceso denegado</h1><p>No tiene permisos para acceder al panel. Contacte con el administrador.</p><p><a href="/logout.php">Salir</a></p></body></html>';
    exit;
}

// Incluir la barra de navegación compartida y la vista principal.
// Nota: index_view.php espera que $user / $pdo estén disponibles en este scope.
require __DIR__ . '/views/layouts/nav.php';
require __DIR__ . '/views/index_view.php';