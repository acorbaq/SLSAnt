<?php
declare(strict_types=1);
/**
 * elaborados_edit_view.php
 *
 * Vista para crear/editar un elaborado. Soporta dos flujos diferentes y
 * carga parciales específicos para cada uno: "elaboracion" y "escandallo".
 *
 * Variables esperadas (proveídas por el controller):
 *  - array|null $elaborado      : datos del elaborado cuando se edita (null = crear)
 *  - array $ingredientes        : catálogo de ingredientes (cada uno puede tener 'id_ingrediente','nombre','indicaciones','alergenos'...)
 *  - string $csrf               : token CSRF
 *  - bool   $debug              : flag debug
 *  - bool   $canModify          : permiso para guardar cambios
 *
 * Diseño general / flujo:
 *  - El formulario principal permite elegir tipo (elaboracion | escandallo).
 *  - Según tipo, se muestra el partial correspondiente (partials/elaborado_view.php
 *    o partials/escandallo_view.php) que contiene las líneas específicas.
 *  - Se minimiza JS: solo se usa para añadir/quitar filas dinámicamente y
 *    para cálculo simple de "restos" en escandallo (peso_inicial - suma_salidas).
 *  - Guardado se realiza mediante POST a /elaborados.php (controller debe validar CSRF y permisos).
 *
 * Nota: los partials reciben las mismas variables ($elaborado, $ingredientes, $csrf, $debug).
 */

$titleSection = $elaborado !== null ? 'Modificar Elaborado - SLSAnt' : 'Nuevo Elaborado - SLSAnt';

if ($elaborado !== null) {
    $isEdit = true;
    $isNew = false;
} else {
    $isEdit = false;
    $isNew = true;
}

// obtener get tipo
$tipo = $_GET['tipo'] ?? null;
?>

<main class="max-w-4xl mx-auto py-8 px-4">
    <header class="mb-6">
        <h1 class="text-2xl font-bold"><?= $isEdit ? 'Editar Elaborado' : 'Crear Nuevo Elaborado' ?></h1>
        <p class="text-sm text-gray-500"><?= $isEdit ? 'Modifica los detalles del elaborado.' : 'Rellena el formulario para crear un nuevo elaborado.' ?></p>
    </header>
    <!-- Selector simple sin JS: enlaces que añaden ?tipo=... -->
    <?php if ($isNew): ?>
    <div class="mb-4">
        <a href="?crear&tipo=elaboracion" class="px-3 py-1 border rounded <?php echo $tipo === 'elaboracion' ? 'bg-teal-100' : ''; ?>">Elaboración</a>
        <a href="?crear&tipo=escandallo" class="px-3 py-1 border rounded <?php echo $tipo === 'escandallo' ? 'bg-teal-100' : ''; ?>">Escandallo</a>
        <a href="?crear&tipo=otros" class="px-3 py-1 border rounded <?php echo $tipo === 'otros' ? 'bg-teal-100' : ''; ?>">Otras elaboraciones</a>
    </div>
    <?php else: ?>
        <div class="mb-4">
            <a class="px-3 py-1 border rounded bg-teal-100">
                <?php
                $tipoStr = (string) ($tipo ?? '');
                if ($tipoStr !== '') {
                    $first = mb_substr($tipoStr, 0, 1, 'UTF-8');
                    $rest  = mb_substr($tipoStr, 1, null, 'UTF-8');
                    echo htmlspecialchars(mb_strtoupper($first, 'UTF-8') . $rest, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                }
                ?>
            </a>
        </div>
    <?php endif; ?>
    <?php if ($tipo === null): ?>
        <p class="text-red-600">Por favor, selecciona el tipo de elaborado (Elaboración o Escandallo) para continuar.</p>
    <?php else: 
        // Incluir el partial según el tipo seleccionado
        if ($tipo === 'elaboracion'):
            require __DIR__ . '/partials/elaborado_view.php';
        elseif ($tipo === 'escandallo'):
            require __DIR__ . '/partials/escandallo_view.php';
        elseif ($tipo === 'otros'):
            require __DIR__ . '/partials/otrasElaboraciones_view.php';
        else: ?>
            <p class="text-red-600">Tipo de elaborado no válido. Por favor, selecciona "Elaboración" o "Escandallo".</p>
        <?php endif;
    endif; ?>
    <!-- Enlace para volver al listado -->
    <p class="mt-4"><a href="/elaborados.php" class="text-teal-600">Volver al listado de elaborados</a></p>
</main> 