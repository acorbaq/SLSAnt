<?php

/**
 * Se llega al pulsar el boton de "Crear Lote" en la vista de elaborados.php
 * Aquí se muestra el formulario para crear un nuevo lote para un elaborado específico.
 * Desde el handler se reciben 5 arrays:
 * - ingredientes: lista de ingredientes del elaborado
 * - unidades: lista de unidades disponibles
 * - elaborado: datos del elaborado seleccionado
 * - ingredientesElaborado: lista de ingredientes específicos del elaborado con sus cantidades y unidades
 * - alergenos: lista de alérgenos asociados a los ingredientes del elaborado
 * 
 * Al acceder a esta vista se rellena al completo el formulario con todos los datos necesarios sobre el elaborado y sus ingredientes.
 * 
 * Esta vista se carga desde lotes.php en el método renderCreate del controlador LotesController.
 * Ya incluye el header y footer desde lotes.php.
 * 
 * Para el correcto funcionamiento de esta vista la información se toma desde los arrays mencionados
 * mientras que las tablas relativas a Lotes, lotes ingredientes, productos comerciales y cierres se usan para crear los lotes.
 * Esta vista es un formulario que debe tomar información de productos comerciales y transformalos en lotes registrando los lotes que se generan y
 * sus ingredientes.
 * Al pulsar "Crear Lote" se envia a una vista intermedia de la sección imprimir etiquetas que se encarga de procesar las impresiones y
 * cerrar el lote (los lotes se cierran tambien cuando llega la fecha de caducidad).
 * Crear Lote (vista)
 *
 * Variables esperadas:
 *  - array  $ingredientes               : catálogo de ingredientes (id_ingrediente, nombre)
 *  - array  $unidades                   : unidades disponibles (id_unidad, nombre, abreviatura)
 *  - array  $elaborado                  : elaborado seleccionado (id_elaborado, nombre, peso_obtenido, dias_viabilidad, tipo)
 *  - array  $ingredientesElaborado      : líneas del elaborado (id_ingrediente, cantidad, id_unidad, nombre)
 *  - array  $productosComerciales       : partidas disponibles (id, nombre, referencia, peso_total,...)
 *  - string $csrf                       : token CSRF
 *  - bool   $canModify                  : permiso para crear
 */

$ingredientes = $ingredientes ?? [];
$unidades = $unidades ?? [];
$elaborado = $elaborado ?? ['id_elaborado' => null, 'nombre' => '', 'dias_viabilidad' => 0];
$ingredientesElaborado = $ingredientesElaborado ?? [];
$productosComerciales = $productosComerciales ?? [];
$csrf = $csrf ?? '';
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

    <form method="post" action="/lotes.php" class="space-y-6 bg-white p-4 rounded shadow">
        <input type="hidden" name="action" value="create_lote">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
        <input type="hidden" name="elaboracion_id" value="<?= htmlspecialchars((string)($elaborado['id_elaborado'] ?? $elaborado['id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Número de lote</label>
                <div class="mt-1 text-sm text-gray-600">Se generará automáticamente al crear el lote.</div>
                <!-- no se envía número de lote desde el cliente -->
            </div>

            <?php if (!$isElaboracion): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Lote origen (parent)</label>
                    <input type="text" name="parent_lote_id" value="" class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" placeholder="ID lote origen si aplica">
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
                <input id="fecha_produccion" type="date" name="fecha_produccion" value="<?= htmlspecialchars($fechaProdDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" required class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Fecha caducidad</label>
                <!-- campo no editable visible + campo hidden para enviar valor -->
                <input id="fecha_cad_visible" type="text" value="<?= htmlspecialchars($fechaCadDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" readonly class="mt-1 block w-full rounded border-gray-100 bg-gray-50 px-3 py-2 sm:text-sm text-gray-700" />
                <input id="fecha_cad" type="hidden" name="fecha_caducidad" value="<?= htmlspecialchars($fechaCadDefault, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" />
                <p class="text-xs text-gray-400 mt-1">Calculada como fecha de producción + <?= $diasViabilidad ?> días de viabilidad.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Peso total *</label>
                <input id="peso_total" type="number" step="0.001" name="peso_total" required class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Unidad de peso</label>
                <div class="mt-1 text-sm text-gray-700">kg</div>
                <input type="hidden" name="unidad_peso" value="kg">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Temp. inicio</label>
                <input type="number" step="0.1" name="temp_inicio" class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Temp. final</label>
                <input type="number" step="0.1" name="temp_final" class="mt-1 block w-full rounded border-gray-300 px-3 py-2 sm:text-sm" />
            </div>
        </div>

        <section>
            <h2 class="text-lg font-medium mb-3">Líneas de ingredientes (definidas en la receta)</h2>
            <p class="text-sm text-gray-500 mb-3">Las líneas son fijas según la receta. Ajusta sólo el peso a usar y la partida origen si procede.</p>

            <div class="space-y-3">
                <?php
                    // Filtrar líneas: si es una Elaboración y la línea es de origen (es_origen == 1) la ocultamos
                    $linesToShow = [];
                    foreach ($ingredientesElaborado as $line) {
                        $es_origen = (int) ($line['es_origen'] ?? $line['es_origenario'] ?? $line['es_origen_flag'] ?? 0);
                        if ($isElaboracion && $es_origen === 1) {
                            // saltar ingredientes de tipo 'origen' en elaboraciones
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
                                <!-- botones compactos en móvil se muestran a la derecha -->
                                <div class="hidden md:flex flex-col items-end gap-2">
                                    <button type="button" data-index="<?= $i ?>" class="load-partida inline-flex items-center gap-2 px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700 hover:bg-gray-50">Seleccionar</button>
                                    <button type="button" data-index="<?= $i ?>" class="load-last inline-flex items-center gap-2 px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700 hover:bg-gray-50">Cargar último</button>
                                </div>
                            </div>
                        </div>

                        <!-- Peso a usar -->
                        <div class="md:col-span-3">
                            <label class="block text-xs text-gray-500 mb-1">Peso a usar *</label>
                            <div class="flex">
                                <input type="number" step="0.001" name="ingredientes[<?= $i ?>][peso]" required class="peso-ingrediente block w-full rounded-l border border-gray-300 px-2 py-2 text-sm" />
                                <span class="inline-flex items-center px-3 rounded-r border-t border-b border-r border-gray-300 bg-gray-50 text-sm text-gray-600">kg</span>
                            </div>
                        </div>

                        <!-- Lote y fecha ingrediente -->
                        <div class="md:col-span-4">
                            <?php if ($isElaboracion): ?>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs text-gray-500">Lote</label>
                                        <input type="text" name="ingredientes[<?= $i ?>][lote_ingrediente]" class="ingred-lote mt-1 block w-full rounded border-gray-300 px-2 py-2 text-sm" placeholder="ej. L-2025-001" />
                                    </div>
                                    <div>
                                        <label class="block text-xs text-gray-500">Fecha caducidad</label>
                                        <input type="date" name="ingredientes[<?= $i ?>][fecha_caducidad_ingrediente]" class="ingred-fecha mt-1 block w-full rounded border-gray-300 px-2 py-2 text-sm" placeholder="YYYY-MM-DD" />
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
                            <button type="button" data-index="<?= $i ?>" class="load-partida inline-flex items-center px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700">Sel</button>
                            <button type="button" data-index="<?= $i ?>" class="load-last inline-flex items-center px-2 py-1 bg-gray-100 border rounded text-xs text-gray-700">Últ</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="flex items-center justify-between mt-4">
            <div class="text-sm text-gray-500">Revisa los datos antes de crear el lote.</div>
            <div class="flex gap-2">
                <a href="/lotes.php" class="px-3 py-2 border rounded text-sm text-gray-700">Cancelar</a>
                <button type="submit" <?= $canModify ? '' : 'disabled' ?> class="px-4 py-2 bg-teal-600 text-white rounded text-sm <?= $canModify ? '' : 'opacity-60 cursor-not-allowed' ?>">Crear Lote</button>
            </div>
        </div>
    </form>
</main>

<script>
    (function() {
        const inputPesoTotal = document.getElementById('peso_total');
        const fechaProd = document.getElementById('fecha_produccion');
        const fechaCadVisible = document.getElementById('fecha_cad_visible');
        const fechaCadHidden = document.getElementById('fecha_cad');
        const diasViabilidad = <?= json_encode($diasViabilidad) ?>;
        const elaboradoPeso = parseFloat('<?= (float)($elaborado['peso_obtenido'] ?? 0) ?>') || 0;

        // actualizar fecha de caducidad al cambiar fecha de producción
        function updateFechaCaducidad() {
            const prod = fechaProd.value;
            if (!prod) {
                fechaCadVisible.value = '';
                fechaCadHidden.value = '';
                return;
            }
            if (diasViabilidad > 0) {
                const d = new Date(prod);
                d.setDate(d.getDate() + parseInt(diasViabilidad, 10));
                const iso = d.toISOString().slice(0, 10);
                fechaCadVisible.value = iso;
                fechaCadHidden.value = iso;
            } else {
                fechaCadVisible.value = '';
                fechaCadHidden.value = '';
            }
        }
        fechaProd.addEventListener('change', updateFechaCaducidad);
        // init
        updateFechaCaducidad();

        // cálculo proporcional de ingredientes
        if (!inputPesoTotal) return;
        const avisoEl = document.createElement('div');
        avisoEl.className = 'text-sm text-red-600 mt-2';
        inputPesoTotal.closest('form').insertBefore(avisoEl, inputPesoTotal.closest('form').firstChild);

        function notify(msg) {
            avisoEl.textContent = msg || '';
        }

        function clearNotify() {
            notify('');
        }

        function getRecetaSum() {
            let sum = 0;
            document.querySelectorAll('.receta-cantidad').forEach(el => {
                const v = parseFloat(el.value) || 0;
                sum += v;
            });
            return sum;
        }

        function computeAndFill() {
            const total = parseFloat(inputPesoTotal.value);
            if (!total || total <= 0) {
                notify('Introduce un peso total válido mayor que 0 para calcular.');
                return;
            }

            let base = elaboradoPeso;
            if (base <= 0) base = getRecetaSum();
            if (!base || base <= 0) {
                notify('No hay referencia para calcular (peso obtenido o suma de receta).');
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
            clearNotify();
        }

        let timer = null;
        inputPesoTotal.addEventListener('input', function() {
            clearTimeout(timer);
            timer = setTimeout(computeAndFill, 300);
        });
    })();
</script>

<script>
    (function() {
        // productos comerciales disponibles (para el selector modal)
        const productosComerciales = <?= json_encode(array_values($productosComerciales), JSON_HEX_TAG | JSON_HEX_APOS) ?> || [];

        // crear modal simple al vuelo
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

            m.querySelector('#pc-modal-close').addEventListener('click', () => {
                m.classList.add('hidden');
            });
            return m;
        }

        const modal = createModal();
        const pcListEl = modal.querySelector('#pc-list');
        const pcSearch = modal.querySelector('#pc-search');

        function escapeHtml(str) {
            return (str + '').replace(/[&<>"']/g, s => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [s]));
        }

        function renderList(filter = '') {
            pcListEl.innerHTML = '';
            const q = (filter || '').toLowerCase();
            productosComerciales.forEach(pc => {
                const label = (pc.nombre || '') + (pc.referencia ? ' — ' + pc.referencia : '') + (pc.peso_total ? (' (' + pc.peso_total + ')') : '');
                if (q && label.toLowerCase().indexOf(q) === -1) return;
                const item = document.createElement('div');
                item.className = 'p-2 border rounded hover:bg-gray-50 flex justify-between items-center';
                item.innerHTML = `<div class="text-sm"><div class="font-medium">${escapeHtml(label)}</div><div class="text-xs text-gray-500">Cad: ${pc.fecha_caducidad || '-'}</div></div>
                    <div><button data-id="${pc.id}" data-nombre="${escapeHtml(label)}" data-fecha="${pc.fecha_caducidad || ''}" data-ref="${escapeHtml(pc.referencia || pc.numero_lote || '')}" class="px-3 py-1 bg-teal-600 text-white rounded text-sm select-pc">Seleccionar</button></div>`;
                pcListEl.appendChild(item);
            });
        }
        pcSearch.addEventListener('input', (e) => renderList(e.target.value));
        renderList();

        // Handler para botones "Cargar"
        document.querySelectorAll('.load-partida').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.getAttribute('data-index');
                modal.dataset.targetIndex = idx;
                pcSearch.value = '';
                renderList();
                modal.classList.remove('hidden');
            });
        });

        // selección dentro de la lista (delegación)
        modal.addEventListener('click', function(e) {
            const sel = e.target.closest('.select-pc');
            if (!sel) return;
            const id = sel.getAttribute('data-id');
            const nombre = sel.getAttribute('data-nombre');
            const fecha = sel.getAttribute('data-fecha');
            const lote = sel.getAttribute('data-ref') || '';

            const idx = modal.dataset.targetIndex;
            const container = document.querySelector('[data-index="' + idx + '"]');
            if (!container) {
                modal.classList.add('hidden');
                return;
            }

            // rellenar campos
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

        // cargar último registro disponible para el ingrediente de la línea
        document.querySelectorAll('.load-last').forEach(btn => {
            btn.addEventListener('click', function() {
                const idx = this.getAttribute('data-index');
                const container = document.querySelector('[data-index="' + idx + '"]');
                if (!container) return;
                const ingId = container.querySelector('input[name="ingredientes[' + idx + '][ingrediente_id]"]')?.value || '';
                if (!ingId) {
                    alert('No se ha identificado el ingrediente en esta línea.');
                    return;
                }
                // filtrar partidas por ingrediente
                const matches = productosComerciales.filter(pc => String(pc.ingrediente_id || pc.ingrediente || '') === String(ingId));
                if (!matches || matches.length === 0) {
                    alert('No hay partidas registradas para este ingrediente.');
                    return;
                }
                // ordenar por fecha de caducidad o created_at, elegir la más reciente
                matches.sort((a, b) => {
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

    })();
</script>