<?php
// app/models/actualizar_empresa.php (o el nombre que tenga este script)

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/actualizar_empresa_errors.log'); // Log específico para este script

session_start(); // Iniciar sesión si no está iniciada (necesario para $_SESSION['ID_Usuario'])

// Incluir la conexión a la base de datos y el helper de bitácora
require_once __DIR__ . '/../config/conexion.php'; 
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0;

// --- Función para generar una respuesta JSON estandarizada, bitacorar y terminar la ejecución ---
// Se define aquí para ser usada en este script.
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null, $con = null, $loggedInUserId = 0, $objetoId = 0, $tipoObjeto = 'sistema', $evento = 'ERROR_DESCONOCIDO', $datosAnterior = NULL, $datosNuevo = NULL) {
        // Solo bitacorar si se proporciona una conexión.
        if ($con) { 
            // Asegurarse de que el mensaje de bitácora sea conciso pero informativo
            $bitacora_message = $message;
            if ($error) {
                $bitacora_message .= " - Error: " . $error;
            }
            if ($datosNuevo) {
                $bitacora_message .= " - Datos: " . $datosNuevo;
            }
            // Asegúrate de que $tipoObjeto y $evento sean relevantes para cada llamada.
            registrarEventoBitacora($con, $objetoId, $tipoObjeto, $evento, $loggedInUserId, $datosAnterior, $bitacora_message);
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => $success,
            'msg' => $message,
            'error' => $error
        ]);
        exit();
    }
}

// Verificar que la conexión a la BD sea válida al inicio del script.
// Si no hay conexión, loguear un error fatal y salir, sin intentar bitacorar en DB.
if (!isset($con) || !is_object($con) || mysqli_connect_errno()) {
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en actualizar_empresa.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg); // Loggear en el archivo de error configurado
    // No podemos usar sendJsonResponse con bitácora porque la conexión no existe
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit(); // Terminar la ejecución
}

// Establecer el Content-Type para todas las respuestas JSON tan pronto como sea posible
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar autenticación del usuario
    if ($loggedInUserId === 0) {
        sendJsonResponse(false, 'Usuario no autenticado.', 'Por favor, inicie sesión.', $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de actualizar empresa sin sesión activa.');
    }

    // 2. Recoger y sanear los datos del POST
    $id = $_POST['id'] ?? null;
    $nombreEmpresa = $_POST['nombreEmpresa'] ?? '';
    $correoEmpresa = $_POST['correoEmpresa'] ?? '';
    $sitioWebEmpresa = $_POST['sitioWebEmpresa'] ?? '';
    $estadoEmpresa = $_POST['estadoEmpresa'] ?? ''; // Asumiendo que 'estadoEmpresa' es un valor como 'Activo' o 'Inactivo'

    // Validación básica de los datos recibidos
    if (empty($id) || !is_numeric($id)) {
        sendJsonResponse(false, 'ID de empresa no válido o faltante.', null, $con, $loggedInUserId, 0, 'empresa', 'VALIDACION_FALLIDA', NULL, 'ID de empresa no válido: ' . ($id ?? 'NULL'));
    }
    $id = (int)$id; // Castear a entero para seguridad

    if (empty($nombreEmpresa)) {
        sendJsonResponse(false, 'El nombre de la empresa es obligatorio.', null, $con, $loggedInUserId, $id, 'empresa', 'VALIDACION_FALLIDA', NULL, 'Nombre de empresa vacío.');
    }

    if (!filter_var($correoEmpresa, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'El correo electrónico de la empresa no es válido.', null, $con, $loggedInUserId, $id, 'empresa', 'VALIDACION_FALLIDA', NULL, 'Correo de empresa inválido: ' . $correoEmpresa);
    }

    // Opcional: Validar formato de URL para sitio web
    if (!empty($sitioWebEmpresa) && !filter_var($sitioWebEmpresa, FILTER_VALIDATE_URL)) {
        sendJsonResponse(false, 'El sitio web de la empresa no es válido.', null, $con, $loggedInUserId, $id, 'empresa', 'VALIDACION_FALLIDA', NULL, 'Sitio web de empresa inválido: ' . $sitioWebEmpresa);
    }
    
    // Asumiendo que 'estadoEmpresa' tiene valores específicos (ej. 'Activo', 'Inactivo')
    $allowed_estados = ['Activo', 'Inactivo', 'Pendiente']; // Ajusta según tus estados reales
    if (!in_array($estadoEmpresa, $allowed_estados)) {
        sendJsonResponse(false, 'El estado de la empresa no es válido.', null, $con, $loggedInUserId, $id, 'empresa', 'VALIDACION_FALLIDA', NULL, 'Estado de empresa inválido: ' . $estadoEmpresa);
    }


    // Iniciar transacción de base de datos
    $con->autocommit(false); 

    try {
        // --- PASO 3: Obtener los datos actuales de la empresa para la bitácora (opcional pero recomendado) ---
        $datos_antes_empresa = null;
        $stmt_get_empresa_antes = $con->prepare("SELECT Nombre_Empresa, Correo_Electronico_Empresa, Sitio_Web_Empresa, Estado_Empresa FROM empresa WHERE ID_Empresa = ? LIMIT 1");
        if ($stmt_get_empresa_antes) {
            $stmt_get_empresa_antes->bind_param("i", $id);
            $stmt_get_empresa_antes->execute();
            $result_get_empresa_antes = $stmt_get_empresa_antes->get_result();
            $datos_antes_empresa_array = $result_get_empresa_antes->fetch_assoc();
            $stmt_get_empresa_antes->close();
            if ($datos_antes_empresa_array) {
                $datos_antes_empresa = json_encode($datos_antes_empresa_array);
            } else {
                // Si la empresa no existe, no podemos actualizarla.
                $con->rollback(); // No hay nada que deshacer, pero es buena práctica.
                sendJsonResponse(false, 'La empresa con el ID proporcionado no existe.', null, $con, $loggedInUserId, $id, 'empresa', 'NO_ENCONTRADA', NULL, 'Intento de actualizar empresa inexistente. ID: ' . $id);
            }
        } else {
            $error_message = 'Error al preparar la consulta de obtención de datos de empresa para bitácora: ' . $con->error;
            error_log($error_message);
            $con->rollback();
            sendJsonResponse(false, 'Error interno del servidor al obtener datos de empresa.', $error_message, $con, $loggedInUserId, $id, 'empresa', 'ERROR_SISTEMA', NULL, $error_message);
        }

        // --- PASO 4: Actualizar la empresa en la base de datos ---
        // Asumiendo nombres de columnas como en tu DB (ajusta si es necesario)
        $sql_update_empresa = "UPDATE empresa SET Nombre_Empresa = ?, Correo_Electronico_Empresa = ?, Sitio_Web_Empresa = ?, Estado_Empresa = ? WHERE ID_Empresa = ?";
        $stmt_update_empresa = $con->prepare($sql_update_empresa);

        if (!$stmt_update_empresa) {
            $error_message = 'Error al preparar la consulta de actualización de empresa: ' . $con->error;
            error_log($error_message);
            throw new Exception($error_message);
        }

        // Sanear datos antes de bind_param
        $nombreEmpresa_saneado = $con->real_escape_string($nombreEmpresa);
        $correoEmpresa_saneado = $con->real_escape_string($correoEmpresa);
        $sitioWebEmpresa_saneado = $con->real_escape_string($sitioWebEmpresa);
        $estadoEmpresa_saneado = $con->real_escape_string($estadoEmpresa);

        $stmt_update_empresa->bind_param('ssssi', 
            $nombreEmpresa_saneado, 
            $correoEmpresa_saneado, 
            $sitioWebEmpresa_saneado, 
            $estadoEmpresa_saneado, 
            $id
        );

        if (!$stmt_update_empresa->execute()) {
            $error_message = 'Error al ejecutar la actualización de empresa: ' . $stmt_update_empresa->error;
            error_log($error_message);
            throw new Exception($error_message);
        }

        if ($stmt_update_empresa->affected_rows === 0) {
            // No se realizó ningún cambio (los datos enviados son idénticos a los actuales)
            $con->rollback(); // No hay cambios que deshacer, pero mantiene el flujo
            sendJsonResponse(false, 'No se realizaron cambios en los datos de la empresa.', 'Los datos proporcionados son idénticos a los existentes.', $con, $loggedInUserId, $id, 'empresa', 'ADVERTENCIA', $datos_antes_empresa, json_encode(['Nombre_Empresa' => $nombreEmpresa, 'Correo_Electronico_Empresa' => $correoEmpresa, 'Sitio_Web_Empresa' => $sitioWebEmpresa, 'Estado_Empresa' => $estadoEmpresa]));
        } else {
            // Si la actualización fue exitosa, confirmar la transacción y enviar respuesta
            $con->commit();

            // Obtener los datos después de la actualización para la bitácora
            $datos_despues_empresa = null;
            $stmt_get_empresa_despues = $con->prepare("SELECT Nombre_Empresa, Correo_Electronico_Empresa, Sitio_Web_Empresa, Estado_Empresa FROM empresa WHERE ID_Empresa = ? LIMIT 1");
            if ($stmt_get_empresa_despues) {
                $stmt_get_empresa_despues->bind_param("i", $id);
                $stmt_get_empresa_despues->execute();
                $result_get_empresa_despues = $stmt_get_empresa_despues->get_result();
                $datos_despues_empresa_array = $result_get_empresa_despues->fetch_assoc();
                $stmt_get_empresa_despues->close();
                if ($datos_despues_empresa_array) {
                    $datos_despues_empresa = json_encode($datos_despues_empresa_array);
                }
            } else {
                error_log("Error al preparar SELECT datos_despues_empresa para bitácora: " . $con->error);
            }

            sendJsonResponse(true, "Empresa con ID {$id} actualizada exitosamente.", null, $con, $loggedInUserId, $id, 'empresa', 'UPDATE', $datos_antes_empresa, $datos_despues_empresa);
        }

        $stmt_update_empresa->close();

    } catch (Exception $e) {
        $con->rollback(); // Deshacer cualquier cambio en la base de datos si ocurre un error
        $log_message = 'Excepción en actualizar_empresa.php (ID_Usuario: ' . $loggedInUserId . ', ID_Empresa: ' . ($id ?? 'N/A') . '): ' . $e->getMessage();
        error_log($log_message); // Loguear el error real
        sendJsonResponse(false, 'Error al procesar la solicitud de actualización de empresa.', 'Detalle: ' . $e->getMessage(), $con, $loggedInUserId, $id, 'empresa', 'ERROR_SISTEMA', $datos_antes_empresa, $log_message);
    } finally {
        // Restaurar el modo autocommit y cerrar la conexión
        if (isset($con) && $con instanceof mysqli) {
            $con->autocommit(true);
            $con->close();
        }
    }
} else {
    // Si la solicitud no es POST, devolver un error
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.', $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Intento de acceso con método no POST.');
}
?>