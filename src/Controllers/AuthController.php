<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Csrf;
use App\Utils\Auth;
use App\Models\User;
use PDO;

/**
 * AuthController
 *
 * Controlador responsable del flujo de autenticación (login).
 *
 * Responsabilidades principales:
 *  - Mostrar la vista de login (GET).
 *  - Procesar el formulario de login (POST): validar CSRF, validar campos,
 *    delegar autenticación a App\Utils\Auth, actualizar last_login y redirigir.
 *  - Mantener la lógica de presentación fuera de la vista; la vista sólo renderiza variables.
 *
 * Diseño y decisiones:
 *  - Toda la lógica que accede a la BD se delega a helpers/Model o se realiza con consultas
 *    preparadas para evitar duplicación y mejorar testabilidad.
 *  - El controlador no devuelve respuestas HTTP complejas: redirige o incluye la view.
 *  - Se garantiza inicialización de sesión/CSRF al inicio del request.
 *
 * Uso:
 *  - Instanciar con una conexión PDO: $c = new AuthController($pdo); $c->handleRequest();
 *
 * @package App\Controllers
 */
class AuthController
{
    private PDO $pdo;

    /**
     * Constructor.
     *
     * @param PDO $pdo Conexión PDO a la base de datos (SQLite)
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Punto de entrada del controlador.
     *
     * Decide la acción a ejecutar en base al método HTTP:
     *  - GET  => showForm()
     *  - POST => handleLogin()
     *
     * También se encarga de asegurar que las utilidades de sesión y CSRF estén inicializadas.
     *
     * @return void
     */
    public function handleRequest(): void
    {
        // Inicializar CSRF y sesión antes de cualquier operación que use $_SESSION.
        Csrf::init();
        Auth::initSession();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Procesar intento de login enviado por formulario.
            $this->handleLogin();
        } else {
            // Mostrar formulario de login.
            $this->showForm();
        }
    }

    /**
     * Muestra la vista de login.
     *
     * Variables expuestas a la vista:
     *  - $errors: array con mensajes de error (si los hay).
     *  - $oldUser: valor previo del campo "user" para mantener la entrada en caso de error.
     *  - $csrf: token CSRF para incluir en el formulario.
     *
     * Reglas:
     *  - No realiza lógica de negocio; sólo prepara datos y requiere la plantilla.
     *
     * @param array $errors Mensajes de error a mostrar en la vista.
     * @param string $oldUser Valor previo del campo usuario.
     * @return void
     */
    private function showForm(array $errors = [], string $oldUser = ''): void
    {
        // Generar o recuperar token CSRF asociado a la sesión.
        $csrf = Csrf::generateToken();

        // Incluir la vista (la vista debe usar htmlentities al imprimir variables).
        // Se usa require en lugar de render engine simple para mantener dependencia mínima.
        require __DIR__ . '/../../public/views/login_view.php';
    }

    /**
     * Procesa el POST de login.
     *
     * Flujo completo:
     *  1) Validar token CSRF. Si inválido -> volver a mostrar form con error.
     *  2) Normalizar y validar presencia de user y password.
     *  3) Delegar autenticación a Auth::attempt($pdo, $user, $pass).
     *     - Auth::attempt realiza la búsqueda, comprueba is_active y verifica password.
     *  4) Si Auth::attempt devuelve null -> credenciales inválidas -> mostrar form con error.
     *  5) Si autenticación ok -> actualizar last_login (modelo o consulta) y redirigir.
     *
     * Notas de seguridad:
     *  - Usar CSRF para mitigar peticiones cross-site.
     *  - Auth::attempt realiza session_regenerate_id() al autenticar.
     *  - Todas las entradas se tratan/escapan en la vista al imprimir.
     *
     * @return void
     */
    private function handleLogin(): void
    {
        $errors = [];

        // 1) Validación CSRF
        $token = $_POST['csrf'] ?? null;
        if (!Csrf::validateToken($token)) {
            // Token inválido: no procesar credenciales ni realizar consultas.
            $errors[] = 'Token CSRF inválido.';
            $this->showForm($errors, (string)($_POST['user'] ?? ''));
            return;
        }

        // 2) Normalizar inputs
        $user = trim((string)($_POST['user'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');

        // Validaciones simples de presencia
        if ($user === '' || $pass === '') {
            $errors[] = 'Usuario y contraseña son requeridos.';
            $this->showForm($errors, $user);
            return;
        }

        // 3) Intento de autenticación delegando a Auth helper
        // Auth::attempt() devuelve array del usuario sin password o null si falla.
        $u = Auth::attempt($this->pdo, $user, $pass);
        if ($u === null) {
            // Credenciales inválidas o cuenta inactiva: no especificar cuál por seguridad.
            $errors[] = 'Credenciales incorrectas o usuario inactivo.';
            $this->showForm($errors, $user);
            return;
        }

        // 4) Autenticación correcta: actualizar last_login y redirigir
        // Se delega la actualización a un modelo (User::updateLastLogin) para mantener SRP.
        // Si no existe el modelo, se puede ejecutar la consulta directa aquí.
        User::updateLastLogin($this->pdo, (int)$u['id']);

        // 5) Redirigir al usuario (ajustar destino según la app)
        header('Location: /');
        exit;
    }
}