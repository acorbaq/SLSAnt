<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Ingrediente;
use App\Models\Elaborado;

use PDO;

/**
 * Modelo Lotes
 *
 * Encapsula acceso a la tabla `lotes` y su hija 'lotes_ingredientes'.
 *
 * Responsabilidades:
 * - Proveer métodos para recuperar (y en el futuro persistir) lotes.
 * - Normalizar tipos básicos devueltos (casts) para facilitar uso desde controladores/vistas.
 *
 * Seguridad / flujo:
 * - Usa sentencias preparadas (PDO) para evitar inyección SQL.
 * - No realiza validaciones de negocio; sólo acceso a datos.
 */
final class Lotes
{
    private PDO $pdo;
    private Ingrediente $ingredienteModel;
    private Elaborado $elaboradoModel;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ingredienteModel = new Ingrediente($pdo);
        $this->elaboradoModel = new Elaborado($pdo);
    }

    // Métodos para crear y litar lotes irían aqui.
    // Crear lote principal y sus ingredientes asociados función crearLote incluye incluye dos funciones una para crear el lote y otra para crear los ingredientes asociados.
    // La trasacción la gestiona el controlador.
    public function crearLote(array $data): int
    {
        // Implementación de creación de lote
        // Retorna el ID del lote creado
        return 0; // Placeholder
    }

    public function crearIngredientes(array $ingredientesData): void
    {
        // Implementación de creación de ingredientes asociados al lote
    }

    // Métodos para recuperar y persistir lotes irían aquí.
}