<?php

// Simulación de conexión a BD
// require '../../config/conexion.php'; // si usaras PDO o mysqli

$response = [];

// Captura los datos enviados
$datos = $_POST;

// Validación básica
if (
    empty($datos['nombre']) ||
    empty($datos['telefono']) ||
    empty($datos['email']) ||
    empty($datos['categoria']) ||
    empty($datos['pais']) ||
    empty($datos['departamento']) ||
    empty($datos['clave']) ||
    empty($datos['repetirClave']) ||
    !isset($datos['terminos']) || $datos['terminos'] !== 'true'
) {
    $response = [
        'success' => false,
        'error' => 'Todos los campos obligatorios deben ser completados y los términos deben ser aceptados.'
    ];
} elseif (!preg_match('/^[0-9]{8}$/', $datos['telefono'])) {
    $response = [
        'success' => false,
        'error' => 'El número de teléfono debe tener 8 dígitos.'
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
    $stmt = $pdo->prepare("INSERT INTO empresas (...) VALUES (...)");
    $stmt->execute([...]);
    */

    $response = [
        'success' => true,
        'msg' => 'Tu empresa se ha registrado correctamente.'
    ];
}

// Devolver respuesta en JSON
header('Content-Type: application/json');
echo json_encode($response);