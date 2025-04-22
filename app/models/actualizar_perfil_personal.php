<?php

// Simulación de conexión a BD
// require '../../config/conexion.php'; // si usaras PDO o mysqli

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica
if (
    empty($datos['nombre']) ||
    empty($datos['apellido']) ||
    empty($datos['email'])
) {
    $response = [
        'success' => false,
        'error' => 'Los campos Nombre, Apellido y Correo Electrónico son obligatorios.'
    ];
} elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
    $response = [
        'success' => false,
        'error' => 'El correo electrónico no es válido.'
    ];
} else {
    // Simulación de actualización en la base de datos (TODO: implementar)
    /*
    $stmt = $pdo->prepare("UPDATE estudiantes SET nombre = :nombre, apellido = :apellido, email = :email, telefono = :telefono WHERE id = :id");
    $stmt->bindParam(':nombre', $datos['nombre']);
    $stmt->bindParam(':apellido', $datos['apellido']);
    $stmt->bindParam(':email', $datos['email']);
    $stmt->bindParam(':telefono', $datos['telefono']);
    $stmt->bindParam(':id', $estudianteId); // Necesitarías obtener el ID del estudiante
    $stmt->execute();
    */

    $response = [
        'success' => true,
        'msg' => 'Tu información personal ha sido actualizada.'
    ];
}

// Devolver respuesta en JSON
header('Content-Type: application/json');
echo json_encode($response);