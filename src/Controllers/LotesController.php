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

final class LotesController
{
    private PDO $pdo;
    private Elaborado $elaboradoModel;
    private Ingrediente $ingredienteModel;
    private Unit $unitModel;
    //private Lotes $lotesModel;

    function __construct(private PDO $db)
    {
        $this->pdo = $db;
        $this->elaboradoModel = new Elaborado($this->pdo);
        $this->ingredienteModel = new Ingrediente($this->pdo);
        $this->unitModel = new Unit($this->pdo);
        //$this->lotesModel = new Lotes($this->pdo);
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            // Si Get es crear cargar vista de creación
            if (isset($_GET['create'])) {
                // tomar el id del get y enviarlo al render create
                $elaboradoId = $_GET['id'] ?? null;
                $this->renderCreate($elaboradoId);
            } else {
                // Si get es otro tipo cargar render list
                $this->renderList();
            }
        } else {
            // Otros métodos HTTP no soportados por ahora
            echo '<pre>';
            print_r($_POST);
            echo '</pre>';
            http_response_code(405); // Method Not Allowed
            echo "Método no permitido.";
            exit;
        }
    }

    private function renderList(): void
    {
        // Obtenemos toda la información de los elaborados para poder mostrarlos en la vista
        $elaborados = $this->elaboradoModel->getAll();
        $canModify = $this->canModify();

        $tiposElaboracion = $this->elaboradoModel->getTipos();

        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        // Incluir la vista de listado. Ruta relativa desde src/Controllers a public/views.
        require __DIR__ . '/../../public/views/lotes_view.php';
    }

    // Renderizar el formualario de creación de lote. Recibe los datos de id del elaborado. Debe obtener información de las tablas elaborados, elaborados_ingredientes, unidades, ingredientes, ingredientes alergenos y alergenos.
    private function renderCreate($elaboradoId): void
    {
        // Validar que el ID del elaborado es válido
        if ($elaboradoId === null || !is_numeric($elaboradoId)) {
            http_response_code(400); // Bad Request
            echo "ID de elaborado inválido.";
            exit;
        }

        // Obtener la información del elaborado
        $elaborado = $this->elaboradoModel->findById((int)$elaboradoId);
        if (!$elaborado) {
            http_response_code(404); // Not Found
            echo "Elaborado no encontrado.";
            exit;
        }
        $ingredientesElaborado = $this->elaboradoModel->getIngredienteElaborado((int)$elaboradoId);

        // Para cada id_ingredinte obtener su información base de la tabla ingedientes y unidades y la asociación de alérgenos
        $ingredientes = $this->ingredienteModel->allIngredientes($this->pdo);
        $alergenos = $this->ingredienteModel->allAlergenos($this->pdo);
        $unidades = $this->unitModel->getAllUnits();

        $canModify = $this->canModify();
        if (!$canModify) {
            http_response_code(403); // Forbidden
            echo "No tienes permiso para crear lotes.";
            exit;
        }

        $csrfToken = Csrf::generateToken();

        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        // Incluir la vista de creación. Ruta relativa desde src/Controllers a public/views.
        require __DIR__ . '/../../public/views/lotes_create_view.php';
    }

    // Los lotes pueden ser creados por todos los usuarios y solo modificado por el 
    // administrador y el gestor. Esta sección puede ser vista por todos los roles autenticados.
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

        return in_array($principal, ['admin', 'gestor'], true);
    }
}
?>