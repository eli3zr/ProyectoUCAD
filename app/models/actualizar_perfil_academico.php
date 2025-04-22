<?php

// Simulación de conexión a BD
// require '../../config/conexion.php'; // si usaras PDO o mysqli

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica (puedes agregar más validaciones según necesites)
if (empty($datos['carrera'])) {
    $response = [
        'success' => false,
        'error' => 'El campo Carrera es obligatorio.'
    ];
} else {
    // Simulación de actualización en la base de datos (TODO: implementar)
    /*
    $stmt = $pdo->prepare("UPDATE estudiantes SET carrera = :carrera, anio_graduacion = :anioGraduacion WHERE id = :id");
    $stmt->bindParam(':carrera', $datos['carrera']);
    $stmt->bindParam(':anioGraduacion', $datos['anioGraduacion']);
    $stmt->bindParam(':id', $estudianteId); // Necesitarías obtener el ID del estudiante
    $stmt->execute();
    */

    $response = [
        'success' => true,
        'msg' => 'Tu información académica ha sido actualizada.'
    ];
}

// Devolver respuesta en JSON
header('Content-Type: application/json');
echo json_encode($response);