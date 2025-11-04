<?php
/**
 * Vista de impresión/listado de lotes.
 * Variables esperadas:
 * - $lotes: array de lotes
 * - $elaboradosLotes: array asociativo indexado por elaboracion_id con datos del elaborado
 * - $tiposElaboracion: array de tipos (id,nombre)
 * - $canModify: boolean
 */

$lotes = $lotes ?? [];
$elaboradosLotes = $elaboradosLotes ?? [];
$tiposElaboracion = $tiposElaboracion ?? [];
$canModify = isset($canModify) ? (bool)$canModify : false;

function getTipoNombreById(array $tipos, $id): string {
    foreach ($tipos as $t) {
        if ((int)($t['id'] ?? null) === (int)$id) {
            return (string)($t['nombre'] ?? '');
        }
    }
    return '';
}
function categoriaDesdeNombre(string $nombre): string {
    $n = mb_strtolower($nombre, 'UTF-8');
    if (strpos($n, 'elabor') !== false) return 'elaboracion';
    if (strpos($n, 'escand') !== false) return 'escandallo';
    return 'otros';
}

// Agrupar lotes por categoría usando tipo del elaborado asociado
$groups = [
    'elaboracion' => [],
    'escandallo'  => [],
    'otros'       => [],
];

foreach ($lotes as $lote) {
    $eid = (int)($lote['elaboracion_id'] ?? 0);
    $elaborado = $elaboradosLotes[$eid] ?? null;
    $tipoNombre = '';
    if ($elaborado && isset($elaborado['tipo'])) {
        $tipoNombre = getTipoNombreById($tiposElaboracion, $elaborado['tipo']);
    }
    $cat = categoriaDesdeNombre($tipoNombre);
    $groups[$cat][] = ['lote' => $lote, 'elaborado' => $elaborado];
}

$tabTitles = [
    'elaboracion' => 'Elaboración',
    'escandallo'  => 'Escandallo',
    'otros'       => 'Otros'
];

$tab = $_GET['tab'] ?? 'elaboracion';
if (!array_key_exists($tab, $tabTitles)) $tab = 'elaboracion';
$q = trim((string)($_GET['q'] ?? ''));
$qLower = $q !== '' ? mb_strtolower($q, 'UTF-8') : '';
?>
<main class="max-w-6xl mx-auto py-8 px-4">
    <header class="mb-6">
        <h1 class="text-2xl font-bold">Imprimir - Lotes</h1>
        <p class="text-sm text-gray-500">Lista de lotes por tipo de elaborado. Usa las pestañas para filtrar.</p>
    </header>

    <form method="get" class="mb-4 flex gap-2 items-center">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
        <input name="q" type="search" value="<?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
               placeholder="Buscar por ID, número de lote, elaboración, fechas..." 
               class="flex-1 border rounded px-3 py-2 text-sm bg-white" />
        <button type="submit" class="px-3 py-2 bg-teal-600 text-white rounded text-sm">Buscar</button>
        <?php if ($qLower !== ''): ?>
            <a href="?tab=<?php echo urlencode($tab); ?>" class="px-3 py-2 border rounded text-sm">Limpiar</a>
        <?php endif; ?>
    </form>

    <nav class="mb-4 flex gap-2">
        <?php 
            $extraQ = $qLower !== '' ? '&q=' . urlencode($q) : '';
            foreach ($tabTitles as $key => $label):
                $isActive = ($tab === $key);
        ?>
            <a href="?tab=<?php echo urlencode($key) . $extraQ; ?>"
               class="px-3 py-1 rounded-md text-sm font-medium <?php echo $isActive ? 'bg-teal-600 text-gray' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                <?php echo htmlspecialchars($label); ?>
                <span class="ml-2 inline-block px-2 py-0.5 text-xs font-semibold bg-gray-200 rounded"><?php echo count($groups[$key]); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php foreach ($tabTitles as $key => $label):
        if ($key !== $tab) continue;
        $list = $groups[$key];
    ?>
        <h2 class="text-lg font-semibold mb-3"><?php echo htmlspecialchars($label); ?></h2>

        <?php if (empty($list)): ?>
            <div class="rounded-md bg-gray-50 border border-gray-100 p-4 text-gray-600">No hay lotes en "<?php echo htmlspecialchars($label); ?>".</div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <?php foreach ($list as $item):
                    $lote = $item['lote'];
                    $elab = $item['elaborado'];
                ?>
                    <article class="border rounded-lg shadow-sm p-4 flex flex-col">
                        <div class="mb-2">
                            <h3 class="text-md font-semibold"><?php echo htmlspecialchars($elab['nombre'] ?? ('Elaborado #' . ($lote['elaboracion_id'] ?? ''))); ?></h3>
                            <p class="text-xs text-gray-500 mt-1">Nº lote: <?php echo htmlspecialchars((string)($lote['numero_lote'] ?? '-')); ?></p>
                        </div>

                        <dl class="text-sm text-gray-700 mb-4">
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Producción</dt>
                                <dd><?php echo htmlspecialchars((string)($lote['fecha_produccion'] ?? '-')); ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Caducidad</dt>
                                <dd><?php echo htmlspecialchars((string)($lote['fecha_caducidad'] ?? '-')); ?></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Peso</dt>
                                <dd><?php echo htmlspecialchars((string)($lote['peso_total'] ?? '-')) . ' ' . htmlspecialchars((string)($lote['unidad_peso'] ?? '')); ?></dd>
                            </div>
                        </dl>

                        <div class="mt-auto space-y-2">
                            <a href="/imprimir.php?id=<?php echo urlencode((string)($lote['id'] ?? '')); ?>"
                               class="block text-center w-full inline-block bg-teal-600 text-white text-sm px-3 py-2 rounded-md">Imprimir</a>
                            <a href="/imprimir.php?view&id=<?php echo urlencode((string)($lote['id'] ?? '')); ?>"
                               class="block text-center w-full inline-block border border-gray-200 text-gray-700 text-sm px-3 py-2 rounded-md">Ver</a>
                            <?php if ($canModify): ?>
                                <a href="/imprimir.php?edit&id=<?php echo urlencode((string)($lote['id'] ?? '')); ?>"
                                   class="block text-center w-full inline-block bg-white border border-gray-200 text-gray-700 text-sm px-3 py-2 rounded-md">Rectificar</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</main>