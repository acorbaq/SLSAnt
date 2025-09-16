<?php
declare(strict_types=1);

/**
 * login.php
 *
 * Front controller ligero para la página de login.
 *
 * Propósito:
 *  - Actuar como punto de entrada HTTP para la ruta /login.php.
 *  - Mantener este archivo libre de lógica de negocio: delega todo al AuthController.
 *
 * Requisitos previos (expectativas):
 *  - src/bootstrap.php debe ejecutar el autoload de Composer y devolver una instancia PDO.
 *    Se asume que al final de bootstrap.php hay: return $pdo;
 *  - Existe la clase App\Controllers\AuthController que recibe PDO en su constructor
 *    y expone un método handleRequest() que procesa la petición (GET/POST).
 *
 * Variables exportadas / contractos:
 *  - Tras `require_once '../src/bootstrap.php'` se espera que haya disponible la variable
 *    $pdo (instancia de PDO). Se documenta con @var para editores/IDE.
 *
 * Flujo general y responsabilidades por paso:
 *  1) Cargar bootstrap:
 *     - Carga autoload, Dotenv, configura/abre la conexión a la BD y devuelve $pdo.
 *     - Cualquier error en bootstrap debe detener la ejecución antes de instanciar el controlador.
 *  2) Instanciar el controlador de autenticación (AuthController):
 *     - Se inyecta la dependencia $pdo para que el controlador realice consultas.
 *     - Inyección explícita facilita testing y separación de responsabilidades.
 *  3) Delegar la petición:
 *     - Llamada a $controller->handleRequest() que:
 *         * Detecta método HTTP (GET/POST).
 *         * Si GET, prepara datos y muestra la vista.
 *         * Si POST, valida CSRF, procesa credenciales, maneja sesión y realiza redirección.
 *  4) No hay lógica de render/validación aquí: todo ello está en el controlador/Views/Models.
 *
 * Consideraciones de seguridad y mantenimiento:
 *  - Mantener este archivo mínimo reduce la superficie de errores y facilita el routing.
 *  - Cualquier excepción no capturada en bootstrap o en el controlador debe manejarse
 *    preferentemente en un middleware o en un handler global; aquí no se capturan.
 *  - Para entornos de producción, validar/gestionar errores y logs en bootstrap/Controller.
 *
 * Ejemplo de uso:
 *  - Acceder desde navegador a http://localhost:8000/login.php
 *  - El AuthController se encargará de mostrar el formulario o procesar el POST.
 *
 * @package SLSAnt\Public
 */

require_once __DIR__ . '/../src/bootstrap.php';
/** @var PDO $pdo */

use App\Controllers\AuthController;

/*
 * Instanciación e invocación del controlador.
 * - El controlador realiza todo el trabajo: renderizar vista o procesar login.
 * - Mantener esta llamada en top-level facilita su ejecución desde el servidor embebido (php -S).
 */
$controller = new AuthController($pdo);
$controller->handleRequest();