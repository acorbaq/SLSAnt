<?php
declare(strict_types=1);

/**
 * usuarios.php
 *
 * Front controller ligero para el menú "Usuarios".
 *
 * Responsabilidades:
 *  - Punto de entrada HTTP para la gestión de usuarios (panel de visualización/acciones).
 *  - Cargar el entorno (bootstrap) y garantizar que las utilidades (Auth, Access, Redirect, Csrf)
 *    y la conexión a BD ($pdo) están disponibles.
 *  - Forzar que el visitante esté autenticado y tenga permiso para ver esta sección.
 *  - Delegar la lógica de negocio y el manejo de la petición (GET/POST) a App\Controllers\UserController.
 *
 * Diseño y buenas prácticas:
 *  - Este archivo no contiene lógica de negocio ni acceso directo a la BD: solo comportamiento HTTP y
 *    verificación de permisos. El controlador y el modelo realizan la lógica real.
 *  - Todas las acciones destructivas (crear, borrar, asignar roles) se procesan por POST y deben validar CSRF.
 *  - Todas las comprobaciones de permiso se repiten servidor‑side: la vista puede ocultar botones, pero
 *    la seguridad se garantiza aquí y en el controlador/modelo.
 *
 * Flujo general:
 *  1) require bootstrap: configura autoload, .env, y devuelve $pdo (la conexión DB).
 *  2) Auth::initSession(): asegura parámetros de sesión y llama session_start() si hace falta.
 *  3) Redirect::requireLogin($pdo): si no hay sesión activa redirige a /login.php y termina.
 *  4) Redirect::requirePermission($pdo, Access::M_USUARIOS): si el usuario autenticado no tiene permiso
 *     para ver este menú redirige a /index.php (o devuelve 403 según política).
 *  5) Instanciar App\Controllers\UserController e invocar ->handleRequest() para atender GET/POST.
 *
 * Variables/contrato esperado por otras capas:
 *  - bootstrap.php debe dejar /** @var PDO $pdo *\/ disponible en este scope.
 *  - UserController renderiza la vista public/views/usuarios_view.php y le pasa las variables necesarias.
 *
 * Seguridad:
 *  - No imprimir datos sensibles (por ejemplo password) en la vista.
 *  - Validar CSRF en todas las acciones POST (esto lo hace el controlador).
 *  - Proteger este front controller con requirePermission para evitar acceso no autorizado.
 *
 * @package SLSAnt\Public
 */

require_once __DIR__ . '/../src/bootstrap.php';
/** @var PDO $pdo */

use App\Utils\Auth;
use App\Utils\Redirect;
use App\Utils\Access;
use App\Controllers\UserController;

// 1) Inicializar sesión segura (configuración de cookies, session_start si procede).
Auth::initSession();

// 2) Forzar que el usuario esté autenticado.
//    - Si no hay usuario, Redirect::requireLogin() enviará Location: /login.php y terminará la ejecución.
Redirect::requireLogin($pdo);

// 3) Verificar que el usuario autenticado tiene permiso para acceder al menú Usuarios.
//    - RequirePermission internamente asegura login y luego comprueba Access::check(...).
//    - Si no está autorizado redirige a /index.php (o la ruta fallback configurada).
Redirect::requirePermission($pdo, Access::M_USUARIOS);

$titleSection = 'Usuarios - SLSAnt';

require_once __DIR__ . '/views/layouts/head.php'; // Incluir head común con CSS/JS
require_once __DIR__ . '/views/layouts/nav.php'; // Incluir barra de navegación común
// 4) Delegar el manejo completo de la petición al controlador.
//    - UserController debe validar método HTTP, CSRF en POST y ejecutar las acciones necesarias.
//    - En GET renderizará la vista con la tabla de usuarios y los controles adecuados según rol.
$controller = new UserController($pdo);
$controller->handleRequest();