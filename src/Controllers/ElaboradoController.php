<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Csrf;
use App\Utils\Auth;
use App\Utils\Access;
use App\Utils\Redirect;
use App\Models\Elaborado;
use App\Models\Ingrediente;
use App\Models\Unit;
use PDO;

/**
 * ElaboradoController
 *
 * Orquesta la interacción entre la petición HTTP, el modelo Elaborado y la vista.
 *
 * Flujo principal:
 *  - constructor recibe dependencias (PDO, opcional user, debug)
 *  - handleRequest() enruta por método HTTP (ahora sólo GET → list)
 *  - list(): obtiene todos los elaborados del modelo y requiere la vista de listado
 *
 * Notas de seguridad/arquitectura:
 *  - El controlador no hace echo directo salvo incluir vistas.
 *  - Validaciones, control de CSRF y permisos para mutaciones deben implementarse aquí
 *    cuando se añadAN acciones POST/PUT/DELETE.
 */
final class ElaboradoController
{
    private PDO $pdo;
    private Elaborado $model;
    private Ingrediente $ingredienteModel;
    private Unit $unitModel;

    /**
     * @param PDO $pdo
     * @param mixed|null $user Información del usuario actual (opcional)
     * @param bool $debug Flag para mostrar info debug en vistas
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new Elaborado($pdo);
        $this->ingredienteModel = new Ingrediente($pdo);
        $this->unitModel = new Unit($pdo);
    }

    /**
     * Punto de entrada del controlador: enrutar según método HTTP.
     *
     * Actualmente sólo implementa listado (GET). Extender para POST/DELETE cuando sea necesario.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            // Procesar mutaciones (crear/guardar)
            $action = $_POST['action'] ?? '';
            if ($action === 'save_escandallo') {
                $this->saveEscandallo();
                return;
            }
            if ($action === 'save_elaboracion') {
                $this->saveElaboracion();
                return;
            }
            if ($action === 'save_otra_elaboracion') {
                $this->saveOtraElaboracion();
                return;
            }
            if ($action === 'update_escandallo') {
                $this->updateEscandallo();
                return;
            }
            if ($action === 'delete') {
                $this->deleteElaborado();
                return;
            }
        }
        if ($method === 'GET') {
            // Rutas GET
            if (isset($_GET['crear'])) {
                $this->renderEdit(null); // formulario para crear nuevo
                return;
            } elseif (isset($_GET['modificar'], $_GET['id']) && ctype_digit((string)$_GET['id'])) {
                $id = (int)$_GET['id'];
                $this->renderEdit($id); // formulario para modificar existente
                return;
            }
        }

        $this->renderList();
    }

    /**
     * Acción: listar elaborados y renderizar la vista.
     *
     * Obtiene datos desde el modelo y deja las variables esperadas por la vista:
     *  - $elaborados (array)
     *  - $debug (bool)
     *
     * La vista debe haber sido diseñada para escapar salidas y formatear.
     *
     * @return void
     */
    private function renderList(): void
    {
        $elaborados = $this->model->getAll();
        $canModify = $this->canModify();
        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        // Incluir la vista de listado. Ruta relativa desde src/Controllers a public/views.
        require __DIR__ . '/../../public/views/elaborados_view.php';
    }
    /** 
     * renderEdit
     * 
     * Renderiza la vista de edición/creación de elaborado.
     * Reglas:
     * - Sólo usuarios con permiso (canModify) pueden acceder; si no => redirigir al listado.
     * - Si $id == null => formulario para crear (vacío).
     * - Si $id > 0 => cargar elaborado; si no existe => redirigir.
     */
    private function renderEdit(?int $id): void
    {
        // Seguridad: bloquear el editor si no tiene permisos
        if (!$this->canModify()) {
            Redirect::to('/elaborados.php');
        }

        $elaborado = null;
        $tiposElaboracion = $this->model->getTiposElaboracion();
        $elaborados = $this->model->getAll();
        $ingredienteElaborado = [];
        $ingredienteOrigen = [];
        if ($id !== null && $id > 0) {
            // Cargar datos del elaborado; redirigir si no existe
            $elaborado = $this->model->findById($id);
            $ingredienteElaborado = $this->model->getIngredienteElaborado($id);
            // filtrar para quedarnos solo con las salidas (es_origen=0)
            $ingredienteOrigen = array_filter($ingredienteElaborado, function ($ie) {
                return (isset($ie['es_origen']) && (int)$ie['es_origen'] === 1);
            });
            $ingredienteElaborado = array_filter($ingredienteElaborado, function ($ie) {
                return (isset($ie['es_origen']) && (int)$ie['es_origen'] === 0);
            });
            if ($elaborado === null) {
                Redirect::to('/elaborados.php');
            }
        }
        // Datos auxiliares para el formulario
        $ingredientes = $this->ingredienteModel->allIngredientes($this->pdo);
        $unidades = $this->unitModel->getAllUnits();
        $debug = defined('APP_DEBUG') && APP_DEBUG === true;
        $csrf = Csrf::generateToken();

        // Renderizar vista de edición
        require __DIR__ . '/../../public/views/elaborados_edit_view.php';
    }

    /**
     * canModify
     * 
     * Determina si el usuario actual tiene permiso para modificar elaborados.
     * 
     * Política:
     * - Obtiene roles del usuario desde la BD.
     * - Resuelve el rol de mayor prioridad con Access::highestRole.
     * - Devuelve true si ese rol es admin, gestor o calidad.   
     * 
     * @return bool true si el usuario puede crear/editar/eliminar
     */
    private function canModify(): bool
    {
        $viewer = Auth::user($this->pdo);
        if (!$viewer) {
            // No autenticado => no puede modificar
            return false;
        }
        // Leer roles desde BD y resolver rol principal
        $roles = Access::getUserRoles($this->pdo, (int)$viewer['id']);
        $principal = Access::highestRole($roles);
        // Permitimos admin, gestor y calidad (calidad es el mínimo)
        return in_array($principal, [Access::ROLE_ADMIN, Access::ROLE_GESTOR, Access::ROLE_CALIDAD], true);
    }
    /**
     * saveEscandallo
     *
     * Procesa POST action=save_escandallo.
     * - Validación mínima de inputs.
     * - Crea registro en `elaborados`.
     * - Para cada salida: crea un ingrediente nuevo que hereda indicaciones y alérgenos
     *   del ingrediente origen y añade la relación en `recetas_ingredientes`.
     *
     * Notas / supuestos:
     * - El formulario debe enviar: origen_id, peso_inicial, salida_nombre[] y salida_peso[] y opcional descripcion/nombre.
     * - fecha_caducidad se deja a la fecha actual (ajustar si necesitas otra lógica).
     * - Se intenta usar la unidad 'kg' (abreviatura 'kg'); si no existe se usa la primera unidad disponible.
     *
     * @return void (redirige al listado al finalizar)
     */
    private function saveEscandallo(): void
    {
        // CSRF + permisos
        if (!Csrf::validateToken($_POST['csrf'] ?? '')) {
            // invalid token
            http_response_code(400);
            echo 'CSRF token inválido';
            return;
        }
        if (!$this->canModify()) {
            Redirect::to('/elaborados.php');
            return;
        }

        // Recolectar y validar datos
        $origenId = isset($_POST['origen_id']) ? (int)$_POST['origen_id'] : 0;
        $pesoInicial = isset($_POST['peso_inicial']) ? (float)$_POST['peso_inicial'] : 0.0;
        $salidaNombres = $_POST['salida_nombre'] ?? [];
        $salidaPesos = $_POST['salida_peso'] ?? [];
        $diasConservacion = isset($_POST['dias_conservacion']) ? (int)$_POST['dias_conservacion'] : null;
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $nombreElaborado = trim((string)($_POST['nombre'] ?? ''));

        if ($origenId <= 0) {
            // origin required
            $this->renderEditWithError(null, 'Seleccione un ingrediente origen.');
            return;
        }
        if ($pesoInicial <= 0) {
            $this->renderEditWithError(null, 'Indique el peso inicial válido.');
            return;
        }
        // Normalize salidas
        $salidas = [];
        for ($i = 0; $i < max(count($salidaNombres), count($salidaPesos)); $i++) {
            $n = trim((string)($salidaNombres[$i] ?? ''));
            $p = isset($salidaPesos[$i]) ? (float)$salidaPesos[$i] : 0.0;
            if ($n === '' && $p <= 0) continue; // ignorar vacíos
            if ($n === '') {
                $this->renderEditWithError(null, 'Cada salida debe tener un nombre.');
                return;
            }
            $salidas[] = ['nombre' => $n, 'peso' => $p];
        }
        if (empty($salidas)) {
            $this->renderEditWithError(null, 'Añada al menos una salida para el escandallo.');
            return;
        }

        // Coger datos del ingrediente origen (indicaciones + alérgenos)
        $origen = $this->ingredienteModel->findById($this->pdo, $origenId);
        if ($origen === null) {
            $this->renderEditWithError(null, 'Ingrediente origen no encontrado.');
            return;
        }
        $origenIndicaciones = $origen['indicaciones'] ?? '';
        // extraer ids de alergenos (soportar distintos formatos)
        $alergenosOrigen = $origen['alergenos'] ?? [];
        $alergenosIds = [];
        foreach ($alergenosOrigen as $a) {
            if (isset($a['id_alergeno'])) $alergenosIds[] = (int)$a['id_alergeno'];
            elseif (isset($a['id'])) $alergenosIds[] = (int)$a['id'];
            elseif (isset($a['id_alergeno'])) $alergenosIds[] = (int)$a['id_alergeno'];
        }

        // unidad kg id
        $stmt = $this->pdo->prepare("SELECT id_unidad FROM unidades_medida WHERE abreviatura = :abr LIMIT 1");
        $stmt->execute([':abr' => 'kg']);
        $unitRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $idUnidadKg = $unitRow ? (int)$unitRow['id_unidad'] : null;
        if ($idUnidadKg === null) {
            // fallback: primera unidad disponible
            $stmt = $this->pdo->query("SELECT id_unidad FROM unidades_medida LIMIT 1");
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            $idUnidadKg = $r ? (int)$r['id_unidad'] : 1;
        }

        // Nombre del elaborado por defecto si no se proporciona
        if ($nombreElaborado === '') {
            $nombreElaborado = ($origen['nombre'] ?? 'Escandallo') . ' - escandallo';
        }


        try {
            // Delega la creación al modelo (el modelo hace la transacción y lanza excepción si falla)
            $this->model->createEscandallo(
                $origenId,
                $pesoInicial,
                $salidas,
                $descripcion,
                $nombreElaborado,
                $diasConservacion,
                $this->ingredienteModel
            );
        } catch (\Throwable $e) {
            // Renderizar formulario con error amigable
            $this->renderEditWithError(null, 'Error guardando escandallo: ' . $e->getMessage());
            return;
        }

        // Ok: redirigir al listado
        Redirect::to('/elaborados.php');
    }

    /**
     * Guarda el proceso de elaboración (POST action=save_elaboracion) elaborado_view.php.
     * - Valida CSRF y permisos.
     * - Recolecta inputs: id, nombre, peso_total, saveAsIngredient, descripcion, ingredientes[], cantidades[] y unidades[]. 
     * - Actualiza las tablas elaborados y elaborados_ingredientes. En caso de que exista saveAsIngredient, también crea/actualiza el ingrediente asociado emplando metodos del propio modelo Ingrediente.
     * 
     */
    private function saveElaboracion(): void
    {
        // CSRF + permisos
        if (!Csrf::validateToken($_POST['csrf'] ?? '')) {
            // invalid token
            http_response_code(400);
            echo 'CSRF token inválido';
            return;
        }
        if (!$this->canModify()) {
            Redirect::to('/elaborados.php');
            return;
        }
        // Recolectar y validar datos
        $elaboradoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $pesoTotal = isset($_POST['peso_total']) ? (float)$_POST['peso_total'] : 0.0;
        $diasConservacion = isset($_POST['dias_viabilidad']) ? (int)$_POST['dias_viabilidad'] : null;
        $saveAsIngredient = isset($_POST['save_as_ingredient']) ? (bool)$_POST['save_as_ingredient'] : false;
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $ingredientes = $_POST['ingredientes'] ?? [];
        $cantidades = $_POST['cantidades'] ?? [];
        $unidades = $_POST['unidades'] ?? [];
        if ($elaboradoId > 0) {
            $this->renderEditWithError(null, 'Para modificar una elaboración, use el formulario de edición.');
            return;
        }
        // El peso total puede ser 0 o más (0 significa que no se especifica)
        if ($pesoTotal < 0) {
            $this->renderEditWithError($elaboradoId, 'Indique el peso total válido (0 o más).');
            return;
        }

        // Registrar datos con json_encode (fallback a print_r si falla)
        $encode = function ($data) {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($json === false) {
            return print_r($data, true);
            }
            return $json;
        };

        // Normalize ingredientes | La tabla elaborados_ingredientes tiene los campos id_ingrediente, cantidad y id_unidad
        $ingredientesData = [];
        // Los ingredientes pueden tener cantidades vacias en cuyo caso se asinara 0 indicadno que no ha sido especificada o lleva una cantidad de no facil medición
        // Normalizar a arrays por si los inputs vienen como valores simples (ej. no usan [] en el formulario)
        $ingredientes = is_array($ingredientes) ? $ingredientes : ($ingredientes === null ? [] : [$ingredientes]);
        $cantidades = is_array($cantidades) ? $cantidades : ($cantidades === null ? [] : [$cantidades]);
        $unidades = is_array($unidades) ? $unidades : ($unidades === null ? [] : [$unidades]);
        $max = max(count($ingredientes), count($cantidades), count($unidades));
        for ($i = 0; $i < $max; $i++) {
            error_log("Dentro del loop, i=$i");
            $id = isset($ingredientes[$i]) && ctype_digit((string)$ingredientes[$i]) ? (int)$ingredientes[$i] : 0;
            $cantidad = isset($cantidades[$i]) && is_numeric($cantidades[$i]) ? (float)$cantidades[$i] : 0.0;
            $unidadId = isset($unidades[$i]) && ctype_digit((string)$unidades[$i]) ? (int)$unidades[$i] : 1; // unidad por defecto 1
            if ($id <= 0) continue; // ignorar vacíos o ids inválidos
            // Permitimos cantidad 0.0 para indicar que no se especifica
            $ingredientesData[] = ['id' => $id, 'cantidad' => $cantidad, 'unidad_id' => $unidadId];
        }
        if (empty($ingredientesData)) {
            $this->renderEditWithError($elaboradoId, 'Añada al menos un ingrediente para la elaboración.');
            return;
        }
        $this->pdo->beginTransaction();
        try {
            // Si saveAsIngredient es true, también crea/actualiza el ingrediente asociado pero seran funciones delegadas al modelo Ingrediente
            // Empezamos creando el elaborado como ingrediente si procede
            if ($saveAsIngredient) {
                // crear ingrediente asociado o actualizar si ya existe
                $existing = $this->ingredienteModel->findByName($this->pdo, $nombre);
                if ($existing !== null) {
                    // actualizar
                    $ingredienteModel->updateIngrediente($this->pdo, $existing['id_ingrediente'], $nombre, '', []);
                    $idIngredienteAsociado = (int)$existing['id_ingrediente'];
                } else {
                    // crear nuevo
                    $idIngredienteAsociado = $this->ingredienteModel->createIngrediente($this->pdo, $nombre, '');
                }
                // Creamos una lista de los alergenos asociados a los ingredientes de entrada
                // para asignarlos al ingrediente empleando el metodo getUniqueAlergenosFromIngredientes del modelo ingrediente solo adminte un array de ids
                // nuestos ingredientes es un array con arrays asociativos que contienen id_ingrediente, cantidad, id_unidad
                // extraer ids de los ingredientes de entrada (pueden venir como id_ingrediente o id)
                $ingIds = [];
                foreach ($ingredientesData as $it) {
                    if (isset($it['id_ingrediente'])) {
                        $ingIds[] = (int)$it['id_ingrediente'];
                    } elseif (isset($it['id'])) {
                        $ingIds[] = (int)$it['id'];
                    }
                }
                $ingIds = array_values(array_unique(array_filter($ingIds, function ($v) {
                    return $v > 0;
                })));

                // obtener alérgenos únicos de esos ingredientes empleando el método del modelo Ingrediente
                // Este metodo solicita un array de ids de ingredientes y devuelve un array de ids de alergenos únicos
                error_log('Ids de ingredientes para alérgenos: ' . $encode($ingIds));
                $alergenosIds = $this->ingredienteModel->getUniqueAlergenosFromIngredientes($ingIds);
                error_log('Ids de alérgenos obtenidos: ' . $encode($alergenosIds));
                if (!empty($alergenosIds)) {
                    $this->ingredienteModel->assignAlergenosByIds($this->pdo, $idIngredienteAsociado, $alergenosIds);
                }
            }
            // crea la elaboración para la tabla elaborados delengadola en el modelo (nombre, descripción, peso_obtenido, dias_viabilidad y tipo)
            $idElaborado = $this->model->createElaboracion(
                $nombre,
                $descripcion,
                $pesoTotal,
                $diasConservacion,
                0, // tipo 0 = elaboración normal
            );
            // ahora actualiza la tabla elaborados_ingredientes con los ingredientes de entrada
            foreach ($ingredientesData as $ing) {
                $this->model->addIngredienteToElaborado(
                    $idElaborado,
                    $ing['id'],
                    $ing['cantidad'],
                    $ing['unidad_id'],
                    false // es_origen = 0 para ingredientes de elaboración
                );
            }
            // Si saveAsIngredient es true, actualiza añadir elaboración como ingrediente asociado y es_origen=1
            if ($saveAsIngredient) {
                $this->model->addIngredienteToElaborado(
                    $idElaborado,
                    $idIngredienteAsociado,
                    0.0, // cantidad 0 para el ingrediente asociado
                    1,   // id_unidad 1 (unidad por defecto)
                    true // es_origen = 1 para el ingrediente asociado
                );
            }
            $this->pdo->commit();
            // Redirigir al listado
            Redirect::to('/elaborados.php');
        } catch (\Throwable $e) {
            // Renderizar formulario con error amigable
            $this->renderEditWithError($elaboradoId, 'Error guardando elaboración: ' . $e->getMessage());
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return;
        }
    }
    /**
     * saveOtraElaboracion
     * 
     * Procesa POST action=save_otra_elaboracion desde elaborado_view.php
     * - Valida CSRF y permisos.
     * - Recolecta inputs: id, nombre, peso_total, descripcion, ingredientes[], cantidades[] y unidades[].
     * - Crea un nuevo elaborado de tipo 1 (otra elaboración) y añade los ingredientes asociados.
     * 
     */
    private function saveOtraElaboracion(): void
    {
        // CSRF + permisos
        if (!Csrf::validateToken($_POST['csrf'] ?? '')) {
            // invalid token
            http_response_code(400);
            echo 'CSRF token inválido';
            return;
        }
        if (!$this->canModify()) {
            Redirect::to('/elaborados.php');
            return;
        }
        // Recolectar y validar datos: id, selected_entity_type, selected_entity_id, peso_obtenido, dias_viabilidad, descripcion
        $elaboradoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $selectedEntityType = trim((string)($_POST['selected_entity_type'] ?? ''));
        $selectedEntityId = isset($_POST['selected_entity_id']) ? (int)$_POST['selected_entity_id'] : 0;
        $pesoObtenido = isset($_POST['peso_obtenido']) ? (float)$_POST['peso_obtenido'] : 0.0;
        $diasViabilidad = isset($_POST['dias_viabilidad']) ? (int)$_POST['dias_viabilidad'] : null;
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $tipo = $_POST['tipo'] ?? "";
        $idTipo =$this->model->getTipoByName($tipo);

        if ($elaboradoId > 0) {
            $this->renderEditWithError(null, 'Para modificar una elaboración, use el formulario de edición.');
            return;
        }
        if ($selectedEntityType === '' || $selectedEntityId <= 0) {
            $this->renderEditWithError(null, 'Seleccione una entidad válida para la otra elaboración.');
            return;
        }
        // El peso obtenido puede ser 0 o más (0 significa que no se especifica)
        if ($pesoObtenido < 0) {
            $this->renderEditWithError(null, 'Indique el peso obtenido válido (0 o más).');
            return;
        }

        // obtener nombre desde el modelo según el tipo seleccionado
        $nombre = '';
        if ($selectedEntityType === 'Ingrediente') {
            $ingrediente = $this->ingredienteModel->findById($this->pdo,$selectedEntityId);
            $nombre = $ingrediente['nombre'] ?? '';
        } elseif ($selectedEntityType === 'Elaborado') {
            $elaborado = $this->model->findById($selectedEntityId);
            $nombre = $elaborado['nombre'] ?? '';
        }
        $nombre = $nombre . ' - ' . ($tipo ?? 'Otra elaboración');
        if ($nombre === '') {
            $this->renderEditWithError(null, 'No se pudo obtener el nombre de la entidad seleccionada.');
            return;
        }
        $this->pdo->beginTransaction();
        try {
            // crea la elaboración para la tabla elaborados delengadola en el modelo (nombre, descripción, peso_obtenido, dias_viabilidad y tipo)
            $idElaborado = $this->model->createElaboracion(
                $nombre,
                $descripcion,
                $pesoObtenido,
                $diasViabilidad,
                $idTipo, // tipo 1 = otra elaboración
            );
            // si el tipo es Ingrediente añadir a elaborados_ingredientes con es_origen=1
            if ($selectedEntityType === 'Ingrediente') {
                $this->model->addIngredienteToElaborado(
                    $idElaborado,
                    $selectedEntityId,
                    0.0, // cantidad 0 para el ingrediente asociado
                    1,   // id_unidad 1 (unidad por defecto)
                    true // es_origen = 1 para el ingrediente asociado
                );
            } 
        } catch (\Throwable $e) {
            // Renderizar formulario con error amigable
            $this->renderEditWithError(null, 'Error guardando otra elaboración: ' . $e->getMessage());
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return;
        }
        $this->pdo->commit();
        // Redirigir al listado
        Redirect::to('/elaborados.php');
    }

    /**
     * Actualiza un escandallo basándose en la estructura POST descrita.
     * Espera campos:
     *  - csrf
     *  - id (id del escandallo)
     *  - origen_id
     *  - peso_inicial
     *  - descripcion
     *  - salida_nombre[] (array)
     *  - salida_peso[] (array)
     *  - restos (opcional)
     */
    private function updateEscandallo(): void
    {
        // CSRF
        $csrf = $_POST['csrf'] ?? '';
        if (!Csrf::validateToken($csrf)) {
            http_response_code(400);
            $this->renderEditWithError(null, 'CSRF token inválido.');
            return;
        }

        // Recoger inputs básicos
        $elaboradoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $origenIdInput = isset($_POST['origen_id']) ? (int)$_POST['origen_id'] : null;
        $pesoInicialRaw = $_POST['peso_inicial'] ?? null;
        $descripcionRaw = $_POST['descripcion'] ?? '';
        $diasConservacionRaw = $_POST['dias_conservacion'] ?? null;
        $restosRaw = $_POST['restos'] ?? null;

        if ($elaboradoId <= 0) {
            $this->renderEditWithError(null, 'Identificador de escandallo inválido.');
            return;
        }

        $viewer = Auth::user($this->pdo);
        $viewerId = $viewer['id'] ?? null;

        if (!$this->canModify()) {
            $this->renderEditWithError($elaboradoId, 'No tiene permisos para modificar este escandallo.');
            return;
        }

        // Normalizar peso/descripcion/restos/dias_conservacion
        $pesoInicial = (is_numeric($pesoInicialRaw) ? (float)$pesoInicialRaw : null);
        $descripcion = trim((string)$descripcionRaw);
        $restos = (is_numeric($restosRaw) ? (float)$restosRaw : null);
        $diasConservacion = (is_numeric($diasConservacionRaw) ? (int)$diasConservacionRaw : null);

        // Recoger arrays de salidas: salida_id[], salida_nombre[] y salida_peso[]
        $ids = $_POST['salida_id'] ?? [];
        $nombres = $_POST['salida_nombre'] ?? [];
        $pesos = $_POST['salida_peso'] ?? [];

        if (!is_array($ids) || !is_array($nombres) || !is_array($pesos)) {
            $this->renderEditWithError($elaboradoId, 'Formato de salidas inválido.');
            return;
        }

        // Emparejar por índice: construir salidas
        $countIds = count($ids);
        $countNames = count($nombres);
        $countPesos = count($pesos);
        $max = max($countIds, $countNames, $countPesos);
        $salidas = [];
        for ($i = 0; $i < $max; $i++) {
            $rawId = isset($ids[$i]) && $ids[$i] !== '' ? (int)$ids[$i] : null;
            $rawName = isset($nombres[$i]) ? trim((string)$nombres[$i]) : '';
            $rawPeso = isset($pesos[$i]) ? $pesos[$i] : null;

            // Omitir entradas vacías (sin nombre y sin peso útil)
            if ($rawName === '' && ($rawPeso === null || $rawPeso === '')) {
                continue;
            }

            $cantidad = (is_numeric($rawPeso) ? (float)$rawPeso : 0.0);

            $salidas[] = [
                'id' => $rawId,
                'nombre' => $rawName,
                'cantidad' => $cantidad,
            ];
        }

        if (count($salidas) === 0) {
            $this->renderEditWithError($elaboradoId, 'Añada al menos una salida para el escandallo.');
            return;
        }

        // Requerir que exista al menos una salida con cantidad > 0 (regla de negocio)
        $anyPositive = false;
        foreach ($salidas as $s) {
            if ($s['cantidad'] > 0) {
                $anyPositive = true;
                break;
            }
        }
        if (!$anyPositive) {
            $this->renderEditWithError($elaboradoId, 'Debe haber al menos una salida con cantidad positiva.');
            return;
        }

        // Helper: comprobar existencia de columna en una tabla (SQLite)
        $columnExists = function (string $table, string $column): bool {
            $stmt = $this->pdo->prepare("PRAGMA table_info(\"$table\")");
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $c) {
                if (isset($c['name']) && $c['name'] === $column) return true;
            }
            return false;
        };

        $ingredienteTieneCreadoPor = $columnExists('ingredientes', 'creado_por_elaborado');
        $elaboradoTieneRestos = $columnExists('elaborados', 'restos');

        try {
            // Inicio transacción
            $this->pdo->beginTransaction();

            // Cargar el elaborado (en SQLite el BEGIN TRANSACTION ya serializa)
            $stmt = $this->pdo->prepare("SELECT * FROM elaborados WHERE id_elaborado = :id_elaborado");
            $stmt->execute([':id_elaborado' => $elaboradoId]);
            $elaboradoActual = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$elaboradoActual) {
                $this->pdo->rollBack();
                $this->renderEditWithError(null, 'Escandallo no encontrado.');
                return;
            }

            // Cargar relaciones ingredientes de este elaborado (no asumimos columnas inexistentes)
            $stmt = $this->pdo->prepare("
                SELECT ei.*, i.id_ingrediente, i.nombre AS ing_nombre, i.indicaciones
                FROM elaborados_ingredientes ei
                JOIN ingredientes i ON ei.id_ingrediente = i.id_ingrediente
                WHERE ei.id_elaborado = :id_elaborado
            ");
            $stmt->execute([':id_elaborado' => $elaboradoId]);
            $ingredientesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Detectar ingrediente origen actual
            $origenRows = array_filter($ingredientesRows, function ($r) {
                return isset($r['es_origen']) && (int)$r['es_origen'] === 1;
            });
            if (count($origenRows) !== 1) {
                $this->pdo->rollBack();
                $this->renderEditWithError($elaboradoId, 'El escandallo debe tener exactamente un ingrediente de origen.');
                return;
            }
            $origenRow = array_values($origenRows)[0];
            $origenIdActual = (int)$origenRow['id_ingrediente'];

            // Validar que no se cambie el origen
            if ($origenIdInput !== null && $origenIdInput !== $origenIdActual) {
                $this->pdo->rollBack();
                $this->renderEditWithError($elaboradoId, 'No se puede cambiar el ingrediente de origen del escandallo.');
                return;
            }

            // Obtener indicaciones del ingrediente origen
            $stmt = $this->pdo->prepare("SELECT indicaciones FROM ingredientes WHERE id_ingrediente = :id LIMIT 1");
            $stmt->execute([':id' => $origenIdActual]);
            $origenMeta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$origenMeta) {
                $this->pdo->rollBack();
                $this->renderEditWithError($elaboradoId, 'Ingrediente origen no encontrado.');
                return;
            }
            $origenIndicaciones = $origenMeta['indicaciones'] ?? '';

            // Obtener ids de alérgenos del origen desde la tabla pivot ingredientes_alergenos
            $stmt = $this->pdo->prepare("SELECT id_alergeno FROM ingredientes_alergenos WHERE id_ingrediente = :id_ingrediente");
            $stmt->execute([':id_ingrediente' => $origenIdActual]);
            $alRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $alergenosIds = [];
            foreach ($alRows as $ar) {
                $alergenosIds[] = (int)$ar['id_alergeno'];
            }

            // Obtener id_unidad para 'kg' (fallback al primero que haya)
            $stmt = $this->pdo->prepare("SELECT id_unidad FROM unidades_medida WHERE abreviatura = :abr LIMIT 1");
            $stmt->execute([':abr' => 'kg']);
            $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $idUnidadKg = $uRow ? (int)$uRow['id_unidad'] : null;
            if ($idUnidadKg === null) {
                $stmt = $this->pdo->query("SELECT id_unidad FROM unidades_medida LIMIT 1");
                $r = $stmt->fetch(PDO::FETCH_ASSOC);
                $idUnidadKg = $r ? (int)$r['id_unidad'] : 1;
            }

            // Mapear salidas existentes (no origen)
            $salidasExistentesMap = [];
            foreach ($ingredientesRows as $r) {
                if (isset($r['es_origen']) && (int)$r['es_origen'] === 0) {
                    $salidasExistentesMap[(int)$r['id_ingrediente']] = $r;
                }
            }

            // Actualizar campos del elaborado si aplican
            $updateCols = [];
            $params = [':id_elaborado' => $elaboradoId];
            if ($pesoInicial !== null && $pesoInicial >= 0) {
                $updateCols[] = "peso_obtenido = :peso_obtenido";
                $params[':peso_obtenido'] = $pesoInicial;
            }
            if ($elaboradoTieneRestos && $restos !== null && $restos >= 0) {
                $updateCols[] = "restos = :restos";
                $params[':restos'] = $restos;
            }
            if ($descripcion !== '') {
                $updateCols[] = "descripcion = :descripcion";
                $params[':descripcion'] = $descripcion;
            }
            if ($diasConservacion !== null && $diasConservacion >= 0) {
                $updateCols[] = "dias_viabilidad = :dias_conservacion";
                $params[':dias_conservacion'] = $diasConservacion;
            }
            if (!empty($updateCols)) {
                $sql = "UPDATE elaborados SET " . implode(', ', $updateCols) . " WHERE id_elaborado = :id_elaborado";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute($params);
            }

            // Procesar salidas: actualizar si vienen IDs, crear si no
            $salidasInputIds = [];
            foreach ($salidas as $s) {
                if (!empty($s['id'])) {
                    // UPDATE existente — comprobar pertenencia
                    $salidaId = (int)$s['id'];
                    if (!isset($salidasExistentesMap[$salidaId])) {
                        $this->pdo->rollBack();
                        $this->renderEditWithError($elaboradoId, "La salida con ID {$salidaId} no pertenece a este escandallo.");
                        return;
                    }

                    // Actualizar nombre si se envía
                    if ($s['nombre'] !== null && $s['nombre'] !== '') {
                        $stmt = $this->pdo->prepare("UPDATE ingredientes SET nombre = :nombre WHERE id_ingrediente = :id");
                        $stmt->execute([':nombre' => $s['nombre'], ':id' => $salidaId]);
                    }

                    // Actualizar cantidad e id_unidad en elaborados_ingredientes
                    $stmt = $this->pdo->prepare("
                        UPDATE elaborados_ingredientes
                        SET cantidad = :cantidad, id_unidad = :id_unidad
                        WHERE id_elaborado = :id_elaborado AND id_ingrediente = :id_ingrediente
                    ");
                    $stmt->execute([
                        ':cantidad' => $s['cantidad'],
                        ':id_unidad' => $idUnidadKg,
                        ':id_elaborado' => $elaboradoId,
                        ':id_ingrediente' => $salidaId,
                    ]);

                    $salidasInputIds[] = $salidaId;
                } else {
                    // Crear ingrediente nuevo (hereda indicaciones y alérgenos del origen)
                    $stmt = $this->pdo->prepare("INSERT INTO ingredientes (nombre, indicaciones) VALUES (:nombre, :indicaciones)");
                    $stmt->execute([':nombre' => ($s['nombre'] ?: 'SIN_NOMBRE'), ':indicaciones' => $origenIndicaciones]);
                    $nuevoId = (int)$this->pdo->lastInsertId();

                    // Copiar alérgenos (tabla pivot)
                    if (!empty($alergenosIds)) {
                        $stmtIns = $this->pdo->prepare("INSERT OR IGNORE INTO ingredientes_alergenos (id_ingrediente, id_alergeno) VALUES (:id_ingrediente, :id_alergeno)");
                        foreach ($alergenosIds as $aid) {
                            $stmtIns->execute([':id_ingrediente' => $nuevoId, ':id_alergeno' => $aid]);
                        }
                    }

                    // Crear relación en elaborados_ingredientes (necesita id_unidad)
                    $stmt = $this->pdo->prepare("
                        INSERT INTO elaborados_ingredientes (id_elaborado, id_ingrediente, cantidad, id_unidad, es_origen)
                        VALUES (:id_elaborado, :id_ingrediente, :cantidad, :id_unidad, 0)
                    ");
                    $stmt->execute([
                        ':id_elaborado' => $elaboradoId,
                        ':id_ingrediente' => $nuevoId,
                        ':cantidad' => $s['cantidad'],
                        ':id_unidad' => $idUnidadKg
                    ]);

                    // Marcar creador si la columna existe
                    if ($ingredienteTieneCreadoPor) {
                        $stmt = $this->pdo->prepare("UPDATE ingredientes SET creado_por_elaborado = :creado WHERE id_ingrediente = :id");
                        $stmt->execute([':creado' => $elaboradoId, ':id' => $nuevoId]);
                    }

                    $salidasInputIds[] = $nuevoId;
                }
            }

            // Eliminar salidas existentes que ya no aparecen en la request
            foreach ($salidasExistentesMap as $existingId => $exRow) {
                if (!in_array($existingId, $salidasInputIds, true)) {
                    // Eliminar relación primero
                    $stmt = $this->pdo->prepare("DELETE FROM elaborados_ingredientes WHERE id_elaborado = :id_elaborado AND id_ingrediente = :id_ingrediente");
                    $stmt->execute([':id_elaborado' => $elaboradoId, ':id_ingrediente' => $existingId]);

                    // Verificar si alguien más usa ese ingrediente
                    $stmt = $this->pdo->prepare("SELECT COUNT(*) AS cnt FROM elaborados_ingredientes WHERE id_ingrediente = :id_ingrediente");
                    $stmt->execute([':id_ingrediente' => $existingId]);
                    $cntRow = $stmt->fetch(PDO::FETCH_ASSOC);
                    $cnt = $cntRow ? (int)$cntRow['cnt'] : 0;

                    if ($cnt === 0) {
                        // Solo borrar el ingrediente si existe la columna creado_por_elaborado y coincide con este elaborado
                        if ($ingredienteTieneCreadoPor) {
                            $stmt = $this->pdo->prepare("SELECT creado_por_elaborado FROM ingredientes WHERE id_ingrediente = :id LIMIT 1");
                            $stmt->execute([':id' => $existingId]);
                            $meta = $stmt->fetch(PDO::FETCH_ASSOC);
                            $creadoPor = $meta['creado_por_elaborado'] ?? null;
                            if ($creadoPor !== null && (int)$creadoPor === $elaboradoId) {
                                // borrar ingrediente; las filas en ingredientes_alergenos se borrarán por FK ON DELETE CASCADE
                                $stmt = $this->pdo->prepare("DELETE FROM ingredientes WHERE id_ingrediente = :id");
                                $stmt->execute([':id' => $existingId]);
                            }
                        }
                        // Si no hay marca de creación, por seguridad NO borramos el ingrediente.
                    }
                }
            }

            // Commit y auditoría
            $this->pdo->commit();

            if (method_exists($this, 'auditLog')) {
                $this->auditLog($viewerId ?? null, 'update_elaborado', [
                    'id_elaborado' => $elaboradoId,
                    'salidas' => $salidasInputIds,
                ]);
            }

            Redirect::to('/elaborados.php');
            return;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // Log interno con traza completa
            $errMsg = 'Error actualizando escandallo: ' . $e->getMessage();
            error_log($errMsg . "\n" . $e->getTraceAsString());
            if (isset($this->logger) && method_exists($this->logger, 'error')) {
                $this->logger->error('Error actualizando escandallo', [
                    'exception' => $e,
                    'elaborado' => $elaboradoId,
                    'user' => $viewerId ?? null
                ]);
            }
            if ($debug) {
                $this->renderEditWithError($elaboradoId, $errMsg);
            } else {
                $this->renderEditWithError($elaboradoId, 'Error actualizando escandallo. Contacte con el administrador.');
            }
            return;
        }
    }
    /**
     * deleteElaborado
     * 
     * Procesa POST action=delete.
     * - Valida CSRF y permisos.
     * - Carga el elaborado para distinguir si es escandallo o no.
     * - Llama al método adecuado del modelo para borrar.
     * - Maneja errores esperados (ingredientes en uso) y redirige con mensaje.
     * 
     * @return void (redirige al listado al finalizar)
     */
    private function deleteElaborado(): void
    {
        // CSRF + permisos
        if (!Csrf::validateToken($_POST['csrf'] ?? '')) {
            http_response_code(400);
            echo 'CSRF token inválido';
            return;
        }

        $elaboradoId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($elaboradoId <= 0) {
            Redirect::to('/elaborados.php');
            return;
        }

        // Obtener usuario actual (viewer) para auditoría/permisos
        $viewer = Auth::user($this->pdo);
        $viewerId = $viewer['id'] ?? null;

        // Comprueba permisos sobre el recurso (mejor pasar el id)
        if (!$this->canModify($elaboradoId, $viewerId ?? null)) {
            $this->renderEditWithError($elaboradoId, 'No tiene permisos para eliminar este elaborado.');
            return;
        }

        try {
            // Cargar elaborado para distinguir tipo
            $elaborado = $this->model->findById($elaboradoId);
            if ($elaborado === null) {
                Redirect::to('/elaborados.php');
                return;
            }
            $isEscandallo = isset($elaborado['tipo']) && (int)$elaborado['tipo'] === 1;

            if ($isEscandallo) {
                // deleteEscandallo maneja su propia transacción y validaciones
                $this->model->deleteEscandallo($elaboradoId);
            } else {
                // deleteElaboracion también puede delegar transacción si lo deseas
                $this->model->deleteElaboracion($elaboradoId);
            }

            // Auditoría (si el proyecto lo usa)
            if (method_exists($this, 'auditLog')) {
                $this->auditLog($viewerId ?? null, 'delete_elaboracion', ['id_elaborado' => $elaboradoId, 'tipo' => ($isEscandallo ? 'escandallo' : 'elaboracion')]);
            }

            Redirect::to('/elaborados.php');
            return;
        } catch (\RuntimeException $e) {
            // Errores esperados (por ejemplo, ingredientes usados en otros elaborados)
            if (isset($this->logger) && method_exists($this->logger, 'info')) {
                $this->logger->info('Cancelado borrado elaborado por restricción de uso de ingredientes', [
                    'elaborado' => $elaboradoId,
                    'reason' => $e->getMessage(),
                    'user' => $viewerId ?? null
                ]);
            }

            $debug = defined('APP_DEBUG') && APP_DEBUG === true;
            $rawMsg = $e->getMessage() ?: 'No se puede eliminar el escandallo: algunos ingredientes están en uso por otros elaborados.';

            // Si no estamos en modo debug, devolver un mensaje genérico si el mensaje revela detalles técnicos
            if (!$debug) {
                if (preg_match('/\b(SQL|PDO|FOREIGN KEY|constraint|constraint failed|in use|already exists)\b/i', $rawMsg)) {
                    $userMsg = 'No se puede eliminar el escandallo: algunos ingredientes están en uso por otros elaborados.';
                } else {
                    // Limitar la longitud para no exponer demasiado en la URL
                    $userMsg = mb_strlen($rawMsg) > 200 ? mb_substr($rawMsg, 0, 200) . '...' : $rawMsg;
                }
            } else {
                // En debug incluir clase de excepción para facilitar diagnóstico
                $userMsg = $rawMsg . ' (' . get_class($e) . ')';
            }

            Redirect::to('/elaborados.php?error=' . urlencode($userMsg));
            return;
        } catch (\Throwable $e) {
            // Log interno con traza completa
            $errMsg = 'Error borrando elaborado: ' . $e->getMessage();
            error_log($errMsg . "\n" . $e->getTraceAsString());
            if (isset($this->logger) && method_exists($this->logger, 'error')) {
                $this->logger->error('Error borrando elaborado', [
                    'exception' => $e,
                    'elaborado' => $elaboradoId,
                    'user' => $viewerId ?? null
                ]);
            }

            $debug = defined('APP_DEBUG') && APP_DEBUG === true;
            if ($debug) {
                // Mostrar información útil en debug (acortar traza para la URL)
                $traceSnippet = mb_substr($e->getTraceAsString(), 0, 1000);
                $redirMsg = 'Error borrando elaborado: ' . $e->getMessage() . ' | Trace: ' . $traceSnippet;
            } else {
                $redirMsg = 'Error borrando elaborado. Contacte con el administrador.';
            }

            // Evitar URLs excesivamente largas
            if (mb_strlen($redirMsg) > 1000) {
                $redirMsg = mb_substr($redirMsg, 0, 1000) . '...';
            }

            Redirect::to('/elaborados.php?error=' . urlencode($redirMsg));
            return;
        }
    }


    /**
     * renderEditWithError
     *
     * Auxiliar para re-renderizar el formulario de edición/creación con un mensaje de error.
     *
     * @param int|null $id
     * @param string $msg
     * @return void
     */
    private function renderEditWithError($idOrMsg, string $msg = ''): void
    {
        // Compatibilidad: si se llamó con un solo argumento string, ajustarlo
        if (is_string($idOrMsg) && $msg === '') {
            $msg = $idOrMsg;
            $id = null;
        } else {
            $id = is_null($idOrMsg) ? null : (int)$idOrMsg;
        }

        error_log("renderEditWithError: $msg");
        // Cargar datos auxiliares como en renderEdit
        $elaborado = null;
        if ($id !== null && $id > 0) {
            $elaborado = $this->model->findById($id);
        }
        $ingredientes = $this->ingredienteModel->allIngredientes($this->pdo);
        $csrf = Csrf::generateToken();
        $switchBlockedMessage = $msg;
        require __DIR__ . '/../../public/views/elaborados_edit_view.php';
    }
}
