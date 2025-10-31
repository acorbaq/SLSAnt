<?php
declare(strict_types=1);

/**
 * elaborados.php
 *
 * Front controller para la sección "Elaborados" (recetas / escandallos).
 *
 * Propósito
 * - Punto de entrada HTTP para todas las operaciones relacionadas con
 *   elaborados: listar, crear, editar, borrar, visualizar detalles.
 *
 * Contrato / dependencias (proporcionadas por bootstrap):
 *  - $pdo (PDO) debe estar disponible tras require de src/bootstrap.php.
 *  - Helpers/AppUtils: Auth, Redirect, y (opcional) el controller específico.
 *
 * Flujo general / responsabilidades
 * 1. Cargar bootstrap: autoload, lectura de .env, creación de $pdo y constantes.
 * 2. Inicializar la sesión y mecanismo de autenticación (Auth::initSession()).
 * 3. Forzar que el usuario esté autenticado (Redirect::requireLogin):
 *    - Si no hay sesión válida, la utilidad suele redirigir al login y terminar
 *      la ejecución.
 * 4. Obtener datos del usuario autenticado (Auth::user) para usar en vistas/permiso.
 * 5. Preparar metadatos de la vista (ej. $titleSection) y renderizar los parciales
 *    comunes (head, nav).
 * 6. Delegar la lógica de negocio a un controller (comentado): el controller
 *    debe procesar GET/POST, validar CSRF, permisos y persistir cambios.
 *
 * Seguridad / notas
 * - No realizar validaciones sensibles en la vista; el controller debe encargarse.
 * - Usar el $pdo proporcionado por bootstrap; no crear nuevas conexiones aquí.
 * - Mantener la inclusión de head.php antes que cualquier salida <body> para
 *   evitar problemas de cabeceras y asegurar que $titleSection y $debug
 *   estén definidos para las vistas parciales.
 *
 * Recomendaciones de implementación
 * - Instanciar y usar App\Controllers\ElaboradoController para separar responsabilidades.
 * - Pasar explícitamente al controller las dependencias ($pdo, $user, $debug).
 * - Evitar mezclar lógica de negocio en este archivo: solo orquestación y render.
 *
 * Ejemplo de contrato para el controller (informal):
 *  $controller = new ElaboradoController($pdo, $user, $debug);
 *  $controller->handleRequest(); // procesa GET/POST y require vistas parciales
 *
 * @package SLSAnt
 * @author ...
 * @license MIT
 */

require_once __DIR__ . '/../src/bootstrap.php'; // carga autoload, .env, define APP_DEBUG, crea $pdo

use App\Utils\Auth;
use App\Utils\Redirect;
use App\Controllers\LotesController; 

// Iniciar/asegurar la sesión PHP y mecanismos de auth (cookies, token, etc.)
Auth::initSession();

// Forzar autenticación: si no hay usuario logueado esta función debe redirigir al login.
// Nota: requireLogin típicamente finaliza la ejecución (exit/throw) después de redirigir.
Redirect::requireLogin($pdo);

// A partir de este punto, y si no se ha redirigido, el usuario está autenticado.
// Auth::user() debe devolver la representación del usuario (array o objeto) o null.
$user = Auth::user($pdo);

// Metadatos para la vista: título que usará layouts/head.php
$titleSection = 'Lotes - SLSAnt';

// Incluir parciales de layout. head.php debe imprimir <head> y abrir <body>.
// head.php también normaliza variables como $debug si no están definidas.
require_once __DIR__ . '/views/layouts/head.php';
require_once __DIR__ . '/views/layouts/nav.php';

/**
 * Delegación a controller
 *
 * Aquí conviene instanciar el controller responsable de la sección.
 * El controller debe:
 *  - Analizar $_GET / $_POST / $_SERVER['REQUEST_METHOD']
 *  - Validar CSRF tokens en mutaciones
 *  - Validar permisos (roles, ownership)
 *  - Preparar datos para la vista y requerir la vista correspondiente
 *
 * Ejemplo (descomentar cuando exista la clase):
 *
 *   $controller = new ElaboradoController($pdo, $user, APP_DEBUG);
 *   $controller->handleRequest();
 *
 * En este archivo NO se debe implementar la lógica de negocio; sólo la orquestación.
 */

$controller = new LotesController($pdo);
$controller->handleRequest(); // GET/POST - delegar comportamiento aquí
