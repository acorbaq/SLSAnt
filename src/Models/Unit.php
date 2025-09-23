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
}