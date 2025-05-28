<?php
// app/models/login.php

session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/conexion.php';

$response = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $con->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    error_log("--- Intento de Login ---");
    error_log("Email recibido: " . $email);

    if (empty($email) || empty($password)) {
        $response = [
            'success' => false,
            'error' => 'Por favor, ingresa tu correo electrónico y contraseña.'
        ];
        error_log("Error: Campos vacíos.");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response = [
            'success' => false,
            'error' => 'El formato del correo electrónico no es válido.'
        ];
        error_log("Error: Formato de email inválido.");
    } else {
        try {
            // CORRECCIÓN: CAMBIADO 'u.Tipo_ENUM' a 'u.Tipo'
            // CORRECCIÓN: CAMBIADO 'c.Contrasena_Hash_VARCHAR' a 'c.Contrasena_Hash'
            $sql = "SELECT u.ID_Usuario, u.Tipo, c.Contrasena_Hash 
                    FROM usuario u
                    JOIN contrasenas c ON u.ID_Usuario = c.ID_Usuario
                    WHERE u.Correo_Electronico = ?"; // Corregido a 'Correo_Electronico' si es el nombre exacto

            error_log("DEBUG: SQL Query being prepared: " . $sql); // Deja este log

            $stmt = $con->prepare($sql);

            if ($stmt) {
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $stmt->store_result();

                error_log("SQL ejecutado. Filas encontradas: " . $stmt->num_rows);

                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id_usuario, $tipo_usuario, $hashed_password);
                    $stmt->fetch();

                    error_log("ID_Usuario de BD: " . $id_usuario);
                    error_log("Tipo de BD: " . $tipo_usuario); // Nombre corregido
                    error_log("Contrasena_Hash de BD: " . $hashed_password); // Nombre corregido

                    if (password_verify($password, $hashed_password)) {
                        error_log("password_verify(): ¡TRUE! Contraseña verificada correctamente.");
                        $_SESSION['id_usuario'] = $id_usuario;
                        $_SESSION['tipo_usuario'] = $tipo_usuario;

                        $response = [
                            'success' => true,
                            'msg' => 'Inicio de sesión exitoso.',
                            'tipo_usuario' => $tipo_usuario
                        ];
                    } else {
                        error_log("password_verify(): ¡FALSE! Contraseña ingresada NO coincide con el hash.");
                        $response = [
                            'success' => false,
                            'error' => 'Correo electrónico o contraseña incorrectos.'
                        ];
                    }
                } else {
                    error_log("Error: Usuario no encontrado con el email proporcionado o JOIN falló.");
                    $response = [
                        'success' => false,
                        'error' => 'Correo electrónico o contraseña incorrectos.'
                    ];
                }
                $stmt->close();
            } else {
                error_log("Error al preparar la sentencia SQL: " . $con->error);
                $response = [
                    'success' => false,
                    'error' => 'Error interno del servidor al preparar la consulta: ' . $con->error
                ];
            }
        } catch (Exception $e) {
            error_log("Excepción inesperada en el login: " . $e->getMessage());
            $response = [
                'success' => false,
                'error' => 'Error inesperado en el servidor: ' . $e->getMessage()
            ];
        }
    }
} else {
    error_log("Error: Solicitud no POST.");
    $response = [
        'success' => false,
        'error' => 'Método de solicitud no permitido.'
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
$con->close();
?>