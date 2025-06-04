<?php
// app/models/cambiar_contrasena.php

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
    if (!isset($_SESSION['ID_Usuario'])) { 
        sendJsonResponse(false, 'Usuario no autenticado.', 'Por favor, inicie sesión.');
    }

    $idUsuario = $_SESSION['ID_Usuario']; 
    
    $currentPassword = $_POST['currentPassword'] ?? '';
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmNewPassword = $_POST['confirmNewPassword'] ?? ''; 

    if (empty($currentPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        sendJsonResponse(false, 'Todos los campos de contraseña son obligatorios.', 'Por favor, complete todos los campos.');
    }
    
    if ($newPassword !== $confirmNewPassword) {
        sendJsonResponse(false, 'La nueva contraseña y su confirmación no coinciden.', 'Las nuevas contraseñas no son iguales.');
    }
    
    if (strlen($newPassword) < 8) {
        sendJsonResponse(false, 'La nueva contraseña debe tener al menos 8 caracteres.', 'Contraseña demasiado corta.');
    }

    try {
        // 1. Obtener el hash actual de la contraseña del usuario
        // Nombre de la tabla corregido a 'contraseñas' (plural)
        $stmt = $con->prepare("SELECT Contrasena_Hash FROM contrasenas WHERE ID_Usuario = ?"); 
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta de obtención de contraseña.');
        }
        $stmt->bind_param('i', $idUsuario);
        if (!$stmt->execute()) {
            throw new Exception('Error al ejecutar la consulta de obtención de contraseña.');
        }
        $stmt->bind_result($hashedPasswordFromDB);
        $stmt->fetch();
        $stmt->close();

        if (!$hashedPasswordFromDB) {
            sendJsonResponse(false, 'No se encontró la contraseña actual para este usuario.', 'Usuario no encontrado o sin contraseña registrada.');
        }

        // 2. Verificar si la contraseña actual ingresada por el usuario coincide con el hash almacenado
        if (!password_verify($currentPassword, $hashedPasswordFromDB)) {
            sendJsonResponse(false, 'La contraseña actual es incorrecta.', 'Verificación de contraseña fallida.');
        }

        // 3. Hashear la nueva contraseña para almacenarla
        $newHashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($newHashedPassword === false) {
             throw new Exception('Error al hashear la nueva contraseña.');
        }

        // 4. Actualizar la contraseña en la base de datos
        // Nombre de la tabla corregido a 'contraseñas' (plural)
        $stmt = $con->prepare("UPDATE contrasenas SET Contrasena_Hash = ? WHERE ID_Usuario = ?"); 
        if (!$stmt) {
            throw new Exception('Error al preparar la consulta de actualización de contraseña.');
        }
        $stmt->bind_param('si', $newHashedPassword, $idUsuario); 
        
        if (!$stmt->execute()) {
            throw new Exception('Error al ejecutar la actualización de contraseña.');
        }
        
        if ($stmt->affected_rows === 0) {
            sendJsonResponse(false, 'La contraseña no fue cambiada.', 'Asegúrese de que la nueva contraseña sea diferente a la actual.');
        } else {
             sendJsonResponse(true, 'Contraseña actualizada exitosamente.');
        }
        
        $stmt->close();

    } catch (Exception $e) {
        sendJsonResponse(false, 'Error al procesar la solicitud de cambio de contraseña.', 'Detalle: ' . $e->getMessage());
    } finally {
        if (isset($con) && $con instanceof mysqli) {
            $con->close();
        }
    }
} else {
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.');
}