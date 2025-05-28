<?php
require_once '../config/conexion.php';
header('Content-Type: application/json');

$response = array();

try {
    // Realiza un JOIN entre 'usuario' y 'perfil_estudiante'
    // Asegúrate de que los nombres de las columnas y tablas sean EXACTOS como en tu BD.
    $sql = "SELECT
                u.ID_Usuario,
                u.Nombre AS NombreEstudiante,
                u.Correo_Electronico,
                u.estado_us AS Estado,
                pe.ID_Perfil_Estudiante,
                pe.Carrera,
                pe.Fecha_Nacimiento,
                pe.Genero,
                pe.Experiencia_Laboral,
                pe.Foto_Perfil
            FROM
                usuario u
            JOIN
                perfil_estudiante pe ON u.ID_Usuario = pe.ID_Usuario
            WHERE u.Tipo = 'estudiante' -- Filtra solo los usuarios que son estudiantes
            ORDER BY u.ID_Usuario DESC";

    $resultado = $con->query($sql);

    if ($resultado) {
        $estudiantes = array();
        while ($fila = $resultado->fetch_assoc()) {
            $estudiantes[] = $fila;
        }
        $response = array('success' => true, 'data' => $estudiantes);
        $resultado->free();
    } else {
        $response = array('success' => false, 'message' => 'Error al obtener estudiantes: ' . $con->error);
    }

} catch (Exception $e) {
    $response = array('success' => false, 'message' => 'Excepción: ' . $e->getMessage());
} finally {
    if (isset($con) && $con instanceof mysqli) {
        $con->close();
    }
}

echo json_encode($response);
?>