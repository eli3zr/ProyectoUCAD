<?php
header('Content-Type: application/json');

$response = [];
$errores = []; // Array para almacenar errores

// Verificar si se recibieron todos los campos requeridos
if (!isset($_POST["nombre"], $_POST["apellido"], $_POST["email"], $_POST["clave"], $_POST["repetirClave"], $_FILES["cv"])) {
    $errores[] = "Faltan datos requeridos.";
}

// Validaciones básicas
if (empty($_POST["nombre"])) {
    $errores[] = "El nombre es requerido.";
}
if (empty($_POST["apellido"])) {
    $errores[] = "El apellido es requerido.";
}
if (!filter_var($_POST["email"], FILTER_VALIDATE_EMAIL)) {
    $errores[] = "El formato del correo electrónico no es válido.";
}
if (strlen($_POST["clave"]) < 6) {
    $errores[] = "La clave debe tener al menos 6 caracteres.";
}
if ($_POST["clave"] !== $_POST["repetirClave"]) {
    $errores[] = "Las claves no coinciden.";
}

// Validación del CV (AHORA FUERA DE LA CADENA ELSEIF)
if ($_FILES["cv"]["error"] === UPLOAD_ERR_NO_FILE) {
    $errores[] = "Por favor, sube tu currículum vitae.";
} elseif ($_FILES["cv"]["error"] !== UPLOAD_ERR_OK) {
    $errores[] = "Error al subir el archivo CV.";
} elseif ($_FILES["cv"]["size"] > 2 * 1024 * 1024) { // 2MB en bytes
    $errores[] = "El archivo CV excede el tamaño máximo de 2MB.";
}

// Responder con errores si hay alguno
if (!empty($errores)) {
    $response = ["success" => false, "error" => implode("<br>", $errores)]; // Unir los errores con saltos de línea
} else {
    // ** Aquí iría la lógica para encriptar la contraseña y guardar en la base de datos,
    // ** y para mover el archivo CV a una ubicación permanente.

    // Simulación de registro exitoso
    $response = ["success" => true, "message" => "Registro completado exitosamente."];
}

echo json_encode($response);
?>