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
      <!-- BOTONES: Crear / Asignar roles abren el editor via GET -->
      <?php if ($viewerRole === \App\Utils\Access::ROLE_ADMIN): ?>
        <div class="mb-4 text-right">
          <a href="/usuarios.php?crear" class="inline-block px-4 py-2 bg-teal-500 text-white rounded mr-2">Crear usuario</a>
        </div>
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
            <?php if ($debug): ?>
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
                    <!-- Abrir editor en GET para modificar -->
                    <a href="/usuarios.php?modificar&id=<?php echo (int)$u['id']; ?>" class="text-blue-600 hover:underline mr-3">Modificar</a>

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