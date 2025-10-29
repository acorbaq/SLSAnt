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
        
        $this->renderList();
    }

    private function renderList(): void
    {
        //$elaborados = $this->lotesModel->getAll();
        $canModify = $this->canModify();

        //$tiposElaboracion = $this->lotesModel->getTipos();

        $debug = defined('APP_DEBUG') && APP_DEBUG === true;

        // Incluir la vista de listado. Ruta relativa desde src/Controllers a public/views.
        require __DIR__ . '/../../public/views/lotes_view.php';
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