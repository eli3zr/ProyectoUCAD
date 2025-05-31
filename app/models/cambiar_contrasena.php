<?php
// app/models/cambiar_contrasena.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../config/conexion.php'; 

function sendJsonResponse($success, $message, $error = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'msg' => $message,
        'error' => $error
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['id_usuario'])) {
        sendJsonResponse(false, 'Usuario no autenticado.', 'Por favor, inicie sesión.');
    }

    $idUsuario = $_SESSION['id_usuario'];
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';

    if (empty($currentPassword) || empty($newPassword)) {
        sendJsonResponse(false, 'Todos los campos son obligatorios.', 'Por favor, complete todos los campos de contraseña.');
    }

    if (strlen($newPassword) < 8) {
        sendJsonResponse(false, 'La nueva contraseña debe tener al menos 8 caracteres.', 'Contraseña demasiado corta.');
    }

    try {
        // Obtener la contraseña actual hasheada del usuario
        $stmt = $con->prepare("SELECT contrasena_hash FROM contrasena WHERE ID_Usuario = ?");
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta de obtención de contraseña: ' . $con->error);
        }
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->bind_result($hashedPasswordFromDB);
        $stmt->fetch();
        $stmt->close();

        if (!$hashedPasswordFromDB) {
            sendJsonResponse(false, 'No se encontró la contraseña actual para este usuario.', 'Usuario no encontrado o sin contraseña registrada.');
        }

        // Verificar la contraseña actual
        if (!password_verify($currentPassword, $hashedPasswordFromDB)) {
            sendJsonResponse(false, 'La contraseña actual es incorrecta.', 'Verificación de contraseña fallida.');
        }

        // Hashear la nueva contraseña
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        // Actualizar la contraseña en la base de datos
        $stmt = $con->prepare("UPDATE contrasena SET contrasena_hash = ? WHERE ID_Usuario = ?");
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta de actualización de contraseña: ' . $con->error);
        }
        $stmt->bind_param('si', $newHashedPassword, $idUsuario);
        if (!$stmt->execute()) {
            throw new Exception('Error al ejecutar la actualización de contraseña: ' . $stmt->error);
        }
        $stmt->close();

        sendJsonResponse(true, 'Contraseña actualizada exitosamente.');

    } catch (Exception $e) {
        error_log("Error en cambiar_contrasena.php: " . $e->getMessage());
        sendJsonResponse(false, 'Error al procesar la solicitud.', $e->getMessage());
    } finally {
        if (isset($con)) {
            $con->close();
        }
    }
} else {
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.');
}
?>