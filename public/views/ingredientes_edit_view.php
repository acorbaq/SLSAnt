<?php
declare(strict_types=1);
/**
 * ingredientes_edit_view.php
 *
 * Vista/fragmento que muestra el formulario para crear o editar un ingrediente.
 *
 * Contrato / variables esperadas (proporcionadas por IngredienteController):
 *  - array|null $ingrediente : null para crear, o array con keys:
 *        - id_ingrediente (int)
 *        - nombre (string)
 *        - indicaciones (string)
 *        - alergenos (array of ['id_alergeno'=>int,'nombre'=>string])  // relaciones existentes
 *  - array $alergenos        : catálogo completo de alérgenos (rows con id_alergeno,nombre)
 *  - string $csrf            : token CSRF generado por Csrf::generateToken()
 *  - bool   $debug           : flag opcional para depuración
 *
 * Responsabilidades principales:
 *  - Renderizar un formulario único que sirve tanto para creación (ingrediente === null)
 *    como para edición (ingrediente con datos).
 *  - Pre-poblar los campos cuando se está en modo edición.
 *  - Incluir token CSRF en el formulario y usar POST para mutaciones.
 *  - No realiza validaciones de negocio ni persiste: el controller debe validar y aplicar cambios.
 *
 * Seguridad / notas de flujo:
 *  - La vista añade el token CSRF en un campo oculto; la verificación la hace el controller.
 *  - Todas las salidas se escapan con htmlentities para evitar XSS.
 *  - El formulario envía action=save y id (0 para crear). El controller decide create/update.
 */
$titleSection = $ingrediente ? 'Editar ingrediente - SLSAnt' : 'Crear ingrediente - SLSAnt';
?>
<body>
<main class="max-w-lg mx-auto py-12 px-4">

  <!-- Título dinámico: indica si estamos creando o editando -->
  <h1 class="text-2xl mb-4"><?php echo $ingrediente ? 'Editar ingrediente' : 'Crear ingrediente'; ?></h1>

  <!--
    Formulario principal
    - method POST porque modifica estado en el servidor.
    - action apunta al front controller /ingredientes.php (el controller procesa action=save).
    - Incluye token CSRF en campo oculto; controller valida antes de realizar cambios.
  -->
  <form method="post" action="/ingredientes.php" class="bg-white p-6 rounded shadow" autocomplete="off">
    <!-- Token CSRF: obligatorio para prevenir CSRF; valor generado por el controller -->
    <input type="hidden" name="csrf" value="<?php echo htmlentities($csrf ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">

    <!-- Indica a handlePost() que esta petición debe guardarse -->
    <input type="hidden" name="action" value="save">

    <!-- id: 0 => crear; >0 => actualizar (el controller decide la operación) -->
    <input type="hidden" name="id" value="<?php echo (int)($ingrediente['id_ingrediente'] ?? 0); ?>">

    <!--
      Campo "nombre"
      - required en cliente; el controller debe volver a validar en servidor.
      - El valor se pre-puebla si $ingrediente existe.
    -->
    <div class="mb-3">
      <label class="block text-sm">Nombre</label>
      <input
        name="nombre"
        required
        class="w-full px-3 py-2 border"
        value="<?php echo htmlentities((string)($ingrediente['nombre'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    </div>

    <!--
      Campo "indicaciones"
      - Texto libre para métodos de conservación o notas.
      - Pre-poblado en edición; vacío en creación.
    -->
    <div class="mb-3">
      <label class="block text-sm">Indicaciones de conservación</label>
      <textarea
        name="indicaciones"
        class="w-full px-3 py-2 border"
        rows="4"><?php echo htmlentities((string)($ingrediente['indicaciones'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
    </div>

    <!--
      Checkboxes de alérgenos
      - $alergenos: lista completa para iterar.
      - $ingrediente['alergenos']: relaciones actuales; usamos array_column para obtener ids seleccionados.
      - Cada checkbox envía alergenos[] con id_alergeno; el controller/model normalizará y asignará.
      - Diseño: etiquetas inline; se adapta para muchos ítems.
    -->
    <div class="mb-4">
      <div class="text-sm mb-2">Alérgenos</div>
      <?php
        // Lista de ids seleccionados (defensivo: si no hay alergenos permisos como array vacío)
        $sel = is_array($ingrediente['alergenos'] ?? null) ? array_column($ingrediente['alergenos'], 'id_alergeno') : [];
        foreach ($alergenos as $a):
          // Asegurar que cada alergeno tiene id_nombre esperados
          $aid = (int)($a['id_alergeno'] ?? $a['id'] ?? 0);
          $aname = (string)($a['nombre'] ?? $a['name'] ?? '');
          $checked = in_array($aid, $sel, true) ? 'checked' : '';
      ?>
        <label class="inline-flex items-center mr-3 mb-2">
          <input type="checkbox" name="alergenos[]" value="<?php echo $aid; ?>" <?php echo $checked; ?>>
          <span class="ml-2 text-sm"><?php echo htmlentities($aname, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
        </label>
      <?php endforeach; ?>
    </div>

    <!-- Botones de acción -->
    <div class="flex gap-2">
      <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded">Guardar</button>
      <a href="/ingredientes.php" class="px-4 py-2 border rounded">Cancelar</a>
    </div>
  </form>

  <!--
    Nota de depuración:
    - Si $debug está activado el controller puede pasar variable para inspeccionar $ingrediente.
    - Esto nunca debe estar activado en producción.
  -->
  <?php if (!empty($debug)): ?>
    <pre class="mt-4"><?php echo htmlentities(var_export($ingrediente, true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></pre>
  <?php endif; ?>

</main>
</body>
</html>
