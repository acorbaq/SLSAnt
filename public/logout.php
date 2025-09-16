<?php
declare(strict_types=1);
/**
 * logout.php
 *
 * Front controller ligero para la acción de logout.
 *
 * Propósito:
 *  - Punto de entrada HTTP para cerrar sesión (logout) de la aplicación.
 *  - Mantener la responsabilidad HTTP aquí (validación de método, inclusión de bootstrap)
 *    y delegar la lógica de negocio (validación CSRF, limpieza de sesión, redirección)
 *    al AuthController en src/.
 *
 * Expectativas / contrato:
 *  - src/bootstrap.php debe cargar el autoload y devolver una instancia PDO al final:
 *      return $pdo;
 *  - AuthController::logout() manejará la validación del método HTTP/CSRF y la limpieza de sesión.
 *
 * Seguridad / recomendaciones:
 *  - Logout debe hacerse por POST (no por GET) y validarse con token CSRF para evitar ataques CSRF.
 *  - Este front controller es intencionalmente minimal: no realiza lógica sensible aquí.
 *
 * @package SLSAnt\Public
 */

 // 1) Cargar bootstrap: autoload, configuración y conexión a BD (si el controlador la necesita).
require_once __DIR__ . '/../src/bootstrap.php';
/** @var PDO $pdo */

use App\Controllers\AuthController;

/*
 * Flujo de ejecución (en este archivo):
 *  - Se instancia el controlador de autenticación inyectando la dependencia $pdo.
 *  - Se delega la acción de logout al controlador ($controller->logout()).
 *
 * El AuthController es responsable de:
 *  - Comprobar que la petición es POST (si así se decidió) y validar el token CSRF.
 *  - Ejecutar Auth::logout() (o lógica equivalente) para eliminar $_SESSION['user_id']
 *    y regenerar el id de sesión.
 *  - Emitir la redirección final (p. ej. a /login.php).
 *
 * Motivo de delegar al controlador:
 *  - Mantener separación de responsabilidades (HTTP front controllers mínimos, lógica en src/).
 *  - Facilitar testing y reutilización (p. ej. si logout necesita limpiar otros recursos).
 */

// Instanciar controlador y delegar la operación.
// Nota: el constructor espera $pdo según el contrato de la aplicación.
$controller = new AuthController($pdo);

// Delegamos la responsabilidad completa de logout al controlador.
// El método debe validar método/CSRF y realizar la redirección apropiada.
// Si el controlador lanza excepciones no capturadas, se propagará y debería manejarse en un handler global.
$controller->logout();