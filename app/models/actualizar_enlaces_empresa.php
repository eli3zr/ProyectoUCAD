<?php
// Habilitar la visualización de errores (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Configuración de errores para depuración (¡Cambia a 0 en producción!)
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Asegúrate de que la carpeta 'logs' exista y sea escribible.


// 1. Iniciar la sesión (debe ser lo primero)
session_start();

// 2. Establecer el encabezado de contenido (debe ser antes de cualquier echo)
header('Content-Type: application/json');

// 3. Incluir la conexión a la base de datos y el helper de bitácora
// CORRECT PATH: Desde 'app/models/' necesitas subir un nivel a 'app/' y luego ir a 'config/'
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

$response = ['success' => false, 'error' => '', 'msg' => ''];

// 4. Verificar que la conexión a la base de datos sea válida
// Consistente con los otros archivos: no se bitacora directamente aquí si falla la conexión.
if (!isset($con) || !($con instanceof mysqli) || $con->connect_error) {
    error_log("Fallo crítico: La conexión a la base de datos no está disponible o falló en actualizar_enlaces_empresas.php: " . ($con->connect_error ?? 'Desconocido'));
    $response['error'] = 'Error interno del servidor: Fallo en la conexión a la base de datos.';
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit(); // Termina la ejecución aquí
}

// 5. Verificar autenticación y autorización
$usuario_id = $_SESSION['ID_Usuario'] ?? 0; // Usar 0 como default para la bitácora
$usuario_rol_id = $_SESSION['ID_Rol'] ?? null;

if (!$usuario_id) {
    $response['error'] = 'No autorizado. Por favor, inicie sesión.';
    http_response_code(401); // Unauthorized
    registrarEventoBitacora($con, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', 0, NULL, 'Intento de acceso a actualización de enlaces sin sesión activa.');
    echo json_encode($response);
    exit(); // Termina la ejecución aquí
}

if ($usuario_rol_id !== 1) { // Verifica si el rol es el de empresa (ID_Rol = 1)
    $response['error'] = 'Acceso denegado. Este perfil es solo para usuarios con rol de empresa.';
    http_response_code(403); // Forbidden
    registrarEventoBitacora($con, $usuario_id, 'usuario', 'ACCESO_NO_AUTORIZADO', $usuario_id, NULL, 'Intento de acceso a enlaces con rol incorrecto. Rol: ' . $usuario_rol_id);
    echo json_encode($response);
    exit(); // Termina la ejecución aquí
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
                            // Consistente: No se bitacora GET de listas exitosas
                        } else {
                            error_log("Error al obtener tipos de red: " . $con->error);
                            $response['error'] = 'Error al obtener tipos de red social: ' . $con->error;
                            http_response_code(500);
                            registrarEventoBitacora($con, 0, 'tipo_red', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al obtener tipos de red social: ' . $con->error);
                            echo json_encode($response); // Envía la respuesta de error
                            exit(); // Termina la ejecución
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
                                // Consistente: No se bitacora GET de datos de perfil/enlaces exitosos
                            } else {
                                error_log("Error al preparar consulta de enlaces existentes: " . $con->error);
                                $response['error'] = 'Error al preparar la consulta de enlaces existentes: ' . $con->error;
                                http_response_code(500);
                                registrarEventoBitacora($con, 0, 'redes_empresas', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar consulta de enlaces existentes: ' . $con->error);
                                echo json_encode($response); // Envía la respuesta de error
                                exit(); // Termina la ejecución
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
                        // Consistente con los otros archivos: no se bitacora si el perfil no existe para GET.
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
                    registrarEventoBitacora($con, 0, 'perfil_empresa', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar la consulta de perfil de empresa (enlaces): ' . $con->error);
                }
                break;

            default:
                $response['error'] = 'Acción GET no válida.';
                http_response_code(400); // Bad Request
                registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Acción GET no válida en actualizar_enlaces_empresas.php: ' . ($action ?: 'vacío'));
                break;
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Leer y decodificar el JSON enviado en el cuerpo de la solicitud
        $input = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $response['error'] = 'JSON de entrada inválido: ' . json_last_error_msg();
            http_response_code(400); // Bad Request
            // Consistente: LOGIN_FALLIDO para errores de validación de entrada
            registrarEventoBitacora($con, $usuario_id, 'sistema', 'LOGIN_FALLIDO', $usuario_id, NULL, 'JSON inválido en actualizar_enlaces_empresas: ' . json_last_error_msg());
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
                    // Consistente: LOGIN_FALLIDO para errores de validación de entrada
                    registrarEventoBitacora($con, $usuario_id, 'perfil_empresa', 'LOGIN_FALLIDO', $usuario_id, NULL, 'Guardar enlaces fallido: ID de perfil de empresa no válido.');
                    break; // break normal de switch, no afecta el if que lo contiene
                }

                // Validar que enlaces_to_save sea un array
                if (!is_array($enlaces_to_save)) {
                    $response['error'] = 'Formato de enlaces inválido. Se esperaba un array.';
                    http_response_code(400);
                    // Consistente: LOGIN_FALLIDO para errores de validación de entrada
                    registrarEventoBitacora($con, $usuario_id, 'redes_empresas', 'LOGIN_FALLIDO', $usuario_id, NULL, 'Guardar enlaces fallido: Formato de enlaces inválido.');
                    break; // break normal de switch
                }

                $con->begin_transaction();

                try {
                    // --- BITACOLA: Obtener datos antes de la actualización (borrado) en 'redes_empresas' ---
                    $stmt_get_enlaces_existentes = $con->prepare("SELECT URL_Red, tipo_red_id_tipo_red FROM redes_empresas WHERE ID_Perfil_Empresa = ?");
                    $stmt_get_enlaces_existentes->bind_param("i", $perfil_empresa_id);
                    $stmt_get_enlaces_existentes->execute();
                    $result_get_enlaces_existentes = $stmt_get_enlaces_existentes->get_result();
                    $enlaces_existentes_data = $result_get_enlaces_existentes->fetch_all(MYSQLI_ASSOC);
                    $stmt_get_enlaces_existentes->close();
                    
                    $datos_antes_borrado = json_encode($enlaces_existentes_data);

                    // 1. Eliminar todos los enlaces existentes para este perfil
                    $stmt_delete = $con->prepare("DELETE FROM redes_empresas WHERE ID_Perfil_Empresa = ?");
                    if ($stmt_delete) {
                        $stmt_delete->bind_param('i', $perfil_empresa_id);
                        if (!$stmt_delete->execute()) {
                            // Registrar ERROR_SISTEMA en fallo de DELETE
                            registrarEventoBitacora($con, $perfil_empresa_id, 'redes_empresas', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al ejecutar DELETE de enlaces existentes: ' . $stmt_delete->error);
                            throw new Exception('Error al eliminar enlaces existentes: ' . $stmt_delete->error);
                        }
                        $filas_eliminadas = $stmt_delete->affected_rows;
                        $stmt_delete->close();

                        // --- BITACOLA: Registrar DELETE exitoso ---
                        if ($filas_eliminadas > 0) {
                             registrarEventoBitacora($con, $perfil_empresa_id, 'redes_empresas', 'DELETE', $usuario_id, $datos_antes_borrado, NULL);
                        }
                    } else {
                        // Registrar ERROR_SISTEMA en preparación de DELETE
                        registrarEventoBitacora($con, 0, 'redes_empresas', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar DELETE de enlaces: ' . $con->error);
                        throw new Exception('Error al preparar la eliminación de enlaces: ' . $con->error);
                    }

                    // 2. Insertar los nuevos enlaces
                    if (!empty($enlaces_to_save)) {
                        $stmt_insert = $con->prepare("INSERT INTO redes_empresas (ID_Perfil_Empresa, URL_Red, tipo_red_id_tipo_red) VALUES (?, ?, ?)");
                        if ($stmt_insert) {
                            $enlaces_insertados = [];
                            foreach ($enlaces_to_save as $enlace) {
                                $url = $enlace['url'] ?? '';
                                $tipo_red_id = $enlace['tipo_red_id'] ?? null;

                                if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL) && is_numeric($tipo_red_id) && $tipo_red_id > 0) {
                                    $stmt_insert->bind_param('isi', $perfil_empresa_id, $url, $tipo_red_id);
                                    if (!$stmt_insert->execute()) {
                                        // Registrar ERROR_SISTEMA en fallo de INSERT de un enlace
                                        registrarEventoBitacora($con, $perfil_empresa_id, 'redes_empresas', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al ejecutar INSERT de enlace: ' . $stmt_insert->error . " URL: " . $url);
                                        throw new Exception('Error al insertar enlace: ' . $stmt_insert->error);
                                    }
                                    $enlaces_insertados[] = ['url' => $url, 'tipo_red_id' => $tipo_red_id];
                                } else {
                                    error_log("Advertencia: Enlace inválido o incompleto no insertado para Perfil ID: $perfil_empresa_id - URL: '$url', Tipo ID: '$tipo_red_id'");
                                    // Registrar ADVERTENCIA si un enlace individual es inválido
                                    registrarEventoBitacora($con, $perfil_empresa_id, 'redes_empresas', 'ADVERTENCIA', $usuario_id, NULL, "Intento de insertar enlace inválido para Perfil ID: $perfil_empresa_id. URL: '$url', Tipo ID: '$tipo_red_id'");
                                }
                            }
                            $stmt_insert->close();
                            // --- BITACOLA: Registrar INSERT exitoso si se insertaron enlaces ---
                            if (!empty($enlaces_insertados)) {
                                $datos_despues_insercion = json_encode($enlaces_insertados);
                                registrarEventoBitacora($con, $perfil_empresa_id, 'redes_empresas', 'INSERT', $usuario_id, NULL, $datos_despues_insercion);
                            }
                        } else {
                            // Registrar ERROR_SISTEMA en preparación de INSERT
                            registrarEventoBitacora($con, 0, 'redes_empresas', 'ERROR_SISTEMA', $usuario_id, NULL, 'Error al preparar la inserción de enlaces: ' . $con->error);
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
                    // Registrar ERROR_SISTEMA por excepción en la transacción
                    registrarEventoBitacora($con, $perfil_empresa_id, 'redes_empresas', 'ERROR_SISTEMA', $usuario_id, NULL, 'Excepción en transacción de enlaces: ' . $e->getMessage());
                }
                break;

            default:
                $response['error'] = 'Acción POST no válida.';
                http_response_code(400); // Bad Request
                registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Acción POST no válida en actualizar_enlaces_empresas.php: ' . ($action ?: 'vacío'));
                break;
        }
    } else {
        // Método HTTP no permitido
        header('Allow: GET, POST');
        http_response_code(405); // Method Not Allowed
        $response['error'] = 'Método HTTP no permitido.';
        registrarEventoBitacora($con, 0, 'sistema', 'ADVERTENCIA', $usuario_id, NULL, 'Método HTTP no permitido en actualizar_enlaces_empresas: ' . $_SERVER['REQUEST_METHOD']);
    }
} catch (Exception $e) {
    // Capturar cualquier excepción no manejada anteriormente
    error_log("Error fatal en actualizar_enlaces_empresas.php: " . $e->getMessage());
    $response['success'] = false;
    $response['error'] = 'Error interno del servidor inesperado.';
    http_response_code(500); // Internal Server Error
    registrarEventoBitacora($con, 0, 'sistema', 'ERROR_SISTEMA', $usuario_id, NULL, 'Excepción fatal en actualizar_enlaces_empresas: ' . $e->getMessage());
} finally {
    // Asegurarse de cerrar la conexión a la base de datos
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
    }
}

echo json_encode($response);
exit();
?>