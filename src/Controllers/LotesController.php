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
use PDO;

final class LotesController
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
            if (isset($_GET['create'])) {
                // tomar el id del get y enviarlo al render create
                $elaboradoId = $_GET['id'] ?? null;
                $this->renderCreate($elaboradoId);
            } else {
                // Si get es otro tipo cargar render list
                $this->renderList();
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Si POST es crear procesar formulario
            if (isset($_POST['action']) && $_POST['action'] === 'create_lote') {
                $this->createLote($_POST);
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

    // Procesar el formulario de cración de lote
    // El formulario recibe los datos, los valida, crea los lotes y redirige a la vista de impresión del lote creado.
    private function createLote(array $postData): void
    {
        // Validar CSRF
        if (!Csrf::validateToken($postData['csrf'] ?? '')) {
            http_response_code(403); // Forbidden
            echo "Token CSRF inválido. llego aqui por error";
            exit;
        }
        $canModify = $this->canModify();
        if (!$canModify) {
            http_response_code(403); // Forbidden
            echo "No tienes permiso para crear lotes.";
            exit;
        }
        // Aquí se procesarían los datos del formulario, se validarían y se crearían los lotes en la base de datos.
        // 0) Crear un array $data con los datos necesarios para crear el lote.
        // Información del lote principal desde $postData [elaboracion_id] => 1006 [parent_lote_id] =>  [fecha_produccion] => 2025-11-01 [fecha_caducidad] => 2025-11-08 [peso_total] => 15 [unidad_peso] => kg [temp_inicio] =>  [temp_final] => 
        // Adaptar los datos a la tabla lotes elaboracion_id, parent_lote_id, fecha_produccion, fecha_caducidad, peso_total, unidad_peso, temp_inicio, temp_final
        // 1) obtenemos el ultimo lote creado para ese elaborado
        $ultimoLote = $this->lotesModel->obtenerLotePorIdElaboracion((int)$postData['elaboracion_id']);
        $numeroUltimoLote = $ultimoLote ? (int)$ultimoLote['numero_lote'] : 0;
        // 2) generamos el nuevo número de lote
        $nuevoNumeroLote = $this->lotesModel->generarNumeroLote($numeroUltimoLote, (int)$postData['elaboracion_id']);
        $loteData = [
            'elaboracion_id' => (int)$postData['elaboracion_id'],
            'parent_lote_id' => $postData['parent_lote_id'] !== '' ? (string)$postData['parent_lote_id'] : null,
            'numero_lote' => $nuevoNumeroLote,
            'fecha_produccion' => $postData['fecha_produccion'] ?? null,
            'fecha_caducidad' => $postData['fecha_caducidad'] ?? null,
            'peso_total' => isset($postData['peso_total']) ? (float)$postData['peso_total'] : null,
            'unidad_peso' => $postData['unidad_peso'] ?? null,
            'temp_inicio' => isset($postData['temp_inicio']) && $postData['temp_inicio'] !== '' ? (float)$postData['temp_inicio'] : null,
            'temp_final' => isset($postData['temp_final']) && $postData['temp_final'] !== '' ? (float)$postData['temp_final'] : null,
        ];  
        // Informa de los ingredientes desde $postData['ingredientes'] que puede venir con distintos nombres de campo
        $ingredientes = $postData['ingredientes'] ?? [];
        // Construir el array de ingredientes adaptado a la tabla lote_ingredientes: ingrediente_id, peso, lote, fecha_caducidad, unidad_cantidad, producto_comercial_id.
        $ingredientesData = [];
        foreach ($ingredientes as $ingrediente) {
            // Aceptar varios nombres que se usan en el formulario/post
            $id = $ingrediente['ingrediente_id'] ?? $ingrediente['id_ingrediente'] ?? null;
            $pcId = $ingrediente['producto_comercial_id'] ?? $ingrediente['pc_id'] ?? null;
            $lote = $ingrediente['lote_ingredientes'] ?? $ingrediente['lote_ingrediente'] ?? $ingrediente['lote'] ?? null;
            $fechaCad = $ingrediente['fecha_caducidad'] ?? $ingrediente['fecha_caducidad_ingrediente'] ?? null;
            $unidad = $ingrediente['unidad_cantidad'] ?? $ingrediente['unidad'] ?? null;

            $ingredientesData[] = [
                'ingrediente_id' => $id !== null && $id !== '' ? (int)$id : null,
                'peso' => isset($ingrediente['peso']) && $ingrediente['peso'] !== '' ? (float)$ingrediente['peso'] : null,
                'porcentaje_origen' => isset($ingrediente['peso']) && isset($postData['peso_total']) ? (float)$ingrediente['peso']/(float)$postData['peso_total'] : null,
                'lote' => $lote !== '' ? $lote : $nuevoNumeroLote,
                'fecha_caducidad' => $fechaCad !== '' ? $fechaCad : null,
                'unidad_cantidad' => $unidad ?? null,
                'producto_comercial_id' => $pcId !== null && $pcId !== '' ? (int)$pcId : null,
            ];
        }
        $data = [
            'lote' => $loteData,
            'ingredientes' => $ingredientesData,
        ];
        // 1) Añadir datos del lote a la tabla lotes
        // 2) Añadir datos de los ingredientes del lote a la tabla lote_ingredientes
        // 3) Obtener el ID del lote creado para redirigir a la impresión
        //try {
            $this->pdo->beginTransaction();
            $createdLoteId = $this->lotesModel->crearLote($data);
            $this->pdo->commit();
            // Tabla lotes: id, elaboracion_id, numero_lote, fecha_producción, fecha_caducidad, peso_total, unidad_peso, temp_inicio, temp_fin, parent_lote_id, is_derivado, created_at
            Redirect::to("/lotes/print?id={$createdLoteId}");
        /*} catch (\Exception $e) {
            $this->pdo->rollBack();
            http_response_code(500); // Internal Server Error
            echo "Error al crear el lote: " . $e->getMessage();
            exit;
        }*/
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