<?php
// C:\xampp\htdocs\Jobtrack_Ucad\app\models\retirar_aplicacion.php

// Iniciar sesión para obtener el ID del estudiante
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de encabezados para API REST
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST"); // Debe ser POST
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Incluir la conexión a la base de datos
require_once __DIR__ . '/../config/conexion.php';

// Función de ayuda para enviar respuesta JSON
function sendJsonResponse($success, $message, $error = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'error' => $error
    ]);
    exit();
}

// Verificar conexión a la BD
if ($con->connect_error) {
    sendJsonResponse(false, 'Error de conexión a la base de datos.', $con->connect_error);
}

// Verificar que el usuario esté logueado
if (!isset($_SESSION['ID_Usuario']) || empty($_SESSION['ID_Usuario'])) {
    sendJsonResponse(false, 'Acceso denegado. Usuario no autenticado.', 'AUTH_ERROR');
}

$id_estudiante = (int)$_SESSION['ID_Usuario']; // ID del estudiante logueado

// Recibir el ID de la aplicación a retirar (asegúrate de enviarlo desde JS)
$data = json_decode(file_get_contents("php://input")); // Lee el cuerpo de la solicitud JSON

if (!isset($data->id_aplicacion) || !is_numeric($data->id_aplicacion)) {
    sendJsonResponse(false, 'ID de aplicación no proporcionado o no válido.', 'INVALID_INPUT');
}

$id_aplicacion = (int)$data->id_aplicacion;

// Lógica de negocio: Cambiar el estado de la aplicación a 'Retirada'
// También puedes verificar el estado actual para no permitir retirar aplicaciones ya procesadas (ej. 'Contratado')
$sql_update = "UPDATE aplicacion_oferta SET Estado_Aplicacion = 'Retirada' WHERE ID_Aplicacion = ? AND ID_Estudiante = ?";
// Ojo: Se verifica ID_Estudiante para que un usuario solo pueda retirar sus propias aplicaciones.

$stmt = null; // Inicializar para el bloque finally

try {
    $stmt = $con->prepare($sql_update);
    if (!$stmt) {
        throw new Exception('Error al preparar la consulta de retiro de aplicación: ' . $con->error);
    }

    $stmt->bind_param('ii', $id_aplicacion, $id_estudiante);
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar la actualización de la aplicación: ' . $stmt->error);
    }

    if ($stmt->affected_rows > 0) {
        sendJsonResponse(true, 'Aplicación retirada exitosamente.');
    } else {
        // Podría ser que la aplicación no exista o no pertenezca a este estudiante,
        // o que ya esté en estado 'Retirada' (si se intenta retirar varias veces)
        sendJsonResponse(false, 'No se pudo retirar la aplicación. Es posible que ya haya sido retirada o no exista.');
    }

} catch (Exception $e) {
    error_log("Error en retirar_aplicacion.php: " . $e->getMessage());
    sendJsonResponse(false, 'Ocurrió un error al retirar la aplicación.', $e->getMessage());
} finally {
    if ($stmt) {
        $stmt->close();
    }
    if ($con) {
        $con->close();
    }
}
?>