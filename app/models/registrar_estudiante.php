<?php

// Simulación de conexión a BD
// include_once '../../config/conexion.php'; // si usaras PDO o mysqli

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica
if (
    empty($datos['nombre']) ||
    empty($datos['apellido']) ||
    empty($datos['email']) ||
    empty($datos['fechaNacimiento']) ||
    empty($datos['genero']) ||
    empty($datos['carrera']) ||
    empty($datos['clave']) ||
    empty($datos['repetirClave']) ||
    !isset($datos['terminos']) || $datos['terminos'] !== 'true'
) {
    $response = [
        'success' => false,
        'error' => 'Todos los campos obligatorios deben ser completados y los términos deben ser aceptados.'
    ];
} elseif ($datos['clave'] !== $datos['repetirClave']) {
    $response = [
        'success' => false,
        'error' => 'Las claves no coinciden.'
    ];
} elseif (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) {
    $response = [
        'success' => false,
        'error' => 'El correo electrónico no es válido.'
    ];
} else {
    // Simulación de guardado en la base de datos (TODO: implementar)
    /*
    $stmt = $pdo->prepare("INSERT INTO estudiantes (...) VALUES (...)");
    $stmt->execute([...]);
    */

    $response = [
        'success' => true,
        'msg' => 'Te has registrado correctamente.'
    ];
}

// Devolver respuesta en JSON
header('Content-Type: application/json');
echo json_encode($response);