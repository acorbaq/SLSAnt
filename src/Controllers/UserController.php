<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Csrf;
use App\Utils\Auth;
use App\Utils\Access;
use App\Utils\Redirect;
use App\Models\User;
use PDO;

/**
 * UserController
 *
 * PHPDoc (responsibilities / contrato):
 * - Gestiona la interacción HTTP para el menú "Usuarios".
 * - Entradas esperadas:
 *     * GET  -> renderiza la vista de listado/gestión de usuarios.
 *     * POST -> ejecuta acciones: create, delete, assign_roles (todas via POST).
 * - Requisitos previos:
 *     * Auth::initSession() (la sesión debe estar inicializada antes de llamar).
 *     * $this->pdo (instancia PDO) configurada y usable.
 * - Seguridad:
 *     * Valida CSRF en POST.
 *     * Valida permisos server-side (solo Admin para acciones destructivas).
 *
 * Flujo general (alto nivel):
 * 1) handleRequest() se llama desde el front controller (public/usuarios.php).
 * 2) Inicializa Csrf y sesión.
 * 3) Si método es POST => handlePost():
 *      - valida token CSRF
 *      - determina acción (create|delete|assign_roles)
 *      - valida rol del actor (Admin requerido para mutaciones)
 *      - delega al modelo User para persistencia
 *      - redirige a /usuarios.php
 *    Si método es GET => renderList():
 *      - obtiene listado de usuarios + roles (User model / Access)
 *      - determina rol principal del viewer (para ajustar UI: Admin vs Gestor)
 *      - define $debug y requiere la vista que renderiza la tabla
 *
 * Notas operativas:
 * - La vista no debe confiar en la UI; cada acción está verificada aquí.
 * - Evitar imprimir datos sensibles (password) en la vista.
 * - Los métodos del modelo User deben coincidir con las llamadas aquí (firma consistente).
 */
class UserController
{
    private PDO $pdo;
    private User $userModel;

    /**
     * Constructor
     *
     * @param PDO $pdo Conexión a la base de datos (inyectada desde bootstrap).
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->userModel = new User($pdo);
    }

    /**
     * handleRequest
     *
     * Punto de entrada para manejar la petición HTTP.
     * - Inicializa utilidades (CSRF, sesión).
     * - Rutea entre GET y POST.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        // Asegurar que el sistema CSRF y la sesión están listos.
        Csrf::init();
        Auth::initSession();

        // Si es POST procesar acción, si no renderizar listado.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost();
            return;
        }

        $this->renderList();
    }

    /**
     * renderList
     *
     * Obtiene los datos necesarios y carga la vista.
     *
     * Pasos:
     * 1) Obtener usuarios mediante el modelo (todos los campos públicos + roles).
     * 2) Obtener lista de roles a mostrar en la UI. Aquí usamos Access::definedRoles()
     *    para garantizar que la UI solo muestra roles reconocidos por la aplicación.
     * 3) Determinar el rol principal del usuario que visualiza (viewer) consultando la BD.
     * 4) Preparar bandera debug y requerir la vista (que usará $users, $roles, $viewerRole, $debug).
     *
     * @return void
     */
    private function renderList(): void
    {
        // Obtener usuarios (el modelo debe devolver array con 'roles' => array)
        $users = $this->userModel->allUsers($this->pdo);

        // Obtener roles definidos por la app (no usar directamente roles tablas para la UI)
        $roles = Access::definedRoles();

        // Obtener el usuario que visualiza la página y sus roles (desde BD)
        $viewer = Auth::user($this->pdo);
        $viewerRoles = $viewer ? Access::getUserRoles($this->pdo, (int)$viewer['id']) : [];
        $viewerRole = Access::highestRole($viewerRoles);

        // Flag de debug (pasado a la vista)
        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        // Renderizar la vista: la vista espera las variables definidas en este scope.
        require __DIR__ . '/../../public/views/usuarios_view.php';
    }

    /**
     * handlePost
     *
     * Procesa acciones enviadas por POST:
     * - valida CSRF (obligatorio)
     * - identifica la acción mediante $_POST['action']
     * - valida permisos del usuario (solo Admin puede crear/eliminar/asignar)
     * - delega al modelo User las operaciones de persistencia
     * - redirige a /usuarios.php al finalizar
     *
     * Excepciones / errores:
     * - Si CSRF inválido -> 400 y exit
     * - Si usuario no autorizado -> redirección a /usuarios.php (silenciosa)
     *
     * @return void
     */
    private function handlePost(): void
    {
        // 1) Validar token CSRF
        $token = (string)($_POST['csrf'] ?? '');
        if (!Csrf::validateToken($token)) {
            http_response_code(400);
            echo 'CSRF inválido';
            exit;
        }

        // 2) Determinar la acción solicitada
        $action = (string)($_POST['action'] ?? '');

        // 3) Comprobar el rol del actor (viewer)
        $viewer = Auth::user($this->pdo);
        $viewerRoles = $viewer ? Access::getUserRoles($this->pdo, (int)$viewer['id']) : [];
        $viewerRole = Access::highestRole($viewerRoles);

        // --- CREATE (solo Admin) ---
        if ($action === 'create') {
            // Comprobación server-side: solo admin puede crear usuarios
            if ($viewerRole !== Access::ROLE_ADMIN) {
                Redirect::to('/usuarios.php');
            }

            $username = (string)($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            // Nota: validar inputs y password strength aquí.
            // El modelo debería encargarse de hashear la contraseña.
            // IMPORTANTE: asegurar firma correcta del método createUser.
            // Ejemplo correcto: $this->userModel->createUser($username, $password);
            $this->userModel->createUser($this->pdo, $username, $password);

            Redirect::to('/usuarios.php');
        }

        // --- DELETE (solo Admin) ---
        if ($action === 'delete') {
            if ($viewerRole !== Access::ROLE_ADMIN) {
                Redirect::to('/usuarios.php');
            }
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                // El modelo debe eliminar relaciones y el usuario
                $this->userModel->deleteUser($this->pdo, $id);
            }
            Redirect::to('/usuarios.php');
        }

        // --- ASSIGN ROLES (solo Admin) ---
        if ($action === 'assign_roles') {
            if ($viewerRole !== Access::ROLE_ADMIN) {
                Redirect::to('/usuarios.php');
            }
            $id = (int)($_POST['id'] ?? 0);
            $roles = $_POST['roles'] ?? []; // array de nombres de role (UI envía nombres)

            if ($id > 0 && is_array($roles)) {
                // Normalizar roles y delegar al modelo
                $this->userModel->assignRolesByNames($id, $roles);
            }
            Redirect::to('/usuarios.php');
        }

        // Acción desconocida: redirigir al listado
        Redirect::to('/usuarios.php');
    }
}