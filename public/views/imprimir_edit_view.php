<?php
// Variables esperadas: $lote (array), $ingredientes (array of arrays), $elaboracion (array).
$lote = $lote ?? $lote_elaboracion ?? ($data['lote'] ?? null);
$ingredientes = $loteIngredientes ?? $rows ?? ($data['ingredientes'] ?? []);
$elaboracion = $elaborado ?? ($data['elaboracion'] ?? null);

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function format_date_attr($d) { return $d ? esc($d) : ''; }
?>
<div class="max-w-6xl mx-auto p-4">
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- IZQUIERDA: Información -->
        <section class="flex-1 bg-white rounded-xl shadow-sm p-6" aria-label="Información del lote y elaboración">
            <header class="mb-4">
                <h1 class="text-2xl font-semibold text-gray-800"><?= esc($elaborado['nombre'] ?? '—') ?> - <?= esc($lote['numero_lote'] ?? '—') ?></h1>
                <p class="text-sm text-gray-500 mt-1">Resumen de producción, elaboración e ingredientes</p>
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
                <?php if ($elaboracion): ?>
                    <div class="bg-white border rounded p-4 text-sm text-gray-700">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div><span class="font-medium">ID elaborado:</span> <?= esc($elaboracion['id_elaborado'] ?? $elaboracion['id'] ?? '—') ?></div>
                            <div><span class="font-medium">Peso obtenido:</span> <?= esc($elaboracion['peso_obtenido'] ?? '—') ?></div>
                            <div class="sm:col-span-2"><span class="font-medium">Nombre:</span> <?= esc($elaboracion['nombre'] ?? '—') ?></div>
                            <div><span class="font-medium">Días viabilidad:</span> <?= esc($elaboracion['dias_viabilidad'] ?? '—') ?></div>
                        </div>
                        <?php if (!empty($elaboracion['descripcion'])): ?>
                            <div class="mt-3 text-gray-600 text-sm"><?= nl2br(esc($elaboracion['descripcion'])) ?></div>
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

        <!-- DERECHA: Etiqueta (formato HTML) -->
        <aside class="w-full lg:w-80 bg-white rounded-xl shadow-sm p-5 flex-shrink-0" role="complementary" aria-label="Etiqueta">
            <div class="mb-4">
                <div class="text-xs text-gray-500 font-semibold">Producto</div>
                <div class="mt-1 text-lg font-semibold text-gray-800"><?= esc($elaboracion['nombre'] ?? '---') ?></div>
                <div class="text-xs text-gray-500 mt-1">Peso obtenido: <span class="font-medium text-gray-700"><?= esc($elaboracion['peso_obtenido'] ?? ($lote['peso_total'] ?? '—')) ?></span></div>
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
                            <li class="border rounded p-3 bg-gray-50">
                                <div class="font-medium text-gray-800"><?= esc($name) ?></div>
                                <?php if ($ref): ?><div class="text-xs text-gray-500 mt-1">Ref: <?= esc($ref) ?></div><?php endif; ?>
                                <?php if ($alergenos_txt): ?><div class="text-xs text-red-700 font-medium mt-1">Alerg.: <?= esc($alergenos_txt) ?></div><?php endif; ?>
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