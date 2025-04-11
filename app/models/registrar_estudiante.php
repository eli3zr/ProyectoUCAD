<?php
header('Content-Type: application/json');

$response = [];

// Verificar si se recibieron todos los campos requeridos
if (!isset($_POST["nombre"], $_POST["apellido"], $_POST["email"], $_POST["clave"], $_POST["repetirClave"], $_FILES["cv"])) {
    $response = ["success" => false, "error" => "Faltan datos requeridos."];
    echo json_encode($response);
    exit;
}

// Validaciones básicas
if (empty($_POST["nombre"])) {
    $response = ["success" => false, "error" => "El nombre es requerido."];
} elseif (empty($_POST["apellido"])) {
    $response = ["success" => false, "error" => "El apellido es requerido."];
} elseif (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
    $response = ["success" => false, "error" => "El formato del correo electrónico no es válido."];
} elseif (strlen($_POST["clave"]) < 6) {
    $response = ["success" => false, "error" => "La clave debe tener al menos 6 caracteres."];
} elseif ($_POST["clave"] !== $_POST["repetirClave"]) {
    $response = ["success" => false, "error" => "Las claves no coinciden."];
} elseif ($_FILES["cv"]["error"] !== UPLOAD_ERR_OK) {
    $response = ["success" => false, "error" => "Error al subir el archivo CV."];
} elseif ($_FILES["cv"]["size"] > 2 * 1024 * 1024) { // 2MB en bytes
    $response = ["success" => false, "error" => "El archivo CV excede el tamaño máximo de 2MB."];
} else {
    // ** Aquí iría la lógica para encriptar la contraseña y guardar en la base de datos,
    // ** y para mover el archivo CV a una ubicación permanente.

    // Simulación de registro exitoso
    $response = ["success" => true, "message" => "Registro completado exitosamente."];
}

echo json_encode($response);
?>