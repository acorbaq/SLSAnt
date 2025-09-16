<?php
declare(strict_types=1);
/**
 * Front controller para la página principal.
 * Carga bootstrap, obtiene estado de sesión y delega la renderización a la vista.
 */

require_once __DIR__ . '/../src/bootstrap.php';
/** @var PDO $pdo */

use App\Utils\Auth;

Auth::initSession();
$user = Auth::user($pdo);

// Incluir la barra de navegación compartida
require __DIR__ . '/views/layouts/nav.php';

// Renderizar la vista principal (separada en public/views)
require __DIR__ . '/views/index_view.php';