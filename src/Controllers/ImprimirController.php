<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Utils\Csrf;
use App\Utils\Auth;
use App\Utils\Access;
use App\Utils\Redirect;
use App\Models\Elaborado;
use App\Models\Ingrediente;
use App\Models\Lotes;
use App\Models\Unit;
use App\Models\Imprimir;
use PDO;

final class ImprimirController
{
    private PDO $pdo;
    private Elaborado $elaboradoModel;
    private Ingrediente $ingredienteModel;
    private Unit $unitModel;
    private Lotes $lotesModel;

    function __construct(private PDO $db)
    {
        $this->pdo = $db;
        $this->elaboradoModel = new Elaborado($this->pdo);
        $this->ingredienteModel = new Ingrediente($this->pdo);
        $this->unitModel = new Unit($this->pdo);
        $this->lotesModel = new Lotes($this->pdo);
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'GET') {
            // Si Get es crear cargar vista de creación
            if (isset($_GET['view'])) {
                // tomar el id del get y enviarlo al render create
                $loteId = $_GET['id'] ?? null;
                $this->renderView($loteId);
            } else {
                // Si get es otro tipo cargar render list
                $this->renderList();
            }
        } elseif ($method === 'POST') {
            // Manejamos el post prindipal de impresión de Lotes
            $action = $_POST['action'] ?? null;
            $loteId = $_POST['lote_id'] ?? null;
            if ($action !== 'imprimirLote') {
                $cantidad = (int)($_POST['cantidad'] ?? 1);
                $this->imprimirLote($loteId, $cantidad);
            }
            $this->renderView($loteId);
        } else {
            http_response_code(405);
            echo "Método no permitido.";
            exit;
        }
    }
    
    private function renderList(): void
    {
        // obtenemos los datos de los lotes actuales
        $lotes = $this->lotesModel->getAllLotes();

        // Construir array asociativo de elaborados indexado por id_elaborado
        $elaboradosLotes = [];
        foreach ($lotes as $lote) {
            $eid = (int)($lote['elaboracion_id'] ?? 0);
            if ($eid <= 0) continue;
            if (!isset($elaboradosLotes[$eid])) {
                $elaborado = $this->elaboradoModel->findById($eid);
                if ($elaborado) {
                    $elaboradosLotes[$eid] = $elaborado;
                } else {
                    // marcar con estructura básica si no se encuentra
                    $elaboradosLotes[$eid] = [
                        'id_elaborado' => $eid,
                        'nombre' => 'Elaborado #' . $eid,
                        'tipo' => null,
                    ];
                }
            }
        }

        // Obtener tipos de elaboración para categorizar
        $tiposElaboracion = $this->elaboradoModel->getTipos();

        // Permisos para acciones en la vista
        $canModify = $this->canModify();

        // incluimos la vista y le pasamos los datos
        require_once __DIR__ . '/../../public/views/imprimir_view.php';
    }

    private function renderView($loteId): void
    {
        $lote = null;
        if ($loteId !== null) {
            $lote = $this->lotesModel->getLoteById((int)$loteId);
        }
        if (!$lote) {
            http_response_code(404);
            echo "Lote no encontrado.";
            exit;
        }
        // obtener los ingredientes del lote
        $loteIngredientes = $this->lotesModel->getIngredientesByLoteId((int)$lote['id']);
        // obtener información del elaborado
        $elaborado = $this->elaboradoModel->findById((int)$lote['elaboracion_id']);
        // Obtener tipos de elaboración para categorizar
        $tiposElaborado = $this->elaboradoModel->getTipos();
        // obtener información relevante de ingredientes y alergenos para $loteIngredientes
        foreach ($loteIngredientes as &$li) {
            $ingrediente = $this->ingredienteModel->findById($this->pdo,(int)$li['ingrediente_id']);
            if ($ingrediente) {
                $li['ingrediente'] = $ingrediente;
                // obtener alergenos
                $alergenos = $this->ingredienteModel->obtenerAlergenosPorIngredienteId($this->pdo,(int)$ingrediente['id_ingrediente']);
                // si el array de alergenos está vacío, asignar un array vacío
                $li['alergenos'] = $alergenos ?: [];
            }
        }
        // incluimos la vista de detalle del lote
        require_once __DIR__ . '/../../public/views/imprimir_edit_view.php';
    }

    // Permisos (mismo criterio que en LotesController)
    private function canModify(): bool
    {
        $viewer = Auth::user($this->pdo);
        if (!$viewer) {
            return false;
        }
        $roles = Access::getUserRoles($this->pdo, (int)$viewer['id']);
        $principal = Access::highestRole($roles);
        return in_array($principal, ['admin', 'gestor'], true);
    }
}