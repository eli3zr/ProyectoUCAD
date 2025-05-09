<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombreEstudiante = $_POST['nombreEstudiante'] ?? '';
    $correoElectronico = $_POST['correoElectronico'] ?? '';
    $carrera = $_POST['carrera'] ?? '';
    $estado = $_POST['estado'] ?? '';

    if (empty($nombreEstudiante)) {
        $response = array('success' => false, 'message' => 'Error: El nombre del Estudiante es requerido.');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if (empty($correoElectronico) || !filter_var($correoElectronico, FILTER_VALIDATE_EMAIL)) {
        $response = array('success' => false, 'message' => 'Error: El correo electrónico no es válido.');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if (empty($carrera)) {
        $response = array('success' => false, 'message' => 'Error: El nombre de la Carrera es requerido.');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    if (empty($estado)) {
        $response = array('success' => false, 'message' => 'Error: El estado del estudiante es requerido.');
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    $response = array(
        'success' => true,
        'message' => 'Estudiante guardado exitosamente.'
    );

    // Simulación de una respuesta con error (descomentar para probar el error en el cliente)
    // $response = array(
    //     'success' => false,
    //     'message' => 'Error al guardar la empresa (simulado).'
    // );

    header('Content-Type: application/json');
    echo json_encode($response);

} else {
    $response = array(
        'success' => false,
        'message' => 'Acceso no permitido.'
    );
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>