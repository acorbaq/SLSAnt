
<?php
/**
 * public/views/imprimir_edit_view.php
 * Vista (solo presentación) para mostrar información del lote, elaboración e ingredientes
 * y ofrecer controles para impresión. La lógica (detectar modo: 'escandallo'|'elaboracion'|'otro',
 * preparar ingredientes alternativos, manejar impresión) debe implementarse en controladores/JS.
 *
 * Variables esperadas (preparadas por el controlador):
 * - $lote (array|null)
 * - $ingredientes (array) // lista ya resuelta según el modo
 * - $elaborado (array|null)
 * - $mode (string) // 'escandallo' | 'elaboracion' | 'otro' | null
 * - $show_individual_print_buttons (bool) // true para mostrar botón en cada ingrediente (por ej. escandallo)
 *
 * Nota: esta vista solo renderiza atributos y botones con data-attributes para que JS/Controlador actúe.
 * @author Andrés Cordeiro
 */

$lote = $lote ?? $lote_elaboracion ?? ($data['lote'] ?? null);
$ingredientes = $loteIngredientes ?? $rows ?? ($data['ingredientes'] ?? []);
$elaborado = $elaborado ?? ($data['elaborado'] ?? null);
$tiposElaborado = $tiposElaborado ?? [];
$mode = $mode ?? ($data['mode'] ?? null);

function getTipoNombreById(array $tipos, $id): string {
    foreach ($tipos as $t) {
        if ((int)($t['id'] ?? null) === (int)$id) {
            return (string)($t['nombre'] ?? '');
        }
    }
    return '';
}

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function format_date_attr($d) { return $d ? esc($d) : ''; }

// Detectar modo a partir de $elaborado['tipo'] y $tiposElaborado
$tipo_id = $elaborado['tipo'] ?? null;
$tipo_nombre = getTipoNombreById($tiposElaborado, $tipo_id);
$tipo_slug = null;
if (!empty($tipo_nombre)) {
    $n = mb_strtolower($tipo_nombre, 'UTF-8');
    if (strpos($n, 'escandallo') !== false) {
        $tipo_slug = 'escandallo';
    } elseif (strpos($n, 'elabor') !== false) {
        $tipo_slug = 'elaboracion';
    } elseif (strpos($n, 'congel') !== false) {
        $tipo_slug = 'congelacion';
    } elseif (strpos($n, 'envas') !== false) {
        $tipo_slug = 'envasado';
    } else {
        // fallback: slug simple del nombre
        $tipo_slug = preg_replace('/[^a-z0-9]+/','-', preg_replace('/\s+/','-', preg_replace('/[^\x20-\x7E]/','', $n)));
    }
}

// $mode puede venir del controlador; si no, usar el detectado por tipo; finalmente 'otro' por defecto
$mode = $mode ?? ($data['mode'] ?? $tipo_slug ?? 'otro');
$show_individual_print_buttons = isset($show_individual_print_buttons) ? (bool)$show_individual_print_buttons : ($mode === 'escandallo');
?>
<div class="max-w-6xl mx-auto p-4">
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- IZQUIERDA: Información -->
        <section class="flex-1 bg-white rounded-xl shadow-sm p-6" aria-label="Información del lote y elaboración">
            <header class="mb-4 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-800">
                        <?= esc($elaborado['nombre'] ?? ($elaborado['nombre'] ?? '—')) ?> - <?= esc($lote['numero_lote'] ?? '—') ?>
                    </h1>
                    <p class="text-sm text-gray-500 mt-1">Resumen de producción, elaboración e ingredientes</p>
                </div>

                <!-- Controles de impresión (solo UI; la acción debe conectarse con JS) -->
                <div class="flex gap-2 items-center">
                    <button type="button"
                        data-action="print-label"
                        data-print-mode="<?= esc($mode ?? 'default') ?>"
                        class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-sm rounded shadow hover:bg-green-500 focus:outline-none"
                        title="Imprimir la etiqueta principal del lote">
                        Imprimir etiqueta
                    </button>
                </div>
            </header>

            <?php if ($lote): ?>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-gray-700 mb-6">
                    <div class="bg-gray-50 p-3 rounded">
                        <dt class="font-medium text-xs text-gray-500">N° Lote</dt>
                        <dd class="mt-1 text-base text-gray-800"><?= esc($lote['numero_lote'] ?? $lote['id'] ?? '—') ?></dd>
                    </div>

                    <div class="bg-gray-50 p-3 rounded">
                        <dt class="font-medium text-xs text-gray-500">Elaboración ID</dt>
                        <dd class="mt-1 text-base text-gray-800"><?= esc($lote['elaboracion_id'] ?? '—') ?></dd>
                    </div>

                    <div class="bg-gray-50 p-3 rounded">
                        <dt class="font-medium text-xs text-gray-500">Fecha producción</dt>
                        <dd class="mt-1 text-gray-800">
                            <?php if (!empty($lote['fecha_produccion'])): ?>
                                <time datetime="<?= format_date_attr($lote['fecha_produccion']) ?>"><?= esc($lote['fecha_produccion']) ?></time>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                    </div>

                    <div class="bg-gray-50 p-3 rounded">
                        <dt class="font-medium text-xs text-gray-500">Fecha caducidad</dt>
                        <dd class="mt-1 text-gray-800">
                            <?php if (!empty($lote['fecha_caducidad'])): ?>
                                <time datetime="<?= format_date_attr($lote['fecha_caducidad']) ?>"><?= esc($lote['fecha_caducidad']) ?></time>
                            <?php else: ?>—<?php endif; ?>
                        </dd>
                    </div>

                    <div class="bg-gray-50 p-3 rounded">
                        <dt class="font-medium text-xs text-gray-500">Peso total</dt>
                        <dd class="mt-1 text-gray-800"><?= esc(($lote['peso_total'] ?? '—') . ' ' . ($lote['unidad_peso'] ?? '')) ?></dd>
                    </div>

                    <div class="bg-gray-50 p-3 rounded">
                        <dt class="font-medium text-xs text-gray-500">Temperatura</dt>
                        <dd class="mt-1 text-gray-800"><?= esc(($lote['temp_inicio'] ?? '-') . ' °C / ' . ($lote['temp_final'] ?? '-') . ' °C') ?></dd>
                    </div>
                </dl>
            <?php else: ?>
                <div class="text-sm text-gray-500 mb-6">No hay datos del lote disponibles.</div>
            <?php endif; ?>

            <section class="mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-2">Elaboración</h2>
                <?php if ($elaborado): ?>
                    <div class="bg-white border rounded p-4 text-sm text-gray-700">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div><span class="font-medium">ID elaborado:</span> <?= esc($elaborado['id_elaborado'] ?? $elaborado['id'] ?? '—') ?></div>
                            <div><span class="font-medium">Peso obtenido:</span> <?= esc($elaborado['peso_obtenido'] ?? '—') ?></div>
                            <div class="sm:col-span-2"><span class="font-medium">Nombre:</span> <?= esc($elaborado['nombre'] ?? '—') ?></div>
                            <div><span class="font-medium">Días viabilidad:</span> <?= esc($elaborado['dias_viabilidad'] ?? '—') ?></div>
                        </div>
                        <?php if (!empty($elaborado['descripcion'])): ?>
                            <div class="mt-3 text-gray-600 text-sm"><?= nl2br(esc($elaborado['descripcion'])) ?></div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="text-sm text-gray-500">No hay datos de la elaboración disponibles.</div>
                <?php endif; ?>
            </section>

            <section>
                <h2 class="text-lg font-semibold text-gray-800 mb-2">Ingredientes</h2>
                <?php if (!empty($ingredientes)): ?>
                    <div class="overflow-x-auto border rounded">
                        <table class="min-w-full text-sm divide-y table-auto">
                            <thead class="bg-gray-100">
                                <tr class="text-left text-gray-700">
                                    <th class="px-4 py-3 font-medium">Ingrediente</th>
                                    <th class="px-4 py-3 font-medium">Peso</th>
                                    <th class="px-4 py-3 font-medium">Ref / Lote</th>
                                    <th class="px-4 py-3 font-medium">Caducidad</th>
                                    <th class="px-4 py-3 font-medium">Alergenos</th>
                                    <?php if ($show_individual_print_buttons): ?>
                                        <th class="px-4 py-3 font-medium">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y">
                                <?php foreach ($ingredientes as $ing):
                                    $ingNombre = $ing['ingrediente_resultante'] ?? ($ing['ingrediente']['nombre'] ?? '');
                                    $peso = $ing['peso'] ?? '';
                                    $ref = $ing['referencia_proveedor'] ?? $ing['lote'] ?? '';
                                    $cad = $ing['fecha_caducidad'] ?? '';
                                    $alergenos = $ing['alergenos'] ?? ($ing['ingrediente']['alergenos'] ?? []);
                                    $alergenos_txt = !empty($alergenos) ? implode(', ', array_map(fn($a)=>$a['nombre'] ?? $a, $alergenos)) : '';
                                ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 align-top"><?= esc($ingNombre) ?></td>
                                        <td class="px-4 py-3 align-top"><?= esc($peso ?: '—') ?></td>
                                        <td class="px-4 py-3 text-gray-600 align-top"><?= esc($ref ?: '—') ?></td>
                                        <td class="px-4 py-3 text-gray-600 align-top">
                                            <?php if ($cad): ?><time datetime="<?= format_date_attr($cad) ?>"><?= esc($cad) ?></time><?php else: ?>—<?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <?php if ($alergenos_txt): ?>
                                                <span class="inline-block bg-red-50 text-red-700 text-xs px-2 py-1 rounded font-medium"><?= esc($alergenos_txt) ?></span>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <?php if ($show_individual_print_buttons): ?>
                                            <td class="px-4 py-3 align-top">
                                                <div class="flex gap-2">
                                                    <button type="button"
                                                        class="px-2 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-500 focus:outline-none"
                                                        data-action="print-ingrediente"
                                                        data-ingrediente-id="<?= esc($ing['id'] ?? $ing['ingrediente_id'] ?? '') ?>"
                                                        data-ingrediente-nombre="<?= esc($ingNombre) ?>">
                                                        Imprimir
                                                    </button>
                                                    <!-- delegado: el controlador/JS puede usar data-* para abrir modal o hacer fetch -->
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-sm text-gray-500">No hay ingredientes registrados.</div>
                <?php endif; ?>
            </section>
        </section>

        <!-- DERECHA: Etiqueta (formato HTML, preview) -->
        <aside class="w-full lg:w-80 bg-white rounded-xl shadow-sm p-5 flex-shrink-0" role="complementary" aria-label="Etiqueta">
            <div class="mb-4">
                <div class="text-xs text-gray-500 font-semibold">Producto</div>
                <div class="mt-1 text-lg font-semibold text-gray-800"><?= esc($elaborado['nombre'] ?? '---') ?></div>
                <div class="text-xs text-gray-500 mt-1">Peso obtenido: <span class="font-medium text-gray-700"><?= esc($elaborado['peso_obtenido'] ?? ($lote['peso_total'] ?? '—')) ?></span></div>
            </div>

            <div class="mb-4">
                <div class="text-xs text-gray-500 font-semibold">Lote / Trazabilidad</div>
                <div class="mt-2 text-sm text-gray-700">
                    <div><span class="font-medium">N° Lote:</span> <?= esc($lote['numero_lote'] ?? '---') ?></div>
                    <div><span class="font-medium">Elab. ID:</span> <?= esc($lote['elaboracion_id'] ?? '---') ?></div>
                    <div class="text-xs text-gray-500 mt-1">Prod: <?= esc($lote['fecha_produccion'] ?? '---') ?> — Cad: <?= esc($lote['fecha_caducidad'] ?? '---') ?></div>
                </div>
            </div>

            <div class="mb-4">
                <div class="text-xs text-gray-500 font-semibold">Ingredientes</div>
                <?php if (!empty($ingredientes)): ?>
                    <ul class="mt-2 space-y-3 text-sm">
                        <?php foreach ($ingredientes as $ing):
                            $name = $ing['ingrediente_resultante'] ?? ($ing['ingrediente']['nombre'] ?? '');
                            $ref = $ing['referencia_proveedor'] ?? $ing['lote'] ?? '';
                            $alergenos = $ing['alergenos'] ?? ($ing['ingrediente']['alergenos'] ?? []);
                            $alergenos_txt = !empty($alergenos) ? implode(', ', array_map(fn($a)=>$a['nombre'] ?? $a, $alergenos)) : '';
                        ?>
                            <li class="border rounded p-3 bg-gray-50 flex justify-between items-start">
                                <div>
                                    <div class="font-medium text-gray-800"><?= esc($name) ?></div>
                                    <?php if ($ref): ?><div class="text-xs text-gray-500 mt-1">Ref: <?= esc($ref) ?></div><?php endif; ?>
                                    <?php if ($alergenos_txt): ?><div class="text-xs text-red-700 font-medium mt-1">Alerg.: <?= esc($alergenos_txt) ?></div><?php endif; ?>
                                </div>

                                <?php if ($show_individual_print_buttons): ?>
                                    <div class="ml-3 flex-shrink-0">
                                        <button type="button"
                                            class="px-2 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-500 focus:outline-none"
                                            data-action="print-ingrediente"
                                            data-ingrediente-id="<?= esc($ing['id'] ?? $ing['ingrediente_id'] ?? '') ?>"
                                            data-ingrediente-nombre="<?= esc($name) ?>">
                                            Imprimir
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="text-sm text-gray-500 mt-2">Sin ingredientes</div>
                <?php endif; ?>
            </div>

            <div>
                <div class="text-xs text-gray-500 font-semibold">Notas</div>
                <div class="text-xs text-gray-600 mt-2">Temperaturas: <span class="font-medium text-gray-700"><?= esc(($lote['temp_inicio'] ?? '-') . ' / ' . ($lote['temp_final'] ?? '-')) ?> °C</span></div>
                <div class="text-xs text-gray-600 mt-1">Unidad peso: <span class="font-medium"><?= esc($lote['unidad_peso'] ?? '-') ?></span></div>
            </div>
        </aside>
    </div>
</div>