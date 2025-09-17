<?php
declare(strict_types=1);

/**
 * ingredientes_view.php
 *
 * Vista que lista los ingredientes disponibles junto a sus alérgenos e indicaciones.
 *
 * Contrato / variables esperadas (proporcionadas por el controller):
 *  - array $ingredientes : lista de ingredientes. Cada elemento contiene:
 *        - id_ingrediente (int)
 *        - nombre (string)
 *        - indicaciones (string)
 *        - alergenos (array of ['id_alergeno' => int, 'nombre' => string])
 *  - array $alergenos   : catálogo completo de alérgenos (id_alergeno, nombre) — útil para formularios
 *  - bool  $canModify   : si true, el usuario puede crear/editar/eliminar (roles >= calidad)
 *  - bool  $debug       : flag opcional para mostrar información de depuración
 *
 * Responsabilidades:
 *  - Mostrar tabla con todos los ingredientes.
 *  - Mostrar botones de "Crear", "Editar" y "Eliminar" sólo si $canModify === true.
 *  - Incluir token CSRF en formularios de mutación (delete).
 *
 * Seguridad / flujo:
 *  - Esta vista es puramente de presentación; las comprobaciones de permisos y CSRF
 *    deben realizarse en el controller (IngredienteController). La vista sólo
 *    oculta/enseña controles según $canModify.
 *  - Todos los valores mostrados se escapan con htmlentities para prevenir XSS.
 *
 * Notas de implementación:
 *  - Csrf::init() y Csrf::generateToken() se usan para generar el token que acompaña
 *    a los formularios que realizan acciones POST.
 *  - La eliminación se realiza mediante un formulario POST con confirmación JS simple.
 */

use App\Utils\Csrf;

// Inicializar utilidades CSRF; el controller normalmente ya llamó Csrf::init() pero
// aquí lo aseguramos antes de generar el token para los formularios en la vista.
Csrf::init();
$csrf = Csrf::generateToken();
?>
<main class="max-w-5xl mx-auto py-8 px-4">

  <!-- Encabezado -->
  <header>
    <h1 class="text-2xl font-bold mb-4">Ingredientes</h1>

    <!-- Indicador debug (opcional) -->
    <?php if (!empty($debug)): ?>
      <p class="text-sm text-gray-500 mb-4">Modo DEBUG activado.</p>
    <?php endif; ?>
  </header>

  <!-- Acción de creación: sólo visible si el usuario tiene permiso de modificación.
       Diseño: el botón está en un contenedor alineado a la derecha (text-right)
       para que quede encima de la tabla y alineado a la derecha en vez de flotando. -->
  <?php if (!empty($canModify)): ?>
    <div class="mb-4 text-right">
      <a href="/ingredientes.php?crear" class="px-4 py-2 bg-teal-500 text-white rounded">Crear ingrediente</a>
    </div>
  <?php endif; ?>

  <!-- Tabla principal: listamos ingredientes y sus alérgenos.
       Cada celda se escapa con htmlentities para evitar inyección de HTML. -->
  <div class="bg-white shadow rounded overflow-hidden">
    <table class="w-full text-left">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-3">ID</th>
          <th class="p-3">Nombre</th>
          <th class="p-3">Indicaciones</th>
          <th class="p-3">Alérgenos</th>
          <th class="p-3">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <!-- Recorremos $ingredientes; controller garantiza que es array -->
      <?php foreach ($ingredientes as $ing): ?>
        <tr class="border-t">
          <!-- ID: cast seguro a int -->
          <td class="p-3"><?php echo (int)$ing['id_ingrediente']; ?></td>

          <!-- Nombre e indicaciones: escape para XSS -->
          <td class="p-3"><?php echo htmlentities((string)$ing['nombre'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
          <td class="p-3"><?php echo htmlentities((string)$ing['indicaciones'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>

          <!-- Alérgenos: mapear nombres y mostrar como lista separada por comas.
               Nota: si necesita formatos más ricos (badges, tablas), adaptar aquí. -->
          <td class="p-3">
            <?php
              // Obtener array de nombres; proteger si 'alergenos' no existe o no es array
              $names = [];
              if (is_array($ing['alergenos'] ?? null)) {
                  foreach ($ing['alergenos'] as $a) {
                      $names[] = (string)($a['nombre'] ?? '');
                  }
              }
              echo htmlentities(implode(', ', $names), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            ?>
          </td>

          <!-- Acciones: mostrar enlaces Editar / Eliminar sólo si puede modificar.
               - Editar: GET a ?modificar&id=...
               - Eliminar: POST con CSRF token y confirm() JS.
               Importante: la verificación final de permiso y CSRF la hace el controller. -->
          <td class="p-3">
            <?php if (!empty($canModify)): ?>
              <a href="/ingredientes.php?modificar&id=<?php echo (int)$ing['id_ingrediente']; ?>" class="text-blue-600 mr-3">Editar</a>

              <form method="post" action="/ingredientes.php" style="display:inline" onsubmit="return confirm('Eliminar ingrediente?');">
                <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo (int)$ing['id_ingrediente']; ?>">
                <button type="submit" class="text-red-600">Eliminar</button>
              </form>
            <?php else: ?>
              <span class="text-sm text-gray-500">Sin acciones</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>

      <!-- Mensaje cuando no hay ingredientes -->
      <?php if (empty($ingredientes)): ?>
        <tr>
          <td colspan="5" class="p-4 text-sm text-gray-500">No hay ingredientes.</td>
        </tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Enlace para volver al panel -->
  <p class="mt-4"><a href="/index.php" class="text-teal-600">Volver al panel</a></p>
</main>
</body>
</html>