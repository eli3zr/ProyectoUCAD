<?php
require_once '../config/conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Esperamos el ID_Usuario del estudiante a eliminar
    $idUsuario = $_POST['id'] ?? ''; // Asumo que el JS envía el ID bajo el nombre 'id'

    // --- Validaciones de Entrada ---
    if (empty($idUsuario) || !is_numeric($idUsuario)) {
        echo json_encode(array('success' => false, 'message' => 'Error: ID de usuario inválido para eliminar.'));
        exit();
    }

    // --- Iniciar Transacción ---
    $con->begin_transaction();

    try {
        // 1. Eliminar de la tabla 'perfil_estudiante' primero (debido a la dependencia de clave foránea)
        $stmt_perfil = $con->prepare("DELETE FROM perfil_estudiante WHERE ID_Usuario = ?");
        if ($stmt_perfil === false) {
            throw new Exception("Error al preparar la consulta de eliminación de perfil_estudiante: " . $con->error);
        }
        $stmt_perfil->bind_param("i", $idUsuario);

        if (!$stmt_perfil->execute()) {
            throw new Exception("Error al eliminar el perfil del estudiante: " . $stmt_perfil->error);
        }
        $stmt_perfil->close();

        // 2. Eliminar de la tabla 'usuario'
        $stmt_usuario = $con->prepare("DELETE FROM usuario WHERE ID_Usuario = ? AND Tipo = 'estudiante'"); // Añade Tipo para seguridad
        if ($stmt_usuario === false) {
            throw new Exception("Error al preparar la consulta de eliminación de usuario: " . $con->error);
        }
        $stmt_usuario->bind_param("i", $idUsuario);

        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al eliminar el usuario: " . $stmt_usuario->error);
        }

        // Si al menos una fila fue afectada en 'usuario' (lo que indica que se eliminó el usuario principal)
        if ($stmt_usuario->affected_rows > 0) {
            $con->commit(); // Confirmar la transacción
            echo json_encode(array('success' => true, 'message' => 'Estudiante eliminado exitosamente.'));
        } else {
            // Esto podría ocurrir si el ID_Usuario no existe o no es de tipo 'estudiante'
            $con->rollback(); // Revertir si no se eliminó el usuario
            echo json_encode(array('success' => false, 'message' => 'No se encontró el estudiante para eliminar o no es un usuario tipo "estudiante".'));
        }
        $stmt_usuario->close();

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $con->rollback();
        echo json_encode(array('success' => false, 'message' => 'Error en la operación de eliminación: ' . $e->getMessage()));
    } finally {
        if (isset($con) && $con instanceof mysqli) {
            $con->close();
        }
    }

} else {
    echo json_encode(array('success' => false, 'message' => 'Acceso no permitido. Este script solo acepta solicitudes POST.'));
}
?>