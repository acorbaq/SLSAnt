<?php
/**
 * Vista: Crear Lote
 * Archivo: /home/andres.cordeiro/www/SLSAnt/public/views/lotes_create_view.php
 *
 * Reescrito para asegurar que las validaciones finales en cliente funcionan correctamente:
 * - Se incluyen campos ocultos (.pc-id, .pc-ref, .pc-fecha) por línea para que los handlers JS los rellenen.
 * - La validación de envío comprueba pesos, lote y fecha para elaboraciones (isElaboracion = true).
 * - Si $canModify es false, el formulario se bloquea en cliente (todos los inputs deshabilitados) y el submit queda inhabilitado.
 *
 * Variables esperadas (tal como antes):
 *  - array  $ingredientes
 *  - array  $unidades
 *  - array  $elaborado
 *  - array  $ingredientesElaborado
 *  - array  $productosComerciales
 *  - string $csrf
 *  - bool   $canModify
 */

$ingredientes = $ingredientes ?? [];
$unidades = $unidades ?? [];
$elaborado = $elaborado ?? ['id_elaborado' => null, 'nombre' => '', 'dias_viabilidad' => 0];
$ingredientesElaborado = $ingredientesElaborado ?? [];
$productosComerciales = $productosComerciales ?? [];
$csrf = $csrfToken ?? '';
$canModify = isset($canModify) ? (bool)$canModify : true;
$tiposElaboracion = $tiposElaboracion ?? [];

// determinar nombre de tipo si está disponible
$tipoNombre = '';
if (!empty($tiposElaboracion) && isset($elaborado['tipo'])) {
    foreach ($tiposElaboracion as $t) {
        if ((string)($t['id'] ?? '') === (string)$elaborado['tipo']) {
            $tipoNombre = (string)($t['nombre'] ?? '');
            break;
        }
    }
}
if ($tipoNombre === '') {
    $tipoNombre = (string)($elaborado['tipo_nombre'] ?? '');
}

// considerar "Elaboración" si el nombre contiene 'elabor' o el id es 1 (fallback)
$isElaboracion = false;
if ($tipoNombre !== '') {
    $isElaboracion = (mb_stripos($tipoNombre, 'elabor') !== false);
} elseif (isset($elaborado['tipo'])) {
    $isElaboracion = ((string)$elaborado['tipo'] === '1');
}

// fechas por defecto
$fechaProdDefault = date('Y-m-d');
$diasViabilidad = (int)($elaborado['dias_viabilidad'] ?? 0);
$fechaCadDefault = $diasViabilidad > 0
    ? date('Y-m-d', strtotime("+{$diasViabilidad} days", strtotime($fechaProdDefault)))
    : '';
?>
<main class="max-w-4xl mx-auto py-8 px-4">
    <header class="mb-6">
        <h1 class="text-2xl font-semibold">Crear Lote — <?= htmlspecialchars($elaborado['nombre'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
        <p class="text-sm text-gray-500 mt-1">Formulario prellenado con las líneas definidas en la receta. No es posible añadir/eliminar líneas aquí.</p>
    </header>

    <?php if (!$canModify): ?>
        <div class="rounded-md bg-yellow-50 border border-yellow-200 p-4 text-yellow-800 mb-6">No tienes permisos para crear lotes.</div>
    <?php endif; ?>

    <form method="post" action="/lotes.php" class="space-y-6 bg-white p-4 rounded shadow" id="lotes-form" <?= $canModify ? '' : 'data-disabled="1"' ?>>
        <input type="hidden" name="action" value="create_lote">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="elaboracion_id" value="<?= htmlspecialchars((string)($elaborado['id_elaborado'] ?? $elaborado['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Número de lote</label>
                <div class="mt-1 text-sm text-gray-600">Se generará automáticamente al crear el lote.</div>
            </div>

            <?php if (!$isElaboracion): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lote origen (parent)</label>
                    <input type="text" name="parent_lote_id" value="" class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" placeholder="ID lote origen si aplica" <?= $canModify ? '' : 'disabled' ?>>
                    <p class="text-xs text-gray-400 mt-1">Sólo para escandallo / otros (no aplicable a elaboraciones completas).</p>
                </div>
            <?php else: ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lote origen</label>
                    <div class="mt-1 text-sm text-gray-600">No aplicable (Elaboración)</div>
                </div>
            <?php endif; ?>

            <div>
                <label class="block text-sm font-medium text-gray-700">Fecha de producción *</label>
                <input id="fecha_produccion" type="date" name="fecha_produccion" value="<?= htmlspecialchars($fechaProdDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" <?= $canModify ? '' : 'disabled' ?> />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Fecha caducidad</label>
                <input id="fecha_cad_visible" type="text" value="<?= htmlspecialchars($fechaCadDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" readonly class="mt-1 block w-full rounded border-gray-100 bg-gray-50 px-3 py-2 sm:text-sm text-gray-700" />
                <input id="fecha_cad" type="hidden" name="fecha_caducidad" value="<?= htmlspecialchars($fechaCadDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
                <p class="text-xs text-gray-400 mt-1">Calculada como fecha de producción + <?= $diasViabilidad ?> días de viabilidad.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Peso total *</label>
                <input id="peso_total" type="number" step="0.001" name="peso_total" required class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" <?= $canModify ? '' : 'disabled' ?> />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Unidad de peso</label>
                <div class="mt-1 text-sm text-gray-700">kg</div>
                <input type="hidden" name="unidad_peso" value="kg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Temp. inicio</label>
                <input type="number" step="0.1" name="temp_inicio" class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" <?= $canModify ? '' : 'disabled' ?> />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Temp. final</label>
                <input type="number" step="0.1" name="temp_final" class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" <?= $canModify ? '' : 'disabled' ?> />
            </div>
        </div>

        <section>
            <h2 class="text-lg font-medium mb-3">Líneas de ingredientes (definidas en la receta)</h2>
            <p class="text-sm text-gray-500 mb-3">Las líneas son fijas según la receta. Ajusta sólo el peso a usar y la partida origen si procede.</p>

            <div class="space-y-3" id="ingredientes-list">
                <?php
                // Filtrar líneas: si es una Elaboración y la línea es de origen (es_origen == 1) la ocultamos
                $linesToShow = [];
                foreach ($ingredientesElaborado as $line) {
                    $es_origen = (int) ($line['es_origen'] ?? $line['es_origenario'] ?? $line['es_origen_flag'] ?? 0);
                    if ($isElaboracion && $es_origen === 1) {
                        continue;
                    }
                    $linesToShow[] = $line;
                }
                foreach ($linesToShow as $i => $line):
                    $ingId = $line['id_ingrediente'] ?? $line['ingrediente_id'] ?? '';
                    $ingName = $line['nombre'] ?? $line['nombre_ingrediente'] ?? '';
                    $cantidadReceta = $line['cantidad'] ?? $line['cantidad_receta'] ?? '0';
                    $unidadReceta = '-';
                    foreach ($unidades as $u) {
                        if ((string)($u['id_unidad'] ?? $u['id']) === (string)($line['id_unidad'] ?? $line['unidad_id'] ?? '')) {
                            $unidadReceta = $u['abreviatura'] ?? $u['nombre'];
                            break;
                        }
                    }
                ?>
                    <div data-index="<?= $i ?>" class="bg-white border rounded-lg p-4 grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <!-- Nombre + receta -->
                        <div class="md:col-span-4">
                            <div class="flex items-baseline justify-between">
                                <div>
                                    <div class="text-sm font-semibold text-gray-800"><?= htmlspecialchars($ingName ?: 'Ingrediente') ?></div>
                                    <div class="text-xs text-gray-500 mt-1">Cantidad receta: <span class="font-medium text-gray-700"><?= htmlspecialchars((string)$cantidadReceta) ?></span> <span class="text-gray-400"><?= htmlspecialchars($unidadReceta) ?></span></div>
                                </div>
                                <div class="hidden md:flex flex-col items-end gap-2">
                                    <button type="button" data-index="<?= $i ?>" class="load-partida inline-flex items-center gap-2 px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700 hover:bg-gray-50" <?= $canModify ? '' : 'disabled' ?>>Seleccionar</button>
                                    <button type="button" data-index="<?= $i ?>" class="load-last inline-flex items-center gap-2 px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700 hover:bg-gray-50" <?= $canModify ? '' : 'disabled' ?>>Cargar último</button>
                                </div>
                            </div>
                        </div>

                        <!-- Peso a usar -->
                        <div class="md:col-span-3">
                            <label class="block text-xs text-gray-500 mb-1">Peso a usar *</label>
                            <div class="flex">
                                <input type="number" step="0.001" name="ingredientes[<?= $i ?>][peso]" required class="peso-ingrediente block w-full rounded-l border border-gray-300 px-2 py-2 text-sm" <?= $canModify ? '' : 'disabled' ?> />
                                <span class="inline-flex items-center px-3 rounded-r border-t border-b border-r border-gray-300 bg-gray-50 text-sm text-gray-600">kg</span>
                            </div>
                        </div>

                        <!-- Lote y fecha ingrediente -->
                        <div class="md:col-span-4">
                            <input type="hidden" name="ingredientes[<?= $i ?>][id_ingrediente]" value="<?= htmlspecialchars((string)$ingId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
                            <!-- Campos que rellena el selector / carga último. Siempre presentes (aunque ocultos si no aplican) -->
                            <input type="hidden" class="pc-id" name="ingredientes[<?= $i ?>][pc_id]" value="">
                            <input type="hidden" class="pc-ref" name="ingredientes[<?= $i ?>][pc_ref]" value="">
                            <input type="hidden" class="pc-fecha" name="ingredientes[<?= $i ?>][pc_fecha]" value="">

                            <?php if ($isElaboracion): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-500">Lote</label>
                                        <input type="text" name="ingredientes[<?= $i ?>][lote_ingrediente]" class="ingred-lote mt-1 block w-full rounded border-gray-300 px-2 py-2 text-sm" placeholder="ej. L-2025-001" <?= $canModify ? '' : 'disabled' ?> />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Fecha caducidad</label>
                                        <input type="date" name="ingredientes[<?= $i ?>][fecha_caducidad_ingrediente]" class="ingred-fecha mt-1 block w-full rounded border-gray-300 px-2 py-2 text-sm" placeholder="YYYY-MM-DD" <?= $canModify ? '' : 'disabled' ?> />
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-500">Lote</label>
                                        <div class="mt-1 text-sm text-gray-700">Asignado desde el lote padre / partida</div>
                                        <input type="hidden" name="ingredientes[<?= $i ?>][lote_ingrediente]" class="ingred-lote" value="" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Fecha caducidad</label>
                                        <div class="mt-1 text-sm text-gray-700"><?= htmlspecialchars($fechaCadDefault ?: '-', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                                        <input type="hidden" name="ingredientes[<?= $i ?>][fecha_caducidad_ingrediente]" class="ingred-fecha" value="<?= htmlspecialchars($fechaCadDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-2 text-xs text-gray-500">Partida: <span class="font-medium fecha-pc" data-index="<?= $i ?>"><?= '' ?></span></div>

                            <input type="hidden" class="receta-cantidad" value="<?= htmlspecialchars((string)$cantidadReceta, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                        </div>

                        <!-- Botones en móvil -->
                        <div class="md:col-span-1 flex md:hidden items-start gap-2">
                            <button type="button" data-index="<?= $i ?>" class="load-partida inline-flex items-center px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700" <?= $canModify ? '' : 'disabled' ?>>Sel</button>
                            <button type="button" data-index="<?= $i ?>" class="load-last inline-flex items-center px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700" <?= $canModify ? '' : 'disabled' ?>>Últ</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="flex items-center justify-between mt-4">
            <div class="text-sm text-gray-500">Revisa los datos antes de crear el lote.</div>
            <div class="flex gap-2">
                <a href="/lotes.php" class="px-3 py-2 border rounded text-sm text-gray-700">Cancelar</a>
                <button type="submit" <?= $canModify ? '' : 'disabled' ?> id="submit-create" class="px-4 py-2 bg-teal-600 text-white rounded text-sm <?= $canModify ? '' : 'opacity-60 cursor-not-allowed' ?>">Crear Lote</button>
            </div>
        </div>
    </form>
</main>

<script>
(function() {
    // variables iniciales
    const diasViabilidad = <?= json_encode($diasViabilidad) ?>;
    const elaboradoPeso = parseFloat('<?= (float)($elaborado['peso_obtenido'] ?? 0) ?>') || 0;
    const fechaProdEl = document.getElementById('fecha_produccion');
    const fechaCadVisible = document.getElementById('fecha_cad_visible');
    const fechaCadHidden = document.getElementById('fecha_cad');
    const inputPesoTotal = document.getElementById('peso_total');

    // actualizar fecha caducidad según fecha producción y diasViabilidad
    function updateFechaCaducidad() {
        const prod = fechaProdEl.value;
        if (!prod) {
            fechaCadVisible.value = '';
            fechaCadHidden.value = '';
            return;
        }
        if (diasViabilidad > 0) {
            const d = new Date(prod);
            d.setDate(d.getDate() + parseInt(diasViabilidad, 10));
            const iso = d.toISOString().slice(0,10);
            fechaCadVisible.value = iso;
            fechaCadHidden.value = iso;
        } else {
            fechaCadVisible.value = '';
            fechaCadHidden.value = '';
        }
    }
    fechaProdEl.addEventListener('change', updateFechaCaducidad);
    updateFechaCaducidad();

    // cálculo proporcional de ingredientes
    function getRecetaSum() {
        let sum = 0;
        document.querySelectorAll('.receta-cantidad').forEach(el => {
            sum += parseFloat(el.value) || 0;
        });
        return sum;
    }

    function computeAndFill() {
        const total = parseFloat(inputPesoTotal.value);
        if (!total || total <= 0) {
            showAviso('Introduce un peso total válido mayor que 0 para calcular.');
            return;
        }
        let base = elaboradoPeso;
        if (base <= 0) base = getRecetaSum();
        if (!base || base <= 0) {
            showAviso('No hay referencia para calcular (peso obtenido o suma de receta).');
            return;
        }
        const factor = total / base;
        document.querySelectorAll('[data-index]').forEach(container => {
            const idx = container.getAttribute('data-index');
            const recetaInput = container.querySelector('.receta-cantidad');
            const receta = parseFloat(recetaInput ? recetaInput.value : 0) || 0;
            const newPeso = +(receta * factor).toFixed(3);
            const pesoInput = container.querySelector('input[name="ingredientes[' + idx + '][peso]"]');
            if (pesoInput) pesoInput.value = newPeso;
        });
        clearAviso();
    }

    // aviso visual simple
    const avisoEl = document.createElement('div');
    avisoEl.className = 'text-sm text-red-600 mt-2';
    const formEl = document.getElementById('lotes-form');
    formEl.insertBefore(avisoEl, formEl.firstChild);
    function showAviso(msg) { avisoEl.textContent = msg || ''; }
    function clearAviso() { avisoEl.textContent = ''; }

    if (inputPesoTotal) {
        let timer = null;
        inputPesoTotal.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(computeAndFill, 300);
        });
    }

    // manejador modal y selección de partidas comerciales
    const productosComerciales = <?= json_encode(array_values($productosComerciales), JSON_HEX_TAG | JSON_HEX_APOS) ?> || [];

    function createModal() {
        const m = document.createElement('div');
        m.id = 'pc-modal';
        m.className = 'fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50';
        m.innerHTML = `
  <div class="bg-white rounded-lg max-w-2xl w-full mx-4 overflow-auto" role="dialog" aria-modal="true">
    <div class="p-4 border-b flex items-center justify-between">
      <strong>Seleccionar partida comercial</strong>
      <button type="button" id="pc-modal-close" class="text-gray-600">Cerrar</button>
    </div>
    <div class="p-4">
      <input id="pc-search" class="w-full rounded border-gray-300 px-3 py-2 mb-3" placeholder="Buscar por nombre o referencia" />
      <div id="pc-list" class="space-y-2 max-h-64 overflow-auto"></div>
    </div>
  </div>
`;
        document.body.appendChild(m);
        m.querySelector('#pc-modal-close').addEventListener('click', () => m.classList.add('hidden'));
        return m;
    }

    const modal = createModal();
    const pcListEl = modal.querySelector('#pc-list');
    const pcSearch = modal.querySelector('#pc-search');

    function escapeHtml(str){ return (str+'').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[s])); }

    function renderList(filter='') {
        pcListEl.innerHTML = '';
        const q = (filter || '').toLowerCase();
        productosComerciales.forEach(pc => {
            const label = (pc.nombre || '') + (pc.referencia ? ' — ' + pc.referencia : '') + (pc.peso_total ? (' (' + pc.peso_total + ')') : '');
            if (q && label.toLowerCase().indexOf(q) === -1) return;
            const item = document.createElement('div');
            item.className = 'p-2 border rounded hover:bg-gray-50 flex justify-between items-center';
            const fecha = pc.fecha_caducidad || pc.p_fecha || '';
            const ref = (pc.referencia || pc.numero_lote || '') || '';
            item.innerHTML = `<div class="text-sm"><div class="font-medium">${escapeHtml(label)}</div><div class="text-xs text-gray-500">Cad: ${escapeHtml(fecha || '-')}</div></div>
                <div><button data-id="${escapeHtml(pc.id || '')}" data-nombre="${escapeHtml(label)}" data-fecha="${escapeHtml(fecha)}" data-ref="${escapeHtml(ref)}" class="px-3 py-1 bg-teal-600 text-white rounded text-sm select-pc">Seleccionar</button></div>`;
            pcListEl.appendChild(item);
        });
    }
    pcSearch.addEventListener('input', (e) => renderList(e.target.value));
    renderList();

    document.querySelectorAll('.load-partida').forEach(btn => {
        btn.addEventListener('click', function() {
            const idx = this.getAttribute('data-index');
            modal.dataset.targetIndex = idx;
            pcSearch.value = '';
            renderList();
            modal.classList.remove('hidden');
        });
    });

    modal.addEventListener('click', function(e) {
        const sel = e.target.closest('.select-pc');
        if (!sel) return;
        const id = sel.getAttribute('data-id') || '';
        const nombre = sel.getAttribute('data-nombre') || '';
        const fecha = sel.getAttribute('data-fecha') || '';
        const lote = sel.getAttribute('data-ref') || '';
        const idx = modal.dataset.targetIndex;
        const container = document.querySelector('[data-index="' + idx + '"]');
        if (!container) { modal.classList.add('hidden'); return; }

        const refInput = container.querySelector('.pc-ref');
        const idInput = container.querySelector('.pc-id');
        const fechaHidden = container.querySelector('.pc-fecha');
        const fechaSpan = container.querySelector('.fecha-pc');
        const loteInput = container.querySelector('.ingred-lote');
        const fechaIngredInput = container.querySelector('.ingred-fecha');

        if (refInput) refInput.value = nombre;
        if (idInput) idInput.value = id;
        if (fechaHidden) fechaHidden.value = fecha || '';
        if (fechaSpan) fechaSpan.textContent = fecha || '-';
        if (loteInput && lote) loteInput.value = lote;
        if (fechaIngredInput && fecha) fechaIngredInput.value = fecha;

        modal.classList.add('hidden');
    });

    document.querySelectorAll('.load-last').forEach(btn => {
        btn.addEventListener('click', function() {
            const idx = this.getAttribute('data-index');
            const container = document.querySelector('[data-index="' + idx + '"]');
            if (!container) return;
            const ingId = container.querySelector('input[name="ingredientes[' + idx + '][id_ingrediente]"]')?.value || '';
            if (!ingId) { alert('No se ha identificado el ingrediente en esta línea.'); return; }
            const matches = productosComerciales.filter(pc => String(pc.ingrediente_id || pc.ingrediente || '') === String(ingId));
            if (!matches || matches.length === 0) { alert('No hay partidas registradas para este ingrediente.'); return; }
            matches.sort((a,b) => {
                const fa = new Date(a.fecha_caducidad || a.created_at || 0).getTime();
                const fb = new Date(b.fecha_caducidad || b.created_at || 0).getTime();
                return fb - fa;
            });
            const last = matches[0];
            const ref = (last.referencia || last.numero_lote || last.nombre || '').toString();
            const fecha = last.fecha_caducidad || '';
            const id = last.id || '';

            const refInput = container.querySelector('.pc-ref');
            const idInput = container.querySelector('.pc-id');
            const fechaHidden = container.querySelector('.pc-fecha');
            const fechaSpan = container.querySelector('.fecha-pc');
            const loteInput = container.querySelector('.ingred-lote');
            const fechaIngredInput = container.querySelector('.ingred-fecha');

            if (refInput) refInput.value = ref;
            if (idInput) idInput.value = id;
            if (fechaHidden) fechaHidden.value = fecha;
            if (fechaSpan) fechaSpan.textContent = fecha || '-';
            if (loteInput && ref) loteInput.value = ref;
            if (fechaIngredInput && fecha) fechaIngredInput.value = fecha;
        });
    });

    // Validación final antes de enviar
    (function() {
        const isElaboracion = <?= json_encode((bool)$isElaboracion) ?>;
        formEl.addEventListener('submit', function(e) {
            // Si el formulario está deshabilitado, impedir envío
            if (formEl.dataset.disabled === '1') {
                e.preventDefault();
                return;
            }

            // En todos lo cosas hay que comprobar peso total, temperaturas de inicio y final
            const pesoTotalEl = formEl.querySelector('input[name="peso_total"]');
            const pesoTotalRaw = String(pesoTotalEl.value || '').trim().replace(',', '.');
            const pesoTotalVal = pesoTotalRaw === '' ? 0 : parseFloat(pesoTotalRaw);
            if (isNaN(pesoTotalVal) || pesoTotalVal <= 0) {
                alert('El peso total del lote debe ser un número válido mayor que 0.');
                try { pesoTotalEl.focus(); } catch(_) {}
                e.preventDefault();
                return;
            }
            // Validación de temperaturas: exigir ambos valores, numéricos y coherentes
            const tempInicioEl = formEl.querySelector('input[name="temp_inicio"]');
            const tempFinalEl = formEl.querySelector('input[name="temp_final"]');
            const tempInicioRaw = String(tempInicioEl?.value || '').trim().replace(',', '.');
            const tempFinalRaw = String(tempFinalEl?.value || '').trim().replace(',', '.');

            // exigir ambas temperaturas (no permitir envío si faltan)
            if (tempInicioRaw === '' || tempFinalRaw === '') {
                alert('Debes indicar las temperaturas de inicio y final del proceso.');
                try { (tempInicioRaw === '' ? tempInicioEl : tempFinalEl).focus(); } catch(_) {}
                e.preventDefault();
                return;
            }

            const tempInicioVal = parseFloat(tempInicioRaw);
            const tempFinalVal = parseFloat(tempFinalRaw);

            if (isNaN(tempInicioVal) || isNaN(tempFinalVal)) {
                alert('Las temperaturas deben ser números válidos.');
                try { (isNaN(tempInicioVal) ? tempInicioEl : tempFinalEl).focus(); } catch(_) {}
                e.preventDefault();
                return;
            }

            if (tempFinalVal < tempInicioVal) {
                alert('La temperatura final no puede ser menor que la temperatura de inicio.');
                try { tempFinalEl.focus(); } catch(_) {}
                e.preventDefault();
                return;
            }

            if (!isElaboracion) {
                // comprobar el lote origen se ha definido correctamente
                const parentLoteEl = formEl.querySelector('input[name="parent_lote_id"]');
                const parentLoteVal = String(parentLoteEl.value || '').trim();
                if (!parentLoteVal) {
                    alert('Debes definir un Lote origen para este lote (no es una Elaboración).');
                    try { parentLoteEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                return;
            }

            const prodDateVal = fechaProdEl.value || '';
            const prodDateObj = prodDateVal ? new Date(prodDateVal + 'T00:00:00') : null;

            // recoger todos los inputs de peso por name (se garantiza nombre consistente)
            const pesoInputs = Array.from(formEl.querySelectorAll('input[name^="ingredientes"][name$="[peso]"]'));

            for (const pesoEl of pesoInputs) {
                const name = pesoEl.getAttribute('name') || '';
                const m = name.match(/^ingredientes\[(\d+)\]/);
                if (!m) continue;
                const idx = m[1];

                const pesoRaw = String(pesoEl.value || '').trim().replace(',', '.');
                const pesoVal = pesoRaw === '' ? 0 : parseFloat(pesoRaw);
                if (isNaN(pesoVal) || pesoVal < 0) {
                    alert('Los pesos de ingredientes deben ser números válidos >= 0.');
                    try { pesoEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                if (pesoVal <= 0) continue; // si no hay peso asignado, saltar validación de lote/fecha

                const loteEl = formEl.querySelector(`input[name="ingredientes[${idx}][lote_ingrediente]"]`);
                const fechaEl = formEl.querySelector(`input[name="ingredientes[${idx}][fecha_caducidad_ingrediente]"]`);
                const container = pesoEl.closest('[data-index]');
                const titleEl = container && (container.querySelector('.text-sm.font-semibold') || container.querySelector('.font-semibold'));
                const ingName = titleEl ? (titleEl.textContent || '').trim() : ('Ingrediente ' + idx);

                if (!loteEl) {
                    alert(`Falta el campo "Lote" para "${ingName}".`);
                    try { pesoEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                if (!fechaEl) {
                    alert(`Falta el campo "Fecha caducidad" para "${ingName}".`);
                    try { pesoEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }

                const loteVal = String(loteEl.value || '').trim();
                const fechaVal = String(fechaEl.value || '').trim();

                if (!loteVal) {
                    alert(`El ingrediente "${ingName}" requiere un lote al tener peso asignado.`);
                    try { loteEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                if (!fechaVal) {
                    alert(`El ingrediente "${ingName}" requiere una fecha de caducidad al tener peso asignado.`);
                    try { fechaEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                if (!/^\d{4}-\d{2}-\d{2}$/.test(fechaVal)) {
                    alert(`La fecha de caducidad de "${ingName}" no tiene formato válido (AAAA-MM-DD).`);
                    try { fechaEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                const fechaObj = new Date(fechaVal + 'T00:00:00');
                if (isNaN(fechaObj.getTime())) {
                    alert(`La fecha de caducidad de "${ingName}" no es una fecha válida.`);
                    try { fechaEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
                if (prodDateObj && fechaObj < prodDateObj) {
                    alert(`La fecha de caducidad de "${ingName}" no puede ser anterior a la fecha de producción.`);
                    try { fechaEl.focus(); } catch(_) {}
                    e.preventDefault();
                    return;
                }
            }
            // todo bien -> permitir envío
        }, { passive: false });
    })();

    // Si el formulario está marcado como deshabilitado desde PHP, inhabilitar inputs en cliente
    (function disableIfNeeded() {
        if (formEl.dataset.disabled !== '1') return;
        const elements = formEl.querySelectorAll('input, select, textarea, button');
        elements.forEach(el => {
            // mantener enlaces <a> activos
            if (el.tagName.toLowerCase() === 'a') return;
            el.disabled = true;
        });
        // habilitar enlace cancelar manualmente (si lo hay)
        const cancel = document.querySelector('a[href="/lotes.php"]');
        if (cancel) cancel.removeAttribute('disabled');
    })();

})();
</script>
