<?php
declare(strict_types=1);
/**
 * usuarios_view.php
 *
 * Vista parcial/plantilla para la gestión de usuarios.
 *
 * Contrato / variables esperadas (inyectadas por App\Controllers\UserController::renderList):
 *  - array $users         : listado de usuarios. Cada elemento es un assoc array con claves:
 *                            'id','username','email','is_active','created_at','last_login','roles' (array de nombres).
 *  - array $roles         : listado de roles disponibles para asignación. Puede ser array de strings (nombres)
 *                            o array de arrays con keys ['id','name']; la vista normaliza a name.
 *  - string|null $viewerRole : rol principal del usuario que ve la página (por ejemplo 'admin' o 'gestor').
 *  - bool $debug          : bandera de depuración (opcional) que activa mensajes de ayuda en la UI.
 *
 * Responsabilidades:
 *  - Mostrar la tabla de usuarios con sus roles.
 *  - Mostrar controles de edición (crear, eliminar, asignar roles) SOLO si el viewerRole es Admin.
 *  - Proteger acciones sensibles vía CSRF token en formularios (Csrf::init + Csrf::generateToken()).
 *  - No efectuar lógica de negocio ni accesos a BD: solo renderizado y envío de formularios al front controller.
 *
 * Seguridad y buenas prácticas:
 *  - Todas las salidas dinámicas se escapan con htmlentities(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').
 *  - Acciones destructivas (crear, eliminar, asignar_roles) envían POST con campo 'csrf' para validación server-side.
 *  - La vista asume que el front controller y el controlador ya han verificado autenticación y permisos server-side.
 *
 * Flujo de renderizado (alto nivel):
 *  1) Csrf::init() -> garantiza $_SESSION y crea/lee token.
 *  2) Csrf::generateToken() -> token que se inyecta en formularios.
 *  3) Si $viewerRole === Access::ROLE_ADMIN -> mostrar formulario de creación y botones de acción por fila.
 *  4) Iterar $users y renderizar una fila por cada usuario con sus roles (implode(', ', $u['roles'])).
 *  5) Para asignar roles, renderizar checkbox por cada $roles; el backend recibirá roles[] con nombres.
 *
 * Notas operativas:
 *  - La vista no debe confiar en $roles procedente de BD: idealmente $roles se toma desde Access::definedRoles()
 *    para garantizar que la UI solo muestra roles reconocidos por la aplicación.
 *  - El controlador debe mapear nombres a role_id antes de persistir en users_roles.
 *
 * @package SLSAnt\View
 */

use App\Utils\Csrf;

Csrf::init();
// Generar token CSRF para incluir en todos los formularios de la vista.
$csrf = Csrf::generateToken();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Usuarios · SLSAnt</title>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-gray-50 min-h-screen">
  <main class="max-w-5xl mx-auto py-12 px-4">
    <header class="mb-6">
      <h1 class="text-2xl font-bold">Gestión de usuarios</h1>

      <!-- Modo debug: información auxiliar para desarrolladores -->
      <?php if (!empty($debug)): ?>
        <p class="text-sm text-gray-500">Modo DEBUG activado.</p>
      <?php endif; ?>
    </header>

    <section class="mb-6">
      <!--
        Formulario de creación:
        - Solo visible para Admin según $viewerRole.
        - Envía action=create y token CSRF.
        - El controlador debe validar y hashear la contraseña.
      -->
      <?php if ($viewerRole === \App\Utils\Access::ROLE_ADMIN): ?>
        <form method="post" action="/usuarios.php" class="mb-4" autocomplete="off">
          <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="create">
          <div class="flex gap-2">
            <input name="username" required placeholder="usuario" class="px-3 py-2 border rounded" />
            <input name="password" type="password" required placeholder="contraseña" class="px-3 py-2 border rounded" />
            <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded">Crear</button>
          </div>
        </form>
      <?php endif; ?>

      <!-- Tabla de usuarios -->
      <div class="bg-white shadow rounded overflow-hidden">
        <table class="w-full text-left">
          <thead class="bg-gray-100">
            <tr>
              <th class="p-3 text-sm">ID</th>
              <th class="p-3 text-sm">Usuario</th>
              <th class="p-3 text-sm">Roles</th>
              <th class="p-3 text-sm">Acciones</th>
            </tr>
          </thead>
          <tbody>

            <!-- DEBUG: número de usuarios encontrados -->
            <?php if (!empty($debug)): ?>
              <tr><td colspan="4" class="p-3 text-sm"><strong>DEBUG:</strong> usuarios encontradas: <?php echo (int)count($users); ?></td></tr>
            <?php endif; ?>

            <!-- Iterar usuarios: cada $u debe ser array con 'roles' => array<string> -->
            <?php foreach ($users as $u): ?>
              <tr class="border-t">
                <!-- Escapar todas las salidas -->
                <td class="p-3 text-sm"><?php echo htmlentities((string)$u['id'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
                <td class="p-3 text-sm"><?php echo htmlentities((string)$u['username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>

                <!-- Roles: implode de nombres -->
                <td class="p-3 text-sm">
                  <?php
                    $rolesList = is_array($u['roles'] ?? null) ? $u['roles'] : [];
                    echo htmlentities(implode(', ', $rolesList), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                  ?>
                </td>

                <td class="p-3 text-sm">
                  <?php if ($viewerRole === \App\Utils\Access::ROLE_ADMIN): ?>
                    <!--
                      Asignar roles:
                      - Se renderiza un form con checkboxes por cada role definido en $roles.
                      - El nombre que se envía es el nombre del role; el backend debe mapear a role_id.
                      - Checked si el roleName está presente en $u['roles'].
                    -->
                    <details style="display:inline;margin-left:0.5rem;">
                      <summary class="text-sm text-blue-600 hover:underline">Asignar roles</summary>
                      <form method="post" action="/usuarios.php" class="mt-2">
                        <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="assign_roles">
                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">

                        <?php foreach ($roles as $r):
                          // Normalizar: $r puede ser string o array ['id'=>..,'name'=>..]
                          $roleName = is_array($r) ? ($r['name'] ?? '') : (string)$r;
                          $checked = in_array($roleName, $rolesList, true) ? 'checked' : '';
                        ?>
                          <label class="inline-flex items-center mr-2 text-sm">
                            <input type="checkbox" name="roles[]" value="<?php echo htmlentities($roleName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" <?php echo $checked; ?>>
                            <span class="ml-1"><?php echo htmlentities($roleName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
                          </label>
                        <?php endforeach; ?>

                        <div class="mt-2">
                          <button type="submit" class="px-3 py-1 bg-teal-500 text-white rounded text-sm">Guardar</button>
                        </div>
                      </form>
                    </details>

                    <!-- Eliminar usuario: botón en form POST -->
                    <div class="inline-block float-right">
                      <form method="post" action="/usuarios.php" onsubmit="return confirm('Eliminar usuario?');">
                        <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                        <button type="submit" class="text-red-600 hover:underline">Eliminar</button>
                      </form>
                    </div>

                  <?php else: ?>
                    <!-- Gestor: solo ver (sin acciones) -->
                    <span class="text-sm text-gray-500">Sin acciones</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>

            <!-- Mensaje cuando no hay usuarios -->
            <?php if (empty($users)): ?>
              <tr><td colspan="4" class="p-4 text-sm text-gray-500">No hay usuarios.</td></tr>
            <?php endif; ?>

          </tbody>
        </table>
      </div>
    </section>

    <p class="text-sm text-gray-400"><a href="/index.php" class="text-teal-600 hover:underline">Volver al panel</a></p>
  </main>
</body>
</html>