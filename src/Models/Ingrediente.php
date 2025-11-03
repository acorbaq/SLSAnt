<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

/**
 * Class Ingrediente
 *
 * Modelo responsable de las operaciones CRUD y de relación entre ingredientes y alérgenos.
 *
 * Responsabilidades principales:
 *  - Proveer listados de ingredientes con sus alérgenos asociados.
 *  - Obtener el detalle de un ingrediente incluyendo los alérgenos relacionados.
 *  - Crear, actualizar y eliminar ingredientes.
 *  - Gestionar la tabla pivote (ingredientes_alergenos) que relaciona N:M ingredientes ↔ alérgenos.
 *
 * Notas de diseño:
 *  - Todas las consultas usan prepared statements o consultas parametrizadas para evitar inyección SQL.
 *  - Las operaciones que modifican relaciones múltiples usan transacciones para mantener consistencia.
 *  - La tabla de alérgenos se considera un catálogo relativamente inmutable (seeded), por eso las funciones
 *    de lectura (allAlergenos) devuelven la lista completa para poblar formularios.
 *
 * Tablas implicadas (expectativas):
 *  - alergenos (id_alergeno INTEGER PRIMARY KEY, nombre TEXT UNIQUE)
 *  - ingredientes (id_ingrediente INTEGER PRIMARY KEY, nombre TEXT, indicaciones TEXT)
 *  - ingredientes_alergenos (id INTEGER PRIMARY KEY, id_ingrediente INTEGER, id_alergeno INTEGER)
 *
 * @package App\Models
 */
class Ingrediente
{
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PDO $pdo Conexión a la base de datos (inyección).
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * allAlergenos
     *
     * Recupera el catálogo completo de alérgenos.
     *
     * Flujo:
     *  - Ejecuta una consulta simple SELECT sobre la tabla `alergenos`.
     *  - Devuelve un array de filas asociativas: [ ['id_alergeno'=>1,'nombre'=>'Gluten'], ... ]
     *
     * Uso típico: poblar checkbox/select en formularios de ingrediente.
     *
     * @param PDO $pdo
     * @return array<int,array{ id_alergeno: string, nombre: string }>
     */
    public function allAlergenos(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT id_alergeno,nombre FROM alergenos ORDER BY id_alergeno");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Normalizar retorno: evitar false
        return $rows === false ? [] : $rows;
    }

    /**
     * allIngredientes
     *
     * Recupera todos los ingredientes y adjunta la lista de alérgenos por ingrediente.
     *
     * Flujo general:
     *  1) Trae la lista básica de ingredientes (id, nombre, indicaciones).
     *  2) Si la lista está vacía devuelve [].
     *  3) Construye una lista de ids y realiza una única consulta para recuperar todas las relaciones
     *     ingrediente → alérgenos (evita N+1 queries).
     *  4) Mapea los resultados y adjunta 'alergenos' a cada ingrediente como array de objetos
     *     con keys 'id' y 'nombre'.
     *
     * Consideraciones:
     *  - Uso de placeholders (?) para la consulta IN(...) para soportar cualquier número de ids.
     *  - Si la tabla pivot cambia de nombre (ingredientes_alergenos) hay que actualizar las consultas.
     *
     * @param PDO $pdo
     * @return array<int,array<string,mixed>> lista de ingredientes con clave 'alergenos'
     */
    public function allIngredientes(PDO $pdo): array
    {
        // 1) Obtener ingredientes básicos
        $stmt = $pdo->query("SELECT id_ingrediente,nombre,indicaciones FROM ingredientes ORDER BY nombre ASC");
        $ings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($ings === false || empty($ings)) return [];

        // 2) Obtener ids y preparar consulta para relaciones
        $ids = array_column($ings, 'id_ingrediente');
        if (empty($ids)) {
            foreach ($ings as &$i) { $i['alergenos'] = []; }
            return $ings;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        // 3) Traer todas las relaciones para los ids obtenidos en una sola query
        $sql = "SELECT ia.id_ingrediente, a.id_alergeno, a.nombre
                FROM ingredientes_alergenos ia
                JOIN alergenos a ON a.id_alergeno = ia.id_alergeno
                WHERE ia.id_ingrediente IN ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 4) Mapear roles por ingrediente y adjuntar
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r['id_ingrediente']][] = ['id' => (int)$r['id_alergeno'], 'nombre' => $r['nombre']];
        }
        foreach ($ings as &$i) {
            $i['alergenos'] = $map[(int)$i['id_ingrediente']] ?? [];
        }

        return $ings;
    }

    /**
     * findById
     *
     * Obtiene un ingrediente por id incluyendo sus alérgenos.
     *
     * Flujo:
     *  - SELECT principal sobre ingredientes por id.
     *  - Si no existe devuelve null.
     *  - Consulta secundaria sobre la tabla pivot para adjuntar los alérgenos (id_nombre).
     *
     * @param PDO $pdo
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(PDO $pdo, int $id): ?array
    {
        $stmt = $pdo->prepare("SELECT id_ingrediente,nombre,indicaciones FROM ingredientes WHERE id_ingrediente = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) return null;

        // Adjuntar alérgenos relacionados (id_alergeno, nombre)
        $stmt = $pdo->prepare("SELECT a.id_alergeno,a.nombre FROM ingredientes_alergenos ia JOIN alergenos a ON a.id_alergeno = ia.id_alergeno WHERE ia.id_ingrediente = :id");
        $stmt->execute([':id' => $id]);
        $row['alergenos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $row;
    }
    public function findByName(PDO $pdo, string $name): ?array
    {
        $stmt = $pdo->prepare("SELECT id_ingrediente,nombre,indicaciones FROM ingredientes WHERE nombre = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) return null;

        // Adjuntar alérgenos relacionados (id_alergeno, nombre)
        $stmt = $pdo->prepare("SELECT a.id_alergeno,a.nombre FROM ingredientes_alergenos ia JOIN alergenos a ON a.id_alergeno = ia.id_alergeno WHERE ia.id_ingrediente = :id");
        $stmt->execute([':id' => $row['id_ingrediente']]);
        $row['alergenos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $row;
    }
    // obtenerNombrePorId
    public function obtenerNombrePorId(int $id): ?string
    {
        $stmt = $this->pdo->prepare("SELECT nombre FROM ingredientes WHERE id_ingrediente = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row['nombre'];
    }
    /**
     * createIngrediente
     *
     * Inserta un nuevo ingrediente.
     *
     * Flujo:
     *  - INSERT simple en la tabla ingredientes con nombre e indicaciones.
     *  - Devuelve lastInsertId() como id del nuevo ingrediente.
     *
     * @param PDO $pdo
     * @param string $nombre
     * @param string $indicaciones
     * @return int id creado
     * @throws RuntimeException en fallo de inserción
     */
    public function createIngrediente(PDO $pdo, string $nombre, string $indicaciones): int
    {
        $stmt = $pdo->prepare("INSERT INTO ingredientes (nombre,indicaciones) VALUES (:n,:i)");
        $ok = $stmt->execute([':n' => $nombre, ':i' => $indicaciones]);
        if (!$ok) throw new RuntimeException('No se pudo crear ingrediente');
        return (int)$pdo->lastInsertId();
    }

    /**
     * updateIngrediente
     *
     * Actualiza campos editables de un ingrediente.
     *
     * Flujo:
     *  - Construye dinámicamente lista de campos a actualizar según $data.
     *  - Ejecuta UPDATE con los parámetros necesarios.
     *
     * Consideraciones:
     *  - No hace validación de negocio aquí (debe hacerse en controladores).
     *
     * @param PDO $pdo
     * @param int $id
     * @param array<string,mixed> $data { 'nombre'?: string, 'indicaciones'?: string }
     * @return void
     */
    public function updateIngrediente(PDO $pdo, int $id, array $data): void
    {
        $fields = [];
        $params = [':id' => $id];

        if (array_key_exists('nombre', $data)) {
            $fields[] = 'nombre = :nombre';
            $params[':nombre'] = $data['nombre'];
        }
        if (array_key_exists('indicaciones', $data)) {
            $fields[] = 'indicaciones = :indicaciones';
            $params[':indicaciones'] = $data['indicaciones'];
        }

        if (empty($fields)) {
            // Nada que actualizar
            return;
        }

        $sql = 'UPDATE ingredientes SET ' . implode(', ', $fields) . ' WHERE id_ingrediente = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }
    /**
     * deleteIngrediente
     *
     * Elimina un ingrediente y sus relaciones en la tabla pivot.
     *
     * @param PDO $pdo
     * @param int $id
     * @return void
     * @throws \Exception on failure
     */
    public function deleteIngrediente(PDO $pdo, int $id): void
    {
        // Asumimos que la transacción la controla quien llama (Elaborado::deleteEscandallo)
        // Borrar relaciones pivot (ingredientes_alergenos) primero es seguro aunque existan FK ON DELETE CASCADE
        $stmt = $pdo->prepare("DELETE FROM ingredientes_alergenos WHERE id_ingrediente = :id");
        $stmt->execute([':id' => $id]);

        // Finalmente borrar ingrediente
        $stmt = $pdo->prepare("DELETE FROM ingredientes WHERE id_ingrediente = :id");
        $stmt->execute([':id' => $id]);
    }

    /**
     * assignAlergenosByIds
     *
     * Reemplaza las relaciones alérgeno ↔ ingrediente.
     *
     * Flujo:
     *  - Normaliza la lista de ids recibida (entero, filtrar vacíos).
     *  - Inicia una transacción para garantizar que el estado final sea consistente.
     *  - Elimina todas las relaciones previas para el ingrediente.
     *  - Inserta las nuevas relaciones (INSERT OR IGNORE para evitar duplicados).
     *  - Commit / rollback en caso de error.
     *
     * @param PDO $pdo
     * @param int $idIngrediente
     * @param array<int|string> $alergenosIds Array de ids de alérgenos (provenientes de formulario)
     * @return void
     * @throws \Throwable Propaga excepciones en caso de fallo (se hace rollback)
     */
    public function assignAlergenosByIds(PDO $pdo, int $idIngrediente, array $alergenosIds): void
    {
        // Normalizar: filtrar valores vacíos y convertir a ints
        $ids = array_map('intval', array_filter($alergenosIds, fn($v) => $v !== ''));

        // Nota: la transacción la debe gestionar el controlador que llama; aquí solo se aplican las operaciones.
        // Borrar relaciones actuales
        $stmt = $pdo->prepare("DELETE FROM ingredientes_alergenos WHERE id_ingrediente = :id");
        $stmt->execute([':id' => $idIngrediente]);

        // Insertar las nuevas relaciones si las hay
        if (!empty($ids)) {
            $ins = $pdo->prepare("INSERT OR IGNORE INTO ingredientes_alergenos (id_ingrediente,id_alergeno) VALUES (:iid,:aid)");
            foreach ($ids as $aid) {
                $ins->execute([':iid' => $idIngrediente, ':aid' => $aid]);
            }
        }
    }

    // obtener alegenos unicos de un array de ids de ingredientes
    /**
     * getUniqueAlergenosFromIngredientes
     *
     * Dado un array de ingredientes (cada uno con clave id_ingrediente) devuelve
     * un array de ids de alérgenos únicos.
     *
     * @param array<int,array<string,mixed>> $ingredientes
     * @return int[] lista única de ids de alérgenos
     */
    public function getUniqueAlergenosFromIngredientes(array $ingredientes): array
    {
        $alergenosIds = [];
        foreach ($ingredientes as $ing){
            if ($ing <= 0) continue; // ignorar ids inválidos o vacíos
            $ingData = $this->findById($this->pdo, $ing); // usar el método findById del modelo Ingrediente
            if ($ingData === null) continue; // ignorar ingredientes no encontrados

            // Obtener los alérgenos del ingrediente
            $alergenos = $ingData['alergenos'] ?? [];
            foreach ($alergenos as $a) {
                if (isset($a['id_alergeno']) && ctype_digit((string)$a['id_alergeno'])) {
                    $alergenosIds[] = (int)$a['id_alergeno'];
                }
            }
        }

        return array_values(array_unique($alergenosIds));
    }
    // getLastIngredient
    
    // removeAllAlergenosFromIngrediente
    /**
     * Elimina todas las relaciones de alérgenos para un ingrediente dado.
     * @param PDO $pdo
     * @param int $idIngrediente
     * @return void
     */
    public function removeAllAlergenosFromIngrediente(PDO $pdo, int $idIngrediente): void
    {
        $stmt = $pdo->prepare("DELETE FROM ingredientes_alergenos WHERE id_ingrediente = :id");
        $stmt->execute([':id' => $idIngrediente]);
    }
}