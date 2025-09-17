<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Csrf;
use App\Utils\Auth;
use App\Utils\Access;
use App\Utils\Redirect;
use App\Models\Ingrediente;
use PDO;

/**
 * IngredienteController
 *
 * Controlador responsable de la sección "Ingredientes" / "Alérgenos".
 *
 * Responsabilidades:
 * - Rendir la lista pública de ingredientes (GET).
 * - Mostrar formulario de edición/creación (?crear, ?modificar&id=...) (GET) — solo para roles autorizados.
 * - Procesar guardado/actualización y eliminación (POST) validando CSRF y permisos.
 *
 * Reglas de autorización (canModify):
 * - Solo roles con prioridad 'calidad' o superior (admin, gestor, calidad) pueden crear/editar/eliminar.
 *
 * Flujo general:
 * - handleRequest() discrimina entre GET/POST y rutea a renderList/renderEdit/handlePost.
 * - renderEdit valida permisos antes de cargar datos sensibles; si no autorizado redirige al listado.
 * - handlePost valida token CSRF, permisos y ejecuta create/update/delete a través del modelo Ingrediente.
 *
 * Notas de seguridad/operación:
 * - Las vistas pueden ocultar botones pero la verificación server-side se hace aquí.
 * - Csrf::init() y Auth::initSession() se llaman al inicio para garantizar estado correcto.
 * - Redirecciones se realizan mediante Redirect::to() para terminar la ejecución inmediatamente.
 */
class IngredienteController
{
    private PDO $pdo;
    private Ingrediente $model;

    /**
     * Constructor.
     *
     * @param PDO $pdo Conexión a la base de datos inyectada.
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->model = new Ingrediente($pdo);
    }

    /**
     * handleRequest
     *
     * Punto de entrada que rutea la petición HTTP.
     *
     * Comportamiento:
     * - Inicializa CSRF y sesión.
     * - Si es GET:
     *     * ?crear -> abrir formulario vacío (renderEdit(null))
     *     * ?modificar&id=NN -> abrir formulario con datos (renderEdit(id))
     *     * otherwise -> listado (renderList)
     * - Si es POST -> handlePost() para acciones (save/delete).
     *
     * @return void
     */
    public function handleRequest(): void
    {
        // Inicializar utilidades de seguridad (genera token cuando haga falta)
        Csrf::init();
        Auth::initSession();

        // GET: priorizar solicitud del editor (?crear / ?modificar)
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if (isset($_GET['crear'])) {
                $this->renderEdit(null);
                return;
            }
            if (isset($_GET['modificar']) && isset($_GET['id'])) {
                $this->renderEdit((int)$_GET['id']);
                return;
            }
        }

        // POST: procesar acciones seguras (save/delete)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }

        // Default: lista pública de ingredientes
        $this->renderList();
    }

    /**
     * canModify
     *
     * Determina si el usuario actual tiene permiso para modificar la entidad.
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
     * renderList
     *
     * Carga los datos y renderiza la vista de listado pública.
     *
     * Pasos:
     * - Obtener todos los ingredientes con sus alérgenos desde el modelo.
     * - Obtener lista fija de alérgenos para la UI.
     * - Determinar si el usuario puede modificar (para mostrar botones).
     * - Incluir la vista que usará las variables: $ingredientes, $alergenos, $canModify, $debug.
     *
     * @return void
     */
    private function renderList(): void
    {
        // Datos para la vista
        $ingredientes = $this->model->allIngredientes($this->pdo);
        $alergenos = $this->model->allAlergenos($this->pdo);
        $canModify = $this->canModify();
        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        // Renderizar vista (la vista debe escapar datos y usar $canModify para botones)
        require __DIR__ . '/../../public/views/ingredientes_view.php';
    }

    /**
     * renderEdit
     *
     * Muestra el formulario de edición/creación.
     *
     * Reglas:
     * - Solo usuarios autorizados (canModify) pueden acceder; si no => redirección al listado.
     * - Si $id == null => formulario para crear (vacío).
     * - Si $id > 0 => cargar ingrediente; si no existe => redirigir.
     *
     * Variables pasadas a la vista:
     * - $ingrediente (array|null), $alergenos (lista), $csrf, $debug
     *
     * @param int|null $id Id del ingrediente o null para crear
     * @return void
     */
    private function renderEdit(?int $id): void
    {
        // Seguridad: bloquear el editor si no tiene permisos
        if (!$this->canModify()) {
            Redirect::to('/ingredientes.php');
        }

        $ingrediente = null;
        if ($id !== null && $id > 0) {
            // Cargar datos del ingrediente con sus alérgenos; redirigir si no existe
            $ingrediente = $this->model->findById($this->pdo, $id);
            if ($ingrediente === null) {
                Redirect::to('/ingredientes.php');
            }
        }

        // Datos auxiliares para el formulario
        $alergenos = $this->model->allAlergenos($this->pdo);
        $debug = defined('APP_DEBUG') && APP_DEBUG === true;
        $csrf = Csrf::generateToken();

        // Renderizar vista de edición
        require __DIR__ . '/../../public/views/ingredientes_edit_view.php';
    }

    /**
     * handlePost
     *
     * Procesa acciones POST seguras:
     * - Validar token CSRF.
     * - action=save -> crear o actualizar ingrediente (según id).
     * - action=delete -> eliminar ingrediente.
     *
     * Comportamiento detallado:
     * - En cada acción se re-valida permiso con canModify() antes de mutar.
     * - Para save: recoge nombre, indicaciones y array de ids de alergenos; delega al modelo.
     * - Para delete: borra el ingrediente y relaciones pivot.
     *
     * @return void
     */
    private function handlePost(): void
    {
        // Validar CSRF token siempre
        $token = (string)($_POST['csrf'] ?? '');
        if (!Csrf::validateToken($token)) {
            http_response_code(400);
            echo 'CSRF inválido';
            exit;
        }

        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save') {
            // Seguridad: solo usuarios con permiso pueden modificar/crear
            if (!$this->canModify()) {
                Redirect::to('/ingredientes.php');
            }

            $id = (int)($_POST['id'] ?? 0);
            $nombre = (string)($_POST['nombre'] ?? '');
            $indic = (string)($_POST['indicaciones'] ?? '');
            $alergIds = $_POST['alergenos'] ?? [];
            // Normalizar: asegurarse de que sea array de ids
            if (!is_array($alergIds)) {
                $alergIds = [];
            }

            if ($id > 0) {
                // Actualizar: modelo se encarga de escapar/hashear si procede
                $this->model->updateIngrediente($this->pdo, $id, ['nombre' => $nombre, 'indicaciones' => $indic]);
                $this->model->assignAlergenosByIds($this->pdo, $id, $alergIds);
            } else {
                // Crear nuevo ingrediente y asignar alérgenos
                $newId = $this->model->createIngrediente($this->pdo, $nombre, $indic);
                $this->model->assignAlergenosByIds($this->pdo, $newId, $alergIds);
            }

            // Redirigir al listado tras el cambio
            Redirect::to('/ingredientes.php');
        }

        if ($action === 'delete') {
            // Seguridad: solo roles autorizados pueden borrar
            if (!$this->canModify()) {
                Redirect::to('/ingredientes.php');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $this->model->deleteIngrediente($this->pdo, $id);
            }
            Redirect::to('/ingredientes.php');
        }

        // Acción desconocida -> devolver al listado
        Redirect::to('/ingredientes.php');
    }
}