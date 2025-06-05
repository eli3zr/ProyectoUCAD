<?php
// app/models/eliminar_estudiante.php

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/eliminar_estudiante_errors.log'); // Log específico para este script

session_start(); // Iniciar sesión si no está iniciada (necesario para $_SESSION['ID_Usuario'])

// Incluir la conexión a la base de datos y el helper de bitácora
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
// Este es el ID del usuario que REALIZA la acción (un administrador, por ejemplo).
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
                $bitacora_message .= " - Datos Nuevos: " . (is_array($datosNuevo) ? json_encode($datosNuevo) : $datosNuevo);
            }
            if ($datosAnterior) {
                $bitacora_message .= " - Datos Anteriores: " . (is_array($datosAnterior) ? json_encode($datosAnterior) : $datosAnterior);
            }
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
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en eliminar_estudiante.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg); // Loggear en el archivo de error configurado
    // No podemos usar sendJsonResponse con bitácora porque la conexión no existe
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit(); // Terminar la ejecución
}

// Establecer el Content-Type para todas las respuestas JSON tan pronto como sea posible
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar autenticación del usuario que realiza la acción (ej. administrador)
    if ($loggedInUserId === 0) {
        sendJsonResponse(false, 'Usuario no autenticado para realizar esta acción.', 'Por favor, inicie sesión.', $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de eliminar estudiante sin sesión activa.');
    }

    // Esperamos el ID_Usuario del estudiante a eliminar
    $idUsuarioAEliminar = $_POST['id'] ?? null; // ID del estudiante a eliminar
    $nombreEstudiante = ''; // Para capturar el nombre y usarlo en la bitácora

    // --- Validaciones de Entrada ---
    if (empty($idUsuarioAEliminar) || !is_numeric($idUsuarioAEliminar)) {
        sendJsonResponse(false, 'Error: ID de usuario inválido para eliminar.', null, $con, $loggedInUserId, 0, 'usuario', 'VALIDACION_FALLIDA', NULL, 'ID de usuario a eliminar inválido: ' . ($idUsuarioAEliminar ?? 'NULL'));
    }
    $idUsuarioAEliminar = (int)$idUsuarioAEliminar; // Castear a entero para seguridad

    // --- Obtener datos del estudiante antes de eliminar para la bitácora ---
    $datos_estudiante_antes = null;
    $stmt_get_data = $con->prepare("SELECT u.Nombre, u.Correo_Electronico, u.Tipo, u.estado_us, pe.Carrera, pe.Fecha_Nacimiento, pe.Genero, pe.Experiencia_Laboral, pe.Foto_Perfil FROM usuario u LEFT JOIN perfil_estudiante pe ON u.ID_Usuario = pe.ID_Usuario WHERE u.ID_Usuario = ? AND u.Tipo = 'estudiante' LIMIT 1");

    if ($stmt_get_data) {
        $stmt_get_data->bind_param("i", $idUsuarioAEliminar);
        $stmt_get_data->execute();
        $result_data = $stmt_get_data->get_result();
        $datos_estudiante_antes_array = $result_data->fetch_assoc();
        $stmt_get_data->close();

        if ($datos_estudiante_antes_array) {
            $datos_estudiante_antes = json_encode($datos_estudiante_antes_array);
            $nombreEstudiante = $datos_estudiante_antes_array['Nombre'] ?? 'Desconocido';
        } else {
            // Si el estudiante no se encuentra o no es de tipo 'estudiante'
            sendJsonResponse(false, 'No se encontró el estudiante con el ID proporcionado o no es un usuario tipo "estudiante".', null, $con, $loggedInUserId, $idUsuarioAEliminar, 'estudiante', 'NO_ENCONTRADO', NULL, 'Intento de eliminar estudiante inexistente o tipo incorrecto. ID: ' . $idUsuarioAEliminar);
        }
    } else {
        $error_message = "Error al preparar la consulta para obtener datos del estudiante antes de eliminar: " . $con->error;
        error_log($error_message);
        sendJsonResponse(false, 'Error interno del servidor al verificar el estudiante.', $error_message, $con, $loggedInUserId, $idUsuarioAEliminar, 'sistema', 'ERROR_SISTEMA', NULL, $error_message);
    }

    // --- Iniciar Transacción ---
    $con->begin_transaction();

    try {
        // 1. Eliminar de la tabla 'perfil_estudiante' primero (debido a la dependencia de clave foránea)
        $stmt_perfil = $con->prepare("DELETE FROM perfil_estudiante WHERE ID_Usuario = ?");
        if ($stmt_perfil === false) {
            throw new Exception("Error al preparar la consulta de eliminación de perfil_estudiante: " . $con->error);
        }
        $stmt_perfil->bind_param("i", $idUsuarioAEliminar);

        if (!$stmt_perfil->execute()) {
            throw new Exception("Error al eliminar el perfil del estudiante: " . $stmt_perfil->error);
        }
        $stmt_perfil->close();

        // 2. Eliminar de la tabla 'usuario'
        // Añade Tipo para seguridad extra, asegurando que solo se eliminen 'estudiantes'
        $stmt_usuario = $con->prepare("DELETE FROM usuario WHERE ID_Usuario = ? AND Tipo = 'estudiante'");
        if ($stmt_usuario === false) {
            throw new Exception("Error al preparar la consulta de eliminación de usuario: " . $con->error);
        }
        $stmt_usuario->bind_param("i", $idUsuarioAEliminar);

        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al eliminar el usuario: " . $stmt_usuario->error);
        }

        // Si al menos una fila fue afectada en 'usuario' (lo que indica que se eliminó el usuario principal)
        if ($stmt_usuario->affected_rows > 0) {
            $con->commit(); // Confirmar la transacción
            sendJsonResponse(true, 'Estudiante "' . $nombreEstudiante . '" (ID: ' . $idUsuarioAEliminar . ') eliminado exitosamente.', null, $con, $loggedInUserId, $idUsuarioAEliminar, 'estudiante', 'DELETE', $datos_estudiante_antes, NULL);
        } else {
            // Esto podría ocurrir si el ID_Usuario no existe o no es de tipo 'estudiante' (ya validado antes, pero como fallback)
            $con->rollback(); // Revertir si no se eliminó el usuario
            sendJsonResponse(false, 'No se encontró el estudiante para eliminar o no es un usuario tipo "estudiante" (después de verificación inicial).', null, $con, $loggedInUserId, $idUsuarioAEliminar, 'estudiante', 'NO_ENCONTRADO_FINAL', $datos_estudiante_antes, NULL);
        }
        $stmt_usuario->close();

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $con->rollback();
        $log_message = 'Excepción en eliminar_estudiante.php (Usuario que realiza la acción: ' . $loggedInUserId . ', Estudiante afectado: ' . $idUsuarioAEliminar . '): ' . $e->getMessage();
        error_log($log_message); // Loguear el error real
        sendJsonResponse(false, 'Error en la operación de eliminación: ' . $e->getMessage(), $e->getMessage(), $con, $loggedInUserId, $idUsuarioAEliminar, 'estudiante', 'ERROR_SISTEMA', $datos_estudiante_antes, $log_message);
    } finally {
        // Restaurar el modo autocommit y cerrar la conexión
        if (isset($con) && $con instanceof mysqli) {
            $con->autocommit(true); // Restaurar autocommit después de una transacción
            $con->close();
        }
    }

} else {
    sendJsonResponse(false, 'Acceso no permitido. Este script solo acepta solicitudes POST.', null, $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Intento de acceso con método no POST.');
}
?>