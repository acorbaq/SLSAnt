<?php
/**
 * bootstrap.php
 *
 * Inicializa configuración mínima de la aplicación:
 *  - carga variables de entorno desde .env (vlucas/phpdotenv)
 *  - determina y asegura la existencia del fichero SQLite
 *  - crea y configura la conexión PDO a SQLite
 *
 * Objetivo: centralizar la preparación del entorno y devolver un objeto PDO listo para usar.
 *
 * Uso:
 *   $pdo = require __DIR__ . '/../src/bootstrap.php';
 *
 * Retorno:
 *   @return \PDO Instancia de PDO conectada a la base de datos SQLite.
 *
 * Excepciones:
 *   - Puede lanzar \RuntimeException o \PDOException si hay errores graves
 *     (crear archivo, permisos, o conexión).
 *
 * Notas:
 *  - DB_PATH en .env puede ser ruta absoluta o relativa. Si es relativa,
 *    se interpreta respecto a la raíz del proyecto.
 *  - El archivo .env no se debe commitear en el repositorio (ya está en .gitignore).
 */

// Cargar autoload de Composer para poder usar Dotenv y otras dependencias.
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__); // ruta al directorio raíz del proyecto (uno arriba de src)

// -------------------------------------------------------------
// 1) Cargar variables de entorno (.env)
// -------------------------------------------------------------
// Comprobar si existe el fichero .env en la raíz del proyecto y cargarlo.
// Vlucas/phpdotenv coloca las variables en $_ENV / $_SERVER (según configuración).
if (file_exists($root . '/.env')) {
    // createImmutable evita que las variables existentes sean sobrescritas por defecto
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->load();
}

// -------------------------------------------------------------
// 2) Determinar la ruta del fichero SQLite
// -------------------------------------------------------------
// Preferencia: variable DB_PATH en .env. Si no existe, usar fallback.
$dbPath = $_ENV['DB_PATH'] ?? ($root . '/database.sqlite');

// Si DB_PATH es una ruta relativa (no comienza por "/" ni con unidad Windows),
// la convertimos a una ruta absoluta relativa a la raíz del proyecto.
// Esto evita confusión si en .env pones "database/database.sqlite" o "./database/database.sqlite".
if (!preg_match('#^(?:/|[A-Za-z]:\\\\)#', $dbPath)) {
    // eliminar prefijos "./" y normalizar
    $relative = preg_replace('#^\./#', '', $dbPath);
    $dbPath = $root . '/' . $relative;
}

// -------------------------------------------------------------
// 3) Asegurar existencia del directorio/archivo y permisos
// -------------------------------------------------------------
// Antes de tocar el fichero, aseguramos que el directorio existe.
$dbDir = dirname($dbPath);
if (!is_dir($dbDir)) {
    // Intentar crear el directorio recursivamente con permisos 0755.
    // Si falla, lanzamos excepción para que el llamador lo detecte.
    if (!mkdir($dbDir, 0755, true) && !is_dir($dbDir)) {
        throw new RuntimeException("No se pudo crear el directorio de BD: {$dbDir}");
    }
}

// Si el fichero no existe, lo creamos (touch) para garantizar que PDO pueda abrirlo.
// chmod garantiza permisos mínimos; ajusta según necesidades de tu entorno.
if (!file_exists($dbPath)) {
    if (false === @touch($dbPath)) {
        throw new RuntimeException("No se pudo crear el fichero de BD: {$dbPath}");
    }
    @chmod($dbPath, 0660);
}

// -------------------------------------------------------------
// 4) Conectar a SQLite mediante PDO y configurar opciones
// -------------------------------------------------------------
// Construimos el DSN para SQLite y creamos la instancia PDO.
// Lanzamos excepciones en caso de error (ATTR_ERRMODE => ERRMODE_EXCEPTION).
try {
    $pdo = new PDO('sqlite:' . $dbPath);
} catch (PDOException $e) {
    // Re-lanzar con contexto adicional. En producción puedes gestionar esto de otra manera.
    throw new RuntimeException("Error al conectar con la base de datos SQLite: " . $e->getMessage(), 0, $e);
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Activar claves foráneas en SQLite (no activas por defecto).
// Esto es importante si usas relaciones con FOREIGN KEY en tu esquema.
$pdo->exec("PRAGMA foreign_keys = ON;");

// Devolver la instancia PDO para que otros scripts la requieran.
return $pdo;