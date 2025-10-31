<?php
/**
 * Vista para la gestión de los lotes de elaborados.
 * 
 * Esta vista incluye 3 pestañas:
 * - Elaboración: para listar en botones los elaborados de tipo elaboración. 4 botones por fila.
 * - Escandallo: para listar en botones los elaborados de tipo escandallo. 4 botones por fila.
 * - Otros: para listar en botones los elaborados de tipo otros. 4 botones por fila.
 *
 * Cada botón lleva al formulario de creación de un nuevo lote para el elaborado seleccionado.
 *
 * El handle request del controlador nos envia $elaborados (lista de elaborados), $tiposElaboracion (tipos)
 * y $canModify (booleano que indica si el usuario puede crear/modificar lotes).
 * 
 * lotes_view está insertado en el principal lotes.php que incluye ademas la cabecera y el pie de página.
 */

$tiposElaboracion = $tiposElaboracion ?? [];
$elaborados = $elaborados ?? [];
$canModify = isset($canModify) ? (bool)$canModify : true;

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

/*
 * Nuevo: filtrado por búsqueda (servidor).
 * - Parámetro GET 'q' (simple substring case-insensitive UTF-8)
 * - Filtra $elaborados antes de agrupar.
 */
$q = trim((string)($_GET['q'] ?? ''));

// normalizamos la búsqueda a minúsculas para mb_stripos
$qLower = $q !== '' ? mb_strtolower($q, 'UTF-8') : '';

if ($qLower !== '') {
    $elaborados = array_values(array_filter($elaborados, function ($e) use ($qLower) {
        // campos a buscar
        $hay = [];
        $hay[] = (string)($e['nombre'] ?? '');
        $hay[] = (string)($e['id_elaborado'] ?? '');
        $hay[] = (string)($e['peso_obtenido'] ?? '');
        $hay[] = (string)($e['dias_viabilidad'] ?? '');
        // búsqueda será realizada en minúsculas con mb_stripos (acepta UTF-8)
        foreach ($hay as $f) {
            if ($f !== '' && mb_stripos($f, $qLower) !== false) return true;
        }
        return false;
    }));
}

// Agrupado en las 3 categorías (igual que antes)
$groups = [
    'elaboracion' => [],
    'escandallo'  => [],
    'otros'       => [],
];

// Clasificar elaborados por categoría en base al nombre del tipo
foreach ($elaborados as $e) {
    $tipoId = $e['tipo'] ?? null;
    $tipoNombre = getTipoNombreById($tiposElaboracion, $tipoId);
    $cat = categoriaDesdeNombre($tipoNombre);
    $groups[$cat][] = $e;
}

$tabTitles = [
    'elaboracion' => 'Elaboración',
    'escandallo'  => 'Escandallo',
    'otros'       => 'Otros'
];

$tab = $_GET['tab'] ?? 'elaboracion';
if (!array_key_exists($tab, $tabTitles)) {
    $tab = 'elaboracion';
}
?>
<main class="max-w-6xl mx-auto py-8 px-4">
    <header class="mb-6">
        <h1 class="text-2xl font-bold">Gestión de Lotes de Elaborados</h1>
        <p class="text-sm text-gray-500">Explora los elaborados por categoría y crea lotes rápidamente.</p>
    </header>

    <?php if (empty($elaborados) && $qLower === ''): ?>
        <div class="rounded-md bg-blue-50 border border-blue-100 p-4 text-blue-700">No hay elaborados disponibles.</div>
    <?php else: ?>
        <!-- Buscador -->
        <form method="get" class="mb-4 flex gap-2 items-center">
            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <input name="q" type="search" value="<?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                   placeholder="Buscar por nombre, ID, tipo, peso o días..." 
                   class="flex-1 border rounded px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-teal-300" />
            <button type="submit" class="px-3 py-2 bg-teal-600 text-white rounded text-sm">Buscar</button>
            <?php if ($qLower !== ''): ?>
                <a href="?tab=<?php echo urlencode($tab); ?>" class="px-3 py-2 border rounded text-sm text-gray-700">Limpiar</a>
            <?php endif; ?>
        </form>

        <!-- Pestañas (preservando q en los enlaces para mantener la búsqueda entre pestañas) -->
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

        <!-- Resultados -->
        <?php if ($qLower !== ''): ?>
            <p class="text-sm text-gray-600 mb-3">Resultados de la búsqueda: <strong><?php echo htmlspecialchars($q, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> — <span class="font-medium"><?php echo count($elaborados); ?></span> coincidencias.</p>
            <?php if (count($elaborados) === 0): ?>
                <div class="rounded-md bg-gray-50 border border-gray-100 p-4 text-gray-600">No se encontraron elaborados para la búsqueda "<?php echo htmlspecialchars($q); ?>".</div>
            <?php endif; ?>
        <?php endif; ?>

        <section>
            <?php foreach ($tabTitles as $key => $label):
                if ($key !== $tab) continue;
                $list = $groups[$key];
            ?>
                <h2 class="text-lg font-semibold mb-3"><?php echo htmlspecialchars($label); ?></h2>

                <?php if (empty($list)): ?>
                    <div class="rounded-md bg-gray-50 border border-gray-100 p-4 text-gray-600">No hay elaborados en "<?php echo htmlspecialchars($label); ?>".</div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach ($list as $elaborado): ?>
                            <article class="border rounded-lg shadow-sm p-4 flex flex-col">
                                <div class="mb-2">
                                    <h3 class="text-md font-semibold"><?php echo htmlspecialchars($elaborado['nombre']); ?></h3>
                                    <p class="text-xs text-gray-500 mt-1">ID: <?php echo htmlspecialchars((string)($elaborado['id_elaborado'] ?? '-')); ?></p>
                                </div>

                                <dl class="text-sm text-gray-700 mb-4">
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Peso</dt>
                                        <dd><?php echo htmlspecialchars((string)($elaborado['peso_obtenido'] ?? '-')); ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-gray-500">Días viabilidad</dt>
                                        <dd><?php echo htmlspecialchars((string)($elaborado['dias_viabilidad'] ?? '-')); ?></dd>
                                    </div>
                                </dl>

                                <div class="mt-auto space-y-2">
                                    <a href="/lotes.php?create&id=<?php echo urlencode($elaborado['id_elaborado']); ?>"
                                           class="block text-center w-full inline-block bg-teal-600 text-white text-sm px-3 py-2 rounded-md">Crear lote</a>
                                    <?php if ($canModify): ?>
                                        <?php
                                            $tipoNombre = getTipoNombreById($tiposElaboracion, $elaborado['tipo'] ?? null);
                                            $categoria = categoriaDesdeNombre($tipoNombre);
                                        ?>
                                        <a href="/elaborados.php?modificar&tipo=<?php echo urlencode($categoria); ?>&id=<?php echo urlencode($elaborado['id_elaborado']); ?>"
                                           class="block text-center w-full inline-block border border-gray-200 text-gray-700 text-sm px-3 py-2 rounded-md">Editar elaborado</a>
                                    <?php else: ?>
                                        <button class="w-full bg-gray-200 text-gray-500 text-sm px-3 py-2 rounded-md" disabled>Sin permisos</button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</main>