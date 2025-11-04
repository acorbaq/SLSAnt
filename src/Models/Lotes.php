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
        // Normalizar y validar entrada
        if (!isset($data['lote'])) {
            throw new \InvalidArgumentException('Falta la clave "lote" en $data');
        }
        $lote = $data['lote'];
        // Si $lote viene como JSON/string, intentar decodificar
        if (is_string($lote)) {
            $decoded = json_decode($lote, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $lote = $decoded;
            } else {
                echo '<pre>';
                print_r($data);
                echo '</pre>';
                throw new \InvalidArgumentException('El valor de "lote" debe ser un array o JSON correcto');
            }
        }
        if (!is_array($lote)) {
            throw new \InvalidArgumentException('El valor de "lote" debe ser un array');
        }

        $ingredientes = $data['ingredientes'] ?? [];
        // Normalizar ingredientes si vienen como JSON/string
        if (is_string($ingredientes)) {
            $decodedIng = json_decode($ingredientes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decodedIng)) {
                $ingredientes = $decodedIng;
            } else {
                throw new \InvalidArgumentException('El valor de "ingredientes" debe ser un array o JSON correcto');
            }
        }
        if (!is_array($ingredientes)) {
            throw new \InvalidArgumentException('El valor de "ingredientes" debe ser un array');
        }

        // Obtener último número de lote para esta elaboración y generar el nuevo número
        $ultimo = $this->obtenerLotePorIdElaboracion((int)($lote['elaboracion_id'] ?? 0));
        $ultimoNumero = $ultimo['numero_lote'] ?? '';
        $nuevoNumeroLote = $this->generarNumeroLote($ultimoNumero, (int)$lote['elaboracion_id']);

        // Validar parent_lote_id: si viene y no existe en la tabla lotes, lo dejamos a null
        $parentId = null;
        if (isset($lote['parent_lote_id']) && $lote['parent_lote_id'] !== '' && $lote['parent_lote_id'] !== null) {
            $candidate = (int)$lote['parent_lote_id'];
            $chk = $this->pdo->prepare("SELECT id FROM lotes WHERE id = :id LIMIT 1");
            $chk->execute(['id' => $candidate]);
            $exists = $chk->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                $parentId = $candidate;
            } else {
                // Si prefieres lanzar excepción en vez de ignorar, reemplaza la siguiente línea por throw
                // throw new \InvalidArgumentException("parent_lote_id {$candidate} no existe.");
                $parentId = null;
            }
        }

        // 3) Insertar el nuevo lote en la base de datos
        $stmt = $this->pdo->prepare("INSERT INTO lotes (elaboracion_id, numero_lote, fecha_produccion, fecha_caducidad, peso_total, unidad_peso, temp_inicio, temp_final, parent_lote_id, is_derivado, created_at) VALUES (:elaboracion_id, :numero_lote, :fecha_produccion, :fecha_caducidad, :peso_total, :unidad_peso, :temp_inicio, :temp_final, :parent_lote_id, :is_derivado, CURRENT_TIMESTAMP)");
        $stmt->execute([
            'elaboracion_id' => $lote['elaboracion_id'],
            'numero_lote' => $nuevoNumeroLote,
            'fecha_produccion' => $lote['fecha_produccion'],
            'fecha_caducidad' => $lote['fecha_caducidad'],
            'peso_total' => $lote['peso_total'],
            'unidad_peso' => $lote['unidad_peso'],
            'temp_inicio' => $lote['temp_inicio'],
            'temp_final' => $lote['temp_final'],
            'parent_lote_id' => $parentId,
            'is_derivado' => $parentId !== null ? 1 : 0,
        ]);
        $loteId = (int)$this->pdo->lastInsertId();

        if ($loteId <= 0) {
            throw new \RuntimeException('No se obtuvo el ID del lote insertado.');
        }

        // 4) Insertar los ingredientes asociados al lote
        $this->crearIngredientesLote($loteId, $ingredientes);

        return $loteId;
    }


    public function crearIngredientesLote(int $loteId, array $ingredientesData): void
    {
        // Comprobar que el lote existe
        $chk = $this->pdo->prepare("SELECT id FROM lotes WHERE id = :id LIMIT 1");
        $chk->execute(['id' => $loteId]);
        if (!$chk->fetch(PDO::FETCH_ASSOC)) {
            throw new \RuntimeException("Lote con id {$loteId} no existe. Abortando insert de ingredientes.");
        }

        // Prepared statement: usar los nombres de columna según tu esquema
        $stmt = $this->pdo->prepare(
            "INSERT INTO lotes_ingredientes (
                lote_elaboracion_id,
                ingrediente_resultante,
                ingrediente_id,
                peso,
                porcentaje_origen,
                referencia_proveedor,
                created_at,
                lote,
                fecha_caducidad
            ) VALUES (
                :lote_elaboracion_id,
                :ingrediente_resultante,
                :ingrediente_id,
                :peso,
                :porcentaje_origen,
                :referencia_proveedor,
                :created_at,
                :lote,
                :fecha_caducidad
            )"
        );

        foreach ($ingredientesData as $index => $ingrediente) {
            $ingredienteId = isset($ingrediente['ingrediente_id']) && $ingrediente['ingrediente_id'] !== '' ? (int)$ingrediente['ingrediente_id'] : null;

            // Si la FK ingrediente_id es obligatoria en la BD, no permitir null; validar existencia
            if ($ingredienteId === null) {
                throw new \InvalidArgumentException("Falta ingrediente_id en ingrediente índice {$index}.");
            }
            $chkIng = $this->pdo->prepare("SELECT id_ingrediente FROM ingredientes WHERE id_ingrediente = :id LIMIT 1");
            $chkIng->execute(['id' => $ingredienteId]);
            if (!$chkIng->fetch(PDO::FETCH_ASSOC)) {
                throw new \InvalidArgumentException("Ingrediente con id {$ingredienteId} (índice {$index}) no existe.");
            }

            // obtener nombre del ingrediente resultante si aplica
            $ingredienteResultante = $this->ingredienteModel->obtenerNombrePorId($ingredienteId);

            $params = [
                'lote_elaboracion_id' => $loteId,
                'ingrediente_resultante' => $ingredienteResultante ?? null,
                'ingrediente_id' => $ingredienteId,
                'peso' => isset($ingrediente['peso']) && $ingrediente['peso'] !== '' ? (float)$ingrediente['peso'] : null,
                'porcentaje_origen' => $ingrediente['porcentaje_origen'] ?? null,
                'referencia_proveedor' => $ingrediente['lote'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'lote' => $ingrediente['lote'] ?? null,
                'fecha_caducidad' => $ingrediente['fecha_caducidad'] ?? null,
            ];

            try {
                $stmt->execute($params);
            } catch (\PDOException $e) {
                // Añadir contexto para depuración de la FK que falla
                throw new \RuntimeException(
                    "Error al insertar ingrediente índice {$index} (ingrediente_id={$ingredienteId}) para lote {$loteId}: " . $e->getMessage()
                );
            }
        }
    }

    // Métodos para recuperar y persistir lotes irían aquí.
    public function obtenerLotePorIdElaboracion(int $idElab): ?array
    {
        // Implementación de obtención de lote por ID (id, elaboracion_id, numero_lote, fecha_producción, fecha_caducidad, peso_total, unidad_peso, temp_inicio, temp_fin, parent_lote_id, is_derivado, created_at)
        // 1) a partir del id de elaboración obtener el numero lote para su ultima created_at
        $stmt = $this->pdo->prepare("SELECT * FROM lotes WHERE elaboracion_id = :elaboracion_id ORDER BY created_at DESC LIMIT 1");
        $stmt->execute(['elaboracion_id' => $idElab]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // Metodos de tratamiento de lotes.
    public function generarNumeroLote(string|int $ultimoLote, int $elaboracionId): string
    {
        // Normalizar a string para evitar errores de tipo al usar substr/str_pad
        $ultimo = (string)$ultimoLote;

        // Determinar año del último lote (YYYY) si existe, 0 si no hay dato válido
        $anioUltimo = 0;
        if ($ultimo !== '' && strlen($ultimo) >= 2) {
            $anioUltimo = 2000 + (int)substr($ultimo, 0, 2);
        }

        $anioActual = (int)date('Y');
        $mesActual = date('m');
        $anioActualShort = date('y'); // YY

        // Si el año ha cambiado, reiniciamos el secuencial a 001
        if ($anioUltimo !== $anioActual) {
            $numeroSecuencial = '001';
        } else {
            // Tomamos los últimos 3 dígitos del último lote como secuencial y los incrementamos
            $ultimoSecuencial = (int)substr($ultimo, -3);
            $nuevoSecuencial = $ultimoSecuencial + 1;
            $numeroSecuencial = str_pad((string)$nuevoSecuencial, 3, '0', STR_PAD_LEFT);
        }

        // Construimos el número de lote: YY + MM + idElaborado(2 dígitos) + secuencial(3 dígitos)
        $numeroLote = sprintf(
            '%s%s%s%s',
            $anioActualShort,
            $mesActual,
            str_pad((string)$elaboracionId, 2, '0', STR_PAD_LEFT),
            $numeroSecuencial
        );

        return $numeroLote;
    }

    // Metodo para obtener todos los lotes
    public function getAllLotes(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lotes ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);    
    }
    // obtener todos los los ingredientes de todos los lotes
    public function getAllIngredientesLotes(): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lotes_ingredientes ORDER BY created_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);    
    }
    // Metodo para obtener un lote por su ID
    public function getLoteById(int $loteId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lotes WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $loteId]);
        $lote = $stmt->fetch(PDO::FETCH_ASSOC);
        return $lote ?: null;
    }
    // Metodo para obtener los ingredientes asociados a un lote
    public function getIngredientesByLoteId(int $loteId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lotes_ingredientes WHERE lote_elaboracion_id = :lote_elaboracion_id");
        $stmt->execute(['lote_elaboracion_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // Metodo para obtener los ingredientes asociados a un lote por su ID
    public function getIngredientesPorLoteId(int $loteId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM lotes_ingredientes WHERE lote_elaboracion_id = :lote_elaboracion_id");
        $stmt->execute(['lote_elaboracion_id' => $loteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}