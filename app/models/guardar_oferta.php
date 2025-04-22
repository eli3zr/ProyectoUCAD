<?php

// Simulación de conexión a BD
// require '../../conexion.php'; // si usaras PDO o mysqli

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica
if (
    empty($datos['nombrePuesto']) || 
    empty($datos['descripcion']) || 
    empty($datos['requisitos']) || 
    empty($datos['modalidad'])
) {
    $response = [
        'success' => false,
        'error' => 'Todos los campos obligatorios deben ser completados.'
    ];
} else {
    // Simulación de guardado en la base de datos (TODO: implementar)
    /*
    $stmt = $pdo->prepare("INSERT INTO ofertas (...) VALUES (...)");
    $stmt->execute([...]);
    */

    $response = [
        'success' => true,
        'msg' => 'La oferta fue publicada exitosamente.'
    ];
}

// Devolver respuesta en JSON
header('Content-Type: application/json');
echo json_encode($response);
