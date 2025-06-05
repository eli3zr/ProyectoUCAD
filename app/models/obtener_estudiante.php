<?php
// app/models/obtener_estudiante.php

require_once '../config/conexion.php'; // Asegúrate de que esta ruta sea correcta
header('Content-Type: application/json');

$response = array('success' => false, 'message' => '', 'data' => []);

try {
    // Verificar si la conexión está disponible a través de $con
    if (!isset($con) || !$con instanceof mysqli || $con->connect_error) {
        throw new Exception("Error de conexión a la base de datos.");
    }

    $oferta_id = $_GET['oferta_id'] ?? null;

    $sql = "SELECT
                ap.ID_Aplicacion AS ID_Postulacion,  --
                ap.ID_Oferta AS ID_Oferta_Relacionada, --
                ap.ID_Estudiante AS ID_Usuario,     --
                u.Nombre AS NombrePostulante, --
                u.Apellido, --
                u.Correo_Electronico, --
                ap.Fecha_Aplicacion AS Fecha_Postulacion, --
                ap.Estado_Aplicacion AS Estado_Postulacion, --
                ap.Carta_Presentacion, --
                ap.Ruta_CV, --
                ol.Titulo_Puesto AS TituloOferta,
                pe.Carrera_profesional AS Carrera, --
                pe.Fecha_Nacimiento, --
                pe.Genero, --
                ele.descripcion_laboral AS Experiencia_Laboral, -- ¡CAMBIO AQUÍ: OBTENEMOS DE LA NUEVA TABLA!
                pe.Foto_Perfil --
            FROM
                aplicacion_oferta ap --
            JOIN
                oferta_laboral ol ON ap.ID_Oferta = ol.ID_Oferta
            JOIN
                usuario u ON ap.ID_Estudiante = u.ID_Usuario --
            LEFT JOIN
                perfil_estudiante pe ON u.ID_Usuario = pe.ID_Usuario --
            LEFT JOIN
                experiencias_laborales_estudiantes ele ON pe.ID_Perfil_Estudiante = ele.ID_Perfil_Estudiante -- ¡NUEVO JOIN!
            WHERE 1=1";

    $params = [];
    $types = '';

    if ($oferta_id && $oferta_id > 0) {
        $sql .= " AND ap.ID_Oferta = ?";
        $params[] = $oferta_id;
        $types .= 'i';
    }

    // Asegurarse de que si un estudiante tiene múltiples experiencias, solo se muestre una o se agrupe.
    // Para simplificar por ahora, si un estudiante tiene varias descripciones, puede duplicar el postulante
    // o simplemente mostrar la primera. Para obtener una única descripción por estudiante, se requeriría GROUP BY
    // o un subquery/CTE si solo se desea una. Para esta consulta simple, dejémoslo así, si es 1-a-1 o 1-a-muchos no es un problema.
    // Si la relación es 1-a-muchos y quieres todas, necesitarías concatenarlas o procesarlas en el frontend.
    // Si la relación es 1-a-1 entre perfil_estudiante y experiencias_laborales_estudiantes, este JOIN es directo.

    $sql .= " ORDER BY ap.Fecha_Aplicacion DESC";

    $stmt = $con->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error al preparar la consulta: " . $con->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado) {
        $postulantes = array();
        while ($fila = $resultado->fetch_assoc()) {
            $fila['Ruta_CV'] = $fila['Ruta_CV'] ?? ''; //
            $postulantes[] = $fila;
        }
        $response['success'] = true;
        $response['data'] = $postulantes;
    } else {
        $response['message'] = 'Error al obtener postulantes: ' . $con->error;
    }

    $stmt->close();

} catch (Exception $e) {
    $response['message'] = 'Excepción: ' . $e->getMessage();
} finally {
    if (isset($con) && $con instanceof mysqli && $con->ping()) {
        $con->close();
    }
}

echo json_encode($response);
?>