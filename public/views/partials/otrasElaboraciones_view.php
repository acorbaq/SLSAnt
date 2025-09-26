<?php
$tiposElaboracion = $tiposElaboracion ?? ['Envasado', 'Congelación', 'Otros'];
// Hay que excluir Elaboración y Escandallo de la lista
$tiposElaboracion = array_filter($tiposElaboracion, function($t) {
    return $t !== 'Elaboración' && $t !== 'Escandallo';
});
$tiposElaboracion = array_values($tiposElaboracion); // reindexar
$elaborado = isset($elaborado) && is_array($elaborado) ? $elaborado : null;
$elaborado = $elaborado ?? null;
$isEdit = $elaborado !== null;
$nombreVal = $elaborado['nombre'] ?? '';
$descripcionVal = $elaborado['descripcion'] ?? '';
$pesoVal = $elaborado['peso_obtenido'] ?? '';
$diasVal = $elaborado['dias_viabilidad'] ?? '';
$tipoVal = $elaborado['tipo'] ?? ($tiposElaboracion[0] ?? '');
$csrfVal = $csrf ?? '';

// helper seguro
function h($s) { return htmlentities((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<div class="bg-white p-6 rounded shadow">
    <form method="post" action="/elaborados.php" class="space-y-6" novalidate autocomplete="off">
        <input type="hidden" name="csrf" value="<?php echo h($csrfVal); ?>">
        <input type="hidden" name="action" value="<?php echo $isEdit ? 'update_otra_elaboracion' : 'save_otra_elaboracion'; ?>">
        <input type="hidden" name="id" value="<?php echo (int)($elaborado['id_elaborado'] ?? 0); ?>">

<div class="flex flex-col md:flex-row md:items-end gap-4 mb-4">
            <div class="flex-1">
                <!-- Búsqueda combinada: Ingredientes o Elaborados existentes -->
                <?php
                // Preparar lista combinada para el buscador (ingredientes primero, luego elaborados)
                $searchItems = [];
                foreach ($ingredientes as $ing) {
                    $searchItems[] = [
                        'id' => (int)($ing['id_ingrediente'] ?? $ing['id'] ?? 0),
                        'name' => (string)($ing['nombre'] ?? $ing['name'] ?? ''),
                        'kind' => 'Ingrediente'
                    ];
                }
                $allElaborados = $elaborados ?? [];
                foreach ($allElaborados as $el) {
                    $searchItems[] = [
                        'id' => (int)($el['id_elaborado'] ?? 0),
                        'name' => (string)($el['nombre'] ?? ''),
                        'kind' => 'Elaborado'
                    ];
                }
                // Eliminar duplicados por nombre para evitar confusión en el datalist
                $seenNames = [];
                $uniqueSearchItems = [];
                foreach ($searchItems as $item) {
                    $lowerName = mb_strtolower(trim($item['name']));
                    if ($lowerName !== '' && !isset($seenNames[$lowerName])) {
                        $seenNames[$lowerName] = true;
                        $uniqueSearchItems[] = $item; // mantener el primer encontrado
                    }
                }
                $searchItems = $uniqueSearchItems;
                // Ordenar alfabéticamente por nombre
                usort($searchItems, function($a, $b) {
                    return strcmp(mb_strtolower($a['name']), mb_strtolower($b['name']));
                });
                ?>
                <label for="entity-search" class="block text-sm font-medium mb-1">Seleccionar ingrediente o elaborado</label>

                <!-- NUEVO: campo nombre (autocompletado desde el buscador) -->
                <label for="nombre" class="block text-sm font-medium mb-1 mt-2">Nombre</label>                
                <input
                    id="entity-search"
                    list="entity-datalist"
                    type="text"
                    placeholder="Escribe para buscar ingrediente o elaborado..."
                    class="w-full px-3 py-2 border rounded"
                    autocomplete="off">
                <datalist id="entity-datalist">
                    <?php foreach ($searchItems as $it): ?>
                        <option value="<?php echo h($it['name']); ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <!-- Select oculto con metadata para lookup por JS -->
                <select id="entity-select" aria-hidden="true" style="display:none;">
                    <?php foreach ($searchItems as $it): ?>
                        <option value="<?php echo h((string)$it['id']); ?>"
                                data-name="<?php echo h($it['name']); ?>"
                                data-kind="<?php echo h($it['kind']); ?>">
                            <?php echo h($it['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Hidden fields enviados al servidor indicando la entidad seleccionada -->
                <input type="hidden" id="selected_entity_type" name="selected_entity_type" value="">
                <input type="hidden" id="selected_entity_id" name="selected_entity_id" value="">
            </div>

            <div class="w-full md:w-40">
                <label class="block text-sm font-medium mb-1" for="peso_obtenido">Peso obtenido (kg)</label>
                <input id="peso_obtenido" name="peso_obtenido" type="number" step="0.001" min="0"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2"
                       value="<?php echo h((string)$pesoVal); ?>" placeholder="0.000">
            </div>

            <div class="w-full md:w-40">
                <label class="block text-sm font-medium mb-1" for="dias_viabilidad">Días de viabilidad</label>
                <input id="dias_viabilidad" name="dias_viabilidad" type="number" step="1" min="0"
                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2"
                       value="<?php echo h((string)$diasVal); ?>" placeholder="0">
            </div>

            <div>
                <label for="tipo" class="block text-sm font-medium text-gray-700">Tipo</label>
                <select id="tipo" name="tipo" required
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2">
                    <?php foreach ($tiposElaboracion as $t): ?>
                        <option value="<?php echo h($t); ?>" <?php echo ($t === (string)$tipoVal) ? 'selected' : ''; ?>>
                            <?php echo h($t); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label for="descripcion" class="block text-sm font-medium text-gray-700">Descripción / Indicaciones</label>
            <textarea id="descripcion" name="descripcion" rows="4"
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm p-2"
                      placeholder="Indicaciones, conservación, notas..."><?php echo h($descripcionVal); ?></textarea>
        </div>

        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-sm font-medium rounded">
                    <?php echo $isEdit ? 'Actualizar' : 'Crear'; ?>
                </button>
                <a href="/elaborados.php" class="inline-flex items-center px-4 py-2 border rounded text-sm text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
            </div>
            <p class="text-sm text-gray-500">Tipo: agrupa elaborados como Envasado, Congelación, etc.</p>
        </div>
    </form>
</div>

<script>
(function(){
    // Buscar opción en el select oculto por nombre
    function findOptionByNameInHiddenSelect(name) {
        var sel = document.getElementById('entity-select');
        if (!sel) return null;
        name = (name || '').trim();
        for (var i = 0; i < sel.options.length; i++) {
            var o = sel.options[i];
            var oname = (o.dataset && o.dataset.name) ? o.dataset.name.trim() : (o.textContent || '').trim();
            if (oname === name) return o;
        }
        return null;
    }

    var searchInput = document.getElementById('entity-search');
    var nombreInput = document.getElementById('nombre');
    var tipoSelect = document.getElementById('tipo');
    var selType = document.getElementById('selected_entity_type');
    var selId = document.getElementById('selected_entity_id');

    function applyAutoName(opt) {
        if (!opt) return;
        var entName = opt.dataset.name || opt.textContent || '';
        var kind = opt.dataset.kind || '';
        var tipo = (tipoSelect && tipoSelect.value) ? tipoSelect.value : '';
        var auto = entName + (tipo ? ' - ' + tipo : '');
        if (nombreInput && (String(nombreInput.dataset.autofilled || '') === '1' || String(nombreInput.value || '').trim() === '')) {
            nombreInput.value = auto;
            nombreInput.dataset.autofilled = '1';
        }
        if (selType) selType.value = kind;
        if (selId) selId.value = opt.value || '';
    }

    if (searchInput) {
        searchInput.addEventListener('change', function() {
            var val = (searchInput.value || '').trim();
            if (!val) {
                if (selType) selType.value = '';
                if (selId) selId.value = '';
                return;
            }
            var opt = findOptionByNameInHiddenSelect(val);
            if (!opt) {
                alert('No se encontró el ingrediente o elaborado. Seleccione uno de la lista.');
                if (selType) selType.value = '';
                if (selId) selId.value = '';
                return;
            }
            applyAutoName(opt);
        }, false);
    }

    if (tipoSelect) {
        tipoSelect.addEventListener('change', function() {
            var selectedName = (document.getElementById('entity-search') || {}).value || '';
            if (!selectedName) return;
            var opt = findOptionByNameInHiddenSelect(selectedName);
            if (!opt) return;
            applyAutoName(opt);
        }, false);
    }

    if (nombreInput) {
        nombreInput.addEventListener('input', function() {
            nombreInput.dataset.autofilled = '0';
        }, false);
    }

    // Validación existente: ahora #nombre existe y se autocompletará
    var form = document.querySelector('form[action="/elaborados.php"]');
    if (!form) return;

    form.addEventListener('submit', function(ev){
        var searchVal = (searchInput && String(searchInput.value).trim()) || '';
        var peso = form.querySelector('#peso_obtenido');
        var dias = form.querySelector('#dias_viabilidad');


        // validar que el usuario haya seleccionado un elemento válido del datalist
        var selectedIdVal = (selId && selId.value) ? String(selId.value).trim() : '';
        if (!selectedIdVal) {
            // intentar resolver por texto del buscador
            var opt = findOptionByNameInHiddenSelect(searchVal);
            if (!opt) {
                alert('Seleccione un ingrediente o elaborado válido de la lista.');
                searchInput && searchInput.focus();
                ev.preventDefault();
                return;
            }
            // si existe, asegurarse de setear los ocultos antes de submit
            if (selType) selType.value = opt.dataset.kind || '';
            if (selId) selId.value = opt.value || '';
        }

        if (peso && peso.value !== '' && Number(peso.value) < 0) {
            alert('Peso obtenido debe ser un número mayor o igual a 0.');
            peso.focus();
            ev.preventDefault();
            return;
        }

        if (dias && dias.value !== '' && Number(dias.value) < 0) {
            alert('Días de viabilidad debe ser un número mayor o igual a 0.');
            dias.focus();
            ev.preventDefault();
            return;
        }

        var tipo = form.querySelector('#tipo');
        if (tipo && tipo.value && tipo.value !== 'Elaboración') {
            var ok = confirm('Ha seleccionado tipo "' + tipo.value + '". ¿Desea continuar?');
            if (!ok) { ev.preventDefault(); return; }
        }
    }, false);
})();
</script>