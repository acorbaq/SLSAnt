<?php

declare(strict_types=1);
/**
 * partials/elaborado_view.php
 * Vista parcial para mostrar/editar los detalles de un elaborado (una receta creada a partir de varisos ingredientes).
 * Variables esperadas:
 * - $elaborado (array|null): Datos del elaborado (si se está editando) o null (si se está creando uno nuevo).
 * - $ingredientes (array): Lista de ingredientes disponibles para seleccionar.
 * - $csrf (string): Token CSRF para formularios.
 * - $debug (bool): Si está en modo debug (para mostrar información adicional).
 * - $canModify (bool): Si el usuario tiene permisos para modificar (mostrar botones de guardar).
 * 
 * Nota: Esta vista debe ser incluida dentro de un contexto HTML adecuado (header, footer, etc.).
 * 
 */
$ingredientes = $ingredientes ?? [];
usort($ingredientes, fn($a, $b) => strcmp($a['nombre'] ?? '', $b['nombre'] ?? ''));

$elaborado = $elaborado ?? null;

if ($elaborado !== null) {
    $isEdit = true;
    $isNew = false;
} else {
    $isEdit = false;
    $isNew = true;
}

$pesoTotal = $elaborado['peso_total'] ?? 0.0;
$elaboracion = $elaborado['nombre'] ?? '';
$entradas = $ingredienteElaborado ?? [];
$unidades = $unidades ?? [];
// localizar  el id de n.c con array filter
$unidadNCId = null;
$unidadKgId = null;
if (!empty($unidades) && is_array($unidades)) {
    $filtered = array_filter($unidades, fn($u) => (strtolower(trim((string)($u['abreviatura'] ?? ''))) === 'n.c.' || strtolower(trim((string)($u['nombre'] ?? ''))) === 'no especificado'));
    if (!empty($filtered)) {
        $unidadNCId = (int) array_values($filtered)[0]['id_unidad'];
    }

    // localizar id de 'kg' si existe en catálogo (fallback a null)
    $kgFiltered = array_filter($unidades, fn($u) => in_array(strtolower(trim((string)($u['abreviatura'] ?? ''))), ['kg', 'kilo', 'kilogramo']) || in_array(strtolower(trim((string)($u['nombre'] ?? ''))), ['kilogramo', 'kilo', 'kg']));
    if (!empty($kgFiltered)) {
        $unidadKgId = (int) array_values($kgFiltered)[0]['id_unidad'];
    }
}
// Helper para safe escape
function h($s)
{
    return htmlentities((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<div class="mb-4">
    <label class="block text-sm font-medium mb-1" for="nombre">Nombre del elaborado</label>
    <form method="post" action="/elaborados.php" class="bg-white p-6 rounded shadow" autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo h($csrf); ?>">
        <?php if ($isNew): ?>
            <input type="hidden" name="action" value="save_elaboracion">
        <?php else: ?>
            <input type="hidden" name="action" value="update_elaboracion">
        <?php endif; ?>
        <input type="hidden" name="tipo" value="<?php echo (int)($tipoId ?? 0); ?>">
        <input type="hidden" name="id" value="<?php echo (int)($elaborado['id_elaborado'] ?? 0); ?>">
        <!-- Incluye:
            1) Un input para dar nombre a la elaboración.
            2) Un campo de peso_total que se calcula como la suma de pesos (required, numérico).
            3) Un campo de días de viabilidad (numérico).
            3) Una sección para visualizar las indicaciones de conservación y alérgenos del ingredientes que se van incluyendo como parte de la elaboración.
            4) Un formulario para añadir la desccipción de la receta que resume las indicaciones de la elaboración.
            -->
        <div class="flex flex-col md:flex-row md:items-end gap-4 mb-4">
            <div class="flex-1">
                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    required
                    class="w-full px-3 py-2 border"
                    value="<?php echo h($elaboracion); ?>"
                    placeholder="Nombre del elaborado">
            </div>
            <div class="w-full md:w-40">
                <label class="block text-sm font-medium mb-1" for="peso_total">Peso total (kg)</label>
                <input
                    type="number"
                    id="peso_total"
                    name="peso_total"
                    required
                    step="0.01"
                    min="0"
                    class="w-full px-3 py-2 border"
                    value="<?php echo h((string)$pesoTotal); ?>"
                    placeholder="Peso total en kg">
            </div>
            <div class="w-full md:w-40">
                <label class="block text-sm font-medium mb-1" for="dias_viabilidad">Días de viabilidad</label>
                <input
                    type="number"
                    id="dias_viabilidad"
                    name="dias_viabilidad"
                    required
                    step="1"
                    min="0"
                    class="w-full px-3 py-2 border"
                    value="<?php echo h((string)($elaborado['dias_viabilidad'] ?? '')); ?>"
                    placeholder="Días de viabilidad">
            </div>
        </div>
        <div class="flex flex-col md:flex-row md:items-start gap-4 mb-4">
            <div id="origin-indicaciones" class="text-sm text-gray-600 mb-4 md:mb-0 text-left flex-1">
                <!-- JS llenará este div con indicaciones/alérgenos del ingrediente origen -->
            </div>
            <!-- Boton tipo tip para escoger si se quiere guardar el elaborado como un ingrediente -->
            <div class="mt-2 ml-auto self-start md:self-center text-right">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="save_as_ingredient" value="1" class="form-checkbox">
                    <span class="ml-2 text-sm">Guardar elaborado como ingrediente</span>
                </label>
            </div>
        </div>
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1" for="descripcion">Indicaciones de conservación de la elaboración</label>
            <textarea
                id="descripcion"
                name="descripcion"
                rows="4"
                class="w-full px-3 py-2 border"
                placeholder="Descripción de la receta o indicaciones de elaboración"><?php echo h((string)($elaborado['descripcion'] ?? '')); ?></textarea>
        </div>
        <hr class="my-6">

        <!-- Sección para añadir ingredientes a la elaboración -->
        <div class="mb-4">
            <label class="block text-sm font-medium mb-1" for="ingrediente">Añadir ingrediente</label>
            <div class="flex flex-col sm:flex-row sm:items-end gap-4 mb-4">

                <!-- Bloque de búsqueda -->
                <div class="flex-1 min-w-0">
                    <?php if ($isNew): ?>
                        <label class="block text-sm font-medium mb-1">Buscar ingrediente</label>
                        <input
                            id="ingrediente-search"
                            list="ingredientes-datalist"
                            type="text"
                            placeholder="Escribe para buscar..."
                            class="w-full px-3 py-2 border rounded"
                            autocomplete="off">
                        <datalist id="ingredientes-datalist">
                            <?php foreach ($ingredientes as $ing):
                                $ingName = (string)($ing['nombre'] ?? $ing['name'] ?? '');
                            ?>
                                <option value="<?php echo h($ingName); ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    <?php endif; ?>

                    <!-- SELECT oculto con metadata -->
                    <select id="ingrediente-select" aria-hidden="true" style="display:none;">
                        <?php foreach ($ingredientes as $ing):
                            $ingId = (int)($ing['id_ingrediente'] ?? $ing['id'] ?? 0);
                            $ingName = (string)($ing['nombre'] ?? $ing['name'] ?? '');
                            $indic = (string)($ing['indicaciones'] ?? '');
                            $alergenosList = [];
                            if (is_array($ing['alergenos'] ?? null)) {
                                foreach ($ing['alergenos'] as $a) {
                                    $alergenosList[] = (string)($a['nombre'] ?? $a['name'] ?? '');
                                }
                            }
                            $alergenosStr = implode(', ', $alergenosList);
                        ?>
                            <option value="<?php echo h($ingId); ?>"
                                data-name="<?php echo h($ingName); ?>"
                                data-indic="<?php echo h($indic); ?>"
                                data-alergenos="<?php echo h($alergenosStr); ?>">
                                <?php echo h($ingName); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Cantidad -->
                <div class="w-full sm:w-40">
                    <label class="block text-sm font-medium mb-1">Cantidad (kg)</label>
                    <input
                        type="number"
                        id="cantidad"
                        placeholder="Cantidad (kg)"
                        step="0.01"
                        min="0"
                        class="w-full px-3 py-2 border rounded">
                </div>

                <!-- Botón -->
                <div class="w-full sm:w-auto">
                    <button
                        type="button"
                        id="add-ingredient"
                        class="w-full sm:w-auto bg-blue-500 text-white px-4 py-2 rounded">
                        Añadir
                    </button>
                </div>
            </div>
        </div>



        <!-- Lista de ingredientes añadidos -->
        <div class="mb-4">
            <h3 class="text-lg font-medium mb-2">Ingredientes añadidos</h3>
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="p-3 border">Ingrediente</th>
                        <th class="p-3 border">Cantidad (kg)</th>
                        <th class="p-3 border">Acciones</th>
                    </tr>
                </thead>
                <tbody id="ingredient-list">
                    <?php foreach ($entradas as $entry): ?>
                        <tr class="border-t">
                            <td class="p-3 border">
                                <?php
                                $ingName = '';
                                foreach ($ingredientes as $ing) {
                                    if ((int)($ing['id_ingrediente'] ?? 0) === (int)($entry['id_ingrediente'] ?? 0)) {
                                        $ingName = $ing['nombre'] ?? '';
                                        break;
                                    }
                                }
                                echo h($ingName);
                                ?>
                                <input type="hidden" name="ingredientes[]" value="<?php echo (int)($entry['id_ingrediente'] ?? 0); ?>">
                            </td>
                            <td class="p-3 border">
                                <!-- ahora mostramos input number visible + select de unidades.
                                     Si no hay cantidad, dejamos el input vacío y la unidad "n.c." -->
                                <div class="flex items-center gap-2">
                                    <?php $cantVal = isset($entry['cantidad']) && $entry['cantidad'] !== '' ? h((string)$entry['cantidad']) : ''; ?>
                                    <input
                                        type="number"
                                        name="cantidades[]"
                                        class="js-ing-cant-input px-2 py-1 border w-28"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo $cantVal; ?>">
                                    <select name="unidades[]" class="js-ing-unit px-2 py-1 border">
                                        <?php
                                        // determinar id de unidad actual (buscar por id o por abreviatura para compatibilidad)
                                        $curUnitId = null;
                                        $curRaw = (string)($entry['unidad'] ?? '');
                                        // si el valor ya es numérico, usarlo directamente
                                        if (is_numeric($curRaw) && (int)$curRaw > 0) {
                                            $curUnitId = (int)$curRaw;
                                        } else {
                                            foreach ($unidades as $uItem) {
                                                if (
                                                    strtolower((string)($uItem['abreviatura'] ?? '')) === strtolower($curRaw)
                                                    || strtolower((string)($uItem['nombre'] ?? '')) === strtolower($curRaw)
                                                ) {
                                                    $curUnitId = (int)$uItem['id_unidad'];
                                                    break;
                                                }
                                            }
                                        }
                                        if ($curUnitId === null) $curUnitId = $unidadNCId ?: 0;

                                        if (!empty($unidades) && is_array($unidades)) {
                                            foreach ($unidades as $uItem) {
                                                $uid = (int)($uItem['id_unidad'] ?? 0);
                                                $label = (string)($uItem['nombre'] ?? $uItem['abreviatura'] ?? $uid);
                                                $abbr = (string)($uItem['abreviatura'] ?? '');
                                                $sel = ($uid === $curUnitId) ? ' selected' : '';
                                                echo '<option value="' . h((string)$uid) . '"' . $sel . '>' . h($label . ($abbr !== '' ? ' (' . $abbr . ')' : '')) . '</option>';
                                            }
                                        } else {
                                            // fallback legacy: map some common units to arbitrary ids but prefer n.c. = 0
                                            $opts = [['id' => 0, 'label' => 'n.c.'], ['id' => 1, 'label' => 'kg'], ['id' => 2, 'label' => 'g']];
                                            foreach ($opts as $op) {
                                                $sel = ((int)$op['id'] === (int)$curUnitId) ? ' selected' : '';
                                                echo '<option value="' . h((string)$op['id']) . '"' . $sel . '>' . h($op['label']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </td>
                            <td class="p-3 border text-center">
                                <button type="button" class="remove-ingredient bg-red-500 text-white px-2 py-1 rounded">
                                    Eliminar
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Botones de acción -->
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-teal-500 text-white rounded">Guardar Elaboración</button>
            <a href="/elaborados.php" class="px-4 py-2 border rounded">Cancelar</a>
        </div>
    </form>
</div>


<template id="tpl-ingred-row">
    <tr class="border-t">
        <td class="p-3 border">
            <span class="js-ing-name"></span>
            <input type="hidden" name="ingredientes[]" class="js-ing-id" value="">
        </td>
        <td class="p-3 border">
            <!-- input visible para cantidad y select de unidades (por defecto n.c.) -->
            <div class="flex items-center gap-2">
                <input type="number" name="cantidades[]" class="js-ing-cant-input px-2 py-1 border w-28" step="0.01" min="0" value="">
                <select name="unidades[]" class="js-ing-unit px-2 py-1 border">
                    <?php
                    if (!empty($unidades) && is_array($unidades)) {
                        foreach ($unidades as $uItem) {
                            $uid = (int)($uItem['id_unidad'] ?? 0);
                            $label = (string)($uItem['nombre'] ?? $uItem['abreviatura'] ?? $uid);
                            $abbr = (string)($uItem['abreviatura'] ?? '');
                            $sel = ($uid === $unidadNCId) ? ' selected' : '';
                            echo '<option value="' . h((string)$uid) . '"' . $sel . '>' . h($label . ($abbr !== '' ? ' (' . $abbr . ')' : '')) . '</option>';
                        }
                    } else {
                        // fallback: n.c. as 0
                        echo '<option value="0" selected>n.c.</option>';
                        echo '<option value="1">kg</option>';
                        echo '<option value="2">g</option>';
                    }
                    ?>
                </select>
            </div>
        </td>
        <td class="p-3 border text-center">
            <button type="button" class="remove-ingredient bg-red-500 text-white px-2 py-1 rounded">Eliminar</button>
        </td>
    </tr>
</template>

<script src="/js/ui-helper.js"></script>
<script>
    (function () {
        if (window.AppUIHelpers && typeof window.AppUIHelpers.attachBackKeyBinding === 'function') {
            // Guarda la instancia para poder destruir el listener más tarde si se necesita
            window._backKeyBinding = AppUIHelpers.attachBackKeyBinding({
                formSelector: 'form[action="/elaborados.php"]'
            });
        }
    })();
</script>
<script>
    // exponer id de kg y nc como globals para que el submit-guard mínimo fuera del IIFE pueda usarlos
    window.DEFAULT_UNIDAD_NC = <?php echo (int)($unidadNCId ?? 0); ?>;
    window.DEFAULT_UNIDAD_KG = <?php echo (int)($unidadKgId ?? 0); ?>;
    
    (function() {
        // valor por defecto para unidad "n.c." (id) disponible en JS
        var DEFAULT_UNIDAD_NC = <?php echo (int)($unidadNCId ?? 0); ?>;
        // id de 'kg' (si existe en catálogo) para usar como unidad por defecto cuando se proporciona cantidad > 0
        var DEFAULT_UNIDAD_KG = <?php echo (int)($unidadKgId ?? 0); ?>;

        // Delegar filtrado a ui-helper (si está disponible)
        if (window.AppUIHelpers && typeof AppUIHelpers.bindFilterInput === 'function') {
            AppUIHelpers.bindFilterInput({
                inputId: 'ingrediente-search',
                selectId: 'ingrediente-select'
            });
        }


        // Elementos principales
        var addBtn = document.getElementById('add-ingredient');
        var searchInput = document.getElementById('ingrediente-search');
        var hiddenSelect = document.getElementById('ingrediente-select');
        var cantidadInput = document.getElementById('cantidad');
        var listBody = document.getElementById('ingredient-list');
        var tpl = document.getElementById('tpl-ingred-row');

        function findOptionByName(name) {
            if (!hiddenSelect) return null;
            name = (name || '').trim();
            for (var i = 0; i < hiddenSelect.options.length; i++) {
                var o = hiddenSelect.options[i];
                if ((o.dataset && (o.dataset.name || '').trim() === name) || (o.textContent || '').trim() === name) return o;
            }
            return null;
        }

        // Ajustada: Ignorar inputs vacíos (n.c.) al sumar el peso total
        function updatePesoTotal() {
            var total = 0;
            document.querySelectorAll('input[name="cantidades[]"]').forEach(function(inp) {
                var v = (inp.value || '').toString().trim();
                if (v === '') return; // no contar campos vacíos (n.c.)
                total += parseFloat(v) || 0;
            });
            var pesoField = document.getElementById('peso_total');
            if (pesoField) pesoField.value = total.toFixed(2);
        }

 // Añadir ingrediente desde input + datalist/select oculto
        addBtn && addBtn.addEventListener('click', function() {
            var nameVal = (searchInput && searchInput.value || '').trim();
            if (!nameVal) {
                alert('Seleccione un ingrediente válido.');
                return;
            }

            var opt = findOptionByName(nameVal);
            if (!opt) {
                alert('Ingrediente no encontrado. Seleccione uno de la lista.');
                return;
            }

            var ingId = (opt.value || '').toString();
            var ingName = (opt.dataset && opt.dataset.name) ? opt.dataset.name : (opt.textContent || '').trim();
            var cantidadRaw = (cantidadInput && cantidadInput.value) ? String(cantidadInput.value).trim() : '';
            var cantidad = cantidadRaw === '' ? null : parseFloat(cantidadRaw);

            // Si se proporciona valor debe ser > 0
            if (cantidadRaw !== '' && (isNaN(cantidad) || cantidad <= 0)) {
                alert('Introduce una cantidad válida mayor que 0 o deja vacío para "n.c."');
                return;
            }

            // --- NUEVO: evitar duplicados --- //
            var existingIds = Array.from(listBody.querySelectorAll('input[name="ingredientes[]"]'))
                .map(function(i){ return (i.value || '').toString().trim(); });
            if (existingIds.indexOf(ingId) !== -1) {
                alert('El ingrediente ya está añadido.');
                // opcional: poner foco en el ingrediente ya añadido
                return;
            }
            // --- FIN NUEVO --- //

            // clonar template y rellenar
            var node = tpl.content ? tpl.content.cloneNode(true) : tpl.cloneNode(true);
            var nameEl = node.querySelector('.js-ing-name');
            var idInput = node.querySelector('.js-ing-id');
            var cantInput = node.querySelector('.js-ing-cant-input');
            var unitSelect = node.querySelector('.js-ing-unit');

            if (nameEl) nameEl.textContent = ingName;
            if (idInput) idInput.value = ingId;
            if (cantInput) cantInput.value = (cantidad !== null) ? cantidad.toFixed(2) : '';

            // seleccionar unidad por defecto según reglas de negocio:
            //  - si se proporcionó cantidad > 0 -> usar kg (si existe en catálogo), si no existir usar primera opción del select
            //  - si no se proporcionó cantidad (n.c.) -> usar unidad "n.c." (DEFAULT_UNIDAD_NC) si existe, si no usar primera opción del select
            if (unitSelect) {
                if (cantidad !== null && cantidad > 0 && DEFAULT_UNIDAD_KG) {
                    unitSelect.value = String(DEFAULT_UNIDAD_KG);
                } else if (DEFAULT_UNIDAD_NC) {
                    unitSelect.value = String(DEFAULT_UNIDAD_NC);
                } else if (unitSelect.options[0]) {
                    unitSelect.value = unitSelect.options[0].value;
                }
            }

            listBody.appendChild(node);

            // limpiar
            if (searchInput) searchInput.value = '';
            if (cantidadInput) cantidadInput.value = '';

           // poner el foco en el buscador para agilizar la entrada de siguientes ingredientes
           if (searchInput) {
               try { searchInput.focus(); if (typeof searchInput.select === 'function') searchInput.select(); } catch (e) {}
           }

            updatePesoTotal();

            // si existe helper, actualizar panel de indicaciones/alérgenos (el helper observa la lista)
            if (window.AppUIHelpers && typeof AppUIHelpers.syncIndicacionesForList === 'function') {
                AppUIHelpers.syncIndicacionesForList({
                    containerId: 'origin-indicaciones',
                    listId: 'ingredient-list',
                    selectId: 'ingrediente-select'
                });
            }
        }, false);

        // Delegación para eliminar filas
        listBody && listBody.addEventListener('click', function(e) {
            var btn = e.target.closest && e.target.closest('.remove-ingredient');
            if (!btn) return;
            var row = btn.closest && btn.closest('tr');
            if (row) {
                row.remove();
                updatePesoTotal();
            }
        }, false);

        // inicializar suma si hay filas preexistentes
        updatePesoTotal();

        // inicializar sync de indicaciones para la lista (el helper observará cambios)
        if (window.AppUIHelpers && typeof AppUIHelpers.syncIndicacionesForList === 'function') {
            AppUIHelpers.syncIndicacionesForList({
                containerId: 'origin-indicaciones',
                listId: 'ingredient-list',
                selectId: 'ingrediente-select'
            });
        }
    })();

    // AVISO AL GUARDAR (mínimo): comprobar ids de unidades y pedir confirmación si hay alguna distinta de kg
    (function attachMinimalSubmitGuard() {
        var form = document.querySelector('form[action="/elaborados.php"]');
        if (!form) return;
        form.addEventListener('submit', function(ev) {
            // 1) Comprobar mínimo de ingredientes
            var ingredientCount = (form.querySelectorAll('input[name="ingredientes[]"]') || []).length;
            if (ingredientCount < 2) {
                alert('Debe incluir al menos 2 ingredientes antes de guardar la elaboración.');
                ev.preventDefault();
                ev.stopPropagation();
                return;
            }

            // 2) Comprobar unidades distintas de kg (si existe id de kg)
            var kgId = String(window.DEFAULT_UNIDAD_KG || 0);
            if (!kgId || kgId === '0') return; // sin kg definido no validamos cliente
            var selects = Array.from(form.querySelectorAll('select[name="unidades[]"]'));
            if (selects.length === 0) return;
            var hasNonKg = selects.some(function(s) { return String(s.value) !== kgId; });
            if (hasNonKg) {
                if (!confirm('Se han detectado unidades distintas de kg en los ingredientes. Esto puede producir inconsistencias en el cálculo del peso final. ¿Desea continuar y guardar de todos modos?')) {
                    ev.preventDefault();
                    ev.stopPropagation();
                }
            }
        }, false);
    })();
</script>