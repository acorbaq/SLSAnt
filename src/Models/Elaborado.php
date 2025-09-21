<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Ingrediente;

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
    private $ingredienteModel;

    /**
     * Constructor.
     *
     * @param PDO $pdo Conexión PDO ya inicializada (proporcionada por bootstrap).
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ingredienteModel = new Ingrediente($this->pdo);
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
        $sql = 'SELECT id_elaborado, nombre, peso_obtenido, dias_viabilidad, tipo FROM elaborados ORDER BY nombre ASC';
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
        $sql = 'SELECT id_elaborado, nombre, peso_obtenido, descripcion, dias_viabilidad, tipo FROM elaborados WHERE id_elaborado = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $row['id_elaborado'] = (int)($row['id_elaborado'] ?? 0);
        return $row;
    }
    /**
     * Crea un escandallo: inserta elaborados, relacion origen (si procede) y crea salidas.
     *
     * @param int $origenId
     * @param float $pesoInicial
     * @param array $salidas  Array de ['nombre'=>string, 'peso'=>float]
     * @param string $descripcion
     * @param string $nombre
     * @param \App\Models\Ingrediente $ingredienteModel
     * @return int id del elaborado creado
     * @throws \RuntimeException en caso de error
     */
    public function createEscandallo(int $origenId, float $pesoInicial, array $salidas, string $descripcion, string $nombre, int $diasViabilidad, Ingrediente $ingredienteModel): int
    {
        // validar origen y leer indicaciones + alérgenos desde el modelo Ingrediente
        $origen = $ingredienteModel->findById($this->pdo, $origenId);
        if ($origen === null) {
            throw new \RuntimeException('Ingrediente origen no encontrado.');
        }
        $origenIndicaciones = $origen['indicaciones'] ?? '';
        $alergenos = $origen['alergenos'] ?? [];
        $alergenosIds = [];
        foreach ($alergenos as $a) {
            if (isset($a['id_alergeno'])) $alergenosIds[] = (int)$a['id_alergeno'];
            elseif (isset($a['id'])) $alergenosIds[] = (int)$a['id'];
        }

        // buscar unidad kg (fallback a primera unidad)
        $stmt = $this->pdo->prepare("SELECT id_unidad FROM unidades_medida WHERE abreviatura = :abr LIMIT 1");
        $stmt->execute([':abr' => 'kg']);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $idUnidadKg = $row ? (int)$row['id_unidad'] : null;
        if ($idUnidadKg === null) {
            $stmt = $this->pdo->query("SELECT id_unidad FROM unidades_medida LIMIT 1");
            $r = $stmt->fetch(\PDO::FETCH_ASSOC);
            $idUnidadKg = $r ? (int)$r['id_unidad'] : 1;
        }

        // comprobar si la columna es_origen existe en la tabla elaborados_ingredientes
        $hasEsOrigen = false;
        $colStmt = $this->pdo->query("PRAGMA table_info(elaborados_ingredientes)");
        $cols = $colStmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            if (($c['name'] ?? '') === 'es_origen') {
                $hasEsOrigen = true;
                break;
            }
        }

        $this->pdo->beginTransaction();
        try {
            $ins = $this->pdo->prepare("INSERT INTO elaborados (nombre, descripcion, peso_obtenido, dias_viabilidad, tipo) VALUES (:n,:d,:p,:f,:t)");
            $ins->execute([
                ':n' => $nombre,
                ':d' => $descripcion,
                ':p' => $pesoInicial,
                ':f' => $diasViabilidad,
                ':t' => 1
            ]);
            $idElaborado = (int)$this->pdo->lastInsertId();

            // insertar relación origen (si procede)
            if ($origenId > 0) {
                if ($hasEsOrigen) {
                    $insRel = $this->pdo->prepare("INSERT INTO elaborados_ingredientes (id_elaborado, id_ingrediente, cantidad, id_unidad, es_origen) VALUES (:eid,:iid,:cant,:uid,1)");
                    $insRel->execute([
                        ':eid' => $idElaborado,
                        ':iid' => $origenId,
                        ':cant' => $pesoInicial,
                        ':uid' => $idUnidadKg
                    ]);
                } else {
                    // tabla antigua sin es_origen
                    $insRel = $this->pdo->prepare("INSERT INTO elaborados_ingredientes (id_elaborado, id_ingrediente, cantidad, id_unidad) VALUES (:eid,:iid,:cant,:uid)");
                    $insRel->execute([
                        ':eid' => $idElaborado,
                        ':iid' => $origenId,
                        ':cant' => $pesoInicial,
                        ':uid' => $idUnidadKg
                    ]);
                }
            }

            // procesar salidas
            foreach ($salidas as $s) {
                $nombreSalida = (string)($s['nombre'] ?? '');
                $pesoSalida = (float)($s['peso'] ?? 0.0);
                if ($nombreSalida === '') continue;

                // crear ingrediente de salida y asignar alergenos
                $newIngId = $ingredienteModel->createIngrediente($this->pdo, $nombreSalida, $origenIndicaciones);
                if (!empty($alergenosIds)) {
                    $ingredienteModel->assignAlergenosByIds($this->pdo, $newIngId, $alergenosIds);
                }

                // insertar relación elaborados_ingredientes
                $insRel2 = $this->pdo->prepare("INSERT INTO elaborados_ingredientes (id_elaborado, id_ingrediente, cantidad, id_unidad) VALUES (:eid,:iid,:cant,:uid)");
                $insRel2->execute([
                    ':eid' => $idElaborado,
                    ':iid' => $newIngId,
                    ':cant' => $pesoSalida,
                    ':uid' => $idUnidadKg
                ]);
            }

            $this->pdo->commit();
            return $idElaborado;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    /**
     * Obtener ingredientes asociados a un elaborado.
     *
     * Devuelve filas con keys:
     *  - id_ingrediente (int)
     *  - nombre (string)
     *  - cantidad (float)
     *  - id_unidad (int)
     *  - es_origen (int 0/1)
     *
     * @param int $idElaborado
     * @return array<int, array<string,mixed>>
     */
    public function getIngredienteElaborado(int $idElaborado): array
    {
        $sql = 'SELECT ei.id_ingrediente, i.nombre, ei.cantidad, ei.id_unidad, ei.es_origen
                FROM elaborados_ingredientes ei
                JOIN ingredientes i ON ei.id_ingrediente = i.id_ingrediente
                WHERE ei.id_elaborado = :idElaborado';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idElaborado' => $idElaborado]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$r) {
            $r['id_ingrediente'] = isset($r['id_ingrediente']) ? (int)$r['id_ingrediente'] : 0;
            $r['cantidad'] = isset($r['cantidad']) ? (float)$r['cantidad'] : 0.0;
            $r['id_unidad'] = isset($r['id_unidad']) ? (int)$r['id_unidad'] : 0;
            $r['es_origen'] = isset($r['es_origen']) ? (int)$r['es_origen'] : 0;
        }
        unset($r);

        return $rows;
    }

    /**
     * Obtener IDs de ingredientes de salida asociados a un elaborado.
     *
     * @param int $idElaborado
     * @return array<int>
     */
    public function getIdsIngredientesSalida(int $idElaborado): array
    {
        $sql = 'SELECT ei.id_ingrediente
                FROM elaborados_ingredientes ei
                WHERE ei.id_elaborado = :idElaborado AND (ei.es_origen IS NULL OR ei.es_origen = 0)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idElaborado' => $idElaborado]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $ids = [];
        foreach ($rows as $r) {
            if (isset($r['id_ingrediente'])) {
                $ids[] = (int)$r['id_ingrediente'];
            }
        }
        return $ids;
    }

    /**
     * Comprobar si un ingrediente está siendo usado en otros elaborados.
     *
     * @param int $idIngrediente
     * @param int $excludeElaboradoId (opcional) id de elaborado a excluir de la comprobación
     * @return bool true si se usa en otro elaborado, false si no
     */
    public function isIngredienteUsedInOtherElaborados(int $idIngrediente, int $excludeElaboradoId = 0): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM elaborados_ingredientes WHERE id_ingrediente = :idIngrediente';
        if ($excludeElaboradoId > 0) {
            $sql .= ' AND id_elaborado != :excludeElaboradoId';
        }
        $stmt = $this->pdo->prepare($sql);
        $params = [':idIngrediente' => $idIngrediente];
        if ($excludeElaboradoId > 0) {
            $params[':excludeElaboradoId'] = $excludeElaboradoId;
        }
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $cnt = isset($row['cnt']) ? (int)$row['cnt'] : 0;
        return $cnt > 0;
    }

    /**
     * Eliminar las líneas de ingredientes asociadas a un elaborado.
     *
     * @param int $idElaborado
     */
    public function deleteElaboradoLineas(int $idElaborado): void
    {
        $sql = 'DELETE FROM elaborados_ingredientes WHERE id_elaborado = :idElaborado';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idElaborado' => $idElaborado]);
    }

    /**
     * Eliminar un elaborado por id.
     *
     * @param int $idElaborado
     */
    public function deleteElaborado(int $idElaborado): void
    {
        // Ejecutar dentro de transacción si se quiere agrupar con otras operaciones.
        $sql = 'DELETE FROM elaborados WHERE id_elaborado = :idElaborado';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idElaborado' => $idElaborado]);
    }

    /**
     * Eliminar un escandallo y sus ingredientes de salida si no se usan en otros elaborados.
     *
     * Comportamiento:
     *  - Si algún ingrediente de salida está en uso por otro elaborado => lanza RuntimeException (no borra nada).
     *  - Si todos los ingredientes de salida son exclusivos de este escandallo, borra cada ingrediente (delegando a Ingrediente::deleteIngrediente),
     *    borra las relaciones y por último borra el elaborado.
     *
     * @param int $idElaborado
     * @throws \RuntimeException si algún ingrediente de salida está en uso en otro elaborado
     */
    public function deleteEscandallo(int $idElaborado): void
    {
        // Iniciar transacción para evitar estados intermedios
        $this->pdo->beginTransaction();
        try {
            // Obtener ids de ingredientes de salida
            $ingredientesIdsSalida = $this->getIdsIngredientesSalida($idElaborado);

            // Comprobar si alguno está en uso por otros elaborados (excluyendo el propio)
            $usedByOthers = [];
            foreach ($ingredientesIdsSalida as $id) {
                if ($this->isIngredienteUsedInOtherElaborados($id, $idElaborado)) {
                    $usedByOthers[] = $id;
                }
            }

            if (!empty($usedByOthers)) {
                // No hacemos cambios, devolvemos error para que el controlador lo presente
                $this->pdo->rollBack();
                throw new \RuntimeException('Los siguientes ingredientes están en uso por otros elaborados: ' . implode(',', $usedByOthers));
            }

            // Borrar relaciones en elaborados_ingredientes (si quedaron)
            $this->deleteElaboradoLineas($idElaborado);

            // Todos los ingredientes de salida son exclusivos -> borrarlos
            foreach ($ingredientesIdsSalida as $existingId) {
                // delegar borrado de ingrediente y sus relaciones a Ingrediente model
                $this->ingredienteModel->deleteIngrediente($this->pdo, $existingId);
            }

            // Borrar el elaborado en sí
            $this->deleteElaborado($idElaborado);

            $this->pdo->commit();
            return;

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e; // el controlador lo atrapará y lo logueará
        }
    }
}
