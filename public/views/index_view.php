<?php

declare(strict_types=1);

/**
 * index_view.php
 *
 * Vista principal del panel adaptada a permisos por rol.
 *
 * Contracto / variables esperadas (inyección desde front controller):
 *  - PDO   $pdo  : Conexión a la base de datos (debe estar disponible tras require bootstrap).
 *  - array  $user : Información del usuario autenticado (resultado de Auth::user($pdo)), o null si no hay sesión.
 *
 * Propósito / responsabilidades:
 *  - Mostrar las "cards" de acceso a secciones (menús) atendiendo a los permisos del usuario.
 *  - No ejecutar lógica de negocio compleja: delega la obtención de roles/permisos a App\Utils\Access.
 *  - Escapar toda salida de usuario para prevenir XSS.
 *
 * Flujo de ejecución (resumido):
 *  1) Define el catálogo completo de menús (etiqueta, href, descripción).
 *  2) Si hay usuario autenticado:
 *       a) obtiene los roles del usuario desde la BD (Access::getUserRoles),
 *       b) selecciona el rol principal por prioridad (Access::highestRole),
 *       c) obtiene las keys de menús permitidos (Access::menusForUser).
 *  3) Calcula banderas de UI (p. ej. readOnly, canEditGlobal) según el rol principal.
 *  4) Renderiza sólo las cards permitidas; marca visualmente los menús "solo ver".
 *
 * Seguridad:
 *  - La vista asume que el front controller valida la sesión inicial (Auth::initSession()).
 *  - Cada endpoint/acción debe validar permisos server-side con Access::check() independientemente de lo que pinte la UI.
 *
 * Notas de diseño:
 *  - Si se prefiere que la vista no consulte la BD, el front controller puede calcular $allowedKeys y pasarla.
 *  - El uso de highestRole es una política simple para resolver múltiples roles; cambiar si se necesita permisos compuestos.
 *
 * @package SLSAnt\View
 */

use App\Utils\Access;
use App\Utils\Auth;

// Catálogo centralizado de menús (key => metadata).
// Mantener aquí los href y descripciones facilita internacionalización/ajustes posteriores.
$menusCatalog = [
    Access::M_INGREDIENTES => [
        'label' => 'Ingredientes y Alergenos',
        'href'  => '/ingredientes.php',
        'desc'  => 'Visualizar ingredientes, alérgenos y condiciones de conservación.',
    ],
    Access::M_ELABORADOS => [
        'label' => 'Elaborados / Escandallo',
        'href'  => '/elaborados.php',
        'desc'  => 'Crear recetas: escandallo y elaborados. Marcar elaborados como ingredientes.',
    ],
    Access::M_LOTES => [
        'label' => 'Lotes',
        'href'  => '/lotes.php',
        'desc'  => 'Generar y gestionar lotes desde recetas; imprimir etiquetas.',
    ],
    Access::M_IMPRESION => [
        'label' => 'Impresión',
        'href'  => '/impresion.php',
        'desc'  => 'Impresión individual (EZPL), plantillas y reimpresión.',
    ],
    Access::M_APPCC => [
        'label' => 'APPCC',
        'href'  => '/appcc.php',
        'desc'  => 'Fichas de producción por elaboración/escandallo (pendiente diseño).',
    ],
    Access::M_CALIDAD => [
        'label' => 'Calidad',
        'href'  => '/calidad.php',
        'desc'  => 'Registro de puntos de control y trazabilidad (pendiente).',
    ],
    Access::M_USUARIOS => [
        'label' => 'Usuarios',
        'href'  => '/usuarios.php',
        'desc'  => 'Gestión de usuarios y permisos (solo Admin edición completa).',
    ],
];

/*
 * Determinar los menús permitidos para el usuario actual.
 *
 * Razonamiento:
 *  - La vista puede consultar Access para obtener la lista de keys permitidas.
 *  - Si no hay usuario autenticado, por defecto no se muestran menús (podrías cambiar a mostrar "solo ver").
 */
$allowedKeys = [];
$userRole = null;
if (isset($user) && is_array($user) && isset($user['id'])) {
    // menusForUser hace la consulta users_roles -> roles y aplica prioridad de rol
    $allowedKeys = Access::menusForUser($pdo, (int)$user['id']);

    // roles y rol principal para mostrar en UI
    $roles = Access::getUserRoles($pdo, (int)$user['id']);
    $userRole = Access::highestRole($roles);
}

/*
 * Bandera que indica si el usuario puede editar entidades generales.
 * - Admin y Gestor pueden editar globalmente.
 * - Usada por la UI para mostrar/ocultar botones de edición en vistas posteriores.
 */
$canEditGlobal = in_array($userRole, [Access::ROLE_ADMIN, Access::ROLE_GESTOR], true);
?>

<body class="bg-gray-50 min-h-screen">
    <main class="max-w-6xl mx-auto py-12 px-4">
        <header class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Panel principal</h1>
            <?php if (!empty($debug)): ?>
                <!-- Debug: información visible sólo en entornos de desarrollo -->
                <p class="mt-2 text-sm text-gray-600">
                    <span class="inline-block text-xs bg-gray-200 text-gray-800 px-2 py-1 rounded mr-2">DEBUG</span>
                    Seleccione una sección. Los menús se muestran según sus permisos.
                </p>

                <?php if ($user): ?>
                    <p class="mt-2 text-sm text-gray-500">
                        Rol principal:
                        <strong class="text-teal-600"><?php echo htmlentities((string)($userRole ?? 'sin rol'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        &nbsp;·&nbsp;ID usuario: <strong><?php echo htmlentities((string)$user['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
                        <?php if (!empty($user['username'])): ?> &nbsp;·&nbsp;Usuario: <strong><?php echo htmlentities((string)$user['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong><?php endif; ?>
                    </p>
                <?php endif; ?>

                <!-- Información técnica adicional -->
                <div class="mt-4 p-4 bg-gray-50 border rounded text-xs text-gray-700">
                    <div class="mb-2"><strong>Entorno</strong></div>

                    <div class="grid grid-cols-1 gap-2">
                        <div><strong>PHP:</strong> <?php echo htmlentities(PHP_VERSION, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <div>
                            <strong>PDO driver:</strong>
                            <?php
                            try {
                                $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
                            } catch (\Throwable $e) {
                                $driver = '<error>';
                            }
                            echo htmlentities((string)$driver, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                            ?>
                        </div>
                        <div><strong>Fecha / Hora:</strong> <?php echo htmlentities(date('c'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <div><strong>Request:</strong> <?php echo htmlentities($_SERVER['REQUEST_METHOD'] ?? 'N/A', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> <?php echo htmlentities($_SERVER['QUERY_STRING'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <div><strong>Mem (uso/peak):</strong> <?php echo round(memory_get_usage() / 1024 / 1024, 2) . 'MB / ' . round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB'; ?></div>
                    </div>

                    <hr class="my-3" />

                    <div class="mb-2"><strong>Usuario / Permisos</strong></div>
                    <?php if ($user): ?>
                        <div class="text-xs text-gray-600 mb-2">
                            <strong>Roles completos:</strong>
                            <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($roles ?? [], true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                        </div>

                        <div class="text-xs text-gray-600 mb-2">
                            <strong>Allowed menu keys:</strong>
                            <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($allowedKeys ?? [], true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
                        </div>

                        <div class="text-xs text-gray-600 mb-2">
                            <strong>Can edit global:</strong> <?php echo $canEditGlobal ? '<span class="text-green-600">true</span>' : '<span class="text-red-600">false</span>'; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-xs text-gray-600 mb-2">No hay usuario autenticado en la sesión actual.</div>
                    <?php endif; ?>

                    <hr class="my-3" />

                    <div class="mb-2"><strong>Catálogo</strong></div>
                    <div class="text-xs text-gray-600">
                        <strong>Menus catalog keys (count <?php echo count($menusCatalog); ?>):</strong>
                        <pre class="mt-1 p-2 bg-white border rounded"><?php
                                                                        $keys = array_keys($menusCatalog ?? []);
                                                                        echo htmlentities(var_export($keys, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                                                        ?></pre>
                    </div>

                    <hr class="my-3" />

                    <div class="mb-2"><strong>Sesión / Request</strong></div>
                    <div class="text-xs text-gray-600">
                        <div><strong>Session id:</strong> <?php echo htmlentities(session_id() ?: 'no-session', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
                        <div class="mt-2">
                            <strong>Session data (trunc.):</strong>
                            <pre class="mt-1 p-2 bg-white border rounded"><?php
                                                                            // evitar volcar objetos grandes; truncar la salida razonablemente
                                                                            $s = $_SESSION ?? [];
                                                                            $txt = var_export($s, true);
                                                                            if (strlen($txt) > 5000) $txt = substr($txt, 0, 5000) . "\n...truncated...";
                                                                            echo htmlentities($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                                                                            ?></pre>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </header>

        <section class="grid gap-6 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
            <?php
            // Renderizar sólo las tarjetas correspondientes a menús permitidos.
            // Si se desea alternativa: renderizar todos y deshabilitar los no permitidos.
            foreach ($menusCatalog as $key => $m):
                // Seguridad/negocio: saltar menús no permitidos
                if (!in_array($key, $allowedKeys, true)) {
                    continue;
                }

                // Escape de salida antes de render
                $label = htmlentities($m['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $desc  = htmlentities($m['desc'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $href  = htmlentities($m['href'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                // Determinar si el acceso es "solo ver" para este rol (ej. operador)
                $readOnly = false;
                if ($userRole === Access::ROLE_OPERADOR && in_array($key, [Access::M_INGREDIENTES, Access::M_ELABORADOS], true)) {
                    // Política: Operador puede ver ingredientes/elaborados pero no editar
                    $readOnly = true;
                }
            ?>
                <a href="<?php echo $href; ?>"
                    class="block bg-white p-6 rounded-lg shadow hover:shadow-md transition relative <?php echo $readOnly ? 'opacity-80' : ''; ?>">
                    <?php if ($readOnly): ?>
                        <!-- Indicación visual de modo solo lectura -->
                        <span class="absolute top-3 right-3 text-xs px-2 py-1 bg-yellow-100 text-yellow-800 rounded">Solo ver</span>
                    <?php endif; ?>

                    <h2 class="text-lg font-semibold text-gray-800"><?php echo $label; ?></h2>
                    <p class="mt-2 text-sm text-gray-600"><?php echo $desc; ?></p>

                    <!-- Mensaje contextual: gestor puede ver usuarios pero no editar -->
                    <?php if ($userRole === Access::ROLE_GESTOR && $key === Access::M_USUARIOS): ?>
                        <p class="mt-3 text-xs text-gray-500">Puede ver usuarios pero no editar.</p>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </section>

        <footer class="mt-12 text-sm text-gray-500">
            <p>Nota: la UI refleja permisos; cada endpoint debe validar permisos server-side con Access::check().</p>
        </footer>
    </main>
</body>

</html>