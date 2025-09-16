<?php
/**
 * migrator.php
 *
 * Migrador simple y reproducible para SQLite.
 *
 * Objetivos:
 *  - Mantener control de las migraciones aplicadas.
 *  - Aplicar automáticamente archivos .sql y .php ubicados en /migrations
 *  - Registrar cada migración aplicada para evitar reaplicaciones.
 *
 * Reglas de las migraciones:
 *  - Los archivos se aplican en orden lexicográfico por nombre (usa prefijo fecha+secuencia).
 *  - Soporta:
 *      - .sql : contenido SQL (puede incluir BEGIN/COMMIT).
 *      - .php : debe devolver una Closure(PDO $pdo): void que ejecuta la migración.
 *
 * Uso:
 *   php scripts/migrator.php
 *
 * Comportamiento:
 *  1) Carga la conexión PDO desde src/bootstrap.php.
 *  2) Asegura que exista el directorio de migraciones (crea si hace falta).
 *  3) Crea la tabla de control `migrations` si no existe.
 *  4) Lista archivos en /migrations y aplica los no registrados dentro de una transacción.
 *  5) Inserta un registro en `migrations` por cada archivo aplicado.
 *
 * Errores:
 *  - Si una migración falla se hace rollback y el script termina con código 1.
 *
 * @package SLSAnt
 */

declare(strict_types=1);

$root = dirname(__DIR__); // raíz del proyecto (uno arriba de scripts)
$pdo = require $root . '/src/bootstrap.php'; // bootstrap debe devolver $pdo

if (!($pdo instanceof PDO)) {
    // Validación básica: bootstrap debe devolver PDO
    fwrite(STDERR, "Error: src/bootstrap.php no devolvió una instancia PDO válida.\n");
    exit(1);
}

// Directorio donde se colocan las migraciones
$migrationsDir = $root . '/migrations';

// Si no existe, lo creamos para facilitar el flujo de trabajo.
if (!is_dir($migrationsDir)) {
    if (!mkdir($migrationsDir, 0755, true) && !is_dir($migrationsDir)) {
        fwrite(STDERR, "No se pudo crear el directorio de migraciones: {$migrationsDir}\n");
        exit(1);
    }
    fwrite(STDOUT, "Directorio de migraciones creado: {$migrationsDir}\n");
}

// ---------------------------
// 1) Asegurar tabla de control
// ---------------------------
// La tabla `migrations` guarda el nombre de archivo aplicado, batch (opcional) y la fecha.
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    batch INTEGER NOT NULL DEFAULT 0,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);
SQL
);

// ---------------------------
// 2) Leer migraciones ya aplicadas
// ---------------------------
// Obtenemos la lista de nombres para evitar reaplicarlas.
$applied = $pdo->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$appliedMap = array_flip($applied); // mapa para búsqueda O(1)

// ---------------------------
// 3) Listar archivos a aplicar
// ---------------------------
// Filtramos archivos con prefijo numérico y extensión .sql/.php.
// El prefijo numérico ayuda a ordenar por versión/fecha.
$files = array_values(array_filter(scandir($migrationsDir), function ($file) use ($migrationsDir) {
    $path = $migrationsDir . DIRECTORY_SEPARATOR . $file;
    return is_file($path) && preg_match('/^\d+.*\.(sql|php)$/i', $file);
}));

// Orden lexicográfico (importante para aplicar en secuencia correcta)
sort($files, SORT_STRING);

if (empty($files)) {
    fwrite(STDOUT, "No hay archivos de migración en: {$migrationsDir}\n");
    exit(0);
}

// ---------------------------
// 4) Calcular batch (opcional)
// ---------------------------
// batch permite agrupar migraciones aplicadas en una ejecución.
// Calculamos el siguiente entero basado en el máximo existente.
$batchRow = $pdo->query("SELECT COALESCE(MAX(batch), 0) AS maxb FROM migrations")->fetch(PDO::FETCH_ASSOC);
$batch = intval($batchRow['maxb'] ?? 0) + 1;

// ---------------------------
// 5) Aplicar migraciones pendientes
// ---------------------------
// Iteramos los archivos en orden; cada archivo se ejecuta dentro de una transacción.
// Al finalizar correctamente se inserta un registro en la tabla `migrations`.
foreach ($files as $file) {
    // Si está ya aplicada, saltarla.
    if (isset($appliedMap[$file])) {
        continue;
    }

    $path = $migrationsDir . DIRECTORY_SEPARATOR . $file;
    fwrite(STDOUT, "Aplicando: {$file} ... ");

    try {
        // Iniciamos transacción para que cada migración sea atómica.
        $pdo->beginTransaction();

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext === 'sql') {
            // Leer el SQL y ejecutarlo. Se espera que el SQL incluya BEGIN/COMMIT
            // si necesita agrupar múltiples sentencias; PDO::exec puede ejecutar múltiples statements.
            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("No se pudo leer el archivo SQL: {$path}");
            }
            $pdo->exec($sql);

        } elseif ($ext === 'php') {
            // Para .php se exige que el archivo devuelva una Closure(PDO $pdo): void
            // Ejemplo de migración PHP:
            // <?php
            // return function(PDO $pdo) {
            //     $pdo->exec("CREATE TABLE ...");
            // };
            $closure = require $path;
            if (!($closure instanceof Closure)) {
                throw new RuntimeException("El archivo PHP debe devolver una Closure(PDO \$pdo): {$path}");
            }
            // Ejecutar la lógica PHP de la migración
            $closure($pdo);

        } else {
            throw new RuntimeException("Extensión no soportada para migración: {$file}");
        }

        // Registrar migración aplicada en la tabla de control.
        $stmt = $pdo->prepare("INSERT INTO migrations (name, batch, applied_at) VALUES (:name, :batch, :applied_at)");
        $stmt->execute([
            ':name' => $file,
            ':batch' => $batch,
            // Fecha en UTC para consistencia entre entornos.
            ':applied_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ]);

        // Confirmar cambios.
        $pdo->commit();
        fwrite(STDOUT, "OK\n");

    } catch (Throwable $e) {
        // Revertir si algo falla y mostrar el error.
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "ERROR aplicando {$file}: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
}

// Mensaje final con batch aplicado.
fwrite(STDOUT, "Migraciones aplicadas. Batch: {$batch}\n");