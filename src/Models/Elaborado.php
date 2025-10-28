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
    public function createEscandallo(int $origenId, float $pesoInicial, array $salidas, string $descripcion, string $nombre, int $diasViabilidad, int $tipo, Ingrediente $ingredienteModel): int
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
                ':t' => $tipo
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
    public function createElaboracion($nombre,$descripcion,$pesoTotal,$diasViabilidad, $tipo): int //devuelve la id del elaborado creado
    {
        // Implementar la lógica para crear una nueva elaboración en la base de datos.
        $sql = 'INSERT INTO elaborados (nombre, peso_obtenido, descripcion, dias_viabilidad, tipo) 
                VALUES (:nombre, :peso_obtenido, :descripcion, :dias_viabilidad, :tipo)';
        $stmt = $this->pdo->prepare($sql);
        $params = [
            ':nombre' => $nombre,
            ':peso_obtenido' => $pesoTotal === null ? null : (float)$pesoTotal,
            ':descripcion' => $descripcion,
            ':dias_viabilidad' => $diasViabilidad === null ? null : (int)$diasViabilidad,
            ':tipo' => $tipo === null ? 0 : (int)$tipo,
        ];
        $stmt->execute($params);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateElaboracion($id, $nombre, $descripcion, $pesoTotal, $diasViabilidad, $tipo): void
    {
        // Actualizar todos los campos esperados (se puede refactorizar para updates parciales si se desea)
        $sql = 'UPDATE elaborados
                SET nombre = :nombre,
                    peso_obtenido = :peso_obtenido,
                    descripcion = :descripcion,
                    dias_viabilidad = :dias_viabilidad,
                    tipo = :tipo
                WHERE id_elaborado = :id';
        $stmt = $this->pdo->prepare($sql);
        $params = [
            ':id' => (int)$id,
            ':nombre' => $nombre,
            ':peso_obtenido' => $pesoTotal === null ? null : (float)$pesoTotal,
            ':descripcion' => $descripcion,
            ':dias_viabilidad' => $diasViabilidad === null ? null : (int)$diasViabilidad,
            ':tipo' => $tipo === null ? 0 : (int)$tipo,
        ];
        $stmt->execute($params);
    }

    public function addIngredienteToElaborado(int $idElaborado, int $idIngrediente, float $cantidad, int $idUnidad, bool $esOrigen = false): void
    {
        $sql = 'INSERT INTO elaborados_ingredientes (id_elaborado, id_ingrediente, cantidad, id_unidad, es_origen) 
                VALUES (:id_elaborado, :id_ingrediente, :cantidad, :id_unidad, :es_origen)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_elaborado' => $idElaborado,
            ':id_ingrediente' => $idIngrediente,
            ':cantidad' => $cantidad,
            ':id_unidad' => $idUnidad,
            ':es_origen' => $esOrigen ? 1 : 0
        ]);
    }
    public function updateIngredienteToElaborado(int $idElaborado, int $idIngrediente, float $cantidad, int $idUnidad, bool $esOrigen = false): void
    {
        $sql = 'UPDATE elaborados_ingredientes SET cantidad = :cantidad, id_unidad = :id_unidad, es_origen = :es_origen 
                WHERE id_elaborado = :id_elaborado AND id_ingrediente = :id_ingrediente';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id_elaborado' => $idElaborado,
            ':id_ingrediente' => $idIngrediente,
            ':cantidad' => $cantidad,
            ':id_unidad' => $idUnidad,
            ':es_origen' => $esOrigen ? 1 : 0
        ]);
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
     * Eliminar los ingredientes asociado a un elaborado.
     * 
     * @param int $idElaborado
     */
    public function deleteIngredientesAsociados(int $idIngrediente, int $idElaborado): void
    {
        $sql = 'DELETE FROM elaborados_ingredientes WHERE id_ingrediente = :idIngrediente AND id_elaborado = :idElaborado';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idIngrediente' => $idIngrediente, ':idElaborado' => $idElaborado]);
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

    public function deleteElaboracion(int $idElaborado): void
    {
        // Obtener información del elaborado
        $elaborado = $this->findById($idElaborado);
        if ($elaborado === null) {
            throw new \RuntimeException('Elaborado no encontrado.');
        }
        // Empezar transacción
        $this->pdo->beginTransaction();
        try {
            // Obtener id's de ingedientes asociados que son origen
            $origenId = $this->origenIngredienteID($idElaborado);
            // Si origenId es distinto de 0 hay que comprobar si se usa en otros elabordos
            if ($origenId > 0 && $this->isIngredienteUsedInOtherElaborados($origenId, $idElaborado)) {
                // No hacemos cambios, devolvemos error para que el controlador lo presente
                $this->pdo->rollBack();
                throw new \RuntimeException('El ingrediente origen está en uso por otros elaborados.');
            }
            // Eliminar relaciones en elaborados_ingredientes
            $this->deleteElaboradoLineas($idElaborado);
            // Si origen id no es nulo, eliminar el ingrediente origen
            if ($origenId > 0) {
                // Eliminar la asociación ingrediente_alergenos
                $this->ingredienteModel->removeAllAlergenosFromIngrediente($this->pdo, $origenId);
                // Eliminar el ingrediente origen
                $this->ingredienteModel->deleteIngrediente($this->pdo, $origenId);
            }
            // eliminar el elaborado
            $this->deleteElaborado($idElaborado);
            // Confirmar transacción
            $this->pdo->commit();
        } catch (\Throwable $e) {
            // Revertir transacción en caso de error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
                // Log error
                error_log($e->getMessage());
            }
            throw $e; // el controlador lo atrapará y lo logueará
        }
    }

    /**
     * Elimina un elaborado de tipo "otro" (procesos sin ingredientes asociados).
     *
     * Comportamiento:
     * - Estos elaborados representan procesos como envasado/etiquetado/congelado que no crean ni relacionan ingredientes.
     * - Solo se elimina la fila correspondiente en la tabla `elaborados`; no se tocan ingredientes ni relaciones externas.
     * - La operación se ejecuta dentro de una transacción para garantizar atomicidad. En caso de error se revierte la transacción
     *   y se vuelve a lanzar la excepción para que el controlador la gestione.
     *
     * Notas:
     * - Si en el futuro se permitiera asociar ingredientes o generar subproductos a este tipo de elaborados, habrá que adaptar este método para
     *   borrar o preservar las relaciones y/o ingredientes según la nueva regla de negocio.
     *
     * @param int $idElaborado ID del elaborado a eliminar
     * @throws \Throwable Propaga cualquier excepción lanzada por las operaciones PDO
     * @return void
     */
    public function deleteOtraElaboracion(int $idElaborado)
    {
        $this->pdo->beginTransaction();
        try {
            // Borrar el elaborado (la función deleteElaborado ejecuta el DELETE correspondiente).
            $this->deleteElaborado($idElaborado);

            // Confirmar cambios
            $this->pdo->commit();
        } catch (\Throwable $e) {
            // Si hay error, revertir transacción y registrar el mensaje
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
                error_log($e->getMessage());
            }
            // Re-lanzar para que el controlador lo maneje
            throw $e;
        }
    }

    public function origenIngredienteID(int $idElaborado): int
    {
        $sql = 'SELECT id_ingrediente FROM elaborados_ingredientes WHERE id_elaborado = :idElaborado AND es_origen = 1 LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':idElaborado' => $idElaborado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['id_ingrediente'])) {
            return 0;
        }
        return (int)$row['id_ingrediente'];
    }
    public function getTiposElaboracion(): array
    {
        $sql = 'SELECT nombre FROM tipo_elaboracion ORDER BY nombre ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tipos = [];
        foreach ($rows as $r) {
            if (isset($r['nombre'])) {
                $tipos[] = (string)$r['nombre'];
            }
        }

        return $tipos;
    }
    public function getTipoElaboracion(int $id): ?string
    {
        $sql = 'SELECT nombre FROM tipo_elaboracion WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['nombre'])) {
            return null;
        }
        return (string)$row['nombre'];
    }
    public function getTipoByName(string $nombre): ?int
    {
        $sql = 'SELECT id FROM tipo_elaboracion WHERE nombre = :nombre LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':nombre' => $nombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['id'])) {
            return null;
        }
        return (int)$row['id'];
    }
    public function getTipoNameById(int $id): ?string
    {
        $sql = 'SELECT nombre FROM tipo_elaboracion WHERE id = :id LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !isset($row['nombre'])) {
            return null;
        }
        return (string)$row['nombre'];
    }
    public function getTipos(): array
    {
        $sql = 'SELECT id, nombre, descripcion FROM tipo_elaboracion ORDER BY nombre ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tipos = [];
        foreach ($rows as $r) {
            if (isset($r['id']) && isset($r['nombre'])) {
                $tipos[] = ['id' => (int)$r['id'], 'nombre' => (string)$r['nombre'], 'descripcion' => (string)$r['descripcion']];
            }
        }

        return $tipos;
    }
}