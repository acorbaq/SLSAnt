<?php
declare(strict_types=1);
/**
 * elaborados_view.php
 *
 * Vista: listado básico de elaborados (recetas / escandallos) con acciones.
 *
 * Variables esperadas:
 *  - array $elaborados : lista de elaborados. Cada elemento (array) con keys:
 *        - id_elaborado (int)
 *        - nombre (string)
 *        - fecha_caducidad (string, formato ISO o date)
 *        - tipo (int|string)
 *  - bool  $debug       : flag opcional para mostrar información de depuración
 *  - bool  $canModify   : (opcional) si true muestra controles de edición/borrado
 *
 * La vista escapa todas las salidas y usa token CSRF para formularios de mutación.
 */

use App\Utils\Csrf;

// Asegurar CSRF para los formularios de eliminación
Csrf::init();
$csrf = Csrf::generateToken();

$items = $elaborados ?? [];
$canModify = $canModify ?? false;

/**
 * Normaliza el valor de tipo a etiqueta legible.
 */
$fmtTipo = static function ($t): string {
    if (is_int($t) || ctype_digit((string)$t)) {
        return ((int)$t === 1) ? 'Escandallo' : 'Elaboración';
    }
    $s = strtolower((string)$t);
    if ($s === 'escandallo' || $s === '1') return 'Escandallo';
    if ($s === 'elaboracion' || $s === 'elaboración' || $s === '0') return 'Elaboración';
    return ucfirst($s);
};
?>
<main class="max-w-6xl mx-auto py-8 px-4">
  <header class="mb-6">
    <h1 class="text-2xl font-bold">Elaborados</h1>
    <p class="text-sm text-gray-500">Listado de recetas y escandallos disponibles.</p>
  </header>

  <!-- Botón para crear nuevo elaborado -->
  <?php if ($canModify): ?>
    <div class="mb-4 text-right">
      <a href="/elaborados.php?crear" class="inline-block bg-teal-600 text-white px-4 py-2 rounded hover:bg-teal-700">Nuevo Elaborado</a>
    </div>
  <?php endif; ?>

  <div class="bg-white shadow rounded overflow-auto">
    <table class="w-full text-left">
      <thead class="bg-gray-100">
        <tr>
          <th class="p-3">ID</th>
          <th class="p-3">Nombre</th>
          <th class="p-3">Caducidad</th>
          <th class="p-3">Tipo</th>
          <th class="p-3">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr>
            <td colspan="5" class="p-4 text-sm text-gray-500">No hay elaborados registrados.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $row): ?>
            <tr class="border-t">
              <td class="p-3"><?php echo (int)($row['id_elaborado'] ?? 0); ?></td>
              <td class="p-3"><?php echo htmlentities((string)($row['nombre'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              <td class="p-3"><?php echo htmlentities((string)($row['fecha_caducidad'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>
              <td class="p-3"><?php echo htmlentities($fmtTipo($row['tipo'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>

              <td class="p-3">
                <?php if ($canModify): ?>
                  <a href="/elaborados.php?modificar&id=<?php echo (int)($row['id_elaborado'] ?? 0); ?>" class="text-blue-600 mr-3">Editar</a>

                  <form method="post" action="/elaborados.php" style="display:inline" onsubmit="return confirm('¿Eliminar este elaborado?');">
                    <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)($row['id_elaborado'] ?? 0); ?>">
                    <button type="submit" class="text-red-600">Eliminar</button>
                  </form>
                <?php else: ?>
                  <span class="text-sm text-gray-500">Sin acciones</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Enlace para volver al panel -->
  <p class="mt-4"><a href="/index.php" class="text-teal-600">Volver al panel</a></p>

  <!-- Depuración: información útil en entorno de desarrollo -->
  <?php if (!empty($debug)): ?>
    <section class="mt-6 p-4 bg-gray-50 border rounded text-xs text-gray-700">
      <div class="mb-2"><strong>DEBUG: elaborados</strong> (muestra truncada)</div>
      <div>
        <strong>Count:</strong> <?php echo count($items); ?>
      </div>
      <pre class="mt-2 p-2 bg-white border rounded"><?php
        $txt = var_export(array_slice($items, 0, 200), true);
        if (strlen($txt) > 3000) $txt = substr($txt, 0, 3000) . "\n...truncated...";
        echo htmlentities($txt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
      ?></pre>
    </section>
  <?php endif; ?>

</main>
</body>
</html>