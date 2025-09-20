<?php

declare(strict_types=1);
/**
 * partial: escandallo_view.php
 *
 * Campos incluidos (se espera estar dentro de un <form> que envía action=save_escandallo):
 *  - origen_id         : id del ingrediente origen (select)
 *  - peso_inicial      : peso inicial del ingrediente origen (decimal)
 *  - salida_nombre[]   : nombre de cada producto generado (línea)
 *  - salida_peso[]     : peso de cada producto generado (línea)
 *  - restos            : campo calculado = peso_inicial - sum(salida_peso[])
 *
 * Reglas/Responsabilidad del servidor (controller):
 *  - Validar CSRF y permisos.
 *  - Al guardar: crear un ingrediente por cada salida (salida_nombre) que herede
 *    alérgenos e indicaciones del ingrediente origen.
 *  - Persistir el elaborado/escandallo como corresponda (tabla elaborados y recetas_ingredientes...).
 *
 * El partial incluye JS mínimo para añadir/quitar líneas y calcular "restos",
 * y para mostrar las indicaciones/alérgenos del ingrediente origen al cambiar el select.
 *
 * Variables esperadas en scope:
 *  - array $ingredientes  : listado de ingredientes (cada uno puede tener keys id_ingrediente, nombre, indicaciones, alergenos)
 *  - array|null $escandallo (opcional) : datos cuando se edita (puede contener peso_inicial y salidas)
 */
$ingredientes = $ingredientes ?? [];
//ordenar ingredientes por nombre
usort($ingredientes, function ($a, $b) {
    return strcmp($a['nombre'] ?? '', $b['nombre'] ?? '');
});
$escandallo = $escandallo ?? null;

// Pre-popular si venimos de edición
$pesoInicialVal = $escandallo['peso_inicial'] ?? '';
$salidas = $escandallo['salidas'] ?? []; // expected array of ['nombre'=>..., 'peso'=>...]

// Helper para safe escape
function h($s)
{
    return htmlentities((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<div class="mb-4">
    <label class="block text-sm font-medium mb-1">Ingrediente origen</label>

    <form method="post" action="/elaborados.php" class="bg-white p-6 rounded shadow" autocomplete="off">
        <!-- Area de selección del ingrediente de origen -->
        <input type="hidden" name="csrf" value="<?php echo h($csrf ?? ''); ?>">
        <input type="hidden" name="action" value="save_escandallo">
        <input type="hidden" name="id" value="<?php echo (int)($escandallo['id_elaborado'] ?? 0); ?>">
        <!-- Incluye:
     1) Un select con todos los ingredientes ordenados por nombre.
     2) Un campo de peso_inicial (required, numérico).
     3) Una sección para visualizar las indicaciones de conservación y alérgenos del ingrediente de origen.
     4) Un formulario para añadir la desccipción de la receta que resume las indicaciones del escandallo.
    -->

        <!-- Agrupar select y peso inicial en una fila con Tailwind -->
        <div class="flex flex-col md:flex-row md:items-end gap-4 mb-4">
            <div class="flex-1">
                <!-- Buscador por escritura: input con datalist y sincronización con el select -->
                <label class="block text-sm font-medium mb-1">Buscar ingrediente</label>
                <input
                    id="origen-search"
                    list="ingredientes-datalist"
                    type="text"
                    placeholder="Escribe para buscar..."
                    class="w-full px-3 py-2 border mb-2"
                    autocomplete="off">
                <datalist id="ingredientes-datalist">
                    <?php foreach ($ingredientes as $ing):
                        $ingName = (string)($ing['nombre'] ?? $ing['name'] ?? '');
                    ?>
                        <option value="<?php echo h($ingName); ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <select id="origen-select" name="origen_id" required class="w-full px-3 py-2 border">
                    <option value="">-- Selecciona un ingrediente --</option>
                    <?php foreach ($ingredientes as $ing):
                        $ingId = (int)($ing['id_ingrediente'] ?? $ing['id'] ?? 0);
                        $ingName = (string)($ing['nombre'] ?? $ing['name'] ?? '');
                        $selected = ($escandallo !== null && isset($escandallo['origen_id']) && $escandallo['origen_id'] == $ingId) ? 'selected' : '';
                        $indic = (string)($ing['indicaciones'] ?? '');
                        $alergenosList = [];
                        if (is_array($ing['alergenos'] ?? null)) {
                            foreach ($ing['alergenos'] as $a) {
                                $alergenosList[] = (string)($a['nombre'] ?? $a['name'] ?? '');
                            }
                        }
                        $alergenosStr = implode(', ', $alergenosList);
                    ?>
                        <option value="<?php echo h($ingId); ?>" <?php echo $selected; ?> data-indic="<?php echo h($indic); ?>" data-alergenos="<?php echo h($alergenosStr); ?>">
                            <?php echo h($ingName); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="w-full md:w-40">
                <label class="block text-sm font-medium mb-1">Peso inicial (kg)</label>
                <input
                    id="peso-inicial"
                    name="peso_inicial"
                    type="number"
                    step="0.001"
                    required
                    class="w-full px-3 py-2 border"
                    value="<?php echo h($pesoInicialVal); ?>">
            </div>
        </div>
        <div id="origin-indicaciones" class="text-sm text-gray-600 mb-4">
            <!-- JS llenará este div con indicaciones/alérgenos del ingrediente origen -->
        </div>

        <!-- Descripción de la receta del escandallo -->
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1">Indicaciones de conservación de la elaboración</label>
            <textarea
                name="descripcion"
                class="w-full px-3 py-2 border"
                rows="3"><?php echo h($escandallo['descripcion'] ?? ''); ?></textarea>
        </div>
        <hr class="my-6">
        <!-- Área de líneas de salida -->
        <div class="mb-4">
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-lg font-medium">Productos generados</h2>
                <button type="button" id="add-salida" class="px-3 py-1 bg-blue-500 text-white rounded text-sm">Añadir producto</button>
            </div>
            <div id="salidas-list">
                <?php if (empty($salidas)): ?>
                    <!-- Fila vacía inicial si no hay salidas -->
                    <div class="flex gap-2 items-center mb-2 js-line-row">
                        <input name="salida_nombre[]" type="text" class="border px-2 py-1 w-56" placeholder="Nombre producto" value="">
                        <input name="salida_peso[]" type="number" step="0.001" class="border px-2 py-1 w-28" placeholder="kg" value="0">
                        <button type="button" class="js-remove-row text-sm text-red-600 ml-2">Eliminar</button>
                    </div>
                    <?php else:
                    // Renderizar filas existentes
                    foreach ($salidas as $s):
                        $sName = (string)($s['nombre'] ?? '');
                        $sPeso = (string)($s['peso'] ?? '0');
                    ?>
                        <div class="flex gap-2 items-center mb-2 js-line-row">
                            <input name="salida_nombre[]" type="text" class="border px-2 py-1 w-56" placeholder="Nombre producto" value="<?php echo h($sName); ?>">
                            <input name="salida_peso[]" type="number" step="0.001" class="border px-2 py-1 w-28" placeholder="kg" value="<?php echo h($sPeso); ?>">
                            <button type="button" class="js-remove-row text-sm text-red-600 ml-2">Eliminar</button>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
            <!-- Campo calculado "restos" a la derecha -->
            <div class="mt-4">
                <div class="flex justify-end">
                    <div class="text-right">
                        <label class="block text-sm font-medium mb-1">Restos (kg)</label>
                        <input
                            id="restos"
                            name="restos"
                            type="text"
                            readonly
                            class="inline-block w-32 px-3 py-2 border bg-gray-100"
                            value="0.000">
                        <p class="text-xs text-gray-500 mt-1">Peso inicial menos suma de productos generados.</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Botones de acción -->
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded">Guardar Escandallo</button>
            <a href="/elaborados.php" class="px-4 py-2 border rounded">Cancelar</a>
        </div>
    </form>
</div>


<!-- Nota para el controlador: sobre el guardado
     - Al recibir POST action=save_escandallo el controller debe:
       1) Validar CSRF y permisos.
       2) Validar origen_id y peso_inicial.
       3) Validar salidas (nombres no vacíos, pesos numéricos).
       4) Crear el registro de elaborado/escandallo (tabla elaborados).
       5) Para cada salida crear un ingrediente nuevo que herede indicaciones y alérgenos del origen,
          y registrar la relación elaborado->ingrediente en recetas_ingredientes.
-->

<!-- Plantilla para clonación -->
<template id="tpl-salida-row">
    <div class="flex gap-2 items-center mb-2 js-line-row">
        <input name="salida_nombre[]" type="text" class="border px-2 py-1 w-56" placeholder="Nombre producto" value="">
        <input name="salida_peso[]" type="number" step="0.001" class="border px-2 py-1 w-28" placeholder="kg" value="0">
        <button type="button" class="js-remove-row text-sm text-red-600 ml-2">Eliminar</button>
    </div>
</template>

<script src="/js/ui-helper.js"></script>
<script>
  (function(){
    // dynamic list: reutilizable
    var listManager = AppUIHelpers.initDynamicList({
      addBtnId: 'add-salida',
      tplId: 'tpl-salida-row',
      listId: 'salidas-list',
      onAdded: function(){ sumCalc.calc(); },
      onRemoved: function(){ sumCalc.calc(); }
    });

    // bind filter input -> select
    AppUIHelpers.bindFilterInput({
      inputId: 'origen-search',
      selectId: 'origen-select'
    });

    // init generic calculator (peso inicial - suma salidas)
    var sumCalc = AppUIHelpers.initSumCalculator({
      pesoInicialId: 'peso-inicial',
      restosId: 'restos',
      rowInputSelector: 'input[name="salida_peso[]"]',
      decimals: 3
    });

    // --- Mostrar indicaciones / alérgenos del ingrediente origen ---
    var origenSelect = document.getElementById('origen-select');
    var originIndic = document.getElementById('origin-indicaciones');

    function escapeHtml(s){
      return String(s || '').replace(/[&<>"']/g, function(m){
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
      });
    }

    function showOriginInfo(){
      if (!origenSelect || !originIndic) return;
      var opt = origenSelect.selectedOptions && origenSelect.selectedOptions[0];
      if (!opt) { originIndic.innerHTML = ''; return; }
      var indic = opt.dataset.indic || '';
      var algs = opt.dataset.alergenos || '';
      var html = '';
      if (indic) html += '<div><strong>Indicaciones conservación:</strong> ' + escapeHtml(indic) + '</div>';
      if (algs) html += '<div class="mt-1"><strong>Alérgenos visibles:</strong> ' + escapeHtml(algs) + '</div>';
      originIndic.innerHTML = html;
    }

    origenSelect && origenSelect.addEventListener('change', showOriginInfo, false);
    // mostrar info inicial si hay selección
    showOriginInfo();

  })();
</script>