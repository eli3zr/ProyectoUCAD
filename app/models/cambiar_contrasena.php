<?php
// app/models/cambiar_contrasena.php

// Muestra todos los errores y advertencias para depuración (SOLO EN DESARROLLO)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cambiar_contrasena_errors.log'); // Log específico para este script

session_start();

// Incluir la conexión a la base de datos y el helper de bitácora
require_once __DIR__ . '/../config/conexion.php'; 
require_once __DIR__ . '/../utils/bitacora_helper.php'; // Incluir el helper de bitácora

// ** Obtener el ID del usuario logueado de la sesión. Usar 0 como default para la bitácora si no está logueado. **
$loggedInUserId = $_SESSION['ID_Usuario'] ?? 0;

// --- Función para generar una respuesta JSON estandarizada, bitacorar y terminar la ejecución ---
// Se define aquí para ser usada en este script.
if (!function_exists('sendJsonResponse')) {
    function sendJsonResponse($success, $message, $error = null, $con = null, $loggedInUserId = 0, $objetoId = 0, $tipoObjeto = 'sistema', $evento = 'ERROR_DESCONOCIDO', $datosAnterior = NULL, $datosNuevo = NULL) {
        // Solo bitacorar si se proporciona una conexión y no es un éxito (o si es un éxito y se requiere registrar)
        if ($con) { // Siempre intentamos bitacorar si hay conexión disponible
            registrarEventoBitacora($con, $objetoId, $tipoObjeto, $evento, $loggedInUserId, $datosAnterior, $datosNuevo . ' - Mensaje: ' . $message . ($error ? ' - Error: ' . $error : ''));
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
    $error_msg = "FATAL ERROR: La conexión a la base de datos (\$con) no está disponible o es inválida en cambiar_contrasena.php. Detalles: " . mysqli_connect_error();
    error_log($error_msg); // Loggear en el archivo de error configurado
    // No podemos usar sendJsonResponse con bitácora porque la conexión no existe
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Error interno del servidor: La conexión a la base de datos no está disponible.', 'error' => mysqli_connect_error()]);
    exit(); // Terminar la ejecución
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Validar autenticación del usuario
    if ($loggedInUserId === 0) {
        sendJsonResponse(false, 'Usuario no autenticado.', 'Por favor, inicie sesión.', $con, 0, 0, 'sistema', 'ACCESO_NO_AUTORIZADO', NULL, 'Intento de cambio de contraseña sin sesión activa.');
    }
    $idUsuario = (int)$loggedInUserId; // Casteo a entero para seguridad

    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmNewPassword = $_POST['confirmNewPassword'] ?? '';

    // 2. Validaciones de entrada de usuario
    if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        sendJsonResponse(false, 'Todos los campos de contraseña son obligatorios.', 'Por favor, complete todos los campos.', $con, $idUsuario, $idUsuario, 'contrasena', 'VALIDACION_FALLIDA', NULL, 'Campos obligatorios vacíos en cambio de contraseña.');
    }

    if ($newPassword !== $confirmNewPassword) {
        sendJsonResponse(false, 'La nueva contraseña y su confirmación no coinciden.', 'Las nuevas contraseñas no son iguales.', $con, $idUsuario, $idUsuario, 'contrasena', 'VALIDACION_FALLIDA', NULL, 'Nueva contraseña y confirmación no coinciden.');
    }

    if (strlen($newPassword) < 8) {
        sendJsonResponse(false, 'La nueva contraseña debe tener al menos 8 caracteres.', 'Contraseña demasiado corta.', $con, $idUsuario, $idUsuario, 'contrasena', 'VALIDACION_FALLIDA', NULL, 'Nueva contraseña demasiado corta.');
    }

    try {
        // 3. Obtener el hash actual de la contraseña del usuario para verificación
        $hashedPasswordFromDB = null; // Inicializar a null
        $stmt_select = $con->prepare("SELECT Contrasena_Hash FROM contrasenas WHERE ID_Usuario = ?");
        if (!$stmt_select) {
            $error_message = 'Error al preparar la consulta de obtención de contraseña: ' . $con->error;
            error_log($error_message);
            throw new Exception($error_message); // Lanzar excepción para el catch
        }
        $stmt_select->bind_param('i', $idUsuario);
        if (!$stmt_select->execute()) {
            $error_message = 'Error al ejecutar la consulta de obtención de contraseña: ' . $stmt_select->error;
            error_log($error_message);
            throw new Exception($error_message);
        }
        $stmt_select->bind_result($hashedPasswordFromDB);
        $stmt_select->fetch();
        $stmt_select->close(); // Cerrar el statement inmediatamente

        if (!$hashedPasswordFromDB) {
            sendJsonResponse(false, 'No se encontró la contraseña actual para este usuario.', 'Usuario no encontrado o sin contraseña registrada.', $con, $idUsuario, $idUsuario, 'contrasena', 'ADVERTENCIA', NULL, 'Intento de cambio de contraseña para usuario sin hash existente.');
        }

        // 4. Verificar si la contraseña actual ingresada por el usuario coincide con el hash almacenado
        if (!password_verify($currentPassword, $hashedPasswordFromDB)) {
            sendJsonResponse(false, 'La contraseña actual es incorrecta.', 'Verificación de contraseña fallida.', $con, $idUsuario, $idUsuario, 'contrasena', 'INTENTO_FALLIDO', NULL, 'Contraseña actual incorrecta.');
        }

        // 5. Hashear la nueva contraseña para almacenarla
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHashedPassword === false) {
            throw new Exception('Error al hashear la nueva contraseña.');
        }

        // 6. Actualizar la contraseña en la base de datos
        $stmt_update = $con->prepare("UPDATE contrasenas SET Contrasena_Hash = ? WHERE ID_Usuario = ?");
        if (!$stmt_update) {
            $error_message = 'Error al preparar la consulta de actualización de contraseña: ' . $con->error;
            error_log($error_message);
            throw new Exception($error_message);
        }
        $stmt_update->bind_param('si', $newHashedPassword, $idUsuario);

        if (!$stmt_update->execute()) {
            $error_message = 'Error al ejecutar la actualización de contraseña: ' . $stmt_update->error;
            error_log($error_message);
            throw new Exception($error_message);
        }

        if ($stmt_update->affected_rows === 0) {
            // Esto sucede si la nueva contraseña hash es idéntica a la anterior (aunque es poco probable con password_hash)
            // O si la fila no existe (ya capturado por !$hashedPasswordFromDB)
            sendJsonResponse(false, 'La contraseña no fue cambiada.', 'Asegúrese de que la nueva contraseña sea diferente a la actual.', $con, $idUsuario, $idUsuario, 'contrasena', 'ADVERTENCIA', NULL, 'Nueva contraseña idéntica a la actual o no se realizó la actualización.');
        } else {
            sendJsonResponse(true, 'Contraseña actualizada exitosamente.', null, $con, $idUsuario, $idUsuario, 'contrasena', 'UPDATE', NULL, 'Contraseña actualizada exitosamente.');
        }

        $stmt_update->close(); // Cerrar el statement de actualización

    } catch (Exception $e) {
        $log_message = 'Excepción en cambiar_contrasena.php (ID_Usuario: ' . $idUsuario . '): ' . $e->getMessage();
        error_log($log_message); // Loguear el error real
        sendJsonResponse(false, 'Error al procesar la solicitud de cambio de contraseña.', 'Detalle: ' . $e->getMessage(), $con, $idUsuario, $idUsuario, 'contrasena', 'ERROR_SISTEMA', NULL, $log_message);
    } finally {
        // Asegurar que la conexión a la base de datos se cierre
        if (isset($con) && $con instanceof mysqli) {
            $con->close();
        }
    }
} else {
    // Si la solicitud no es POST
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.', $con, $loggedInUserId, 0, 'sistema', 'METODO_NO_PERMITIDO', NULL, 'Intento de acceso con método no POST.');
}
?>