<?php
declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;
/**
* Modelo de unidad
*
* Gestiona operaciones relacionadas con unidades de medida.
 */

class Unit 
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }
    /** 
    * Obtener todas las unidades de la base de datos.
    *
    * @return array Lista de unidades.
    * @throws RuntimeException si la consulta falla.
     */
    public function getAllUnits(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM unidades_medida");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve los id_unidad válidos existentes en la tabla unidades_medida.
     *
     * @return int[] arreglo de ids válidos
     */
    public function getValidUnitIds(): array
    {
        $stmt = $this->pdo->query("SELECT id_unidad FROM unidades_medida");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === false) return [];
        $ids = [];
        foreach ($rows as $r) {
            $id = isset($r['id_unidad']) ? (int)$r['id_unidad'] : 0;
            if ($id > 0) $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    /**
     * Comprueba si un id de unidad existe.
     *
     * @param int $id
     * @return bool
     */
    public function existsUnitId(int $id): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM unidades_medida WHERE id_unidad = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Devuelve el id de la unidad "n.c." (no calculable / no especificado) si existe.
     * Retorna 0 si no se encuentra.
     *
     * @return int
     */
    public function getUnitNcId(): int
    {
        $sql = "SELECT id_unidad FROM unidades_medida 
                WHERE lower(trim(abreviatura)) IN ('n.c.','nc') OR lower(trim(nombre)) = 'no especificado' LIMIT 1";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id_unidad'] : 0;
    }

    /**
     * Devuelve el id de la unidad 'kg' si existe, o la primera unidad disponible como fallback.
     * Retorna 0 si no existen unidades en la tabla.
     *
     * @return int
     */
    public function getUnitKgId(): int
    {
        $stmt = $this->pdo->prepare("SELECT id_unidad FROM unidades_medida WHERE lower(trim(abreviatura)) = :abr LIMIT 1");
        $stmt->execute([':abr' => 'kg']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['id_unidad'])) {
            return (int)$row['id_unidad'];
        }
        // fallback a la primera unidad
        $stmt2 = $this->pdo->query("SELECT id_unidad FROM unidades_medida LIMIT 1");
        $r = $stmt2->fetch(PDO::FETCH_ASSOC);
        return $r ? (int)$r['id_unidad'] : 0;
    }
}
