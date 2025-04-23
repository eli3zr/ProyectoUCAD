<?php

// Simulación de conexión a BD
// require '../../config/conexion.php'; // si usaras PDO o mysqli

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica (opcional, pero recomendable)
if (
    empty($datos['experiencia']) &&
    empty($datos['habilidades'])
) {
    $response = [
        'success' => false,
        'error' => 'Debes proporcionar al menos un resumen de tu experiencia laboral o tus habilidades.'
    ];
} else {
    // Simulación de actualización en la base de datos (TODO: implementar)
    /*
    $stmt = $pdo->prepare("UPDATE estudiantes SET experiencia_laboral = :experiencia, habilidades = :habilidades WHERE id = :id");
    $stmt->bindParam(':experiencia', $datos['experiencia']);
    $stmt->bindParam(':habilidades', $datos['habilidades']);
    $stmt->bindParam(':id', $estudianteId); // Necesitarías obtener el ID del estudiante
    $stmt->execute();
    */

    $response = [
        'success' => true,
        'msg' => 'Tu información laboral ha sido actualizada.'
    ];
}

// Devolver respuesta en JSON
header('Content-Type: application/json');
echo json_encode($response);