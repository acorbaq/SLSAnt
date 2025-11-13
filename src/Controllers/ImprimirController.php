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
use App\Services\TraductorEZPL;
use App\Utils\Printer as PrinterUtil;
use App\Utils\CsrfResponse;

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
        Csrf::init();
        Auth::initSession();
    
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET'; 

        if ($method === 'POST') {
            CsrfResponse::validateOrDie($_POST['csrf'] ?? null, 'json');
            
            // Continuar con la lógica...
            $action = $_POST['action'] ?? null;
            $loteId = $_POST['lote_id'] ?? null;
            
            if ($action === 'imprimirLote') {
                // ✅ Validar entrada
                if (!is_numeric($loteId) || (int)$loteId <= 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID de lote inválido.'
                    ]);
                    exit;
                }
                
                $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 1]
                ]);
                
                $this->imprimirLote((int)$loteId, $cantidad);
            } elseif ($action === 'imprimirIngrediente') {
                // ✅ Validar entrada
                if (!is_numeric($loteId) || (int)$loteId <= 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID de lote inválido.'
                    ]);
                    exit;
                }
                
                $ingredienteId = $_POST['ingrediente_id'] ?? null;
                if (!is_numeric($ingredienteId) || (int)$ingredienteId <= 0) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => 'ID de ingrediente inválido.'
                    ]);
                    exit;
                }
                
                $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 100, 'default' => 1]
                ]);
                
                $this->imprimirIngrediente((int)$loteId, (int)$ingredienteId, $cantidad);
            }
            return;
        }
        
        if ($method === 'GET') {
            if (isset($_GET['view'])) {
                $loteId = $_GET['id'] ?? null;
                $this->renderView($loteId);
            } else {
                $this->renderList();
            }
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
        $csrfToken = Csrf::generateToken();
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

    // Lógica de impresión del lote
    private function imprimirLote(int $loteId, int $cantidad): void
    {
        // ✅ Validar permisos
        if (!$this->canModify()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tiene permisos para imprimir etiquetas.'
            ]);
            exit;
        }

        // preparamos una variable de respuesta
        $rsp = [
            'success' => false,
            'message' => '',
        ];
        // 1. obtenemos el lote completo y sus ingredientes
        $lote = $this->lotesModel->getLoteById((int)$loteId);
        if (!$lote) {
            http_response_code(404);
            $rsp['message'] = 'Lote no encontrado.';
            echo json_encode($rsp);
            exit;
        }
        $loteIngredientes = $this->lotesModel->getIngredientesByLoteId((int)$loteId);
        if (empty($loteIngredientes)) {
            http_response_code(400);
            $rsp['message'] = 'El lote no tiene ingredientes para imprimir.';
            echo json_encode($rsp);
            exit;
        }
        $elaboradoLote = $this->elaboradoModel->findById((int)$lote['elaboracion_id']);
        if (!$elaboradoLote) {
            http_response_code(404);
            $rsp['message'] = 'Elaborado del lote no encontrado.';
            echo json_encode($rsp);
            exit;
        }

        if ($elaboradoLote['tipo'] > 2){
            // Si el lote es de tipo 3 o 4 (subelaborados) en lugar de tomar como ingredientes los ingredientes del lote se debe 
            // obtener los ingredientes del ingrediente del elaborado.
            // si el el lote solo tiene un ingrediente este ingrediente es el elaborado que contiene los ingredientes reales
            if (count($loteIngredientes) === 1) {
                // nos aseguramos de que esto sea así y no se trate de un envasado simple sin elaboración
                $ingredienteId = (int)$loteIngredientes[0]['ingrediente_id'];
                if ($this->elaboradoModel->isIngredienteOrigen($lote['elaboracion_id'],$ingredienteId)) {
                    $loteIdSublotes = $this->elaboradoModel->getElaboradoIdByIngredienteOrigen($ingredienteId);
                    $loteIngredientes = $this->elaboradoModel->getIngredienteElaborado((int)$loteIdSublotes);
                }
            }
            // Si no existe ningún ingrediente es debido a que el elaborado es un envasado/congelado de otro elaborado previo
            // en ese caso hacemos uso del valor parent_lote_id para obtener los ingredientes del lote padre
            if (isset($lote['parent_lote_id'])) {
                $lotePadre = $this->lotesModel->getLoteById((int)$lote['parent_lote_id']);
                if ($lotePadre) {
                    $loteIngredientes = $this->lotesModel->getIngredientesByLoteId((int)$lotePadre['id']);
                }
            }
        }
        // 2. Creamos las variables que necesitamos para la impresión
        // lista de elementos de la etiqueta: nombre elaboración, lista de ingredientes ordenada por peso y con * en los productos que incluyan alergenos,
        // lista de alergenos (únicos) presentes en los ingredientes, fecha de elaboración, fecha de caducidad, lote, tipo.
        // Comprobar si existe parent_lote_id y asignarlo como lote a imprimir
        $loteCodigo = $lote['numero_lote'] ?? '';

        $nombreLb = $elaboradoLote['nombre'] ?? '';
        if (!$nombreLb) {
            http_response_code(404);
            $rsp['message'] = 'Nombre de elaboración no encontrado.';
            echo json_encode($rsp);
            exit;
        }
        $ingredientesLb = '';
        //programación literada a seguir
        //1. ordenar ingredientes por peso descendente
        usort($loteIngredientes, function ($a, $b) {
            return ($b['peso'] ?? 0) <=> ($a['peso'] ?? 0);
        });
        //2. construir la lista de ingredientes. La lista de ingrediente es una cadena con el formato "Ingrediente1*, Ingrediente2, Ingrediente3 (Ingrediente3a, Ingrediente3b*, Ingrediente 3c)*, ..."
        $idIngredientes = [];
        foreach ($loteIngredientes as $li) {
            // Programación literada a seguir:
            // 2.1. normalizar el id del ingrediente ajustando su origen opciones id_ingrediente (si viene de subelaborado) o ingrediente_id (si viene de Lote)
            $ingredienteId = (int)($li['ingrediente_id'] ?? $li['id_ingrediente'] ?? 0);
            // Si el subingrediente es origen saltar el bucle
            if ($this->elaboradoModel->isIngredienteOrigen((int)$lote['elaboracion_id'],$ingredienteId)) {
                continue;
            }

            $idIngredientes[] = $ingredienteId;
            // 2.2. comprobar si el ingrediente tiene alergenos mediante la función obtenerAlergenosPorIngredienteId
            $alergenos = $this->ingredienteModel->obtenerAlergenosPorIngredienteId($this->pdo, $ingredienteId);
            // 2.3. si tiene alergenos añadir * al nombre del ingrediente
            $ingredienteNombre = $li['nombre'] ?? $li['ingrediente_resultante'] ?? 'Ingrediente #' . $ingredienteId;
            if (!empty($alergenos)) {
                $ingredienteNombre .= '*';
            }
            // 2.4. añadir el ingrediente a la lista de ingredientes
            if ($ingredientesLb !== '') {
                $ingredientesLb .= ', ';
            }
            $ingredientesLb .= $ingredienteNombre;
            // obtenemos de forma segura el id del elaborado para dicho ingrediente si existe
            $idElaboradoSubingrediente = $this->elaboradoModel->getElaboradoIdByIngredienteOrigen($ingredienteId);
            // 2.5. Comprobamos si el ingrediente tiene subingredientes (si es un elaborado)
            if ($idElaboradoSubingrediente !== $elaboradoLote) {
                // obtenemos los subingredientes
                $subingredientes = $this->elaboradoModel->getIngredienteElaborado((int)$idElaboradoSubingrediente);
                if (!empty($subingredientes)) {
                    usort($subingredientes, function ($a, $b) {
                        return ($b['peso'] ?? 0) <=> ($a['peso'] ?? 0);
                    });
                    $subingredientesLb = '';
                    // construimos la lista de subingredientes
                    foreach ($subingredientes as $si) {
                        $subingredienteId = (int)($si['ingrediente_id'] ?? $si['id_ingrediente'] ?? 0);
                        // Si el subingrediente es origen saltar el bucle
                        if ($this->elaboradoModel->isIngredienteOrigen((int)$idElaboradoSubingrediente,$subingredienteId)) {
                            continue;
                        }
                        $idIngredientes[] = $subingredienteId;
                        $subalergenos = $this->ingredienteModel->obtenerAlergenosPorIngredienteId($this->pdo, $subingredienteId);
                        $subingredienteNombre = $si['nombre'] ?? 'Ingrediente #' . $subingredienteId;
                        if (!empty($subalergenos)) {
                            $subingredienteNombre .= '*';
                        }
                        if ($subingredientesLb !== '') {
                            $subingredientesLb .= ', ';
                        }
                        $subingredientesLb .= $subingredienteNombre;
                    }
                    // comprobamos si se ha añadido algun ingrediente y si es así lo añadimos la lista de subingredientes entre paréntesis al ingrediente principal
                    if (!empty($subingredientesLb)) {
                        $ingredientesLb .= ' (' . $subingredientesLb . ')';
                    }
                }
            }
            $ingredientesLb .= '.';
        }
        // Limpiar ingredientes
        $alergenosPresentes = $this->ingredienteModel->getUniqueAlergenosFromIngredientes($idIngredientes);
        $alergenosLb = '';
        if (!empty($alergenosPresentes)) {
            foreach ($alergenosPresentes as &$alergeno) {
                $alergenoNombres = $this->ingredienteModel->obtenerNombreAlergenosPorIdAlergeno((int)$alergeno);
                if (!empty($alergenoNombres)) {
                    $alergeno = $alergenoNombres[0];
                } else {
                    $alergeno = 'Alergeno #' . $alergeno;
                }
            }
            $alergenosLb = implode(', ', $alergenosPresentes);
            $alergenosLb .= '.';
        }
        $conservacionLb = $elaboradoLote['descripcion'] ?? '';
        // 4. Transformas las fechas a fomrato dd/mm/yyyy
        // Transformar fechas a formato europeo dd/mm/YYYY
        $fechaElabRaw = $lote['fecha_produccion'] ?? null;
        $fechaElab = null;
        if ($fechaElabRaw) {
            try {
            if (is_numeric($fechaElabRaw)) {
                $dt = (new \DateTime())->setTimestamp((int)$fechaElabRaw);
            } else {
                $dt = new \DateTime($fechaElabRaw);
            }
            $fechaElab = $dt->format('d/m/Y');
            } catch (\Exception $e) {
            // Si no se puede parsear, mantener el valor original como fallback
            $fechaElab = (string)$fechaElabRaw;
            }
        }

        $fechaCadRaw = $lote['fecha_caducidad'] ?? null;
        $fechaCad = null;
        if ($fechaCadRaw) {
            try {
            if (is_numeric($fechaCadRaw)) {
                $dt = (new \DateTime())->setTimestamp((int)$fechaCadRaw);
            } else {
                $dt = new \DateTime($fechaCadRaw);
            }
            $fechaCad = $dt->format('d/m/Y');
            } catch (\Exception $e) {
            $fechaCad = (string)$fechaCadRaw;
            }
        }
        // 4. preparar los datos para la vista de impresión
        $viewData = [
            'nombreLb' => $nombreLb,
            'ingredientesLb' => $ingredientesLb,
            'alergenosLb' => $alergenosLb,
            'conservacionLb' => $conservacionLb,
            'fechaElaboracion' => $fechaElab ?? '',
            'fechaCaducidad' => $fechaCad ?? '',
            'loteCodigo' => $loteCodigo ?? '',
            'tipoElaboracion' => $elaboradoLote['tipo'] ?? '',
            'cantidad' => $cantidad,
        ];

        // Intentar generar EZPL y enviar a impresora
        try {
            $ezpl = TraductorEZPL::generateEZPL($viewData, (int)$cantidad);
            // ver $ezpl en /tmp/preview_etiqueta.ezpl
            echo $ezpl;
            // guardar preview en tmp
            @file_put_contents(sys_get_temp_dir() . '/preview_etiqueta.ezpl', $ezpl);
            // enviar a impresora (nombre de cola por defecto 'godex_raw', cámbialo si hace falta)
            $printed = PrinterUtil::printEzpl($ezpl, 'godex_raw');

            $rsp['success'] = $printed;
            $rsp['message'] = $printed ? 'Impresión enviada correctamente.' : 'Error al enviar a la impresora. Revisa /tmp/print_debug.log';
        } catch (\Throwable $e) {
            $rsp['success'] = false;
            $rsp['message'] = 'Excepción al generar/imprimir: ' . $e->getMessage();
        }
        // volver a la vista del lote/impresion
        $url = '/imprimir.php?view=1&id=' . urlencode((string)$loteId);
        Redirect::to($url);
        exit;
    }
// Lógica de impresión del ingrediente (para escandallos)
    private function imprimirIngrediente(int $loteId, int $ingredienteId, int $cantidad): void
    {
        // ✅ Validar permisos
        if (!$this->canModify()) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'No tiene permisos para imprimir etiquetas.'
            ]);
            exit;
        }

        // preparamos una variable de respuesta
        $rsp = [
            'success' => false,
            'message' => '',
        ];

        // 1. obtenemos el lote completo
        $lote = $this->lotesModel->getLoteById($loteId);
        if (!$lote) {
            http_response_code(404);
            $rsp['message'] = 'Lote no encontrado.';
            echo json_encode($rsp);
            exit;
        }

        // 2. obtenemos el ingrediente
        $ingrediente = $this->ingredienteModel->findById($this->pdo, $ingredienteId);
        if (!$ingrediente) {
            http_response_code(404);
            $rsp['message'] = 'Ingrediente no encontrado.';
            echo json_encode($rsp);
            exit;
        }

        // 3. obtenemos el elaborado del lote
        $elaboradoLote = $this->elaboradoModel->findById((int)$lote['elaboracion_id']);
        if (!$elaboradoLote) {
            http_response_code(404);
            $rsp['message'] = 'Elaborado del lote no encontrado.';
            echo json_encode($rsp);
            exit;
        }

        // 4. Preparar datos para la etiqueta del ingrediente
        $nombreLb = $ingrediente['nombre'] ?? 'Ingrediente #' . $ingredienteId;
        if (!$nombreLb) {
            http_response_code(404);
            $rsp['message'] = 'Nombre de ingrediente no encontrado.';
            echo json_encode($rsp);
            exit;
        }
        // Comprobar si existe parent_lote_id y asignarlo como lote a imprimir
        $loteCodigo = $lote['numero_lote'] ?? '';


        // Ingredientes: para el ingrediente, si es un elaborado, obtener sus subingredientes; sino, vacío o el propio
        $ingredientesLb = '';
        $idIngredientes = [$ingredienteId];
        $idElaboradoSubingrediente = $this->elaboradoModel->getElaboradoIdByIngredienteOrigen($ingredienteId);
        if ($idElaboradoSubingrediente) {
            $subingredientes = $this->elaboradoModel->getIngredienteElaborado((int)$idElaboradoSubingrediente);
            if (!empty($subingredientes)) {
                usort($subingredientes, function ($a, $b) {
                    return ($b['peso'] ?? 0) <=> ($a['peso'] ?? 0);
                });
                foreach ($subingredientes as $si) {
                    $subingredienteId = (int)($si['ingrediente_id'] ?? $si['id_ingrediente'] ?? 0);
                    if ($this->elaboradoModel->isIngredienteOrigen((int)$idElaboradoSubingrediente, $subingredienteId)) {
                        continue;
                    }
                    $idIngredientes[] = $subingredienteId;
                    $subalergenos = $this->ingredienteModel->obtenerAlergenosPorIngredienteId($this->pdo, $subingredienteId);
                    $subingredienteNombre = $si['nombre'] ?? 'Ingrediente #' . $subingredienteId;
                    if (!empty($subalergenos)) {
                        $subingredienteNombre .= '*';
                    }
                    if ($ingredientesLb !== '') {
                        $ingredientesLb .= ', ';
                    }
                    $ingredientesLb .= $subingredienteNombre;
                }
            }
            $ingredientesLb .= '.';
        }

        // Alérgenos
        $alergenosPresentes = $this->ingredienteModel->getUniqueAlergenosFromIngredientes($idIngredientes);
        $alergenosLb = '';
        if (!empty($alergenosPresentes)) {
            foreach ($alergenosPresentes as &$alergeno) {
                $alergenoNombres = $this->ingredienteModel->obtenerNombreAlergenosPorIdAlergeno((int)$alergeno);
                if (!empty($alergenoNombres)) {
                    $alergeno = $alergenoNombres[0];
                } else {
                    $alergeno = 'Alergeno #' . $alergeno;
                }
            }
            $alergenosLb = implode(', ', $alergenosPresentes);
            $alergenosLb .= '.';
        }

        $conservacionLb = $elaboradoLote['descripcion'] ?? 'CONSERVAR EN UN LUGAR FRESCO Y SECO';

        // Fechas del lote principal
        $fechaElabRaw = $lote['fecha_produccion'] ?? null;
        $fechaElab = null;
        if ($fechaElabRaw) {
            try {
                if (is_numeric($fechaElabRaw)) {
                    $dt = (new \DateTime())->setTimestamp((int)$fechaElabRaw);
                } else {
                    $dt = new \DateTime($fechaElabRaw);
                }
                $fechaElab = $dt->format('d/m/Y');
            } catch (\Exception $e) {
                $fechaElab = (string)$fechaElabRaw;
            }
        }

        $fechaCadRaw = $lote['fecha_caducidad'] ?? null;
        $fechaCad = null;
        if ($fechaCadRaw) {
            try {
                if (is_numeric($fechaCadRaw)) {
                    $dt = (new \DateTime())->setTimestamp((int)$fechaCadRaw);
                } else {
                    $dt = new \DateTime($fechaCadRaw);
                }
                $fechaCad = $dt->format('d/m/Y');
            } catch (\Exception $e) {
                $fechaCad = (string)$fechaCadRaw;
            }
        }

        // Preparar datos para la vista de impresión
        $viewData = [
            'nombreLb' => $nombreLb,
            'ingredientesLb' => $ingredientesLb,
            'alergenosLb' => $alergenosLb,
            'conservacionLb' => $conservacionLb,
            'fechaElaboracion' => $fechaElab ?? '',
            'fechaCaducidad' => $fechaCad ?? '',
            'loteCodigo' => $loteCodigo ?? '',
            'tipoElaboracion' => 2, // Escandallo
            'cantidad' => $cantidad,
        ];

        // Intentar generar EZPL y enviar a impresora
        try {
            $ezpl = TraductorEZPL::generateEZPL($viewData, (int)$cantidad);
            // guardar preview en tmp
            @file_put_contents(sys_get_temp_dir() . '/preview_etiqueta.ezpl', $ezpl);
            // enviar a impresora (nombre de cola por defecto 'godex_raw', cámbialo si hace falta)
            $printed = PrinterUtil::printEzpl($ezpl, 'godex_raw');

            $rsp['success'] = $printed;
            $rsp['message'] = $printed ? 'Impresión enviada correctamente.' : 'Error al enviar a la impresora. Revisa /tmp/print_debug.log';
        } catch (\Throwable $e) {
            $rsp['success'] = false;
            $rsp['message'] = 'Excepción al generar/imprimir: ' . $e->getMessage();
        }

        // volver a la vista del lote/impresion
        $url = '/imprimir.php?view=1&id=' . urlencode((string)$loteId);
        Redirect::to($url);
        exit;
    }

}