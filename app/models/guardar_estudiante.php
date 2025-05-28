<?php
// Incluye tu archivo de conexión a la base de datos
require_once '../config/conexion.php';

header('Content-Type: application/json'); // La respuesta siempre será JSON

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recoge los datos enviados desde el formulario
    $nombreEstudiante = $_POST['nombreEstudiante'] ?? '';
    $correoElectronico = $_POST['correoElectronico'] ?? '';
    $carrera = $_POST['carrera'] ?? '';
    $estado = $_POST['estado'] ?? '';

    // --- Validaciones de Entrada (muy importantes) ---
    if (empty($nombreEstudiante)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El nombre del Estudiante es requerido.'));
        exit();
    }
    if (empty($correoElectronico) || !filter_var($correoElectronico, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El correo electrónico no es válido.'));
        exit();
    }
    if (empty($carrera)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El nombre de la Carrera es requerido.'));
        exit();
    }
    if (empty($estado)) {
        echo json_encode(array('success' => false, 'message' => 'Error: El estado del estudiante es requerido.'));
        exit();
    }

    // --- Interacción con la Base de Datos ---
    try {
        // Prepara la consulta SQL para insertar un nuevo estudiante
        // Asegúrate de que los nombres de las columnas coincidan con tu tabla 'estudiantes'
        $stmt = $con->prepare("INSERT INTO estudiantes (nombre, correo_electronico, carrera, estado) VALUES (?, ?, ?, ?)");

        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta: " . $con->error);
        }

        // Vincula los parámetros a la consulta preparada (s = string)
        $stmt->bind_param("ssss", $nombreEstudiante, $correoElectronico, $carrera, $estado);

        // Ejecuta la consulta
        if ($stmt->execute()) {
            echo json_encode(array('success' => true, 'message' => 'Estudiante guardado exitosamente.'));
        } else {
            echo json_encode(array('success' => false, 'message' => 'Error al guardar el estudiante: ' . $stmt->error));
        }

        // Cierra la sentencia preparada
        $stmt->close();

    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'message' => 'Excepción: ' . $e->getMessage()));
    } finally {
        // Cierra la conexión si está abierta
        if (isset($con) && $con instanceof mysqli) {
            $con->close();
        }
    }

} else {
    // Si no es una solicitud POST
    echo json_encode(array('success' => false, 'message' => 'Acceso no permitido. Este script solo acepta solicitudes POST.'));
}
?>