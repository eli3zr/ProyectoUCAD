<?php
// app/models/eliminar_cuenta.php

error_reporting(E_ALL); // Mantener para depuración
ini_set('display_errors', 1);

session_start();

require_once __DIR__ . '/../config/conexion.php'; 

function sendJsonResponse($success, $message, $error = null, $redirect = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'msg' => $message,
        'error' => $error,
        'redirect' => $redirect
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_SESSION['id_usuario'])) {
            throw new Exception('Usuario no autenticado. Por favor, inicie sesión nuevamente para eliminar su cuenta.');
        }
        $idUsuario = $_SESSION['id_usuario'];
        $confirmDeletePassword = $_POST['confirmDeletePassword'] ?? '';

        if (empty($confirmDeletePassword)) {
            throw new Exception('Por favor, introduce tu contraseña para confirmar la eliminación.');
        }

        // Primero, verificar la contraseña para seguridad
        $stmt = $con->prepare("SELECT contrasena_hash FROM contrasena WHERE ID_Usuario = ?");
        if (!$stmt) {
            throw new Exception('Error al preparar verificación de contraseña para eliminar cuenta: ' . $con->error);
        }
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $stmt->bind_result($hashedPasswordFromDB);
        $stmt->fetch();
        $stmt->close();

        if (!$hashedPasswordFromDB || !password_verify($confirmDeletePassword, $hashedPasswordFromDB)) {
            sendJsonResponse(false, 'Contraseña incorrecta.', 'La contraseña introducida no coincide con su contraseña actual.');
        }
        
        // Iniciar transacción
        $con->begin_transaction();

        // --- IMPORTANTE: Obtener el ID_Perfil_Estudiante antes de borrar nada ---
        $idPerfilEstudiante = null; // Inicializar a null
        $stmt_get_perfil = $con->prepare("SELECT ID_Perfil_Estudiante FROM perfil_estudiante WHERE ID_Usuario = ?");
        if (!$stmt_get_perfil) {
            throw new Exception('Error al preparar consulta de perfil de estudiante: ' . $con->error);
        }
        $stmt_get_perfil->bind_param('i', $idUsuario);
        $stmt_get_perfil->execute();
        $stmt_get_perfil->bind_result($idPerfilEstudiante);
        $stmt_get_perfil->fetch();
        $stmt_get_perfil->close();

        // Verificar si existe un perfil para este usuario
        if (is_null($idPerfilEstudiante)) {
            error_log("No se encontró ID_Perfil_Estudiante para el usuario ID: " . $idUsuario . ". Procediendo con eliminación de contraseña y usuario.");
            // Si el usuario no tiene perfil, se omiten las eliminaciones de tablas dependientes de perfil_estudiante.
        } else {
            // Orden de eliminación: Hijos de perfil_estudiante primero
            // 1. Eliminar datos de cv_estudiante
            $stmt_delete_cv = $con->prepare("DELETE FROM cv_estudiante WHERE perfil_estudiante_ID_Perfil_Estudiante = ?");
            if (!$stmt_delete_cv) { throw new Exception('Error al preparar eliminación de CV: ' . $con->error); }
            $stmt_delete_cv->bind_param('i', $idPerfilEstudiante);
            if (!$stmt_delete_cv->execute()) { throw new Exception('Error al eliminar CV de estudiante: ' . $stmt_delete_cv->error); }
            $stmt_delete_cv->close();
            error_log("Eliminado CV para usuario ID: " . $idUsuario);

            // 2. Eliminar datos de contactos_estudiantes
            $stmt_delete_contactos = $con->prepare("DELETE FROM contactos_estudiantes WHERE ID_Perfil_Estudiante = ?");
            if (!$stmt_delete_contactos) { throw new Exception('Error al preparar eliminación de contactos de estudiantes: ' . $con->error); }
            $stmt_delete_contactos->bind_param('i', $idPerfilEstudiante);
            if (!$stmt_delete_contactos->execute()) { throw new Exception('Error al eliminar contactos de estudiante: ' . $stmt_delete_contactos->error); }
            $stmt_delete_contactos->close();
            error_log("Eliminado contactos de estudiante para usuario ID: " . $idUsuario);

            // 3. Eliminar datos de experiencias_laborales_estudiantes
            $stmt_delete_experiencias = $con->prepare("DELETE FROM experiencias_laborales_estudiantes WHERE ID_Perfil_Estudiante = ?");
            if (!$stmt_delete_experiencias) { throw new Exception('Error al preparar eliminación de experiencias laborales: ' . $con->error); }
            $stmt_delete_experiencias->bind_param('i', $idPerfilEstudiante);
            if (!$stmt_delete_experiencias->execute()) { throw new Exception('Error al eliminar experiencias laborales de estudiante: ' . $stmt_delete_experiencias->error); }
            $stmt_delete_experiencias->close();
            error_log("Eliminado experiencias laborales para usuario ID: " . $idUsuario);

            // 4. Eliminar datos de perfil_estudiante (ahora que sus hijos han sido eliminados)
            $stmt_delete_perfil = $con->prepare("DELETE FROM perfil_estudiante WHERE ID_Usuario = ?");
            if (!$stmt_delete_perfil) { throw new Exception('Error al preparar eliminación de perfil de estudiante: ' . $con->error); }
            $stmt_delete_perfil->bind_param('i', $idUsuario);
            if (!$stmt_delete_perfil->execute()) { throw new Exception('Error al eliminar perfil de estudiante: ' . $stmt_delete_perfil->error); }
            $stmt_delete_perfil->close();
            error_log("Eliminado perfil de estudiante para usuario ID: " . $idUsuario);
        }

        // 5. Eliminar la contraseña (hijo de usuario)
        $stmt_delete_pass = $con->prepare("DELETE FROM contrasena WHERE ID_Usuario = ?");
        if (!$stmt_delete_pass) { throw new Exception('Error al preparar eliminación de contraseña: ' . $con->error); }
        $stmt_delete_pass->bind_param('i', $idUsuario);
        if (!$stmt_delete_pass->execute()) { throw new Exception('Error al eliminar contraseña: ' . $stmt_delete_pass->error); }
        $stmt_delete_pass->close();
        error_log("Eliminado contraseña para usuario ID: " . $idUsuario);

        // 6. Finalmente, eliminar el usuario de la tabla `usuario`
        $stmt_delete_user = $con->prepare("DELETE FROM usuario WHERE ID_Usuario = ?");
        if (!$stmt_delete_user) { throw new Exception('Error al preparar eliminación de usuario: ' . $con->error); }
        $stmt_delete_user->bind_param('i', $idUsuario);
        if (!$stmt_delete_user->execute()) { throw new Exception('Error al eliminar usuario: ' . $stmt_delete_user->error); }
        $stmt_delete_user->close();
        error_log("Eliminado usuario ID: " . $idUsuario);

        // Si todo fue exitoso, confirmar la transacción
        $con->commit();

        // Destruir la sesión después de la eliminación exitosa
        session_unset();
        session_destroy();

        sendJsonResponse(true, 'Tu cuenta y todos los datos asociados han sido eliminados exitosamente.', null, '../auth/login.php');
    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        if (isset($con)) {
            $con->rollback();
        }
        error_log("Error en eliminar_cuenta.php: " . $e->getMessage());
        sendJsonResponse(false, 'Error al procesar la solicitud para eliminar la cuenta.', $e->getMessage());
    } finally {
        if (isset($con)) {
            $con->close(); 
        }
    }
} else {
    sendJsonResponse(false, 'Método de solicitud no permitido.', 'Se esperaba una solicitud POST.');
}
?>