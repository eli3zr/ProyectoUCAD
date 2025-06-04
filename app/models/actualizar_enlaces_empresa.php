<?php
// Habilitar la visualización de errores (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Iniciar la sesión (debe ser lo primero)
session_start();

// 2. Establecer el encabezado de contenido (debe ser antes de cualquier echo)
header('Content-Type: application/json');

// 3. Incluir la conexión a la base de datos
// CORRECT PATH: Desde 'app/models/' necesitas subir un nivel a 'app/' y luego ir a 'config/'
require_once __DIR__ . '/../config/conexion.php';

$response = ['success' => false, 'error' => '', 'msg' => ''];

// 4. Verificar que la conexión a la base de datos sea válida
if (!isset($con) || !($con instanceof mysqli) || $con->connect_error) {
    error_log("Fallo crítico: La conexión a la base de datos no está disponible o falló: " . ($con->connect_error ?? 'Desconocido'));
    $response['error'] = 'Error interno del servidor: Fallo en la conexión a la base de datos.';
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit();
}

// 5. Verificar autenticación y autorización
$usuario_id = $_SESSION['ID_Usuario'] ?? null;
$usuario_rol_id = $_SESSION['ID_Rol'] ?? null;

if (!$usuario_id) {
    $response['error'] = 'No autorizado. Por favor, inicie sesión.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

if ($usuario_rol_id !== 1) { // Verifica si el rol es el de empresa (ID_Rol = 1)
    $response['error'] = 'Acceso denegado. Este perfil es solo para usuarios con rol de empresa.';
    http_response_code(403); // Forbidden
    echo json_encode($response);
    exit();
}

// Todo lo anterior es pre-requisito. El flujo principal comienza aquí.
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';

        switch ($action) {
            case 'get_enlaces_data':
                $perfil_empresa_id = null;
                $enlaces = [];
                $tipo_red_ids = [];

                // 1. Obtener el ID_Perfil_Empresa del usuario actual
                $stmt_perfil = $con->prepare("SELECT ID_Perfil_Empresa FROM perfil_empresa WHERE usuario_ID_Usuario = ? LIMIT 1");
                if ($stmt_perfil) {
                    $stmt_perfil->bind_param('i', $usuario_id);
                    $stmt_perfil->execute();
                    $result_perfil = $stmt_perfil->get_result();
                    $perfil = $result_perfil->fetch_assoc();
                    $stmt_perfil->close();

                    if ($perfil) {
                        $perfil_empresa_id = $perfil['ID_Perfil_Empresa'];

                        // 2. Obtener los tipos de red social y sus IDs
                        $stmt_tipos = $con->query("SELECT id_tipo_red, Nombre_red FROM tipo_red ORDER BY Nombre_red");
                        if ($stmt_tipos) {
                            while ($row = $stmt_tipos->fetch_assoc()) {
                                $tipo_red_ids[$row['Nombre_red']] = $row['id_tipo_red'];
                            }
                            $stmt_tipos->close();
                        } else {
                            error_log("Error al obtener tipos de red: " . $con->error);
                        }

                        // 3. Obtener los enlaces existentes para este perfil
                        if ($perfil_empresa_id) {
                            $stmt_enlaces = $con->prepare("SELECT URL_Red, tipo_red_id_tipo_red FROM redes_empresas WHERE ID_Perfil_Empresa = ?");
                            if ($stmt_enlaces) {
                                $stmt_enlaces->bind_param('i', $perfil_empresa_id);
                                $stmt_enlaces->execute();
                                $result_enlaces = $stmt_enlaces->get_result();
                                while ($row = $result_enlaces->fetch_assoc()) {
                                    $enlaces[] = [
                                        'url' => $row['URL_Red'],
                                        'tipo_red_id' => $row['tipo_red_id_tipo_red']
                                    ];
                                }
                                $stmt_enlaces->close();
                            } else {
                                error_log("Error al preparar consulta de enlaces existentes: " . $con->error);
                            }
                        }

                        $response['success'] = true;
                        $response['data'] = [
                            'perfil_empresa_id' => $perfil_empresa_id,
                            'tipo_red_ids' => $tipo_red_ids,
                            'enlaces' => $enlaces
                        ];
                    } else {
                        // Si no se encuentra un perfil de empresa para el usuario, se considera éxito pero con datos nulos.
                        $response['success'] = true;
                        $response['data'] = [
                            'perfil_empresa_id' => null,
                            'tipo_red_ids' => $tipo_red_ids, // Se envían los tipos de red aunque no haya perfil
                            'enlaces' => []
                        ];
                        $response['msg'] = 'No se encontró un perfil de empresa existente para este usuario.';
                    }
                } else {
                    $response['error'] = 'Error al preparar la consulta de perfil de empresa: ' . $con->error;
                    http_response_code(500);
                }
                break;

            default:
                $response['error'] = 'Acción GET no válida.';
                http_response_code(400); // Bad Request
                break;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leer y decodificar el JSON enviado en el cuerpo de la solicitud
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $response['error'] = 'JSON de entrada inválido: ' . json_last_error_msg();
            http_response_code(400); // Bad Request
            echo json_encode($response);
            exit();
        }

        $action = $input['action'] ?? '';
        $perfil_empresa_id = $input['perfil_empresa_id'] ?? null;
        $enlaces_to_save = $input['enlaces'] ?? [];

        switch ($action) {
            case 'save_enlaces':
                // Validar que el perfil_empresa_id sea un entero y no nulo
                if (!is_numeric($perfil_empresa_id) || $perfil_empresa_id <= 0) {
                    $response['error'] = 'ID de perfil de empresa no válido.';
                    http_response_code(400);
                    break;
                }

                // Validar que enlaces_to_save sea un array
                if (!is_array($enlaces_to_save)) {
                    $response['error'] = 'Formato de enlaces inválido. Se esperaba un array.';
                    http_response_code(400);
                    break;
                }

                // Iniciar transacción para asegurar la atomicidad de la operación
                $con->begin_transaction();

                try {
                    // 1. Eliminar todos los enlaces existentes para este perfil
                    $stmt_delete = $con->prepare("DELETE FROM redes_empresas WHERE ID_Perfil_Empresa = ?");
                    if ($stmt_delete) {
                        $stmt_delete->bind_param('i', $perfil_empresa_id);
                        if (!$stmt_delete->execute()) {
                            throw new Exception('Error al eliminar enlaces existentes: ' . $stmt_delete->error);
                        }
                        $stmt_delete->close();
                    } else {
                        throw new Exception('Error al preparar la eliminación de enlaces: ' . $con->error);
                    }

                    // 2. Insertar los nuevos enlaces
                    if (!empty($enlaces_to_save)) {
                        $stmt_insert = $con->prepare("INSERT INTO redes_empresas (ID_Perfil_Empresa, URL_Red, tipo_red_id_tipo_red) VALUES (?, ?, ?)");
                        if ($stmt_insert) {
                            foreach ($enlaces_to_save as $enlace) {
                                $url = $enlace['url'] ?? '';
                                $tipo_red_id = $enlace['tipo_red_id'] ?? null;

                                // Validar URL y tipo_red_id antes de insertar
                                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && is_numeric($tipo_red_id) && $tipo_red_id > 0) {
                                    $stmt_insert->bind_param('isi', $perfil_empresa_id, $url, $tipo_red_id);
                                    if (!$stmt_insert->execute()) {
                                        throw new Exception('Error al insertar enlace: ' . $stmt_insert->error);
                                    }
                                } else {
                                    // Loguear URLs inválidas o incompletas en lugar de fallar la transacción
                                    error_log("Advertencia: Enlace inválido o incompleto no insertado para Perfil ID: $perfil_empresa_id - URL: '$url', Tipo ID: '$tipo_red_id'");
                                }
                            }
                            $stmt_insert->close();
                        } else {
                            throw new Exception('Error al preparar la inserción de enlaces: ' . $con->error);
                        }
                    }

                    $con->commit(); // Confirmar la transacción
                    $response['success'] = true;
                    $response['msg'] = 'Los enlaces han sido actualizados correctamente.';

                } catch (Exception $e) {
                    $con->rollback(); // Revertir la transacción en caso de error
                    $response['success'] = false;
                    $response['error'] = 'Error al guardar los enlaces: ' . $e->getMessage();
                    error_log("Error en transacción de enlaces: " . $e->getMessage()); // Loguear el error para depuración
                    http_response_code(500); // Internal Server Error
                }
                break;

            default:
                $response['error'] = 'Acción POST no válida.';
                http_response_code(400); // Bad Request
                break;
        }
    } else {
        // Método HTTP no permitido
        header('Allow: GET, POST');
        http_response_code(405); // Method Not Allowed
        $response['error'] = 'Método HTTP no permitido.';
    }
} catch (Exception $e) {
    // Capturar cualquier excepción no manejada anteriormente
    error_log("Error fatal en actualizar_enlaces_empresas.php: " . $e->getMessage());
    $response['success'] = false;
    $response['error'] = 'Error interno del servidor inesperado.';
    http_response_code(500); // Internal Server Error
} finally {
    // Asegurarse de cerrar la conexión a la base de datos
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
    }
}

echo json_encode($response);
exit();
?>