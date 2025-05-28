<?php
require_once '../config/conexion.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // El ID_Usuario es crucial para saber qué registros actualizar
    $idUsuario = $_POST['editar_id_usuario'] ?? ''; // Ahora esperamos el ID_Usuario

    // Datos para la tabla 'usuario'
    $nombreUsuario = $_POST['editar_nombre_estudiante'] ?? '';
    $correoElectronico = $_POST['editar_correo_esudiante'] ?? ''; // Mantengo el typo 'esudiante' si lo usas
    $estadoUsuario = $_POST['editar_estado_estudiante'] ?? '';

    // Datos para la tabla 'perfil_estudiante'
    $carrera = $_POST['editar_carrera'] ?? '';
    // Nuevos campos según tu BD:
    $fechaNacimiento = $_POST['editar_fecha_nacimiento'] ?? null;
    $genero = $_POST['editar_genero'] ?? null;
    $experienciaLaboral = $_POST['editar_experiencia_laboral'] ?? null;
    $fotoPerfil = $_POST['editar_foto_perfil'] ?? null;

    // --- Validaciones de Entrada ---
    if (empty($idUsuario) || !is_numeric($idUsuario)) {
        echo json_encode(array('success' => false, 'message' => 'Error: ID de Usuario inválido.'));
        exit();
    }
    if (empty($nombreUsuario)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El nombre del Estudiante es requerido.'));
        exit();
    }
    if (empty($correoElectronico) || !filter_var($correoElectronico, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El correo electrónico no es válido.'));
        exit();
    }
    if (empty($carrera)) {
        echo json_encode(array('success' => false, 'message' => 'Error: La carrera es requerida.'));
        exit();
    }
    if (empty($estadoUsuario)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El estado del estudiante es requerido.'));
        exit();
    }

    // --- Iniciar Transacción ---
    $con->begin_transaction();

    try {
        // 1. Actualizar la tabla 'usuario'
        $stmt_usuario = $con->prepare("UPDATE usuario SET Nombre = ?, Correo_Electronico = ?, estado_us = ? WHERE ID_Usuario = ?");
        if ($stmt_usuario === false) {
            throw new Exception("Error al preparar la consulta de actualización de usuario: " . $con->error);
        }
        $stmt_usuario->bind_param("sssi", $nombreUsuario, $correoElectronico, $estadoUsuario, $idUsuario);

        if (!$stmt_usuario->execute()) {
            throw new Exception("Error al actualizar el usuario: " . $stmt_usuario->error);
        }
        $stmt_usuario->close();

        // 2. Actualizar la tabla 'perfil_estudiante'
        $stmt_perfil = $con->prepare("UPDATE perfil_estudiante SET Carrera = ?, Fecha_Nacimiento = ?, Genero = ?, Experiencia_Laboral = ?, Foto_Perfil = ? WHERE ID_Usuario = ?");
        if ($stmt_perfil === false) {
            throw new Exception("Error al preparar la consulta de actualización de perfil_estudiante: " . $con->error);
        }
        // 'sssssi' - sssss para los 5 campos (STRING), i para ID_Usuario (INT)
        $stmt_perfil->bind_param("sssssi", $carrera, $fechaNacimiento, $genero, $experienciaLaboral, $fotoPerfil, $idUsuario);

        if (!$stmt_perfil->execute()) {
            throw new Exception("Error al actualizar el perfil del estudiante: " . $stmt_perfil->error);
        }
        $stmt_perfil->close();

        // Si ambas actualizaciones fueron exitosas, confirmar la transacción
        $con->commit();
        echo json_encode(array('success' => true, 'message' => 'Estudiante actualizado exitosamente.'));

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $con->rollback();
        echo json_encode(array('success' => false, 'message' => 'Error en la operación de actualización: ' . $e->getMessage()));
    } finally {
        if (isset($con) && $con instanceof mysqli) {
            $con->close();
        }
    }

} else {
    echo json_encode(array('success' => false, 'message' => 'Acceso no permitido. Este script solo acepta solicitudes POST.'));
}
?>