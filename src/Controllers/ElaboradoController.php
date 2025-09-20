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
}