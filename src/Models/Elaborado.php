<?php
declare(strict_types=1);

namespace App\Models;

use PDO;

/**
 * Modelo Elaborado
 *
 * Encapsula acceso a la tabla `elaborados`.
 *
 * Responsabilidades:
 * - Proveer métodos para recuperar (y en el futuro persistir) elaborados.
 * - Normalizar tipos básicos devueltos (casts) para facilitar uso desde controladores/vistas.
 *
 * Seguridad / flujo:
 * - Usa sentencias preparadas (PDO) para evitar inyección SQL.
 * - No realiza validaciones de negocio; sólo acceso a datos.
 */
final class Elaborado
{
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PDO $pdo Conexión PDO ya inicializada (proporcionada por bootstrap).
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Obtener todos los elaborados.
     *
     * Devuelve un array de filas asociativas con las keys:
     *  - id_elaborado (int)
     *  - nombre (string)
     *  - peso_obtenido (string) (tal como viene de BD; la vista puede formatear)
     *  - fecha_caducidad (string)
     *  - tipo (mixed) (tal como viene: int o string según diseño)
     *
     * @return array<int, array<string,mixed>>
     */
    public function getAll(): array
    {
        $sql = 'SELECT id_elaborado, nombre, peso_obtenido, fecha_caducidad, tipo FROM elaborados ORDER BY nombre ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Casts defensivos mínimos: id a int, dejar el resto como vienen (la vista formatea)
        foreach ($rows as &$r) {
            $r['id_elaborado'] = isset($r['id_elaborado']) ? (int)$r['id_elaborado'] : 0;
            // mantener peso_obtenido como numeric/string; la vista usa is_numeric()
            // tipo puede ser int o text dependiendo de migración; no forzamos aquí
        }
        unset($r);

        return $rows;
    }

    /**
     * (Opcional) Obtener un elaborado por id.
     *
     * @param int $id
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        $sql = 'SELECT id_elaborado, nombre, peso_obtenido, fecha_caducidad, tipo FROM elaborados WHERE id_elaborado = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['id_elaborado'] = (int)($row['id_elaborado'] ?? 0);
        return $row;
    }
}