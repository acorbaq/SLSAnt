<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Auth;
use App\Utils\Access;
use App\Models\Elaborado;
use App\Models\Ingrediente;

use PDO;
use App\Utils\Csrf;
use App\Utils\Redirect;

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
    private $user;
    private bool $debug;

    /**
     * @param PDO $pdo
     * @param mixed|null $user Información del usuario actual (opcional)
     * @param bool $debug Flag para mostrar info debug en vistas
     */
    public function __construct(PDO $pdo, $user = null, bool $debug = false)
    {
        $this->pdo = $pdo;
        $this->model = new Elaborado($pdo);
        $this->ingredienteModel = new Ingrediente($pdo);
        $this->user = $user;
        $this->debug = $debug;
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

        // Variables que la vista espera. Si el front controller definió $titleSection/head,
        // esas partes ya se han incluido; aquí sólo requerimos la vista de contenido.
        $debug = $this->debug;

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
        if ($id !== null && $id > 0) {
            // Cargar datos del elaborado; redirigir si no existe
            $elaborado = $this->model->findById($id);
            $ingredienteElaborado = $this->model->getIngredienteElaborado($id);
            if ($elaborado === null) { 
                Redirect::to('/elaborados.php');
            }
        }
        // Datos auxiliares para el formulario
        $ingredientes = $this->ingredienteModel->allIngredientes($this->pdo);
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
     * renderEditWithError
     *
     * Auxiliar para re-renderizar el formulario de edición/creación con un mensaje de error.
     *
     * @param int|null $id
     * @param string $msg
     * @return void
     */
    private function renderEditWithError(?int $id, string $msg): void
    {
        // Cargar datos auxiliares como en renderEdit
        $elaborado = null;
        if ($id !== null && $id > 0) {
            $elaborado = $this->model->findById($id);
        }
        $ingredientes = $this->ingredienteModel->allIngredientes($this->pdo);
        $debug = $this->debug;
        $csrf = Csrf::generateToken();
        $switchBlockedMessage = $msg;
        require __DIR__ . '/../../public/views/elaborados_edit_view.php';
    }

}