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
$tiposElaboracion = $tiposElaboracion ?? [];

// Funcion fmt para formatear el tipo de la tabal elaboarado segun los valores de $tiposElaboracion
// print_r($tiposElaboracion): Array ( [0] => Array ( [id] => 4 [nombre] => Congelación [descripcion] => Proceso de congelación de productos. ) [1] => Array ( [id] => 1 [nombre] => Elaboración [descripcion] => Proceso de elaboración de productos. ) [2] => Array ( [id] => 3 [nombre] => Envasado [descripcion] => Proceso de envasado de productos. ) [3] => Array ( [id] => 2 [nombre] => Escandallo [descripcion] => Proceso de cálculo de costes de productos. ) ) 
$fmtTipo = function ($tipo) use ($tiposElaboracion) {
  $tipoStr = (string) ($tipo ?? '');
  foreach ($tiposElaboracion as $t) {
    if ((string)($t['id'] ?? '') === $tipoStr || strtolower(trim((string)($t['nombre'] ?? ''))) === strtolower(trim($tipoStr))) {
      return (string)($t['nombre'] ?? 'Desconocido');
    }
  }
  return 'Desconocido';
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
          <th class="p-3">Viabilidad</th>
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
              <td class="p-3"><?php echo htmlentities((string)($row['dias_viabilidad'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> días</td>
              <td class="p-3"><?php echo htmlentities($fmtTipo($row['tipo'] ?? 0), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></td>

              <td class="p-3">
                <?php if ($canModify):
                  // Normalizar nombres usando $tiposElaboracion y eliminar acentos/mayúsculas
                  $rawTipo = $row['tipo'] ?? '';

                  // Helper: normalizar texto -> minusculas, sin acentos, sin caracteres no alfanuméricos
                  $normalize = function ($str) {
                    $s = (string)$str;
                    $s = trim(mb_strtolower($s, 'UTF-8'));
                    // intentar transliterar acentos a ASCII
                    $trans = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
                    if ($trans !== false) $s = $trans;
                    // eliminar cualquier caracter que no sea a-z o 0-9
                    $s = preg_replace('/[^a-z0-9]+/u', '', $s);
                    return $s === '' ? $s : $s;
                  };

                  $tipoParam = 'elaboracion'; // fallback por defecto
                  $normRaw = $normalize($rawTipo);

                  // Intentar mapear usando $tiposElaboracion (por id o por nombre, normalizado)
                    // Buscar coincidencia por id o nombre y reducir a una de las 3 categorías:
                    // "elaboracion", "escandallo", "otros"
                    $candidate = '';

                    foreach ($tiposElaboracion as $t) {
                    $idStr = (string)($t['id'] ?? '');
                    $name   = (string)($t['nombre'] ?? '');
                    $normName = $normalize($name);

                    // Coincidencia por id (numérico) o por nombre normalizado
                    if ((string)$rawTipo === $idStr || ($normRaw !== '' && $normRaw === $normName)) {
                      $candidate = $normName !== '' ? $normName : $normRaw;
                      break;
                    }
                    }

                    // Si no hubo coincidencia por tipos definidos, usar el valor raw normalizado
                    if ($candidate === '' && $normRaw !== '') {
                    $candidate = $normRaw;
                    }

                    // Mapear candidate a una de las tres opciones
                    if ($candidate !== '') {
                    if (strpos($candidate, 'escand') !== false) {
                      $tipoParam = 'escandallo';
                    } elseif (strpos($candidate, 'elabor') !== false) {
                      $tipoParam = 'elaboracion';
                    } else {
                      $tipoParam = 'otros';
                    }
                    } else {
                    // Si no hay información, asignar 'otros' por defecto
                    $tipoParam = 'otros';
                    }

                  // Si no se encontró mapeo pero rawTipo es numérico, intentar heurística simple:
                  if ($tipoParam === 'elaboracion' && (is_int($rawTipo) || ctype_digit((string)$rawTipo))) {
                    // buscar id en tipos y usar su nombre normalizado si existe
                    foreach ($tiposElaboracion as $t) {
                      if ((string)($t['id'] ?? '') === (string)$rawTipo) {
                        $tipoParam = $normalize($t['nombre'] ?? '') ?: 'elaboracion';
                        break;
                      }
                    }
                  }

                  // Construir query string de forma segura (tipo será p.ej. "escandallo" o "elaboracion")
                  $qs = 'modificar&' . http_build_query([
                    'tipo' => $tipoParam,
                    'id'   => (int) ($row['id_elaborado'] ?? 0),
                  ]);
                ?>
                <?php
                if (!($tipoParam ===  'otros')) {?>
                  <a href="/elaborados.php?<?php echo $qs; ?>" class="text-blue-600 mr-3">Editar</a>
                <?php } else {?>
                  <span class="text-sm text-gray-600 mr-3">N/edit</span>
                <?php } ?>
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
</main>
<?php if (!empty($debug)): ?>
  <?php
  // Helper: safe export truncando strings largos y limitando profundidad/elementos
  $truncate = function ($v, $maxLen = 1000) {
    if (is_string($v)) {
      if (strlen($v) > $maxLen) return substr($v, 0, $maxLen) . '...truncated...';
      return $v;
    }
    if (is_numeric($v) || is_bool($v) || is_null($v)) return $v;
    return '[non-scalar]';
  };

  $safeExport = function ($data, $depth = 0) use (&$safeExport, $truncate) {
    if ($depth > 3) return '[max-depth]';
    if (is_array($data)) {
      $out = [];
      $i = 0;
      foreach ($data as $k => $v) {
        if ($i++ >= 50) {
          $out['...more...'] = 'truncated after 50 items';
          break;
        }
        $out[$k] = $safeExport($v, $depth + 1);
      }
      return $out;
    }
    if (is_object($data)) {
      // convertir objeto simple a array con propiedades públicas
      $arr = [];
      foreach ((array)$data as $k => $v) {
        $arr[$k] = $safeExport($v, $depth + 1);
      }
      return ['__object__' => $arr];
    }
    return $truncate($data, 2000);
  };

  // Preparar variables a mostrar
  $safeGet = $safeExport($_GET ?? []);
  $safePost = $safeExport(array_diff_key($_POST ?? [], array_flip(['password', 'passwd', 'pwd', 'token', 'secret', 'authorization']))); // ocultar claves sensibles comunes
  $safeServer = $safeExport(array_intersect_key($_SERVER ?? [], array_flip([
    'REQUEST_METHOD',
    'QUERY_STRING',
    'REQUEST_URI',
    'HTTP_HOST',
    'HTTP_USER_AGENT',
    'REMOTE_ADDR',
    'SERVER_NAME',
    'SERVER_ADDR',
    'SERVER_SOFTWARE',
    'SERVER_PROTOCOL'
  ])));
  $itemsFull = $safeExport($items ?? []);
  $itemsCount = count($items ?? []);
  $postedCsrf = (string)($_POST['csrf'] ?? '');
  $currentCsrf = (string)($csrf ?? '');
  $sessionId = session_id() ?: '<no-session>';
  $phpVersion = phpversion();
  $memory = memory_get_usage(true);
  $memoryPeak = memory_get_peak_usage(true);
  $canModifyVal = var_export($canModify ?? false, true);

  // Si existe info de usuario pasarla de forma segura
  $viewerSafe = null;
  if (isset($user) && is_array($user)) {
    $viewerSafe = $safeExport(array_diff_key($user, array_flip(['password', 'passwd', 'pwd', 'token', 'secret', 'authorization'])));
  } elseif (isset($viewer) && is_array($viewer)) {
    $viewerSafe = $safeExport(array_diff_key($viewer, array_flip(['password', 'passwd', 'pwd', 'token', 'secret', 'authorization'])));
  }
  ?>
  <section class="mt-6 p-4 bg-gray-50 border rounded text-xs text-gray-700">
    <div class="mb-2"><strong>DEBUG EXTENDIDO (Elaborados)</strong> — contexto ampliado y saneado</div>

    <div class="grid gap-2">
      <div><strong>Resumen:</strong> Items mostrados: <?php echo (int)$itemsCount; ?> — canModify: <?php echo htmlentities($canModifyVal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>

      <div><strong>Items (primeros 50):</strong>
        <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($itemsFull, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
      </div>

      <div>
        <strong>GET (completo, saneado):</strong>
        <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($safeGet, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
      </div>

      <div>
        <strong>POST (saneado — se ocultan claves sensibles comunes):</strong>
        <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($safePost, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
      </div>

      <div>
        <strong>SERVER (selecto):</strong>
        <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($safeServer, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
      </div>

      <div>
        <strong>CSRF:</strong>
        <div class="mt-1">Posted: <?php echo htmlentities($postedCsrf === '' ? '<none>' : (substr($postedCsrf, 0, 128) . (strlen($postedCsrf) > 128 ? '...truncated...' : '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <div>Current token: <?php echo htmlentities($currentCsrf === '' ? '<none>' : (substr($currentCsrf, 0, 128) . (strlen($currentCsrf) > 128 ? '...truncated...' : '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
      </div>

      <?php if ($viewerSafe !== null): ?>
        <div>
          <strong>Usuario autenticado (saneado):</strong>
          <pre class="mt-1 p-2 bg-white border rounded"><?php echo htmlentities(var_export($viewerSafe, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
        </div>
      <?php endif; ?>

      <div>
        <strong>Entorno PHP / Sesión:</strong>
        <div class="mt-1">PHP: <?php echo htmlentities($phpVersion, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?> — Session: <?php echo htmlentities($sessionId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
        <div>Memory: <?php echo (int)$memory; ?> bytes — Peak: <?php echo (int)$memoryPeak; ?> bytes</div>
      </div>

      <hr class="my-2" />

      <div>
        <strong>Recomendaciones rápidas:</strong>
        <ul class="mt-1 list-disc list-inside text-gray-600">
          <li>Usar este modo solo en entornos de desarrollo.</li>
          <li>No mostrar variables sensibles (passwords, tokens) en producción.</li>
          <li>Si necesita más información (DB, queries) agregar logging en el controller/model.</li>
        </ul>
      </div>
    </div>
  </section>
<?php endif; ?>
</body>

</html>