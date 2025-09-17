<?php
declare(strict_types=1);
/**
 * ingredientes.php
 *
 * Front controller para la sección "Ingredientes" / "Alérgenos".
 *
 * Contrato / responsabilidades:
 * - Cargar el bootstrap de la aplicación (autoloader, configuración, $pdo).
 * - Inicializar la sesión/authentication mínima necesaria.
 * - Incluir la navegación común de la UI.
 * - Instanciar App\Controllers\IngredienteController y delegar el manejo de la petición.
 *
 * Variables / dependencias esperadas:
 * - __DIR__ . '/../src/bootstrap.php' debe definir e inicializar $pdo (PDO) y el autoload.
 * - App\Controllers\IngredienteController debe existir y exponer handleRequest(): void.
 *
 * Seguridad / permisos:
 * - La lista de ingredientes es visible para todos por diseño (comentado).
 * - Las operaciones de modificación (crear/editar/eliminar) deben estar protegidas
 *   en el controlador (IngredienteController::canModify) y validadas server-side.
 * - Si se necesita forzar login globalmente, usar Redirect::requireLogin($pdo) (está comentado).
 *
 * Flujo de ejecución (alto nivel):
 * 1) Cargar bootstrap: define clases, configuración y la variable $pdo (conexión DB).
 * 2) Auth::initSession(): asegurar que $_SESSION / cookies están inicializadas.
 * 3) Incluir la navegación común para la página.
 * 4) Crear el controlador de ingredientes y llamar a handleRequest():
 *    - El controlador inspecciona $_SERVER['REQUEST_METHOD'] y $_GET/$_POST para:
 *        * GET     -> listar ingredientes, o mostrar formulario (?crear, ?modificar&id=...)
 *        * POST    -> procesar acciones (save/delete), validar CSRF y permisos
 *    - El controlador renderiza las vistas adecuadas (public/views/ingredientes_view.php, ingredientes_edit_view.php).
 *
 * Notas operativas:
 * - No se debe confiar únicamente en la UI para ocultar botones: Access::check / permisos server-side
 *   deben estar validados dentro del controlador.
 * - Este archivo es mínimo y delega la lógica real al controlador/modelo; mantenerlo así evita duplicar lógica.
 *
 * @package SLSAnt\Public
 */

require_once __DIR__ . '/../src/bootstrap.php';
// El bootstrap debe:
//  - registrar autoloaders (composer o propio)
//  - crear la conexión PDO y exponerla como $pdo
//  - cargar configuración global (constantes, entorno)

 /** @var PDO $pdo */

use App\Utils\Auth;
use App\Utils\Redirect;
use App\Controllers\IngredienteController;

// Asegurar que la sesión está inicializada y configurada (cookies seguras, session_start(), etc.)
Auth::initSession();


$titleSection = 'Ingredientes - SLSAnt';

require_once __DIR__ . '/views/layouts/head.php'; // Incluir head común con CSS/JS
// Incluir barra de navegación común (presentación). Debe ser ligera y no afectar la lógica.
require_once __DIR__ . '/views/layouts/nav.php';

// Nota sobre autenticación:
// - La vista de listado se considera pública en este diseño (por eso requireLogin está comentado).
// - Si se desea forzar login para ver la lista, descomentar la siguiente línea:
// Redirect::requireLogin($pdo);

// Delegar toda la lógica de la sección al controller específico.
// El controller se encargará de validar permisos, CSRF y renderizar vistas.
$controller = new IngredienteController($pdo);
$controller->handleRequest();